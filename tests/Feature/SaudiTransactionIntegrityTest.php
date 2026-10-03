<?php

namespace Tests\Feature;

use App\Models\DealOffer;
use App\Models\PropertyRequest;
use Tests\TestCase;

class SaudiTransactionIntegrityTest extends TestCase
{
    private function transaction(string $type = 'sale'): array
    {
        $this->actingAsAdmin(['business_mode' => 'realestate', 'country' => 'SA', 'currency' => 'SAR']);
        $property = $this->createProperty(['transaction_type' => $type, 'listing_status' => 'active']);
        $request = PropertyRequest::create([
            'tenant_id' => $this->tenant->id, 'lead_id' => $this->createLead()->id,
            'agent_id' => $this->adminUser->id, 'transaction_type' => $type,
            'property_type' => $property->property_type, 'city' => $property->city, 'status' => 'active',
        ]);
        $deal = $this->createDeal([
            'lead_id' => $property->lead_id, 'property_id' => $property->id,
            'property_request_id' => $request->id, 'stage' => 'offer_received', 'contract_price' => null,
        ]);
        $offer = DealOffer::create([
            'tenant_id' => $this->tenant->id, 'deal_id' => $deal->id,
            'buyer_name' => 'عميل الاختبار', 'offer_price' => 100000, 'status' => 'pending',
        ]);
        return [$property, $request, $deal, $offer];
    }

    public function test_competing_deal_cannot_reserve_same_property_or_release_its_reservation(): void
    {
        [$property, $request, $deal, $offer] = $this->transaction();
        $otherRequest = $request->replicate();
        $otherRequest->lead_id = $this->createLead()->id;
        $otherRequest->save();
        $otherDeal = $deal->replicate();
        $otherDeal->property_request_id = $otherRequest->id;
        $otherDeal->save();
        $otherOffer = $offer->replicate();
        $otherOffer->deal_id = $otherDeal->id;
        $otherOffer->save();

        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'accepted'])->assertOk();
        $this->patchJson(route('deals.updateOffer', $otherOffer), ['status' => 'accepted'])->assertUnprocessable();
        $this->assertSame('pending', $otherOffer->fresh()->status);
        $this->assertSame('offer_received', $otherDeal->fresh()->stage);
        $this->patchJson(route('deals.updateStage', $otherDeal), ['stage' => 'closed_lost'])->assertOk();
        $this->assertSame('pending', $property->fresh()->listing_status);
        $this->assertSame('paused', $request->fresh()->status);
    }

    public function test_competing_property_cannot_fulfil_an_already_reserved_request(): void
    {
        [$property, $request, $deal, $offer] = $this->transaction();
        $otherProperty = $this->createProperty(['lead_id' => $property->lead_id, 'transaction_type' => 'sale', 'listing_status' => 'active']);
        $otherDeal = $deal->replicate();
        $otherDeal->property_id = $otherProperty->id;
        $otherDeal->contract_price = 90000;
        $otherDeal->save();
        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'accepted'])->assertOk();
        $this->patchJson(route('deals.updateStage', $otherDeal), ['stage' => 'closed_won'])->assertUnprocessable();
        $this->assertSame('active', $otherProperty->fresh()->listing_status);
        $this->assertSame('paused', $request->fresh()->status);
    }

    public function test_zero_price_offer_cannot_be_accepted_and_leaves_no_partial_changes(): void
    {
        [$property, $request, $deal, $offer] = $this->transaction();
        $offer->update(['offer_price' => 0]);
        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'accepted'])->assertUnprocessable();
        $this->assertSame('pending', $offer->fresh()->status);
        $this->assertSame('offer_received', $deal->fresh()->stage);
        $this->assertSame('active', $property->fresh()->listing_status);
        $this->assertSame('active', $request->fresh()->status);
        $this->assertSame(0, $deal->checklistItems()->count());
    }

    public function test_accepted_offer_is_retained_and_note_edits_do_not_rewind_stage(): void
    {
        [$property, $request, $deal, $offer] = $this->transaction();
        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'accepted'])->assertOk();
        $this->patchJson(route('deals.updateStage', $deal), ['stage' => 'closing'])->assertOk();
        $this->patchJson(route('deals.updateOffer', $offer), ['notes' => 'ملاحظة لاحقة'])->assertOk();
        $this->assertSame('closing', $deal->fresh()->stage);
        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'rejected'])->assertUnprocessable();
        $this->deleteJson(route('deals.destroyOffer', $offer))->assertUnprocessable();
        $this->assertSame('accepted', $offer->fresh()->status);
    }

    public function test_rental_closure_preserves_history_and_late_commission_becomes_due(): void
    {
        [$property, $request, $deal, $offer] = $this->transaction('rent');
        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'accepted'])->assertOk();
        $this->patchJson(route('deals.updateStage', $deal), ['stage' => 'closed_won'])->assertOk();
        $this->assertSame('leased', $property->fresh()->listing_status);
        $this->assertSame('fulfilled', $request->fresh()->status);
        $this->patchJson(route('deals.updateOffer', $offer), ['status' => 'accepted'])->assertUnprocessable();
        $this->patchJson(route('deals.updateStage', $deal), ['stage' => 'negotiating'])->assertUnprocessable();
        $this->patchJson(route('deals.quickUpdate', $deal), ['contract_price' => 500])->assertUnprocessable();
        $this->patchJson(route('deals.quickUpdate', $deal), ['total_commission' => 2500, 'brokerage_split_pct' => 40])->assertOk();
        $this->assertSame('due', $deal->fresh()->commission_status);
        $this->patchJson(route('deals.quickUpdate', $deal), ['commission_status' => 'paid'])->assertOk();
        $paidAt = $deal->fresh()->commission_paid_at;
        $this->travel(1)->days();
        $this->patchJson(route('deals.quickUpdate', $deal), ['commission_status' => 'paid'])->assertOk();
        $this->assertTrue($paidAt->equalTo($deal->fresh()->commission_paid_at));
        $this->assertSame(1000.0, $deal->fresh()->office_commission_amount);
        $this->assertSame(1500.0, $deal->fresh()->agent_commission_amount);
    }
}
