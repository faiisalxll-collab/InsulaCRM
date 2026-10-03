<?php

namespace App\Http\Requests;

use App\Models\PropertyRequest as PropertySearchRequest;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'finance_required' => $this->boolean('finance_required'),
        ];

        if ($this->has('districts_csv')) {
            $districts = preg_split('/[,،\n]+/u', (string) $this->input('districts_csv')) ?: [];
            $merge['districts'] = array_values(array_unique(array_filter(
                array_map(fn ($district) => trim($district), $districts),
                fn ($district) => $district !== ''
            )));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user->tenant_id;

        $leadRule = Rule::exists('leads', 'id')->where(function ($query) use ($user, $tenantId) {
            $query->where('tenant_id', $tenantId);

            if (! $user->isAdmin()) {
                $query->where('agent_id', $user->id);
            }
        });

        return [
            'lead_id' => ['nullable', $leadRule],
            'agent_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'transaction_type' => ['required', Rule::in(array_keys(PropertySearchRequest::TRANSACTION_TYPES))],
            'property_type' => ['required', Rule::in(CustomFieldService::getValidSlugs('property_type', $user->tenant))],
            'city' => ['required', 'string', 'max:120'],
            'districts_csv' => ['nullable', 'string', 'max:2000'],
            'districts' => ['nullable', 'array', 'max:30'],
            'districts.*' => ['string', 'max:120', 'distinct'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'min_area_sqm' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:100'],
            'min_street_width_m' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'preferred_facings' => ['nullable', 'array', 'max:8'],
            'preferred_facings.*' => ['string', 'max:30', 'distinct'],
            'max_property_age_years' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'finance_required' => ['boolean'],
            'status' => ['required', Rule::in(array_keys(PropertySearchRequest::STATUSES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function criteria(): array
    {
        $data = $this->validated();
        unset($data['districts_csv']);

        $data['districts'] = $data['districts'] ?? [];
        $data['preferred_facings'] = $data['preferred_facings'] ?? [];

        return $data;
    }
}
