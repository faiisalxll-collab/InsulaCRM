<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowingRequest;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyRequest as PropertySearchRequest;
use App\Models\Showing;
use Illuminate\Http\Request;

class ShowingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Showing::class);

        $query = Showing::with(['property', 'lead', 'agent', 'propertyRequest']);

        if (!auth()->user()->isAdmin()) {
            $query->where('agent_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent')) {
            $query->where('agent_id', $request->agent);
        }

        if ($request->filled('from')) {
            $query->where('showing_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('showing_date', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('property', fn ($pq) => $pq->where('address', 'like', "%{$search}%"))
                  ->orWhereHas('lead', fn ($lq) => $lq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $showings = $query->orderBy('showing_date', 'desc')->orderBy('showing_time', 'desc')->paginate(25);

        $agents = collect();
        if (auth()->user()->isAdmin()) {
            $agents = \App\Models\User::where('tenant_id', auth()->user()->tenant_id)
                ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'agent', 'listing_agent', 'buyers_agent']))
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('showings.index', compact('showings', 'agents'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Showing::class);

        $user = auth()->user();

        $properties = Property::where('listing_status', 'active')
            ->orderBy('address')
            ->get(['id', 'address', 'city', 'district', 'state', 'property_type', 'transaction_type']);

        $leads = Lead::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $agents = \App\Models\User::where('tenant_id', $user->tenant_id)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereKey($user->id))
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'agent', 'listing_agent', 'buyers_agent']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyRequests = PropertySearchRequest::with('lead')
            ->where('status', 'active')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
            ->latest()
            ->get();

        $selectedRequest = null;
        if ($request->filled('property_request_id')) {
            $selectedRequest = PropertySearchRequest::query()->findOrFail($request->integer('property_request_id'));
            $this->authorize('view', $selectedRequest);
        }

        return view('showings.create', [
            'properties' => $properties,
            'leads' => $leads,
            'agents' => $agents,
            'propertyRequests' => $propertyRequests,
            'selectedRequest' => $selectedRequest,
            'selectedPropertyId' => $request->integer('property_id') ?: null,
        ]);
    }

    public function store(ShowingRequest $request)
    {
        $this->authorize('create', Showing::class);

        $data = $request->validated();
        $user = auth()->user();
        $data['tenant_id'] = $user->tenant_id;

        if (! empty($data['property_request_id'])) {
            $propertyRequest = PropertySearchRequest::query()->findOrFail($data['property_request_id']);
            $this->authorize('view', $propertyRequest);

            if ($propertyRequest->lead_id) {
                $data['lead_id'] = $propertyRequest->lead_id;
            }

            if ($propertyRequest->agent_id) {
                $data['agent_id'] = $propertyRequest->agent_id;
            }
        }

        if (! $user->isAdmin()) {
            $data['agent_id'] = $user->id;
        } else {
            $data['agent_id'] = $data['agent_id'] ?? $user->id;
        }

        $showing = Showing::create($data);

        // Log activity on the lead if linked
        if ($showing->lead_id) {
            Activity::create([
                'tenant_id' => auth()->user()->tenant_id,
                'lead_id' => $showing->lead_id,
                'deal_id' => $showing->deal_id,
                'agent_id' => auth()->id(),
                'type' => 'meeting',
                'subject' => __('Showing scheduled'),
                'body' => __('Showing at :address on :date at :time', [
                    'address' => $showing->property->address ?? '',
                    'date' => $showing->showing_date->format('M j, Y'),
                    'time' => $showing->showing_time,
                ]),
                'logged_at' => now(),
            ]);
        }

        return redirect()->route('showings.show', $showing)->with('success', __('Showing scheduled successfully.'));
    }

    public function show(Showing $showing)
    {
        $this->authorize('view', $showing);
        $showing->load(['property', 'lead', 'agent', 'deal', 'propertyRequest']);

        return view('showings.show', compact('showing'));
    }

    public function edit(Showing $showing)
    {
        $this->authorize('update', $showing);

        $user = auth()->user();

        $properties = Property::orderBy('address')
            ->get(['id', 'address', 'city', 'district', 'state', 'property_type', 'transaction_type']);

        $leads = Lead::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $agents = \App\Models\User::where('tenant_id', $user->tenant_id)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereKey($user->id))
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'agent', 'listing_agent', 'buyers_agent']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $propertyRequests = PropertySearchRequest::with('lead')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
            ->where(function ($query) use ($showing) {
                $query->where('status', 'active');

                if ($showing->property_request_id) {
                    $query->orWhereKey($showing->property_request_id);
                }
            })
            ->latest()
            ->get();

        return view('showings.edit', compact(
            'showing',
            'properties',
            'leads',
            'agents',
            'propertyRequests'
        ));
    }

    public function update(ShowingRequest $request, Showing $showing)
    {
        $this->authorize('update', $showing);

        $data = $request->validated();
        $user = auth()->user();

        $finalRequestId = array_key_exists('property_request_id', $data)
            ? $data['property_request_id']
            : $showing->property_request_id;

        if ($finalRequestId) {
            $propertyRequest = PropertySearchRequest::query()->findOrFail($finalRequestId);
            $this->authorize('view', $propertyRequest);

            if ($propertyRequest->lead_id) {
                $data['lead_id'] = $propertyRequest->lead_id;
            }

            if ($propertyRequest->agent_id) {
                $data['agent_id'] = $propertyRequest->agent_id;
            }
        }

        if (! $user->isAdmin()) {
            $data['agent_id'] = $user->id;
        }

        $showing->update($data);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'showing' => $showing->fresh()]);
        }

        return redirect()->route('showings.show', $showing)->with('success', __('Showing updated successfully.'));
    }

    public function destroy(Showing $showing)
    {
        $this->authorize('delete', $showing);

        $showing->delete();

        return redirect()->route('showings.index')->with('success', __('Showing deleted successfully.'));
    }
}
