<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaudiPropertyPhotoTest extends TestCase
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

    private function createSaudiProperty(array $overrides = []): Property
    {
        $owner = $overrides['owner'] ?? $this->createLead();
        unset($overrides['owner']);

        return Property::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $owner->id,
            'address' => 'عقار صور اختبار',
            'city' => 'الرياض',
            'property_type' => 'villa',
            'transaction_type' => 'sale',
            'district' => 'نمار',
            'listing_status' => 'active',
        ], $overrides));
    }

    public function test_admin_can_upload_photo_to_property(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin($this->realEstateTenant());
        $property = $this->createSaudiProperty();

        $photo = UploadedFile::fake()->create('front.jpg', 120, 'image/jpeg');

        $this->post(route('properties.photos.upload', $property), [
            'photos' => [$photo],
            'captions' => ['الواجهة الرئيسية'],
        ])->assertRedirect(route('properties.show', $property));

        $record = PropertyPhoto::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->firstOrFail();

        $this->assertSame($this->tenant->id, $record->tenant_id);
        $this->assertSame($this->adminUser->id, $record->uploaded_by);
        $this->assertSame('الواجهة الرئيسية', $record->caption);
        Storage::disk('public')->assertExists($record->path);

        $this->get(route('properties.show', $property))
            ->assertOk()
            ->assertSee('صور العقار')
            ->assertSee('الواجهة الرئيسية');
    }

    public function test_agent_cannot_upload_photo_to_another_agents_property(): void
    {
        Storage::fake('public');
        $this->createTenantWithAdmin($this->realEstateTenant());

        $agentA = $this->createUserWithRole('agent');
        $agentB = $this->createUserWithRole('agent');
        $ownerA = $this->createLead(['agent_id' => $agentA->id]);

        $property = $this->createSaudiProperty(['owner' => $ownerA]);

        $this->actingAs($agentB)
            ->post(route('properties.photos.upload', $property), [
                'photos' => [UploadedFile::fake()->create('private.jpg', 80, 'image/jpeg')],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('property_photos', [
            'property_id' => $property->id,
        ]);
    }

    public function test_photo_cannot_be_deleted_through_different_property(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin($this->realEstateTenant());

        $propertyA = $this->createSaudiProperty(['address' => 'عقار أ']);
        $propertyB = $this->createSaudiProperty(['address' => 'عقار ب']);

        Storage::disk('public')->put('property-photos/test/photo.jpg', 'image-bytes');

        $photo = PropertyPhoto::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $propertyA->id,
            'uploaded_by' => $this->adminUser->id,
            'filename' => 'photo.jpg',
            'original_name' => 'photo.jpg',
            'path' => 'property-photos/test/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'sort_order' => 0,
        ]);

        $this->delete(route('properties.photos.delete', [$propertyB, $photo]))
            ->assertNotFound();

        $this->assertDatabaseHas('property_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_authorized_user_can_delete_property_photo_and_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin($this->realEstateTenant());

        $property = $this->createSaudiProperty();
        Storage::disk('public')->put('property-photos/test/delete.jpg', 'image-bytes');

        $photo = PropertyPhoto::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'uploaded_by' => $this->adminUser->id,
            'filename' => 'delete.jpg',
            'original_name' => 'delete.jpg',
            'path' => 'property-photos/test/delete.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'sort_order' => 0,
        ]);

        $this->delete(route('properties.photos.delete', [$property, $photo]))
            ->assertRedirect(route('properties.show', $property));

        $this->assertDatabaseMissing('property_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_deleting_property_removes_its_photo_files(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin($this->realEstateTenant());

        $property = $this->createSaudiProperty();
        Storage::disk('public')->put('property-photos/test/orphan.jpg', 'image-bytes');

        PropertyPhoto::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'property_id' => $property->id,
            'uploaded_by' => $this->adminUser->id,
            'filename' => 'orphan.jpg',
            'original_name' => 'orphan.jpg',
            'path' => 'property-photos/test/orphan.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'sort_order' => 0,
        ]);

        $this->delete(route('properties.destroy', $property))
            ->assertRedirect(route('properties.index'));

        Storage::disk('public')->assertMissing('property-photos/test/orphan.jpg');
        $this->assertDatabaseMissing('property_photos', [
            'property_id' => $property->id,
        ]);
    }

}
