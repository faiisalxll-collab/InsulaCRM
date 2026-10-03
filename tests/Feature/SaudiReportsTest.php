<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use App\Models\Showing;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class SaudiReportsTest extends TestCase
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

    public function test_saudi_report_summarizes_core_brokerage_flow(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا تقرير نمار',
            'city' => 'الرياض',
            'district' => 'نمار',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'list_price' => 1500000,
            'listing_status' => 'sold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1600000,
            'status' => 'fulfilled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PropertyMatch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'match_score' => 92,
            'hard_constraints_passed' => true,
            'location_score' => 30,
            'price_score' => 20,
            'area_score' => 15,
            'features_score' => 20,
            'finance_score' => 5,
            'match_reasons' => ['city', 'district', 'price'],
            'rejection_reasons' => [],
            'status' => 'eligible',
            'matched_at' => now(),
            'evaluated_at' => now(),
        ]);

        $showing = Showing::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'showing_date' => now()->toDateString(),
            'showing_time' => '17:00',
            'status' => 'completed',
            'outcome' => 'made_offer',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة تقرير',
            'stage' => 'closed_won',
            'stage_changed_at' => now(),
            'contract_price' => 1450000,
            'total_commission' => 36250,
            'commission_status' => 'paid',
            'commission_paid_at' => now(),
        ]);

        $showing->update(['deal_id' => $deal->id]);

        DealOffer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'deal_id' => $deal->id,
            'buyer_name' => $client->full_name,
            'offer_price' => 1450000,
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('reports.index', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('تقارير المكتب العقاري')
            ->assertSee('عملاء جدد')
            ->assertSee('عقارات مضافة')
            ->assertSee('طلبات جديدة')
            ->assertSee('مطابقات مؤهلة')
            ->assertSee('92%')
            ->assertSee('المعاينات')
            ->assertSee('العروض')
            ->assertSee('صفقات مغلقة')
            ->assertSee('حجم الإغلاقات')
            ->assertSee('عمولات مولدة')
            ->assertSee('نمار')
            ->assertSee('فيلا');
    }

    public function test_saudi_reports_reject_agent_from_another_tenant(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $other = Tenant::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
            'email' => 'other-admin@test.com',
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

        $otherAgent = User::factory()->create([
            'tenant_id' => $other->id,
            'role_id' => Role::where('name', 'agent')->firstOrFail()->id,
            'is_active' => true,
        ]);

        $this->get(route('reports.index', [
            'agent_id' => $otherAgent->id,
        ]))->assertNotFound();
    }

    public function test_wholesale_reports_keep_existing_view(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Conversion Funnel')
            ->assertDontSee('تقارير المكتب العقاري');
    }

    public function test_broker_report_only_contains_owned_records(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');

        $ownerA = $this->createLead([
            'agent_id' => $agentA->id,
            'first_name' => 'مالك',
            'last_name' => 'ألف',
        ]);
        $ownerB = $this->createLead([
            'agent_id' => $agentB->id,
            'first_name' => 'مالك',
            'last_name' => 'باء',
        ]);

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $ownerA->id,
            'address' => 'عقار وسيط ألف',
            'city' => 'الرياض',
            'district' => 'حي-خاص-بألف',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $ownerB->id,
            'address' => 'عقار وسيط باء',
            'city' => 'الرياض',
            'district' => 'حي-خاص-بباء',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($agentA)
            ->get(route('reports.index', [
                'agent_id' => $agentB->id,
                'from' => now()->subDay()->toDateString(),
                'to' => now()->addDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('حي-خاص-بألف')
            ->assertDontSee('حي-خاص-بباء');
    }


    public function test_saudi_agent_can_open_own_report(): void
    {
        $this->createTenantWithAdmin($this->realEstateTenant());
        $agent = $this->createUserWithRole('agent');

        $this->actingAs($agent)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('تقارير المكتب العقاري');
    }

    public function test_wholesale_agent_cannot_open_reports(): void
    {
        $this->createTenantWithAdmin(['business_mode' => 'wholesale']);
        $agent = $this->createUserWithRole('agent');

        $this->actingAs($agent)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

}
