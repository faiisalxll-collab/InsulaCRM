<?php

namespace Tests\Feature\Security;

use App\Models\Activity;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class TenantApiIsolationTest extends TestCase
{
    private Tenant $officeA;
    private Tenant $officeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeA = $this->makeTenant('office-a', 'office-a-key');
        $this->officeB = $this->makeTenant('office-b', 'office-b-key');
    }

    public function test_office_a_cannot_read_office_b_property_by_id(): void
    {
        $leadB = $this->makeLead($this->officeB, 'Read', 'Target');
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'lead_id' => $leadB->id,
            'address' => 'Office B Property',
            'city' => 'Riyadh',
            'state' => 'RIYADH',
            'zip_code' => '12345',
        ]);

        $this->getJson("/api/v1/properties/{$property->id}", $this->headersFor($this->officeA))
            ->assertNotFound();
    }

    public function test_property_index_contains_only_current_office_records(): void
    {
        $leadA = $this->makeLead($this->officeA, 'Visible', 'Lead');
        $leadB = $this->makeLead($this->officeB, 'Secret', 'Lead');

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeA->id,
            'lead_id' => $leadA->id,
            'address' => 'Office A Property',
            'city' => 'Riyadh',
            'state' => 'RIYADH',
            'zip_code' => '12345',
        ]);
        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'lead_id' => $leadB->id,
            'address' => 'Office B Property',
            'city' => 'Riyadh',
            'state' => 'RIYADH',
            'zip_code' => '12345',
        ]);

        $response = $this->getJson('/api/v1/properties', $this->headersFor($this->officeA))
            ->assertOk();

        $response->assertSee('Office A Property');
        $response->assertDontSee('Office B Property');
    }

    public function test_office_a_cannot_update_office_b_property(): void
    {
        $leadB = $this->makeLead($this->officeB, 'Update', 'Target');
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'lead_id' => $leadB->id,
            'address' => 'Office B Original',
            'city' => 'Riyadh',
            'state' => 'RIYADH',
            'zip_code' => '12345',
        ]);

        $this->putJson(
            "/api/v1/properties/{$property->id}",
            ['address' => 'Office A Edit'],
            $this->headersFor($this->officeA)
        )->assertNotFound();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'tenant_id' => $this->officeB->id,
            'address' => 'Office B Original',
            'city' => 'Riyadh',
            'state' => 'RIYADH',
            'zip_code' => '12345',
        ]);
    }

    public function test_deal_cannot_reference_another_offices_agent(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();

        $foreignAgent = User::factory()->create([
            'tenant_id' => $this->officeB->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeA->id,
            'first_name' => 'Office',
            'last_name' => 'A',
            'status' => 'new',
        ]);

        $this->postJson('/api/v1/deals', [
            'lead_id' => $lead->id,
            'agent_id' => $foreignAgent->id,
            'title' => 'Cross Office Deal',
        ], $this->headersFor($this->officeA))->assertNotFound();

        $this->assertDatabaseMissing('deals', [
            'tenant_id' => $this->officeA->id,
            'agent_id' => $foreignAgent->id,
        ]);
    }

    public function test_property_cannot_reference_another_offices_lead(): void
    {
        $lead = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'first_name' => 'Office',
            'last_name' => 'B',
            'status' => 'new',
        ]);

        $this->postJson('/api/v1/properties', [
            'lead_id' => $lead->id,
            'address' => 'Cross Office Property',
        ], $this->headersFor($this->officeA))->assertNotFound();

        $this->assertDatabaseMissing('properties', [
            'tenant_id' => $this->officeA->id,
            'lead_id' => $lead->id,
        ]);
    }


    public function test_office_a_cannot_read_office_b_buyer_by_id(): void
    {
        $buyer = Buyer::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'first_name' => 'Private',
            'last_name' => 'Buyer',
            'email' => 'private-buyer@example.test',
        ]);

        $this->getJson("/api/v1/buyers/{$buyer->id}", $this->headersFor($this->officeA))
            ->assertNotFound();
    }

    public function test_buyer_index_does_not_leak_another_office(): void
    {
        Buyer::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeA->id,
            'first_name' => 'Visible',
            'last_name' => 'Buyer',
            'email' => 'visible@example.test',
        ]);
        Buyer::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'first_name' => 'Secret',
            'last_name' => 'Buyer',
            'email' => 'secret@example.test',
        ]);

        $response = $this->getJson('/api/v1/buyers?search=Buyer', $this->headersFor($this->officeA))
            ->assertOk();

        $response->assertSee('visible@example.test');
        $response->assertDontSee('secret@example.test');
    }

    public function test_activity_index_does_not_leak_another_office(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $agentA = User::factory()->create([
            'tenant_id' => $this->officeA->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $agentB = User::factory()->create([
            'tenant_id' => $this->officeB->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        Activity::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeA->id,
            'agent_id' => $agentA->id,
            'type' => 'note',
            'subject' => 'Visible activity',
        ]);
        Activity::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'agent_id' => $agentB->id,
            'type' => 'note',
            'subject' => 'Secret activity',
        ]);

        $response = $this->getJson('/api/v1/activities', $this->headersFor($this->officeA))
            ->assertOk();

        $response->assertSee('Visible activity');
        $response->assertDontSee('Secret activity');
    }


    public function test_global_search_does_not_leak_another_office_records(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $adminA = User::factory()->create([
            'tenant_id' => $this->officeA->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $secretLead = $this->makeLead($this->officeB, 'CrossTenantSecret', 'Lead');
        Buyer::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'first_name' => 'CrossTenantSecret',
            'last_name' => 'Buyer',
            'email' => 'cross-tenant-secret@example.test',
        ]);
        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'lead_id' => $secretLead->id,
            'address' => 'CrossTenantSecret Property',
            'city' => 'Riyadh',
            'state' => 'Riyadh',
            'zip_code' => '14962',
        ]);

        $this->actingAs($adminA)
            ->getJson('/search?q=CrossTenantSecret')
            ->assertOk()
            ->assertDontSee('CrossTenantSecret')
            ->assertDontSee('cross-tenant-secret@example.test');
    }


    public function test_web_activity_mutation_rejects_another_office_activity(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $adminA = User::factory()->create([
            'tenant_id' => $this->officeA->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $agentB = User::factory()->create([
            'tenant_id' => $this->officeB->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $activity = Activity::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'agent_id' => $agentB->id,
            'type' => 'note',
            'subject' => 'Office B private activity',
        ]);

        $this->actingAs($adminA)
            ->delete("/activities/{$activity->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'tenant_id' => $this->officeB->id,
        ]);
    }



    public function test_tenant_admin_cannot_access_platform_database_backups(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $adminA = User::factory()->create([
            'tenant_id' => $this->officeA->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($adminA)->get('/settings/backups/list')->assertForbidden();
        $this->actingAs($adminA)->post('/settings/backups/create')->assertForbidden();
        $this->actingAs($adminA)->get('/settings/backups/download/backup-test.sql')->assertForbidden();
        $this->actingAs($adminA)->delete('/settings/backups/backup-test.sql')->assertForbidden();
    }


    public function test_tenant_admin_cannot_mutate_another_offices_custom_role(): void
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminA = User::factory()->create([
            'tenant_id' => $this->officeA->id,
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        $foreignRole = Role::create([
            'tenant_id' => $this->officeB->id,
            'name' => 'office_b_private_role',
            'display_name' => 'Office B Private Role',
            'is_system' => false,
        ]);

        $this->actingAs($adminA)
            ->put("/settings/roles/{$foreignRole->id}/permissions", ['permissions' => []])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete("/settings/roles/{$foreignRole->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('roles', [
            'id' => $foreignRole->id,
            'tenant_id' => $this->officeB->id,
        ]);
    }

    private function makeLead(Tenant $tenant, string $firstName, string $lastName): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'new',
        ]);
    }

    private function makeTenant(string $slug, string $apiKey): Tenant
    {
        return Tenant::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'email' => "{$slug}@example.test",
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'date_format' => 'd/m/Y',
            'country' => 'SA',
            'measurement_system' => 'metric',
            'locale' => 'en',
            'distribution_method' => 'round_robin',
            'api_key' => $apiKey,
            'api_enabled' => true,
        ]);
    }

    private function headersFor(Tenant $tenant): array
    {
        return ['X-API-Key' => $tenant->api_key];
    }
}
