<?php

namespace Tests\Feature\Security;

use App\Models\Lead;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use App\Policies\LeadPolicy;
use Tests\TestCase;

class SaudiBrokerVisibilityTest extends TestCase
{
    public function test_matches_do_not_expose_another_brokers_private_inventory(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $broker = $this->createUserWithRole('agent');
        $other = $this->createUserWithRole('agent');
        $property = $this->createProperty([
            'lead_id' => $this->createLead(['agent_id' => $other->id])->id,
            'address' => 'SECRET-BROKER-PROPERTY', 'city' => 'Riyadh',
            'property_type' => 'villa', 'transaction_type' => 'sale', 'listing_status' => 'active',
        ]);
        $request = PropertyRequest::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->createLead(['agent_id' => $broker->id])->id,
            'agent_id' => $broker->id, 'city' => 'Riyadh',
            'property_type' => 'villa', 'transaction_type' => 'sale', 'status' => 'active',
        ]);
        $match = PropertyMatch::where('property_id', $property->id)->where('property_request_id', $request->id)->firstOrFail();
        $this->actingAs($broker);
        $this->assertFalse($broker->can('view', $match));
        foreach ([route('property-matches.index'), route('property-requests.show', $request), route('dashboard'), route('showings.create')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('SECRET-BROKER-PROPERTY');
        }
        $this->postJson(route('showings.store'), [
            'property_id' => $property->id, 'property_request_id' => $request->id,
            'showing_date' => '2026-10-10', 'showing_time' => '17:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('property_id');
        $this->assertSame(0, PropertyMatch::visibleTo($broker)->count());
        $this->actingAs($this->adminUser)->get(route('property-matches.index'))->assertOk()->assertSee('SECRET-BROKER-PROPERTY');
    }

    public function test_legacy_roles_do_not_bypass_saudi_record_ownership(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $property = $this->createProperty();
        $deal = $this->createDeal();
        $acquisition = $this->createUserWithRole('acquisition_agent');
        $this->actingAs($acquisition)->get(route('properties.show', $property))->assertForbidden();
        $scout = $this->createUserWithRole('field_scout');
        $this->actingAs($scout)->get(route('properties.index'))->assertForbidden();
        $disposition = $this->createUserWithRole('disposition_agent');
        $this->actingAs($disposition)->get(route('pipeline'))->assertForbidden();
        $this->get(route('deals.show', $deal))->assertForbidden();
    }

    public function test_unassigned_lead_claim_policy_is_still_tenant_scoped(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $foreign = new Lead(['tenant_id' => $this->tenant->id + 1, 'agent_id' => null]);
        $this->assertFalse((new LeadPolicy)->claim($this->adminUser, $foreign));
        $own = new Lead(['tenant_id' => $this->tenant->id, 'agent_id' => null]);
        $this->assertTrue((new LeadPolicy)->claim($this->adminUser, $own));
    }
}
