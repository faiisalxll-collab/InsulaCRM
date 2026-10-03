<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Property;
use App\Models\PropertyRequest;
use App\Models\Showing;
use Tests\TestCase;

class SaudiHistoryProtectionTest extends TestCase
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

    public function test_property_with_deal_history_cannot_be_deleted(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار محفوظ في التاريخ',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        Deal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'property_id' => $property->id,
            'agent_id' => $this->adminUser->id,
            'title' => 'صفقة تحفظ العقار',
            'stage' => 'offer_received',
        ]);

        $this->delete(route('properties.destroy', $property))
            ->assertRedirect(route('properties.show', $property))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->get(route('properties.show', $property))
            ->assertOk()
            ->assertDontSee('حذف العقار نهائيًا؟');
    }

    public function test_request_with_showing_history_cannot_be_deleted(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار معاينة محفوظة',
            'city' => 'الرياض',
            'property_type' => 'apartment',
            'transaction_type' => 'rent',
            'listing_status' => 'active',
        ]);

        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'rent',
            'property_type' => 'apartment',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        Showing::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $request->id,
            'property_id' => $property->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'showing_date' => now()->addDay()->toDateString(),
            'showing_time' => '18:00',
            'status' => 'scheduled',
        ]);

        $this->delete(route('property-requests.destroy', $request))
            ->assertRedirect(route('property-requests.show', $request))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('property_requests', [
            'id' => $request->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->get(route('property-requests.show', $request))
            ->assertOk()
            ->assertDontSee('حذف الطلب نهائيًا؟');
    }

    public function test_unused_property_and_request_can_still_be_deleted(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $client = $this->createLead();

        $property = Property::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار غير مستخدم',
            'city' => 'الرياض',
            'property_type' => 'land',
            'transaction_type' => 'sale',
            'listing_status' => 'active',
        ]);

        $request = PropertyRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $client->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'land',
            'city' => 'الرياض',
            'status' => 'active',
        ]);

        $this->delete(route('properties.destroy', $property))
            ->assertRedirect(route('properties.index'));

        $this->delete(route('property-requests.destroy', $request))
            ->assertRedirect(route('property-requests.index'));

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
        $this->assertDatabaseMissing('property_requests', ['id' => $request->id]);
    }
}
