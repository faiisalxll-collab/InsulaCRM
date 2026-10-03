<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyRequest as PropertySearchRequest;
use App\Models\Tenant;
use App\Policies\PropertyPolicy;
use Tests\TestCase;

class SaudiPropertyManagementTest extends TestCase
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

    public function test_admin_can_create_saudi_property_and_trigger_matching(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());
        $owner = $this->createLead();

        $request = PropertySearchRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1800000,
            'min_area_sqm' => 300,
            'status' => 'active',
        ]);

        $response = $this->post(route('properties.manage.store'), [
            'lead_id' => $owner->id,
            'address' => 'شارع اختبار',
            'city' => 'الرياض',
            'state' => 'الرياض',
            'zip_code' => '14962',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'area_sqm' => 350,
            'list_price' => 1750000,
            'bedrooms' => 5,
            'bathrooms' => 4,
            'street_width_m' => 20,
            'floors' => 2,
            'units' => 1,
            'furnished' => '0',
            'finance_eligible' => '1',
            'listing_status' => 'active',
        ]);

        $property = Property::withoutGlobalScopes()->latest('id')->firstOrFail();

        $response->assertRedirect(route('properties.show', $property));

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'listing_status' => 'active',
            'price_per_sqm' => 5000.00,
        ]);

        $this->assertDatabaseHas('property_matches', [
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $request->id,
            'property_id' => $property->id,
            'status' => 'eligible',
            'hard_constraints_passed' => true,
        ]);
    }

    public function test_agent_cannot_create_property_for_another_agents_client(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');
        $ownerA = $this->createLead(['agent_id' => $agentA->id]);

        $this->actingAs($agentB)
            ->from(route('properties.create'))
            ->post(route('properties.manage.store'), [
                'lead_id' => $ownerA->id,
                'address' => 'عقار غير مصرح',
                'city' => 'الرياض',
                'state' => 'الرياض',
                'zip_code' => '14962',
                'property_type' => 'villa',
                'transaction_type' => 'sale',
                'district' => 'نمار',
                'area_sqm' => 300,
                'list_price' => 1500000,
                'listing_status' => 'active',
            ])
            ->assertRedirect(route('properties.create'))
            ->assertSessionHasErrors('lead_id');

        $this->assertDatabaseMissing('properties', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $ownerA->id,
            'address' => 'عقار غير مصرح',
        ]);
    }

    public function test_agent_cannot_edit_another_agents_property(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');
        $ownerA = $this->createLead(['agent_id' => $agentA->id]);

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $ownerA->id,
            'address' => 'عقار خاص',
            'city' => 'الرياض',
            'state' => 'الرياض',
            'zip_code' => '14962',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'listing_status' => 'active',
        ]);

        $this->actingAs($agentB)
            ->get(route('properties.edit', $property))
            ->assertForbidden();
    }

    public function test_property_update_recalculates_price_per_sqm(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());
        $owner = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار سعر المتر',
            'city' => 'الرياض',
            'state' => 'الرياض',
            'zip_code' => '14962',
            'property_type' => 'land',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'area_sqm' => 400,
            'list_price' => 800000,
            'listing_status' => 'active',
        ]);

        $this->put(route('properties.update', $property), [
            'lead_id' => $owner->id,
            'address' => 'عقار سعر المتر',
            'city' => 'الرياض',
            'state' => 'الرياض',
            'zip_code' => '14962',
            'property_type' => 'land',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'area_sqm' => 500,
            'list_price' => 1000000,
            'furnished' => '0',
            'finance_eligible' => '0',
            'listing_status' => 'active',
        ])->assertRedirect(route('properties.show', $property));

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'price_per_sqm' => 2000.00,
        ]);
    }

    public function test_admin_policy_cannot_authorize_foreign_tenant_property(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $foreignTenant = Tenant::create([
            'name' => 'Foreign Property Office',
            'slug' => 'foreign-office-property-policy',
            'email' => 'foreign-property-policy@example.test',
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'date_format' => 'Y-m-d',
            'country' => 'SA',
            'measurement_system' => 'metric',
            'locale' => 'ar',
            'distribution_method' => 'round_robin',
            'business_mode' => 'realestate',
        ]);

        $foreignLead = \App\Models\Lead::withoutGlobalScopes()->create([
            'tenant_id' => $foreignTenant->id,
            'first_name' => 'مالك',
            'last_name' => 'مكتب آخر',
            'lead_source' => 'referral',
            'status' => 'new',
            'temperature' => 'cold',
        ]);

        $foreignProperty = Property::withoutGlobalScopes()->create([
            'tenant_id' => $foreignTenant->id,
            'lead_id' => $foreignLead->id,
            'address' => 'عقار مكتب آخر',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $policy = app(PropertyPolicy::class);

        $this->assertFalse($policy->view($this->adminUser, $foreignProperty));
        $this->assertFalse($policy->update($this->adminUser, $foreignProperty));
        $this->assertFalse($policy->delete($this->adminUser, $foreignProperty));
    }

}
