<?php

namespace App\Http\Requests;

use App\Services\BusinessModeService;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $propertyTypes = implode(',', CustomFieldService::getValidSlugs('property_type'));
        $conditions = implode(',', CustomFieldService::getValidSlugs('property_condition'));
        $distressMarkers = implode(',', CustomFieldService::getValidSlugs('distress_markers'));

        $rules = [
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:10',
            'property_type' => "required|in:{$propertyTypes}",
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'square_footage' => 'nullable|integer|min:0',
            'year_built' => 'nullable|integer|min:1800|max:' . date('Y'),
            'lot_size' => 'nullable|numeric|min:0',
            'estimated_value' => 'nullable|numeric|min:0',
            'asking_price' => 'nullable|numeric|min:0',
            'condition' => "nullable|in:{$conditions}",
            'notes' => 'nullable|string',
        ];

        if (BusinessModeService::isRealEstate()) {
            // Saudi properties are identified primarily by city, district and location.
            $rules['state'] = 'nullable|string|max:100';
            $rules['zip_code'] = 'nullable|string|max:10';

            $user = $this->user();

            $rules += [
                'lead_id' => [
                    'required',
                    Rule::exists('leads', 'id')->where(function ($query) use ($user) {
                        $query->where('tenant_id', $user->tenant_id);

                        if (! $user->isAdmin()) {
                            $query->where('agent_id', $user->id);
                        }
                    }),
                ],
                'transaction_type' => 'required|in:sale,rent',
                'district' => 'nullable|string|max:120',
                'plan_number' => 'nullable|string|max:100',
                'area_sqm' => 'nullable|numeric|min:0|max:99999999.99',
                'facing' => 'nullable|string|max:30',
                'street_width_m' => 'nullable|numeric|min:0|max:9999.99',
                'property_age_years' => 'nullable|integer|min:0|max:1000',
                'floors' => 'nullable|integer|min:0|max:500',
                'units' => 'nullable|integer|min:0|max:10000',
                'furnished' => 'nullable|boolean',
                'finance_eligible' => 'nullable|boolean',
                'price_per_sqm' => 'nullable|numeric|min:0',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'list_price' => 'nullable|numeric|min:0',
                'listing_status' => 'nullable|in:active,pending,sold,leased,withdrawn,expired',
                'listed_at' => 'nullable|date',
                'sold_at' => 'nullable|date',
                'sold_price' => 'nullable|numeric|min:0',
                'mls_number' => 'nullable|string|max:50',
            ];
        } else {
            $rules += [
                'repair_estimate' => 'nullable|numeric|min:0',
                'after_repair_value' => 'nullable|numeric|min:0|gte:repair_estimate',
                'our_offer' => 'nullable|numeric|min:0',
                'distress_markers' => 'nullable|array',
                'distress_markers.*' => "in:{$distressMarkers}",
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'after_repair_value.gte' => 'ARV must be greater than or equal to the repair estimate.',
        ];
    }
}
