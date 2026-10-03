<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use App\Models\Showing;
use Tests\TestCase;

class SaudiPilotSmokeTest extends TestCase
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

    public function test_pilot_office_can_complete_full_saudi_brokerage_flow(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $this->post(route('leads.store'), [
            'agent_id' => $this->adminUser->id,
            'first_name' => 'سالم',
            'last_name' => 'المالك',
            'phone' => '0501111111',
            'lead_source' => 'office_walk_in',
            'status' => 'new',
            'contact_type' => 'seller_lead',
            'temperature' => 'warm',
            'timezone' => 'Asia/Riyadh',
            'do_not_contact' => '0',
        ])->assertRedirect();

        $owner = Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('phone', '0501111111')
            ->firstOrFail();

        $this->post(route('leads.store'), [
            'agent_id' => $this->adminUser->id,
            'first_name' => 'ناصر',
            'last_name' => 'الباحث',
            'phone' => '0502222222',
            'lead_source' => 'whatsapp',
            'status' => 'new',
            'contact_type' => 'buyer_lead',
            'temperature' => 'hot',
            'timezone' => 'Asia/Riyadh',
            'do_not_contact' => '0',
        ])->assertRedirect();

        $client = Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('phone', '0502222222')
            ->firstOrFail();

        $this->post(route('properties.manage.store'), [
            'lead_id' => $owner->id,
            'address' => 'فيلا بايلوت نمار',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'plan_number' => 'P-100',
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
        ])->assertRedirect();

        $property = Property::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('address', 'فيلا بايلوت نمار')
            ->firstOrFail();

        $this->post(route('property-requests.store'), [
            'lead_id' => $client->id,
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
        ])->assertRedirect();

        $propertyRequest = PropertyRequest::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('lead_id', $client->id)
            ->latest('id')
            ->firstOrFail();

        $match = PropertyMatch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_request_id', $propertyRequest->id)
            ->where('property_id', $property->id)
            ->firstOrFail();

        $this->assertTrue($match->hard_constraints_passed);
        $this->assertSame('eligible', $match->status);
        $this->assertGreaterThan(0, $match->match_score);

        $this->post(route('showings.store'), [
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'showing_date' => now()->addDay()->toDateString(),
            'showing_time' => '17:30',
            'duration_minutes' => 30,
            'status' => 'completed',
            'outcome' => 'made_offer',
        ])->assertRedirect();

        $showing = Showing::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_request_id', $propertyRequest->id)
            ->where('property_id', $property->id)
            ->latest('id')
            ->firstOrFail();

        $this->post(route('showings.startDeal', $showing))->assertRedirect();

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_id', $property->id)
            ->where('property_request_id', $propertyRequest->id)
            ->firstOrFail();

        $this->assertSame('offer_received', $deal->stage);
        $this->assertSame($deal->id, $showing->fresh()->deal_id);

        $this->post(route('deals.storeOffer', $deal), [
            'offer_price' => 1700000,
            'financing_type' => 'bank_finance',
            'notes' => 'عرض البايلوت',
        ])->assertRedirect();

        $offer = DealOffer::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('deal_id', $deal->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($client->full_name, $offer->buyer_name);

        $this->patchJson(route('deals.updateOffer', $offer), [
            'status' => 'countered',
            'counter_price' => 1725000,
        ])->assertOk();

        $this->assertSame('negotiating', $deal->fresh()->stage);

        $this->patchJson(route('deals.updateOffer', $offer), [
            'status' => 'accepted',
        ])->assertOk();

        $deal->refresh();
        $this->assertSame('under_contract', $deal->stage);
        $this->assertSame('1725000.00', $deal->contract_price);
        $this->assertSame('pending', $property->fresh()->listing_status);
        $this->assertSame('paused', $propertyRequest->fresh()->status);
        $this->assertSame(9, $deal->checklistItems()->count());

        $this->assertDatabaseMissing('property_matches', [
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
        ]);

        $this->patchJson(route('deals.quickUpdate', $deal), [
            'total_commission' => 43125,
            'brokerage_split_pct' => 40,
            'commission_status' => 'pending',
        ])->assertOk();

        $this->patchJson(route('deals.updateStage', $deal), [
            'stage' => 'closed_won',
        ])->assertOk();

        $deal->refresh();
        $property->refresh();
        $propertyRequest->refresh();

        $this->assertSame('closed_won', $deal->stage);
        $this->assertSame('due', $deal->commission_status);
        $this->assertSame('sold', $property->listing_status);
        $this->assertSame('1725000.00', $property->sold_price);
        $this->assertNotNull($property->sold_at);
        $this->assertSame('fulfilled', $propertyRequest->status);

        $this->patchJson(route('deals.quickUpdate', $deal), [
            'commission_status' => 'paid',
        ])->assertOk();

        $deal->refresh();

        $this->assertSame('paid', $deal->commission_status);
        $this->assertNotNull($deal->commission_paid_at);
        $this->assertSame(17250.0, $deal->office_commission_amount);
        $this->assertSame(25875.0, $deal->agent_commission_amount);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('لوحة المكتب العقاري');

        $this->get(route('reports.index', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDays(2)->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('تقارير المكتب العقاري')
            ->assertSee('صفقات مغلقة')
            ->assertSee('عمولات مدفوعة');
    }
}
