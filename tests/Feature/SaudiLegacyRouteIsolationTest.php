<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Property;
use Tests\TestCase;

class SaudiLegacyRouteIsolationTest extends TestCase
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

    public function test_wholesale_only_surfaces_are_not_reachable_in_saudi_v1(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار عزل المسارات القديمة',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة عزل المسارات القديمة',
            'stage' => 'offer_received',
        ]);

        $this->get(route('buyers.index'))
            ->assertNotFound();

        $this->get(route('disposition.show', $deal))
            ->assertNotFound();

        $this->get(route('documents.investorPacket', $deal))
            ->assertNotFound();

        $this->post(route('comps.store', $property), [
            'address' => 'Legacy Comp',
            'sold_price' => 1000000,
        ])->assertNotFound();
    }
}
