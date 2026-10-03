<?php

namespace Tests\Feature\Security;

use App\Models\Deal;
use App\Models\PropertyRequest;
use App\Models\Showing;
use Tests\TestCase;

class SaudiTransactionBoundaryTest extends TestCase
{
    private function transaction(): array
    {
        $this->actingAsAdmin(['business_mode' => 'realestate', 'country' => 'SA', 'currency' => 'SAR']);
        $property = $this->createProperty(['transaction_type' => 'sale', 'listing_status' => 'active']);
        $request = PropertyRequest::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->createLead()->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => $property->property_type,
            'city' => $property->city,
            'status' => 'active',
        ]);
        $deal = $this->createDeal([
            'lead_id' => $property->lead_id,
            'property_id' => $property->id,
            'property_request_id' => $request->id,
            'stage' => 'offer_received',
        ]);

        return [$property, $request, $deal];
    }

    public function test_quick_update_cannot_bypass_saudi_stage_transition(): void
    {
        [$property, $request, $deal] = $this->transaction();

        $this->patchJson(route('deals.quickUpdate', $deal), ['stage' => 'closed_won'])
            ->assertUnprocessable()->assertJsonValidationErrors('stage');

        $this->assertSame('offer_received', $deal->fresh()->stage);
        $this->assertSame('active', $property->fresh()->listing_status);
        $this->assertSame('active', $request->fresh()->status);
    }

    public function test_broker_cannot_attach_another_brokers_deal_to_showing(): void
    {
        [$property, $request, $deal] = $this->transaction();
        $broker = $this->createUserWithRole('agent');
        $ownProperty = $this->createProperty(['lead_id' => $this->createLead(['agent_id' => $broker->id])->id]);

        $this->actingAs($broker)->postJson(route('showings.store'), [
            'property_id' => $ownProperty->id,
            'deal_id' => $deal->id,
            'showing_date' => '2026-10-10',
            'showing_time' => '17:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('deal_id');

        $this->assertDatabaseCount('showings', 0);
    }

    public function test_even_admin_cannot_attach_deal_to_unrelated_property(): void
    {
        [$property, $request, $deal] = $this->transaction();
        $otherProperty = $this->createProperty();

        $this->postJson(route('showings.store'), [
            'property_id' => $otherProperty->id,
            'property_request_id' => $request->id,
            'deal_id' => $deal->id,
            'showing_date' => '2026-10-10',
            'showing_time' => '17:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('deal_id');
    }

    public function test_transaction_showing_keeps_relationships_but_allows_feedback(): void
    {
        [$property, $request, $deal] = $this->transaction();
        $showing = Showing::create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'property_request_id' => $request->id,
            'lead_id' => $request->lead_id,
            'agent_id' => $this->adminUser->id,
            'deal_id' => $deal->id,
            'showing_date' => '2026-10-10',
            'showing_time' => '17:00',
        ]);

        $this->putJson(route('showings.update', $showing), ['deal_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('deal_id');
        $this->putJson(route('showings.update', $showing), ['property_request_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('property_request_id');
        $this->putJson(route('showings.update', $showing), ['feedback' => 'تمت المعاينة'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertSuccessful();
        $this->assertSame($deal->id, $showing->fresh()->deal_id);
        $this->assertSame('تمت المعاينة', $showing->fresh()->feedback);
    }

    public function test_wholesale_quick_update_keeps_legacy_stage_contract(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);
        $deal = $this->createDeal(['stage' => 'prospecting']);
        $this->patchJson(route('deals.quickUpdate', $deal), ['stage' => 'contacting'])->assertOk();
        $this->assertSame('contacting', $deal->fresh()->stage);
    }
}
