<?php

namespace App\Http\Controllers;

use App\Events\DealStageChanged;
use App\Facades\Hooks;
use App\Http\Requests\DealRequest;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deal;
use App\Models\DealDocument;
use App\Models\DealOffer;
use App\Models\Role;
use App\Models\Showing;
use App\Models\TransactionChecklist;
use App\Models\User;
use App\Notifications\BuyerMatchFound;
use App\Notifications\DealStageChanged as DealStageChangedNotification;
use App\Services\BuyerScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class DealController extends Controller
{
    public function pipeline(Request $request)
    {
        $this->authorize('viewAny', Deal::class);

        $query = Deal::with(['lead.property', 'property', 'propertyRequest.lead', 'agent']);

        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        }

        // Filters
        if ($request->filled('agent')) {
            $query->where('agent_id', $request->agent);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('lead', function ($lq) use ($search) {
                      $lq->where(function ($inner) use ($search) {
                          $inner->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                      });
                  })
                  ->orWhereHas('lead.property', function ($pq) use ($search) {
                      $pq->where('address', 'like', "%{$search}%");
                  });
            });
        }

        $deals = $query->get()->groupBy('stage');
        $stages = Deal::stageLabels();

        // Agents for filter dropdown (admin only)
        $agents = collect();
        if (auth()->user()->isAdmin()) {
            $agents = \App\Models\User::where('tenant_id', auth()->user()->tenant_id)
                ->whereHas('role', fn($q) => $q->whereIn('name', ['admin', 'agent', 'acquisition_agent', 'disposition_agent', 'listing_agent', 'buyers_agent']))
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('deals.pipeline', compact('deals', 'stages', 'agents'));
    }

    public function updateStage(Request $request, Deal $deal)
    {
        $this->authorize('changeStage', $deal);

        $request->validate([
            'stage' => 'required|in:' . implode(',', array_keys(Deal::stages())),
        ]);

        $oldStage = $deal->stage;
        $updateData = [
            'stage' => $request->stage,
            'stage_changed_at' => now(),
        ];

        // Auto-calculate due_diligence_end_date when moving to under_contract
        if ($request->stage === 'under_contract' && $deal->contract_date && $deal->inspection_period_days > 0) {
            $updateData['due_diligence_end_date'] = $deal->contract_date->copy()->addDays($deal->inspection_period_days);
        }

        $deal->update($updateData);

        if (\App\Services\BusinessModeService::isRealEstate() && $deal->property_id && $deal->property_request_id) {
            $this->syncSaudiTransactionState($deal, $request->stage);
        }

        Activity::create([
            'tenant_id' => auth()->user()->tenant_id,
            'lead_id' => $deal->lead_id,
            'agent_id' => auth()->id(),
            'type' => 'stage_change',
            'subject' => 'Deal stage changed',
            'body' => 'Stage changed from "' . Deal::stageLabel($oldStage) . '" to "' . Deal::stageLabel($request->stage) . '"',
            'logged_at' => now(),
        ]);

        event(new DealStageChanged($deal, $oldStage));
        AuditLog::log('deal.stage_changed', $deal, ['stage' => $oldStage], ['stage' => $request->stage]);
        Hooks::doAction('deal.stage_changed', $deal, $oldStage);

        \App\Services\WebhookService::dispatch('deal.stage_changed', [
            'deal_id' => $deal->id,
            'title' => $deal->title,
            'old_stage' => $oldStage,
            'new_stage' => $request->stage,
            'agent_id' => $deal->agent_id,
        ], auth()->user()->tenant_id);

        // Notify deal agent of stage change
        $tenant = auth()->user()->tenant;
        if ($tenant->wantsNotification('deal_stage_changed') && $deal->agent_id) {
            $deal->load('lead');
            $deal->agent->notify(new DealStageChangedNotification($deal, $oldStage, $tenant));
        }

        // Dispatch buyer matching when deal moves to the mode-appropriate trigger stage
        $matchTrigger = \App\Services\BusinessModeService::getBuyerMatchTriggerStage();
        if ($request->stage === $matchTrigger) {
            app(\App\Services\BuyerMatchService::class)->matchForDeal($deal);

            // Notify admins and relevant agents if buyer matches found
            if ($tenant->wantsNotification('buyer_matched')) {
                $deal->load('buyerMatches');
                $matches = $deal->buyerMatches;
                if ($matches->count() > 0) {
                    $topScore = $matches->max('score') ?? 0;
                    $adminRoleId = Role::where('name', 'admin')->value('id');

                    $isRE = \App\Services\BusinessModeService::isRealEstate($tenant);
                    $notifyRoles = $isRE
                        ? ['listing_agent', 'buyers_agent']
                        : ['disposition_agent'];
                    $extraRoleIds = Role::whereIn('name', $notifyRoles)->pluck('id')->all();

                    $recipients = User::where('tenant_id', $tenant->id)
                        ->whereIn('role_id', array_merge([$adminRoleId], $extraRoleIds))
                        ->where('is_active', true)
                        ->get();
                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients, new BuyerMatchFound($deal, $matches->count(), $topScore, $tenant));
                    }
                }
            }
        }

        // Decrease buyer reliability if deal reverts back to match trigger stage (buyer backed out)
        if ($oldStage !== $matchTrigger && $request->stage === $matchTrigger) {
            $assignedMatch = $deal->buyerMatches()->where('status', 'interested')->first();
            if ($assignedMatch && $assignedMatch->buyer) {
                $assignedMatch->update(['status' => 'passed']);
                BuyerScoreService::recalculate($assignedMatch->buyer);
            }
        }

        // Auto-create transaction checklist when entering under_contract in realestate mode
        if ($request->stage === 'under_contract' && \App\Services\BusinessModeService::isRealEstate()) {
            if ($deal->checklistItems()->count() === 0) {
                foreach (TransactionChecklist::DEFAULT_ITEMS as $item) {
                    TransactionChecklist::create([
                        'tenant_id' => auth()->user()->tenant_id,
                        'deal_id' => $deal->id,
                        ...$item,
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    public function startFromShowing(Showing $showing)
    {
        $this->authorize('create', Deal::class);
        $this->authorize('update', $showing);

        $showing->load(['property.lead', 'propertyRequest.lead']);

        $property = $showing->property;
        $propertyRequest = $showing->propertyRequest;

        abort_unless($property && $propertyRequest, 422, 'المعاينة يجب أن تكون مرتبطة بعقار وطلب عميل.');

        abort_unless(
            (int) $property->tenant_id === (int) auth()->user()->tenant_id
            && (int) $propertyRequest->tenant_id === (int) auth()->user()->tenant_id,
            403
        );

        $clientName = trim((string) ($propertyRequest->lead?->full_name ?? 'عميل'));

        $deal = Deal::firstOrCreate(
            [
                'tenant_id' => auth()->user()->tenant_id,
                'property_id' => $property->id,
                'property_request_id' => $propertyRequest->id,
            ],
            [
                'lead_id' => $property->lead_id,
                'agent_id' => $propertyRequest->agent_id ?? $showing->agent_id ?? auth()->id(),
                'title' => 'صفقة '.$clientName.' - '.$property->address,
                'stage' => 'offer_received',
            ]
        );

        if (! $showing->deal_id) {
            $showing->update(['deal_id' => $deal->id]);
        }

        if ($deal->wasRecentlyCreated) {
            AuditLog::log('deal.created_from_showing', $deal);
        }

        return redirect()
            ->route('deals.show', $deal)
            ->with('success', 'تم فتح الصفقة وربطها بالعقار وطلب العميل.');
    }

    public function show(Deal $deal)
    {
        $this->authorize('view', $deal);
        $deal->load(['lead.property', 'property', 'propertyRequest.lead', 'agent', 'documents', 'buyerMatches.buyer', 'activities.agent']);

        if (\App\Services\BusinessModeService::isRealEstate()) {
            $deal->load(['offers', 'checklistItems']);
        }

        if (request()->ajax()) {
            return response()->json($deal);
        }

        return view('deals.show', compact('deal'));
    }

    public function update(DealRequest $request, Deal $deal)
    {
        $this->authorize('update', $deal);

        $deal->update($request->validated());

        // Recalculate due_diligence_end_date if relevant fields changed
        if ($deal->contract_date && $deal->inspection_period_days > 0 && $deal->stage === 'under_contract') {
            $deal->update([
                'due_diligence_end_date' => $deal->contract_date->copy()->addDays($deal->inspection_period_days),
            ]);
        }

        return response()->json(['success' => true, 'deal' => $deal->fresh()]);
    }

    public function uploadDocument(Request $request, Deal $deal)
    {
        $this->authorize('uploadDocument', $deal);

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('document');
        $path = $file->store("deals/{$deal->id}", 'local');

        DealDocument::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'filename' => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function downloadDocument(DealDocument $document)
    {
        $deal = Deal::findOrFail($document->deal_id);
        $this->authorize('view', $deal);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function notifyBuyer(Deal $deal, \App\Models\DealBuyerMatch $match)
    {
        $this->authorize('notifyBuyer', $deal);

        $match->update(['notified_at' => now()]);

        event(new \App\Events\BuyerNotified($match->buyer, $deal));
        Hooks::doAction('buyer.notified', $match->buyer, $deal);

        return redirect()->back()->with('success', 'Buyer notified successfully.');
    }

    public function export(Request $request)
    {
        $this->authorize('export', Deal::class);

        $query = Deal::with(['lead', 'agent']);

        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        }

        if ($request->filled('agent')) {
            $query->where('agent_id', $request->agent);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('lead', function ($lq) use ($search) {
                      $lq->where(function ($inner) use ($search) {
                          $inner->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                      });
                  });
            });
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            $terms = \App\Services\BusinessModeService::getTerminology();
            $feeColumn = \App\Services\BusinessModeService::getDashboardKpiConfig()['fee_column'];
            fputcsv($handle, [
                __('Title'), __('Lead Name'), __('Stage'), __('Contract Price'),
                $terms['money_label'], __('Agent'), __('Days in Stage'), __('Created Date'),
            ]);
            foreach ($query->with(['lead', 'agent'])->latest()->cursor() as $deal) {
                $daysInStage = $deal->stage_changed_at
                    ? (int) now()->diffInDays($deal->stage_changed_at, true)
                    : '';
                fputcsv($handle, [
                    $deal->title,
                    $deal->lead ? $deal->lead->first_name . ' ' . $deal->lead->last_name : '',
                    Deal::stageLabel($deal->stage),
                    $deal->contract_price,
                    $deal->{$feeColumn},
                    $deal->agent->name ?? '',
                    $daysInStage,
                    $deal->created_at?->format('Y-m-d'),
                ]);
            }
            fclose($handle);
        }, 'deals-export-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Transaction Checklist ─────────────────────────────

    public function storeChecklist(Deal $deal)
    {
        $this->authorize('update', $deal);

        if ($deal->checklistItems()->count() > 0) {
            return response()->json(['message' => __('Checklist already exists.')], 422);
        }

        foreach (TransactionChecklist::DEFAULT_ITEMS as $item) {
            TransactionChecklist::create([
                'tenant_id' => auth()->user()->tenant_id,
                'deal_id' => $deal->id,
                ...$item,
            ]);
        }

        return redirect()->back()->with('success', __('Transaction checklist created.'));
    }

    public function updateChecklistItem(Request $request, TransactionChecklist $item)
    {
        $deal = Deal::findOrFail($item->deal_id);
        $this->authorize('update', $deal);

        $request->validate([
            'status' => 'nullable|in:' . implode(',', array_keys(TransactionChecklist::STATUSES)),
            'deadline' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $data = $request->only(['status', 'deadline', 'notes']);
        if (($data['status'] ?? null) === 'completed' && !$item->completed_at) {
            $data['completed_at'] = now();
        }
        if (($data['status'] ?? null) !== 'completed') {
            $data['completed_at'] = null;
        }

        $item->update($data);
        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function addChecklistItem(Request $request, Deal $deal)
    {
        $this->authorize('update', $deal);

        $request->validate([
            'label' => 'required|string|max:255',
            'deadline' => 'nullable|date',
        ]);

        $maxOrder = $deal->checklistItems()->max('sort_order') ?? 0;

        $item = TransactionChecklist::create([
            'tenant_id' => auth()->user()->tenant_id,
            'deal_id' => $deal->id,
            'item_key' => 'custom_' . time(),
            'label' => $request->label,
            'deadline' => $request->deadline,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function removeChecklistItem(TransactionChecklist $item)
    {
        $deal = Deal::findOrFail($item->deal_id);
        $this->authorize('update', $deal);

        $item->delete();
        return response()->json(['success' => true]);
    }

    // ── Offer Management ──────────────────────────────────

    public function storeOffer(Request $request, Deal $deal)
    {
        $this->authorize('update', $deal);

        if (\App\Services\BusinessModeService::isRealEstate() && $deal->property_request_id && ! $request->filled('buyer_name')) {
            $deal->loadMissing('propertyRequest.lead');
            $request->merge([
                'buyer_name' => trim((string) ($deal->propertyRequest?->lead?->full_name ?? 'عميل')),
            ]);
        }

        $request->validate([
            'buyer_name' => 'required|string|max:255',
            'buyer_agent_name' => 'nullable|string|max:255',
            'buyer_agent_phone' => 'nullable|string|max:50',
            'buyer_agent_email' => 'nullable|email|max:255',
            'offer_price' => 'required|numeric|min:0',
            'earnest_money' => 'nullable|numeric|min:0',
            'financing_type' => 'nullable|in:' . implode(',', array_keys(DealOffer::FINANCING_TYPES)),
            'contingencies' => 'nullable|array',
            'contingencies.*' => 'string',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $offer = DealOffer::create([
            'tenant_id' => auth()->user()->tenant_id,
            'deal_id' => $deal->id,
            ...$request->only([
                'buyer_name', 'buyer_agent_name', 'buyer_agent_phone', 'buyer_agent_email',
                'offer_price', 'earnest_money', 'financing_type', 'contingencies',
                'expiration_date', 'notes',
            ]),
        ]);

        Activity::create([
            'tenant_id' => auth()->user()->tenant_id,
            'lead_id' => $deal->lead_id,
            'deal_id' => $deal->id,
            'agent_id' => auth()->id(),
            'type' => 'note',
            'subject' => __('Offer received'),
            'body' => __(':buyer offered :price', [
                'buyer' => $request->buyer_name,
                'price' => '$' . number_format($request->offer_price, 2),
            ]),
            'logged_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Offer recorded successfully.'));
    }

    public function updateOffer(Request $request, DealOffer $offer)
    {
        $deal = Deal::findOrFail($offer->deal_id);
        $this->authorize('update', $deal);

        $request->validate([
            'status' => 'nullable|in:' . implode(',', array_keys(DealOffer::STATUSES)),
            'counter_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $offer->status;
        $offer->update($request->only(['status', 'counter_price', 'notes']));

        if (\App\Services\BusinessModeService::isRealEstate() && $deal->property_id && $deal->property_request_id) {
            if ($offer->status === 'countered') {
                $deal->update([
                    'stage' => 'negotiating',
                    'stage_changed_at' => now(),
                ]);
            }

            if ($offer->status === 'accepted') {
                $acceptedPrice = $offer->counter_price ?? $offer->offer_price;

                $deal->update([
                    'contract_price' => $acceptedPrice,
                    'contract_date' => $deal->contract_date ?? now()->toDateString(),
                    'stage' => 'under_contract',
                    'stage_changed_at' => now(),
                ]);

                $deal->offers()
                    ->whereKeyNot($offer->id)
                    ->whereIn('status', ['pending', 'countered'])
                    ->update(['status' => 'rejected']);

                $this->syncSaudiTransactionState($deal, 'under_contract');
            }
        }

        if ($oldStatus !== $offer->status) {
            Activity::create([
                'tenant_id' => auth()->user()->tenant_id,
                'lead_id' => $deal->lead_id,
                'deal_id' => $deal->id,
                'agent_id' => auth()->id(),
                'type' => 'note',
                'subject' => __('Offer status changed'),
                'body' => __('Offer from :buyer changed from :old to :new', [
                    'buyer' => $offer->buyer_name,
                    'old' => __(DealOffer::STATUSES[$oldStatus] ?? $oldStatus),
                    'new' => __(DealOffer::STATUSES[$offer->status] ?? $offer->status),
                ]),
                'logged_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'offer' => $offer->fresh()]);
    }

    public function destroyOffer(DealOffer $offer)
    {
        $deal = Deal::findOrFail($offer->deal_id);
        $this->authorize('update', $deal);

        $offer->delete();

        return response()->json(['success' => true]);
    }

    private function syncSaudiTransactionState(Deal $deal, string $stage): void
    {
        $deal->loadMissing(['property', 'propertyRequest']);
        $property = $deal->property;
        $propertyRequest = $deal->propertyRequest;

        if (! $property || ! $propertyRequest) {
            return;
        }

        if ($stage === 'under_contract') {
            if ($property->listing_status === 'active') {
                $property->update(['listing_status' => 'pending']);
            }

            if ($propertyRequest->status === 'active') {
                $propertyRequest->update(['status' => 'paused']);
            }

            return;
        }

        if ($stage === 'closed_won') {
            if ($property->transaction_type === 'rent') {
                $property->update(['listing_status' => 'leased']);
            } else {
                $property->update([
                    'listing_status' => 'sold',
                    'sold_at' => now()->toDateString(),
                    'sold_price' => $deal->contract_price,
                ]);
            }

            if ($propertyRequest->status !== 'fulfilled') {
                $propertyRequest->update(['status' => 'fulfilled']);
            }

            if ($deal->total_commission !== null && $deal->commission_status !== 'paid') {
                $deal->update(['commission_status' => 'due']);
            }

            return;
        }

        if ($stage === 'closed_lost') {
            if ($property->listing_status === 'pending') {
                $property->update(['listing_status' => 'active']);
            }

            if ($propertyRequest->status === 'paused') {
                $propertyRequest->update(['status' => 'active']);
            }
        }
    }

}
