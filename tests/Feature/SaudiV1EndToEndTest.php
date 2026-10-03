<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use App\Models\Showing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaudiV1EndToEndTest extends TestCase
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

    public function test_complete_saudi_brokerage_pilot_flow(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin($this->realEstateTenant());

        // 1) Create the owner.
        $this->post(route('leads.store'), [
            'agent_id' => $this->adminUser->id,
            'first_name' => 'سلمان',
            'last_name' => 'المالك',
            'phone' => '0501000001',
            'lead_source' => 'office_walk_in',
            'status' => 'active_client',
            'contact_type' => 'seller_lead',
            'temperature' => 'warm',
            'timezone' => 'Asia/Riyadh',
            'do_not_contact' => '0',
        ])->assertSessionHasNoErrors();

        $owner = Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('phone', '0501000001')
            ->firstOrFail();

        // 2) Add a live Saudi property.
        $this->post(route('properties.manage.store'), [
            'lead_id' => $owner->id,
            'address' => 'فيلا الاختبار الشامل',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'plan_number' => 'P-2030',
            'area_sqm' => 360,
            'list_price' => 1750000,
            'bedrooms' => 5,
            'bathrooms' => 4,
            'street_width_m' => 20,
            'floors' => 2,
            'units' => 1,
            'furnished' => '0',
            'finance_eligible' => '1',
            'listing_status' => 'active',
        ])->assertSessionHasNoErrors();

        $property = Property::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('address', 'فيلا الاختبار الشامل')
            ->firstOrFail();

        // 3) Upload two property photos.
        $this->post(route('properties.photos.upload', $property), [
            'photos' => [
                UploadedFile::fake()->create('front.jpg', 80, 'image/jpeg'),
                UploadedFile::fake()->create('inside.jpg', 90, 'image/jpeg'),
            ],
        ])->assertSessionHasNoErrors();

        $property->refresh()->load('photos');
        $this->assertCount(2, $property->photos);
        foreach ($property->photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
        }

        // 4) Create the searching client.
        $this->post(route('leads.store'), [
            'agent_id' => $this->adminUser->id,
            'first_name' => 'خالد',
            'last_name' => 'الباحث',
            'phone' => '0501000002',
            'lead_source' => 'whatsapp',
            'status' => 'active_client',
            'contact_type' => 'buyer_lead',
            'temperature' => 'hot',
            'timezone' => 'Asia/Riyadh',
            'do_not_contact' => '0',
        ])->assertSessionHasNoErrors();

        $client = Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('phone', '0501000002')
            ->firstOrFail();

        // 5) Create the request; reverse matching should find the property.
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
            'min_street_width_m' => 15,
            'finance_required' => '1',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

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

        // 6) Schedule and complete the showing from the matched pair.
        $this->post(route('showings.store'), [
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'showing_date' => now()->addDay()->toDateString(),
            'showing_time' => '17:30',
            'duration_minutes' => 30,
        ])->assertSessionHasNoErrors();

        $showing = Showing::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_request_id', $propertyRequest->id)
            ->where('property_id', $property->id)
            ->latest('id')
            ->firstOrFail();

        $this->put(route('showings.update', $showing), [
            'status' => 'completed',
            'outcome' => 'made_offer',
            'feedback' => 'العميل مهتم ويرغب بالتفاوض.',
        ])->assertRedirect(route('showings.show', $showing));

        // 7) Start an idempotent deal from the showing.
        $this->post(route('showings.startDeal', $showing))->assertRedirect();

        $deal = Deal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('property_id', $property->id)
            ->where('property_request_id', $propertyRequest->id)
            ->firstOrFail();

        $this->assertSame($deal->id, $showing->fresh()->deal_id);
        $this->assertSame('offer_received', $deal->stage);

        // 8) Record an offer, counter it, then accept it.
        $this->from(route('deals.show', $deal))
            ->post(route('deals.storeOffer', $deal), [
                'offer_price' => 1680000,
                'financing_type' => 'bank_finance',
                'notes' => 'عرض أولي',
            ])
            ->assertRedirect(route('deals.show', $deal));

        $offer = DealOffer::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('deal_id', $deal->id)
            ->latest('id')
            ->firstOrFail();

        $this->patchJson(route('deals.updateOffer', $offer), [
            'status' => 'countered',
            'counter_price' => 1710000,
        ])->assertOk();

        $this->assertSame('negotiating', $deal->fresh()->stage);

        $this->patchJson(route('deals.updateOffer', $offer), [
            'status' => 'accepted',
        ])->assertOk();

        $deal->refresh();
        $this->assertSame('under_contract', $deal->stage);
        $this->assertSame('1710000.00', $deal->contract_price);
        $this->assertSame('pending', $property->fresh()->listing_status);
        $this->assertSame('paused', $propertyRequest->fresh()->status);
        $this->assertCount(9, $deal->checklistItems);

        // 9) Complete the closing checklist.
        foreach ($deal->checklistItems as $item) {
            $this->patchJson(route('deals.updateChecklistItem', $item), [
                'status' => 'completed',
            ])->assertOk();
        }

        $this->assertSame(
            9,
            $deal->fresh()->checklistItems()->where('status', 'completed')->count()
        );

        // 10) Record commission and close the transaction.
        $this->patchJson(route('deals.quickUpdate', $deal), [
            'total_commission' => 42750,
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
        $this->assertSame('1710000.00', $property->sold_price);
        $this->assertSame('fulfilled', $propertyRequest->status);

        // No active match should remain after successful closing.
        $this->assertDatabaseMissing('property_matches', [
            'tenant_id' => $this->tenant->id,
            'property_request_id' => $propertyRequest->id,
            'property_id' => $property->id,
            'status' => 'eligible',
        ]);

        // 11) Mark the commission paid.
        $this->patchJson(route('deals.quickUpdate', $deal), [
            'commission_status' => 'paid',
        ])->assertOk();

        $deal->refresh();
        $this->assertSame('paid', $deal->commission_status);
        $this->assertNotNull($deal->commission_paid_at);
        $this->assertSame(17100.0, $deal->office_commission_amount);
        $this->assertSame(25650.0, $deal->agent_commission_amount);

        // 12) Dashboard and reports must reflect the completed transaction.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('لوحة المكتب العقاري');

        $this->get(route('reports.index', [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('تقارير المكتب العقاري')
            ->assertSee('صفقات مغلقة')
            ->assertSee('عمولات مدفوعة')
            ->assertSee('نمار');
    }
}
