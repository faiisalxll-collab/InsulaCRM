<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Services\AddressNormalizationService;
use App\Services\CustomFieldService;
use App\Services\ZipTimezoneService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    /**
     * Display a listing of properties.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Property::class);

        $query = Property::with('lead')
            ->withCount(['matches as eligible_matches_count' => fn ($q) => $q->visibleTo(auth()->user())->where('status', 'eligible')]);

        if (auth()->user()->isAgent()) {
            $query->whereHas('lead', function ($q) {
                $q->where('agent_id', auth()->id());
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('plan_number', 'like', "%{$search}%")
                  ->orWhere('zip_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('district')) {
            $query->where('district', 'like', '%'.trim($request->district).'%');
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('distress')) {
            $distressFilters = (array) $request->distress;
            foreach ($distressFilters as $marker) {
                $query->whereJsonContains('distress_markers', $marker);
            }
        }

        if ($request->filled('listing_status')) {
            $query->where('listing_status', $request->listing_status);
        }

        $properties = $query->latest()->paginate(25);

        return view('properties.index', [
            'properties' => $properties,
            'propertyTypes' => CustomFieldService::getOptions('property_type'),
        ]);
    }

    /**
     * Show a standalone Saudi property creation form.
     */
    public function create()
    {
        $this->authorize('create', Property::class);

        return view('properties.create', $this->formOptions());
    }

    /**
     * Store a standalone Saudi property.
     */
    public function standaloneStore(PropertyRequest $request)
    {
        $this->authorize('create', Property::class);

        $data = $request->validated();
        $data = \App\Services\BusinessModeService::isRealEstate()
            ? AddressNormalizationService::normalizeSaudiAll($data)
            : AddressNormalizationService::normalizeAll($data);
        $data['tenant_id'] = auth()->user()->tenant_id;

        $lead = Lead::query()->findOrFail($data['lead_id']);
        $this->authorize('update', $lead);

        $data['price_per_sqm'] = $this->pricePerSqm($data);

        $property = Property::create($data);

        AuditLog::log('property.created', $property);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'تم حفظ العقار وتشغيل المطابقة تلقائيًا.');
    }

    public function edit(Property $property)
    {
        $this->authorize('update', $property);

        return view('properties.edit', [
            'property' => $property,
            ...$this->formOptions(),
        ]);
    }

    public function update(PropertyRequest $request, Property $property)
    {
        $this->authorize('update', $property);

        $data = $request->validated();
        $data = \App\Services\BusinessModeService::isRealEstate()
            ? AddressNormalizationService::normalizeSaudiAll($data)
            : AddressNormalizationService::normalizeAll($data);

        $lead = Lead::query()->findOrFail($data['lead_id'] ?? $property->lead_id);
        $this->authorize('update', $lead);

        $data['lead_id'] = $lead->id;
        $data['price_per_sqm'] = $this->pricePerSqm($data);

        $property->update($data);

        AuditLog::log('property.updated', $property);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'تم تحديث العقار وإعادة حساب المطابقات.');
    }

    public function destroy(Property $property)
    {
        $this->authorize('delete', $property);

        if (
            \App\Services\BusinessModeService::isRealEstate()
            && ($property->showings()->exists() || $property->deals()->exists())
        ) {
            return redirect()
                ->route('properties.show', $property)
                ->with('error', 'لا يمكن حذف عقار دخل في معاينة أو صفقة. غيّر حالة العرض للحفاظ على سجل المكتب.');
        }

        AuditLog::log('property.deleted', $property);
        $property->delete();

        return redirect()
            ->route('properties.index')
            ->with('success', 'تم حذف العقار.');
    }

    /**
     * Store a property for a lead (AJAX or form).
     */
    public function store(PropertyRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $this->authorize('create', Property::class);

        $data = $request->validated();
        $data = \App\Services\BusinessModeService::isRealEstate()
            ? AddressNormalizationService::normalizeSaudiAll($data)
            : AddressNormalizationService::normalizeAll($data);
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['lead_id'] = $lead->id;

        // Compute MAO: (ARV x 0.70) - Repair Estimate (wholesale only)
        if (!\App\Services\BusinessModeService::isRealEstate()
            && !empty($data['after_repair_value']) && !empty($data['repair_estimate'])) {
            $data['maximum_allowable_offer'] = ($data['after_repair_value'] * 0.70) - $data['repair_estimate'];
        }

        $property = \App\Services\BusinessModeService::isRealEstate()
            ? Property::create($data)
            : Property::updateOrCreate(
                ['lead_id' => $lead->id],
                $data
            );

        AuditLog::log('property.created', $property);

        // ZIP-based timezone detection is a US helper. Skip it when the
        // localized property does not carry a ZIP code.
        if ($property->zip_code) {
            $timezone = ZipTimezoneService::detect($property->zip_code);
            if ($timezone && ! $lead->timezone) {
                $lead->update(['timezone' => $timezone]);
            }
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'property' => $property]);
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Property saved successfully.');
    }

    /**
     * Store a property submitted by a field scout (standalone, no lead required).
     */
    public function fieldScoutStore(Request $request)
    {
        $this->authorize('createFieldScout', Property::class);

        $data = $request->validate([
            'address'          => 'required|string|max:255',
            'city'             => 'required|string|max:100',
            'state'            => 'required|string|max:2',
            'zip_code'         => 'required|string|max:10',
            'property_type'    => 'required|in:' . implode(',', CustomFieldService::getValidSlugs('property_type')),
            'distress_markers' => 'nullable|array',
            'distress_markers.*' => 'string|in:' . implode(',', CustomFieldService::getValidSlugs('distress_markers')),
            'notes'            => 'nullable|string|max:2000',
        ]);

        $data = AddressNormalizationService::normalizeAll($data);
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['distress_markers'] = $data['distress_markers'] ?? [];

        $property = Property::create($data);

        AuditLog::log('property.created', $property);

        return redirect()->route('dashboard')->with('success', 'Property submitted successfully.');
    }

    public function uploadPhotos(Request $request, Property $property)
    {
        $this->authorize('update', $property);

        $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'captions' => 'nullable|array',
            'captions.*' => 'nullable|string|max:255',
        ]);

        $nextOrder = (int) ($property->photos()->max('sort_order') ?? -1) + 1;
        $uploaded = 0;

        foreach ($request->file('photos') as $index => $file) {
            $filename = (string) Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                "property-photos/{$property->tenant_id}/{$property->id}",
                $filename,
                'public'
            );

            PropertyPhoto::create([
                'tenant_id' => $property->tenant_id,
                'property_id' => $property->id,
                'uploaded_by' => auth()->id(),
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'caption' => $request->input("captions.{$index}"),
                'sort_order' => $nextOrder + $index,
            ]);

            $uploaded++;
        }

        AuditLog::log('property.photos_uploaded', $property, [], ['count' => $uploaded]);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', "تم رفع {$uploaded} صورة للعقار.");
    }

    public function deletePhoto(Property $property, PropertyPhoto $photo)
    {
        $this->authorize('update', $property);

        if (
            (int) $photo->property_id !== (int) $property->id
            || (int) $photo->tenant_id !== (int) auth()->user()->tenant_id
        ) {
            abort(404);
        }

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        AuditLog::log('property.photo_deleted', $property);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'تم حذف صورة العقار.');
    }

    /**
     * Show a property detail page.
     */
    public function show(Property $property)
    {
        $this->authorize('view', $property);
        $property->load(['lead', 'photos.uploader']);
        $property->loadCount([
            'matches as eligible_matches_count' => fn ($query) => $query->visibleTo(auth()->user())->where('status', 'eligible'),
            'showings',
            'deals',
        ]);

        return view('properties.show', [
            'property' => $property,
            'propertyTypes' => CustomFieldService::getOptions('property_type'),
        ]);
    }

    private function formOptions(): array
    {
        $user = auth()->user();

        $leads = Lead::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'phone', 'agent_id']);

        return [
            'leads' => $leads,
            'propertyTypes' => CustomFieldService::getOptions('property_type', $user->tenant),
        ];
    }

    private function pricePerSqm(array $data): ?float
    {
        $price = $data['list_price'] ?? $data['asking_price'] ?? null;
        $area = $data['area_sqm'] ?? null;

        if ($price === null || $area === null || (float) $area <= 0) {
            return $data['price_per_sqm'] ?? null;
        }

        return round((float) $price / (float) $area, 2);
    }
}
