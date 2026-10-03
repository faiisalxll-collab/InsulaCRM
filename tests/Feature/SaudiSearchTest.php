<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Deal;
use App\Models\Property;
use App\Models\PropertyRequest;
use Tests\TestCase;

class SaudiSearchTest extends TestCase
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

    public function test_saudi_search_does_not_return_legacy_buyers(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        Buyer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'LEGACYBUYERUNIQUE',
            'last_name' => 'Old',
            'email' => 'legacy-buyer@example.test',
        ]);

        $results = collect(
            $this->getJson(route('search', ['q' => 'LEGACYBUYERUNIQUE']))
                ->assertOk()
                ->json('results')
        );

        $this->assertCount(0, $results->where('type', 'buyer'));
    }

    public function test_saudi_search_finds_deal_by_direct_property_and_request_client(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead([
            'first_name' => 'مالك',
            'last_name' => 'البحث',
        ]);
        $client = $this->createLead([
            'first_name' => 'سعد',
            'last_name' => 'المطابقةالمميزة',
        ]);

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'فيلا شارع البحث المباشر',
            'city' => 'الرياض',
            'district' => 'نمار',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'property_request_id' => $propertyRequest->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة البحث السعودي',
            'stage' => 'offer_received',
        ]);

        $byProperty = collect(
            $this->getJson(route('search', ['q' => 'البحث المباشر']))
                ->assertOk()
                ->json('results')
        );

        $dealResult = $byProperty
            ->where('type', 'deal')
            ->firstWhere('url', route('deals.show', $deal));

        $this->assertNotNull($dealResult);
        $this->assertSame('سعد المطابقةالمميزة', $dealResult['title']);
        $this->assertStringContainsString('فيلا شارع البحث المباشر', $dealResult['subtitle']);

        $byClient = collect(
            $this->getJson(route('search', ['q' => 'المطابقةالمميزة']))
                ->assertOk()
                ->json('results')
        );

        $this->assertTrue(
            $byClient->where('type', 'deal')->contains('url', route('deals.show', $deal))
        );
    }

    public function test_saudi_broker_search_does_not_leak_another_brokers_property_or_request(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');

        $leadA = $this->createLead([
            'agent_id' => $agentA->id,
            'first_name' => 'عميل',
            'last_name' => 'خاصألف',
        ]);

        $propertyA = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $leadA->id,
            'address' => 'عقار سري وسيط ألف',
            'city' => 'الرياض',
            'district' => 'نمار',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $requestA = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $leadA->id,
            'agent_id' => $agentA->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'مدينة سرية ألف',
            'status' => 'active',
        ]);

        $this->actingAs($agentB);

        $propertyResults = collect(
            $this->getJson(route('search', ['q' => 'سري وسيط ألف']))
                ->assertOk()
                ->json('results')
        );

        $this->assertFalse(
            $propertyResults->where('type', 'property')->contains('url', route('properties.show', $propertyA))
        );

        $requestResults = collect(
            $this->getJson(route('search', ['q' => 'مدينة سرية ألف']))
                ->assertOk()
                ->json('results')
        );

        $this->assertFalse(
            $requestResults->where('type', 'request')->contains('url', route('property-requests.show', $requestA))
        );
    }

    public function test_saudi_search_returns_property_request_as_core_result(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $client = $this->createLead([
            'first_name' => 'نورة',
            'last_name' => 'طالبةعقار',
            'phone' => '0503333333',
        ]);

        $propertyRequest = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $results = collect(
            $this->getJson(route('search', ['q' => 'طالبةعقار']))
                ->assertOk()
                ->json('results')
        );

        $requestResult = $results
            ->where('type', 'request')
            ->firstWhere('url', route('property-requests.show', $propertyRequest));

        $this->assertNotNull($requestResult);
        $this->assertStringContainsString('نورة طالبةعقار', $requestResult['title']);
        $this->assertStringContainsString('إيجار', $requestResult['subtitle']);
    }

    public function test_single_arabic_character_is_treated_as_short_query(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $this->getJson(route('search', ['q' => 'ن']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }
}
