<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyCriteriaRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\PropertyRequest as PropertySearchRequest;
use App\Models\User;
use App\Services\AddressNormalizationService;
use App\Services\CustomFieldService;
use App\Services\PropertyMatchingService;
use Illuminate\Http\Request;

class PropertyRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PropertySearchRequest::class);

        $query = PropertySearchRequest::with(['lead', 'agent'])
            ->withCount(['matches as eligible_matches_count' => fn ($q) => $q->where('status', 'eligible')]);

        if (! auth()->user()->isAdmin()) {
            $query->where('agent_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->string('transaction_type')->toString());
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->string('property_type')->toString());
        }

        if ($request->filled('city')) {
            $query->whereRaw('LOWER(TRIM(city)) = ?', [
                mb_strtolower(trim($request->string('city')->toString())),
            ]);
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('city', 'like', "%{$search}%")
                    ->orWhereHas('lead', function ($leadQuery) use ($search) {
                        $leadQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $propertyRequests = $query->latest()->paginate(25)->withQueryString();

        return view('property-requests.index', [
            'propertyRequests' => $propertyRequests,
            'propertyTypes' => CustomFieldService::getOptions('property_type'),
            'transactionTypes' => PropertySearchRequest::TRANSACTION_TYPES,
            'statuses' => PropertySearchRequest::STATUSES,
        ]);
    }

    public function create()
    {
        $this->authorize('create', PropertySearchRequest::class);

        return view('property-requests.create', $this->formOptions());
    }

    public function store(PropertyCriteriaRequest $request)
    {
        $this->authorize('create', PropertySearchRequest::class);

        $data = $request->criteria();
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['city'] = AddressNormalizationService::normalizeCity($data['city']);
        $data['agent_id'] = auth()->user()->isAdmin()
            ? ($data['agent_id'] ?? auth()->id())
            : auth()->id();

        $propertyRequest = PropertySearchRequest::create($data);

        AuditLog::log('property_request.created', $propertyRequest);

        return redirect()
            ->route('property-requests.show', $propertyRequest)
            ->with('success', __('Property request created and matched successfully.'));
    }

    public function show(PropertySearchRequest $propertyRequest)
    {
        $this->authorize('view', $propertyRequest);

        $propertyRequest->load(['lead', 'agent']);
        $matches = $propertyRequest->matches()
            ->with(['property.lead'])
            ->where('status', 'eligible')
            ->orderByDesc('match_score')
            ->paginate(25);

        return view('property-requests.show', [
            'propertyRequest' => $propertyRequest,
            'matches' => $matches,
            'propertyTypes' => CustomFieldService::getOptions('property_type'),
            'transactionTypes' => PropertySearchRequest::TRANSACTION_TYPES,
            'statuses' => PropertySearchRequest::STATUSES,
        ]);
    }

    public function edit(PropertySearchRequest $propertyRequest)
    {
        $this->authorize('update', $propertyRequest);

        return view('property-requests.edit', [
            'propertyRequest' => $propertyRequest,
            ...$this->formOptions(),
        ]);
    }

    public function update(PropertyCriteriaRequest $request, PropertySearchRequest $propertyRequest)
    {
        $this->authorize('update', $propertyRequest);

        $data = $request->criteria();
        $data['city'] = AddressNormalizationService::normalizeCity($data['city']);
        $data['agent_id'] = auth()->user()->isAdmin()
            ? ($data['agent_id'] ?? $propertyRequest->agent_id ?? auth()->id())
            : auth()->id();

        $propertyRequest->update($data);

        AuditLog::log('property_request.updated', $propertyRequest);

        return redirect()
            ->route('property-requests.show', $propertyRequest)
            ->with('success', __('Property request updated and matching refreshed.'));
    }

    public function refresh(PropertySearchRequest $propertyRequest, PropertyMatchingService $matching)
    {
        $this->authorize('refresh', $propertyRequest);

        $matching->refreshForRequest($propertyRequest);

        return redirect()
            ->route('property-requests.show', $propertyRequest)
            ->with('success', __('Property matches refreshed.'));
    }

    public function destroy(PropertySearchRequest $propertyRequest)
    {
        $this->authorize('delete', $propertyRequest);

        if ($propertyRequest->showings()->exists() || $propertyRequest->deals()->exists()) {
            return redirect()
                ->route('property-requests.show', $propertyRequest)
                ->with('error', 'لا يمكن حذف طلب دخل في معاينة أو صفقة. حدّث حالته للحفاظ على سجل المكتب.');
        }

        AuditLog::log('property_request.deleted', $propertyRequest);
        $propertyRequest->delete();

        return redirect()
            ->route('property-requests.index')
            ->with('success', __('Property request deleted.'));
    }

    private function formOptions(): array
    {
        $user = auth()->user();

        $leads = Lead::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'phone']);

        $agents = $user->isAdmin()
            ? User::assignable($user->tenant)->orderBy('name')->get(['id', 'name'])
            : User::query()->whereKey($user->id)->get(['id', 'name']);

        return [
            'leads' => $leads,
            'agents' => $agents,
            'propertyTypes' => CustomFieldService::getOptions('property_type', $user->tenant),
            'transactionTypes' => PropertySearchRequest::TRANSACTION_TYPES,
            'statuses' => PropertySearchRequest::STATUSES,
        ];
    }
}
