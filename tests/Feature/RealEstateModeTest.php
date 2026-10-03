<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Services\BusinessModeService;
use Tests\TestCase;

class RealEstateModeTest extends TestCase
{
    public function test_realestate_tenant_gets_realestate_pipeline_stages(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);

        $response = $this->get('/pipeline');
        $response->assertStatus(200);
        $response->assertSee('اتفاقية تسويق');
        $response->assertSee('عقار نشط');
        $response->assertSee('تفاوض');
    }

    public function test_wholesale_tenant_gets_wholesale_pipeline_stages(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $response = $this->get('/pipeline');
        $response->assertStatus(200);
        $response->assertSee('Prospecting');
        $response->assertSee('Dispositions');
    }

    public function test_realestate_sidebar_shows_saudi_v1_navigation(): void
    {
        $this->actingAsAdmin([
            'business_mode' => 'realestate',
            'country' => 'SA',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
        ]);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('العقارات');
        $response->assertSee('الطلبات');
        $response->assertSee('المطابقات');
        $response->assertSee('المعاينات');
        $response->assertSee('الصفقات');
        $response->assertDontSee('href="/listings"', false);
        $response->assertDontSee('href="/open-houses"', false);
        $response->assertDontSee('href="/buyers"', false);
    }

    public function test_wholesale_sidebar_hides_re_links(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $response->assertDontSee('href="/showings"', false);
        $response->assertDontSee('href="/open-houses"', false);
        $response->assertDontSee('href="/listings"', false);
    }

    public function test_mode_middleware_blocks_wholesale_from_showings(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $response = $this->get('/showings');
        $response->assertStatus(404);
    }

    public function test_mode_middleware_blocks_wholesale_from_open_houses(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $response = $this->get('/open-houses');
        $response->assertStatus(404);
    }

    public function test_mode_middleware_blocks_wholesale_from_listings(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $response = $this->get('/listings');
        $response->assertStatus(404);
    }

    public function test_realestate_property_shows_cma_not_arv(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $property = $this->createProperty();

        $response = $this->get("/properties/{$property->id}");
        $response->assertStatus(200);
        $response->assertSee('Comparable Market Analysis');
    }

    public function test_wholesale_property_shows_arv_not_cma(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);
        $property = $this->createProperty();

        $response = $this->get("/properties/{$property->id}");
        $response->assertStatus(200);
        $response->assertSee('ARV Worksheet');
    }

    public function test_realestate_deal_shows_commission_calculator(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $deal = $this->createDeal(['stage' => 'active_listing']);

        $response = $this->get("/pipeline/{$deal->id}");
        $response->assertStatus(200);
        $response->assertSee('العمولة');
    }

    public function test_realestate_deal_shows_transaction_checklist(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $deal = $this->createDeal(['stage' => 'under_contract']);

        $response = $this->get("/pipeline/{$deal->id}");
        $response->assertStatus(200);
        $response->assertSee('خطوات الإغلاق');
    }

    public function test_realestate_deal_shows_offers_section(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $deal = $this->createDeal(['stage' => 'offer_received']);

        $response = $this->get("/pipeline/{$deal->id}");
        $response->assertStatus(200);
        $response->assertSee('العروض والتفاوض');
    }

    public function test_realestate_deal_hides_us_only_transaction_fields(): void
    {
        $this->actingAsAdmin([
            'business_mode' => 'realestate',
            'country' => 'SA',
            'currency' => 'SAR',
            'locale' => 'ar',
        ]);

        $deal = $this->createDeal([
            'stage' => 'under_contract',
            'inspection_period_days' => 10,
            'mls_number' => 'LEGACY-MLS-123',
            'listing_commission_pct' => 2.5,
            'buyer_commission_pct' => 2.5,
        ]);

        $this->get("/pipeline/{$deal->id}")
            ->assertOk()
            ->assertSee('إجمالي العمولة')
            ->assertSee('حصة المكتب من العمولة')
            ->assertDontSee('MLS #')
            ->assertDontSee('Listing Commission')
            ->assertDontSee('Buyer Commission')
            ->assertDontSee('Due Diligence');
    }

    public function test_realestate_pipeline_hides_us_only_editor_fields(): void
    {
        $this->actingAsAdmin([
            'business_mode' => 'realestate',
            'country' => 'SA',
            'currency' => 'SAR',
            'locale' => 'ar',
        ]);

        $this->createDeal(['stage' => 'offer_received']);

        $this->get('/pipeline')
            ->assertOk()
            ->assertDontSee('MLS #')
            ->assertDontSee('Listing Commission')
            ->assertDontSee('Buyer Commission')
            ->assertSee('إجمالي العمولة');
    }

    public function test_saudi_web_property_can_be_saved_without_us_address_fields(): void
    {
        $this->actingAsAdmin([
            'business_mode' => 'realestate',
            'country' => 'SA',
            'currency' => 'SAR',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
        ]);

        $lead = $this->createLead();

        $response = $this->post(route('leads.property.store', $lead), [
            'address' => 'شارع اختبار',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'plan_number' => '4321',
            'area_sqm' => 500,
            'bedrooms' => 5,
            'bathrooms' => 4,
            'street_width_m' => 20,
            'floors' => 2,
            'units' => 1,
            'furnished' => '0',
            'finance_eligible' => '1',
            'list_price' => 2500000,
            'listing_status' => 'active',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('leads.show', $lead));

        $property = Property::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->firstOrFail();

        $this->assertNull($property->state);
        $this->assertNull($property->zip_code);
        $this->assertSame('villa', $property->property_type);
        $this->assertSame('sale', $property->transaction_type);
        $this->assertSame('نمار', $property->district);
        $this->assertTrue($property->finance_eligible);
        $this->assertSame('5000.00', $property->price_per_sqm);
    }


    public function test_saudi_stage_change_does_not_run_legacy_buyer_matching(): void
    {
        $this->actingAsAdmin([
            'business_mode' => 'realestate',
            'country' => 'SA',
            'currency' => 'SAR',
            'locale' => 'ar',
        ]);

        $lead = $this->createLead();
        $property = $this->createProperty([
            'lead_id' => $lead->id,
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'city' => 'الرياض',
            'listing_status' => 'active',
        ]);

        $deal = $this->createDeal([
            'lead_id' => $lead->id,
            'property_id' => $property->id,
            'stage' => 'lead',
        ]);

        \App\Models\Buyer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'max_purchase_price' => 99999999,
        ]);

        $this->patchJson(route('deals.updateStage', $deal), [
            'stage' => 'active_listing',
        ])->assertOk();

        $this->assertDatabaseMissing('deal_buyer_matches', [
            'deal_id' => $deal->id,
        ]);
    }

}
