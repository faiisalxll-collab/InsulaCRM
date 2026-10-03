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
}
