<?php

namespace Tests\Feature\Security;

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
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'address' => 'Office B Property',
        ]);

        $this->getJson("/api/v1/properties/{$property->id}", $this->headersFor($this->officeA))
            ->assertNotFound();
    }

    public function test_property_index_contains_only_current_office_records(): void
    {
        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeA->id,
            'address' => 'Office A Property',
        ]);
        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'address' => 'Office B Property',
        ]);

        $response = $this->getJson('/api/v1/properties', $this->headersFor($this->officeA))
            ->assertOk();

        $response->assertSee('Office A Property');
        $response->assertDontSee('Office B Property');
    }

    public function test_office_a_cannot_update_office_b_property(): void
    {
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->officeB->id,
            'address' => 'Office B Original',
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
