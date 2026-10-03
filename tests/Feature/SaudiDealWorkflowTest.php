<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Property;
use App\Models\PropertyRequest;
use App\Models\Showing;
use Tests\TestCase;

class SaudiDealWorkflowTest extends TestCase
{
    private function realEstateTenant(): array
    {
        return [
            'business_mode' => 'realestate',
            'country' => 'SA',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
        ];
    }

    public function test_showing_can_start_deal_linked_to_property_and_request(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا نمار',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'list_price' => 1700000,
            'listing_status' => 'active',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1800000,
            'status' => 'active',
        ]);

        $showing = Showing::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'showing_date' => '2026-10-05',
            'showing_time' => '17:00',
            'status' => 'completed',
            'outcome' => 'made_offer',
        ]);

        $response = $this->post(route('showings.startDeal', $showing));

        $deal = Deal::withoutGlobalScopes()->latest('id')->firstOrFail();

        $response->assertRedirect(route('deals.show', $deal));

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'stage' => 'offer_received',
        ]);

        $this->assertSame($deal->id, $showing->fresh()->deal_id);
        $this->assertSame($client->id, $deal->fresh()->propertyRequest->lead_id);
    }

    public function test_starting_same_deal_twice_does_not_duplicate_transaction(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'شقة اختبار',
            'city' => 'الرياض',
            'property_type' => 'apartment',
            'transaction_type' => 'rent',
            'listing_status' => 'active',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $showing = Showing::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'showing_date' => '2026-10-05',
            'showing_time' => '18:00',
        ]);

        $this->post(route('showings.startDeal', $showing))->assertRedirect();
        $this->post(route('showings.startDeal', $showing))->assertRedirect();

        $this->assertSame(1, Deal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_id', $property->id)
            ->where('property_request_id', $propertyRequest->id)
            ->count());
    }

    public function test_agent_cannot_start_deal_from_another_agents_showing(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');

        $owner = $this->createLead(['agent_id' => $agentA->id]);
        $client = $this->createLead(['agent_id' => $agentA->id]);

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار وسيط أ',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $agentA->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $showing = Showing::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'lead_id' => $client->id,
            'agent_id' => $agentA->id,
            'showing_date' => '2026-10-05',
            'showing_time' => '19:00',
        ]);

        $this->actingAs($agentB)
            ->post(route('showings.startDeal', $showing))
            ->assertForbidden();

        $this->assertDatabaseMissing('deals', [
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
        ]);
    }

    public function test_accepting_offer_moves_transaction_under_contract_and_pauses_inventory(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا تفاوض',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'list_price' => 1800000,
            'listing_status' => 'active',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1900000,
            'status' => 'active',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة تفاوض',
            'stage' => 'offer_received',
        ]);

        $offer = \App\Models\DealOffer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'buyer_name' => $client->full_name,
            'offer_price' => 1750000,
            'status' => 'pending',
        ]);

        $this->patchJson(route('deals.updateOffer', $offer), [
            'status' => 'accepted',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('under_contract', $deal->fresh()->stage);
        $this->assertSame('1750000.00', $deal->fresh()->contract_price);
        $this->assertSame('pending', $property->fresh()->listing_status);
        $this->assertSame('paused', $propertyRequest->fresh()->status);
    }

    public function test_closing_sale_marks_property_sold_request_fulfilled_and_commission_due(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا إغلاق',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'pending',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'status' => 'paused',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة إغلاق بيع',
            'stage' => 'under_contract',
            'contract_price' => 1700000,
            'total_commission' => 42500,
            'brokerage_split_pct' => 40,
            'commission_status' => 'pending',
        ]);

        $this->patchJson(route('deals.updateStage', $deal), [
            'stage' => 'closed_won',
        ])->assertOk();

        $this->assertSame('closed_won', $deal->fresh()->stage);
        $this->assertSame('due', $deal->fresh()->commission_status);
        $this->assertSame('sold', $property->fresh()->listing_status);
        $this->assertSame('1700000.00', $property->fresh()->sold_price);
        $this->assertNotNull($property->fresh()->sold_at);
        $this->assertSame('fulfilled', $propertyRequest->fresh()->status);
        $this->assertSame(17000.0, $deal->fresh()->office_commission_amount);
        $this->assertSame(25500.0, $deal->fresh()->agent_commission_amount);
    }

    public function test_closing_rental_marks_property_leased(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'شقة إيجار',
            'city' => 'الرياض',
            'property_type' => 'apartment',
            'transaction_type' => 'rent',
            'listing_status' => 'pending',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'الرياض',
            'status' => 'paused',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة إيجار',
            'stage' => 'under_contract',
            'contract_price' => 85000,
        ]);

        $this->patchJson(route('deals.updateStage', $deal), [
            'stage' => 'closed_won',
        ])->assertOk();

        $this->assertSame('leased', $property->fresh()->listing_status);
        $this->assertSame('fulfilled', $propertyRequest->fresh()->status);
    }

    public function test_lost_transaction_reopens_pending_property_and_paused_request(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار يعود للسوق',
            'city' => 'الرياض',
            'property_type' => 'land',
            'transaction_type' => 'sale',
            'listing_status' => 'pending',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'land',
            'city' => 'الرياض',
            'status' => 'paused',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة لم تتم',
            'stage' => 'under_contract',
        ]);

        $this->patchJson(route('deals.updateStage', $deal), [
            'stage' => 'closed_lost',
        ])->assertOk();

        $this->assertSame('active', $property->fresh()->listing_status);
        $this->assertSame('active', $propertyRequest->fresh()->status);
    }

    public function test_marking_commission_paid_sets_payment_timestamp(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());
        $deal = $this->createDeal([
            'stage' => 'closed_won',
            'total_commission' => 25000,
            'brokerage_split_pct' => 30,
        ]);

        $this->patchJson(route('deals.quickUpdate', $deal), [
            'commission_status' => 'paid',
        ])->assertOk();

        $this->assertSame('paid', $deal->fresh()->commission_status);
        $this->assertNotNull($deal->fresh()->commission_paid_at);
    }

}
