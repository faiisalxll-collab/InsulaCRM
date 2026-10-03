<?php

namespace Tests\Feature\Security;

use App\Models\DealOffer;
use Tests\TestCase;

class SaudiLegacyPersistenceTest extends TestCase
{
    public function test_saudi_model_preserves_address_tokens_on_create_and_update(): void
    {
        $this->createTenantWithAdmin(['business_mode' => 'realestate']);
        // No session: normalization must derive the mode from the record's tenant.
        $property = $this->createProperty([
            'address' => '  King  Road North  ', 'city' => 'Riyadh',
            'state' => 'Riyadh Region', 'zip_code' => '12345-6789',
        ]);
        $this->assertSame('King Road North', $property->fresh()->address);
        $this->assertSame('Riyadh Region', $property->fresh()->state);
        $this->assertSame('12345-6789', $property->fresh()->zip_code);
        $property->update(['address' => '  Prince Street South  ']);
        $this->assertSame('Prince Street South', $property->fresh()->address);
    }

    public function test_saudi_legacy_offer_does_not_display_us_finance_labels(): void
    {
        $this->actingAsAdmin(['business_mode' => 'realestate']);
        $deal = $this->createDeal(['stage' => 'offer_received']);
        foreach (['fha', 'va', 'conventional'] as $type) {
            DealOffer::create([
                'tenant_id' => $this->tenant->id, 'deal_id' => $deal->id,
                'buyer_name' => 'عميل سابق', 'offer_price' => 100000,
                'financing_type' => $type, 'status' => 'pending',
            ]);
        }
        $this->get(route('deals.show', $deal))->assertOk()
            ->assertSee('تمويل سابق')->assertDontSee('FHA')->assertDontSee('Conventional');
        $this->assertDatabaseHas('deal_offers', ['deal_id' => $deal->id, 'financing_type' => 'fha']);
    }

    public function test_saudi_legacy_deal_api_cannot_bypass_linked_workflow(): void
    {
        $this->createTenantWithAdmin([
            'business_mode' => 'realestate', 'api_enabled' => true, 'api_key' => 'saudi-audit-key',
        ]);
        $deal = $this->createDeal(['stage' => 'offer_received']);
        $headers = ['X-API-Key' => 'saudi-audit-key'];
        $this->postJson('/api/v1/deals', ['lead_id' => $deal->lead_id, 'stage' => 'closed_won'], $headers)
            ->assertNotFound();
        $this->putJson('/api/v1/deals/'.$deal->id, ['stage' => 'closed_won'], $headers)
            ->assertNotFound();
        $this->assertSame('offer_received', $deal->fresh()->stage);
        $this->assertDatabaseCount('deals', 1);
        $this->getJson('/api/v1/deals/'.$deal->id, $headers)->assertOk();
    }
}
