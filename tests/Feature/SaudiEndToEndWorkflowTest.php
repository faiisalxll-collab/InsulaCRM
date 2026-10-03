<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use App\Models\Showing;
use Tests\TestCase;

class SaudiEndToEndWorkflowTest extends TestCase
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

    public function test_complete_saudi_broker_workflow_from_property_to_paid_commission(): void
    {
        $this->actingAsAdmin($this->realEstateTenant());

        $owner = $this->createLead();
        $buyer = $this->createLead();

        // 1) المكتب يدخل العقار.
        $this->post(route('properties.manage.store'), [
            'lead_id' => $owner->id,
            'address' => 'فيلا رحلة كاملة',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'plan_number' => '1001',
            'area_sqm' => 400,
            'list_price' => 1800000,
            'bedrooms' => 5,
            'bathrooms' => 4,
            'street_width_m' => 20,
            'finance_eligible' => '1',
            'listing_status' => 'active',
        ])->assertRedirect();

        $property = Property::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('address', 'فيلا رحلة كاملة')
            ->firstOrFail();

        // 2) المكتب يدخل طلب العميل.
        $this->post(route('property-requests.store'), [
            'lead_id' => $buyer->id,
            'agent_id' => $this->adminUser->id,
            'transaction_type' => 'sale',
            'property_type' => 'villa',
            'city' => 'الرياض',
            'districts_csv' => 'نمار',
            'max_price' => 1900000,
            'min_area_sqm' => 350,
            'min_bedrooms' => 4,
            'finance_required' => '1',
            'status' => 'active',
        ])->assertRedirect();

        $propertyRequest = PropertyRequest::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('lead_id', $buyer->id)
            ->latest('id')
            ->firstOrFail();

        // 3) المحرك يجد المطابقة تلقائيًا.
        $match = PropertyMatch::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_request_id', $propertyRequest->id)
            ->where('property_id', $property->id)
            ->firstOrFail();

        $this->assertTrue($match->hard_constraints_passed);
        $this->assertSame('eligible', $match->status);

        // 4) الوسيط يجدول المعاينة من نفس العقار والطلب.
        $this->post(route('showings.store'), [
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'showing_date' => '2026-10-05',
            'showing_time' => '17:00',
            'duration_minutes' => 30,
        ])->assertRedirect();

        $showing = Showing::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_request_id', $propertyRequest->id)
            ->where('property_id', $property->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($buyer->id, $showing->lead_id);
        $this->assertSame($this->adminUser->id, $showing->agent_id);

        // 5) بعد المعاينة يسجل أن العميل قدم عرضًا.
        $this->put(route('showings.update', $showing), [
            'status' => 'completed',
            'outcome' => 'made_offer',
            'feedback' => 'العميل مهتم ويريد تقديم عرض.',
        ])->assertRedirect(route('showings.show', $showing));

        // 6) فتح الصفقة من المعاينة.
        $this->post(route('showings.startDeal', $showing))->assertRedirect();

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_request_id', $propertyRequest->id)
            ->where('property_id', $property->id)
            ->firstOrFail();

        $this->assertSame('offer_received', $deal->stage);
        $this->assertSame($owner->id, $deal->lead_id);

        // 7) تسجيل العرض.
        $this->post(route('deals.storeOffer', $deal), [
            'offer_price' => 1750000,
            'financing_type' => 'bank_finance',
            'notes' => 'عرض العميل بعد المعاينة.',
        ])->assertRedirect();

        $offer = DealOffer::withoutGlobalScopes()
            ->where('deal_id', $deal->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($buyer->full_name, $offer->buyer_name);

        // 8) قبول العرض يحول الصفقة إلى اتفاق ويوقف العقار والطلب.
        $this->patchJson(route('deals.updateOffer', $offer), [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertSame('under_contract', $deal->fresh()->stage);
        $this->assertSame('pending', $property->fresh()->listing_status);
        $this->assertSame('paused', $propertyRequest->fresh()->status);
        $this->assertSame(9, $deal->checklistItems()->count());

        // 9) تسجيل العمولة قبل الإغلاق.
        $this->patchJson(route('deals.quickUpdate', $deal), [
            'total_commission' => 43750,
            'brokerage_split_pct' => 40,
            'commission_status' => 'pending',
        ])->assertOk();

        // 10) إغلاق الصفقة.
        $this->patchJson(route('deals.updateStage', $deal), [
            'stage' => 'closed_won',
        ])->assertOk();

        $this->assertSame('sold', $property->fresh()->listing_status);
        $this->assertSame('fulfilled', $propertyRequest->fresh()->status);
        $this->assertSame('due', $deal->fresh()->commission_status);
        $this->assertSame('1750000.00', $property->fresh()->sold_price);
        $this->assertSame(17500.0, $deal->fresh()->office_commission_amount);
        $this->assertSame(26250.0, $deal->fresh()->agent_commission_amount);

        // 11) تسجيل استلام العمولة.
        $this->patchJson(route('deals.quickUpdate', $deal), [
            'commission_status' => 'paid',
        ])->assertOk();

        $this->assertSame('paid', $deal->fresh()->commission_status);
        $this->assertNotNull($deal->fresh()->commission_paid_at);
    }
}
