<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyRequest;
use App\Models\Tenant;
use App\Policies\LeadPolicy;
use Tests\TestCase;

class SaudiClientWorkflowTest extends TestCase
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

    public function test_admin_can_create_saudi_client_with_local_source(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $this->get(route('leads.create'))
            ->assertOk()
            ->assertSee('عميل جديد')
            ->assertSee('واتساب')
            ->assertSee('منصة عقارية')
            ->assertDontSee('Zillow');

        $response = $this->post(route('leads.store'), [
            'agent_id' => $this->adminUser->id,
            'first_name' => 'محمد',
            'last_name' => 'العتيبي',
            'phone' => '0500000000',
            'lead_source' => 'whatsapp',
            'status' => 'new',
            'contact_type' => 'buyer_lead',
            'temperature' => 'warm',
            'timezone' => 'Asia/Riyadh',
            'do_not_contact' => '0',
            'notes' => 'يبحث عن فيلا غرب الرياض',
        ]);

        $lead = Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('first_name', 'محمد')
            ->firstOrFail();

        $response->assertRedirect(route('leads.show', $lead));

        $this->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('باحث عن عقار')
            ->assertSee('واتساب')
            ->assertSee('متوسط')
            ->assertSee('إضافة عقار للعميل')
            ->assertSee('إضافة طلب للعميل');
    }

    public function test_saudi_client_page_lists_multiple_properties_and_requests(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $lead = $this->createLead([
            'lead_source' => 'referral',
            'contact_type' => 'active_client',
        ]);

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'address' => 'فيلا العميل الأولى',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'address' => 'شقة العميل الثانية',
            'city' => 'الرياض',
            'property_type' => 'apartment',
            'transaction_type' => 'rent',
            'listing_status' => 'active',
        ]);

        PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'land',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'status' => 'active',
        ]);

        PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'الرياض',
            'districts' => ['العوالي'],
            'status' => 'active',
        ]);

        $this->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('فيلا العميل الأولى')
            ->assertSee('شقة العميل الثانية')
            ->assertSee('نمار')
            ->assertSee('العوالي');
    }

    public function test_broker_cannot_reassign_owned_client_to_another_broker(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');
        $lead = $this->createLead([
            'agent_id' => $agentA->id,
            'lead_source' => 'referral',
            'status' => 'new',
            'temperature' => 'cold',
        ]);

        $this->actingAs($agentA)
            ->from(route('leads.edit', $lead))
            ->put(route('leads.update', $lead), [
                'agent_id' => $agentB->id,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'lead_source' => 'referral',
                'status' => 'new',
                'temperature' => 'cold',
                'timezone' => 'Asia/Riyadh',
                'do_not_contact' => '0',
            ])
            ->assertRedirect(route('leads.edit', $lead))
            ->assertSessionHasErrors('agent_id');

        $this->assertSame($agentA->id, $lead->fresh()->agent_id);
    }

    public function test_legacy_real_estate_source_can_be_edited_but_is_not_offered_to_new_clients(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $lead = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'agent_id' => $this->adminUser->id,
            'first_name' => 'Legacy',
            'last_name' => 'Client',
            'lead_source' => 'zillow',
            'status' => 'new',
            'temperature' => 'cold',
        ]);

        $this->get(route('leads.edit', $lead))
            ->assertOk()
            ->assertSee('مصدر قديم: Zillow');

        $this->put(route('leads.update', $lead), [
            'agent_id' => $this->adminUser->id,
            'first_name' => 'Legacy',
            'last_name' => 'Client',
            'lead_source' => 'zillow',
            'status' => 'active_client',
            'contact_type' => 'buyer_lead',
            'temperature' => 'warm',
            'timezone' => 'Asia/Riyadh',
            'do_not_contact' => '0',
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertSame('zillow', $lead->fresh()->lead_source);
        $this->assertSame('active_client', $lead->fresh()->status);
    }

    public function test_admin_policy_cannot_authorize_foreign_tenant_client(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $foreignTenant = Tenant::create([
            'name' => 'Foreign Office',
            'slug' => 'foreign-office-lead-policy',
            'email' => 'foreign-lead-policy@example.test',
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

        $foreignLead = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $foreignTenant->id,
            'first_name' => 'عميل',
            'last_name' => 'مكتب آخر',
            'lead_source' => 'referral',
            'status' => 'new',
            'temperature' => 'cold',
        ]);

        $policy = app(LeadPolicy::class);

        $this->assertFalse($policy->view($this->adminUser, $foreignLead));
        $this->assertFalse($policy->update($this->adminUser, $foreignLead));
        $this->assertFalse($policy->delete($this->adminUser, $foreignLead));
    }

}
