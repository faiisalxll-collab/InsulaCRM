<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyRequest;
use Tests\TestCase;

class SaudiPropertyRequestWorkflowTest extends TestCase
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

    public function test_admin_can_create_property_request_and_receive_match(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());
        $lead = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'address' => 'عقار اختبار',
            'city' => 'الرياض',
            'state' => 'الرياض',
            'zip_code' => '14962',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'area_sqm' => 350,
            'bedrooms' => 5,
            'street_width_m' => 20,
            'finance_eligible' => true,
            'list_price' => 1700000,
            'listing_status' => 'active',
        ]);

        $response = $this->post(route('property-requests.store'), [
            'lead_id' => $lead->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts_csv' => 'نمار',
            'max_price' => 1800000,
            'min_area_sqm' => 300,
            'min_bedrooms' => 4,
            'finance_required' => '1',
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();

        $request = PropertyRequest::withoutGlobalScopes()->latest('id')->firstOrFail();

        $response->assertRedirect(route('property-requests.show', $request));

        $this->assertDatabaseHas('property_requests', [
            'id' => $request->id,
            'tenant_id' => $this->tenant->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('property_matches', [
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $request->id,
            'property_id' => $property->id,
            'status' => 'eligible',
            'hard_constraints_passed' => true,
        ]);
    }

    public function test_agent_cannot_view_another_agents_request(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');

        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $agentA->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $this->actingAs($agentB)
            ->get(route('property-requests.show', $request))
            ->assertForbidden();
    }

    public function test_agent_cannot_attach_request_to_another_agents_client(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');
        $leadA = $this->createLead(['agent_id' => $agentA->id]);

        $this->actingAs($agentB)
            ->from(route('property-requests.create'))
            ->post(route('property-requests.store'), [
                'lead_id' => $leadA->id,
                'transaction_type' => 'sale',
                'property_type' => 'villa',
                'city' => 'الرياض',
                'districts_csv' => 'نمار',
                'max_price' => 1500000,
                'finance_required' => '0',
                'status' => 'active',
            ])
            ->assertRedirect(route('property-requests.create'))
            ->assertSessionHasErrors('lead_id');

        $this->assertDatabaseMissing('property_requests', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $leadA->id,
            'agent_id' => $agentB->id,
        ]);
    }

    public function test_agent_request_index_only_lists_owned_requests(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');

        PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $agentA->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'OWN-CITY-MARKER',
            'status' => 'active',
        ]);

        PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $agentB->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'OTHER-CITY-MARKER',
            'status' => 'active',
        ]);

        $this->actingAs($agentA)
            ->get(route('property-requests.index'))
            ->assertOk()
            ->assertSee('OWN-CITY-MARKER', false)
            ->assertDontSee('OTHER-CITY-MARKER', false);
    }

    public function test_match_page_links_directly_to_prefilled_showing(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());
        $owner = $this->createLead();
        $client = $this->createLead();

        $property = \App\Models\Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا رابط المعاينة',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'list_price' => 1500000,
            'listing_status' => 'active',
        ]);

        $propertyRequest = \App\Models\PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1600000,
            'status' => 'active',
        ]);

        $showUrl = route('property-requests.show', $propertyRequest);
        $scheduleUrl = route('showings.create', [
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
        ]);

        $this->get($showUrl)
            ->assertOk()
            ->assertSee('جدولة معاينة')
            ->assertSee($scheduleUrl);

        $this->get($scheduleUrl)
            ->assertOk()
            ->assertSee('تم فتح المعاينة من مطابقة معتمدة')
            ->assertSee('فيلا رابط المعاينة');
    }

    public function test_prefilled_showing_uses_request_client_and_agent(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());
        $owner = $this->createLead();
        $client = $this->createLead();

        $property = \App\Models\Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا معاينة محفوظة',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'list_price' => 1500000,
            'listing_status' => 'active',
        ]);

        $propertyRequest = \App\Models\PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1600000,
            'status' => 'active',
        ]);

        $response = $this->post(route('showings.store'), [
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'showing_date' => '2026-10-06',
            'showing_time' => '17:30',
            'duration_minutes' => 30,
        ]);

        $showing = \App\Models\Showing::withoutGlobalScopes()->latest('id')->firstOrFail();

        $response->assertRedirect(route('showings.show', $showing));
        $this->assertSame($client->id, $showing->lead_id);
        $this->assertSame($this->adminUser->id, $showing->agent_id);
        $this->assertSame($propertyRequest->id, $showing->property_request_id);
        $this->assertSame($property->id, $showing->property_id);
    }


    public function test_request_with_showing_cannot_be_deleted(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = \App\Models\Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار سجل الطلب',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $propertyRequest = \App\Models\PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        \App\Models\Showing::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'showing_date' => now()->addDay()->toDateString(),
            'showing_time' => '18:00',
        ]);

        $this->delete(route('property-requests.destroy', $propertyRequest))
            ->assertRedirect(route('property-requests.show', $propertyRequest))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('property_requests', ['id' => $propertyRequest->id]);
    }

}
