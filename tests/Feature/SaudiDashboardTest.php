<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyRequest;
use Tests\TestCase;

class SaudiDashboardTest extends TestCase
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

    public function test_realestate_dashboard_uses_saudi_operations_view(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار لوحة الاختبار',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'list_price' => 1500000,
            'listing_status' => 'active',
        ]);

        PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts' => ['نمار'],
            'max_price' => 1600000,
            'status' => 'active',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('لوحة المكتب العقاري')
            ->assertSee('العقارات النشطة')
            ->assertSee('الطلبات النشطة')
            ->assertSee('المطابقات الحالية')
            ->assertSee('أفضل المطابقات الآن')
            ->assertSee('عقار لوحة الاختبار');
    }

    public function test_wholesale_dashboard_keeps_existing_dashboard(): void
    {
        $this->actingAsAdmin(['business_mode' => 'wholesale']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('لوحة المكتب العقاري')
            ->assertSee('Customize');
    }
}
