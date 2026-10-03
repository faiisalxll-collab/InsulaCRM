<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyRequest;
use App\Services\PropertyMatchingService;
use Tests\TestCase;

class SaudiPropertyMatchingTest extends TestCase
{
    public function test_namar_villa_passes_hard_constraints_and_scores_explainably(): void
    {
        $admin = $this->createTenantWithAdmin();
        $tenant = $this->tenant;
        $lead = $this->createLead();
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id,
            'address' => 'شارع تجريبي', 'city' => 'Riyadh', 'state' => 'RIYADH', 'zip_code' => '14962',
            'property_type' => 'single_family', 'transaction_type' => 'sale',
            'district' => 'Namar', 'area_sqm' => 340, 'bedrooms' => 5,
            'street_width_m' => 20, 'finance_eligible' => true,
            'list_price' => 1700000, 'facing' => 'north', 'property_age_years' => 4,
        ]);
        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id, 'agent_id' => $admin->id,
            'transaction_type' => 'sale', 'property_type' => 'single_family',
            'city' => 'Riyadh', 'districts' => ['Namar'], 'max_price' => 1800000,
            'min_area_sqm' => 300, 'min_bedrooms' => 5, 'min_street_width_m' => 15,
            'preferred_facings' => ['north'], 'max_property_age_years' => 5,
            'finance_required' => true,
        ]);

        $result = app(PropertyMatchingService::class)->evaluate($request, $property);

        $this->assertTrue($result['hard_constraints_passed']);
        $this->assertSame(100, $result['match_score']);
        $this->assertContains('district', $result['match_reasons']);
        $this->assertSame([], $result['rejection_reasons']);
    }

    public function test_wrong_district_is_rejected_as_hard_constraint(): void
    {
        $admin = $this->createTenantWithAdmin();
        $tenant = $this->tenant;
        $lead = $this->createLead();
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id,
            'address' => 'Test', 'city' => 'Riyadh', 'state' => 'RIYADH', 'zip_code' => '12345',
            'property_type' => 'single_family', 'transaction_type' => 'sale',
            'district' => 'Al Malqa', 'area_sqm' => 350, 'list_price' => 1700000,
        ]);
        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'transaction_type' => 'sale',
            'property_type' => 'single_family', 'city' => 'Riyadh',
            'districts' => ['Namar'], 'max_price' => 1800000,
        ]);

        $result = app(PropertyMatchingService::class)->evaluate($request, $property);

        $this->assertFalse($result['hard_constraints_passed']);
        $this->assertSame(0, $result['match_score']);
        $this->assertContains('district', $result['rejection_reasons']);
    }

    public function test_cross_tenant_property_is_always_rejected(): void
    {
        $adminA = $this->createTenantWithAdmin(['slug' => 'office-a', 'email' => 'a@test.com']);
        $a = $this->tenant;

        $adminB = $this->createTenantWithAdmin(['slug' => 'office-b', 'email' => 'b@test.com']);
        $b = $this->tenant;
        $leadB = $this->createLead();
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $b->id, 'lead_id' => $leadB->id,
            'address' => 'Private', 'city' => 'Riyadh', 'state' => 'RIYADH', 'zip_code' => '12345',
            'property_type' => 'single_family', 'transaction_type' => 'sale',
        ]);
        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $a->id, 'agent_id' => $adminA->id,
            'transaction_type' => 'sale', 'property_type' => 'single_family', 'city' => 'Riyadh',
        ]);

        $result = app(PropertyMatchingService::class)->evaluate($request, $property);

        $this->assertFalse($result['hard_constraints_passed']);
        $this->assertContains('tenant_mismatch', $result['rejection_reasons']);
    }

    public function test_request_creation_persists_only_prefiltered_active_property_matches(): void
    {
        $admin = $this->createTenantWithAdmin();
        $tenant = $this->tenant;
        $lead = $this->createLead();

        $eligible = Property::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id,
            'address' => 'Eligible', 'city' => 'Riyadh', 'state' => 'Riyadh', 'zip_code' => null,
            'property_type' => 'villa', 'transaction_type' => 'sale',
            'district' => 'Namar', 'area_sqm' => 340, 'list_price' => 1700000,
            'finance_eligible' => true, 'listing_status' => 'active',
        ]);

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id,
            'address' => 'Too expensive', 'city' => 'Riyadh', 'state' => 'Riyadh', 'zip_code' => null,
            'property_type' => 'villa', 'transaction_type' => 'sale',
            'district' => 'Namar', 'area_sqm' => 340, 'list_price' => 2500000,
            'finance_eligible' => true, 'listing_status' => 'active',
        ]);

        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id, 'agent_id' => $admin->id,
            'transaction_type' => 'sale', 'property_type' => 'villa',
            'city' => 'Riyadh', 'districts' => ['Namar'], 'max_price' => 1800000,
            'min_area_sqm' => 300, 'finance_required' => true, 'status' => 'active',
        ]);

        $this->assertDatabaseHas('property_matches', [
            'tenant_id' => $tenant->id,
            'property_request_id' => $request->id,
            'property_id' => $eligible->id,
            'hard_constraints_passed' => true,
            'status' => 'eligible',
        ]);

        $this->assertSame(1, \App\Models\PropertyMatch::withoutGlobalScopes()
            ->where('property_request_id', $request->id)->count());
    }

    public function test_property_update_reverse_matches_active_requests_without_duplicates(): void
    {
        $admin = $this->createTenantWithAdmin();
        $tenant = $this->tenant;
        $lead = $this->createLead();

        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id, 'agent_id' => $admin->id,
            'transaction_type' => 'sale', 'property_type' => 'villa',
            'city' => 'Riyadh', 'districts' => ['Namar'], 'max_price' => 1800000,
            'status' => 'active',
        ]);

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id,
            'address' => 'Reverse', 'city' => 'Riyadh', 'state' => 'Riyadh', 'zip_code' => null,
            'property_type' => 'villa', 'transaction_type' => 'sale',
            'district' => 'Namar', 'area_sqm' => 320, 'list_price' => 1700000,
            'listing_status' => 'active',
        ]);

        $property->update(['street_width_m' => 20]);
        $property->update(['street_width_m' => 25]);

        $this->assertSame(1, \App\Models\PropertyMatch::withoutGlobalScopes()
            ->where('property_request_id', $request->id)
            ->where('property_id', $property->id)
            ->count());
    }

}
