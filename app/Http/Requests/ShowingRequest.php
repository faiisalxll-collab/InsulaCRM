<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Models\Property;
use App\Models\PropertyRequest as PropertySearchRequest;
use App\Models\Showing;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user->tenant_id;
        $creating = $this->isMethod('post');

        $requestExists = Rule::exists('property_requests', 'id')->where(function ($query) use ($user, $tenantId) {
            $query->where('tenant_id', $tenantId);

            if (! $user->isAdmin()) {
                $query->where('agent_id', $user->id);
            }
        });

        $leadExists = Rule::exists('leads', 'id')->where(function ($query) use ($user, $tenantId) {
            $query->where('tenant_id', $tenantId);

            if (! $user->isAdmin()) {
                $query->where('agent_id', $user->id);
            }
        });

        $agentRules = [
            'nullable',
            Rule::exists('users', 'id')->where('tenant_id', $tenantId),
        ];

        if (! $user->isAdmin()) {
            $agentRules[] = Rule::in([$user->id]);
        }

        return [
            'property_request_id' => ['nullable', $requestExists],
            'property_id' => [
                $creating ? 'required' : 'sometimes',
                Rule::exists('properties', 'id')->where(function ($query) use ($user, $tenantId) {
                    $query->where('tenant_id', $tenantId);
                    if (! $user->isAdmin()) {
                        $query->whereIn('lead_id', function ($owners) use ($user, $tenantId) {
                            $owners->select('id')->from('leads')->where('tenant_id', $tenantId)->where('agent_id', $user->id);
                        });
                    }
                }),
            ],
            'lead_id' => ['nullable', $leadExists],
            'deal_id' => ['nullable', Rule::exists('deals', 'id')->where(function ($query) use ($user, $tenantId) {
                $query->where('tenant_id', $tenantId);
                if (! $user->isAdmin()) {
                    $query->where('agent_id', $user->id);
                }
            })],
            'agent_id' => $agentRules,
            'showing_date' => $creating ? 'required|date' : 'sometimes|date',
            'showing_time' => $creating ? 'required' : 'sometimes',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'status' => ['nullable', Rule::in(array_keys(Showing::STATUSES))],
            'feedback' => 'nullable|string',
            'outcome' => ['nullable', Rule::in(array_keys(Showing::OUTCOMES))],
            'listing_agent_name' => 'nullable|string|max:255',
            'listing_agent_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $showing = $this->route('showing');
                $creating = $this->isMethod('post');

                $requestId = $this->has('property_request_id')
                    ? $this->input('property_request_id')
                    : $showing?->property_request_id;

                $propertyId = $this->has('property_id')
                    ? $this->input('property_id')
                    : $showing?->property_id;

                $dealId = $this->has('deal_id') ? $this->input('deal_id') : $showing?->deal_id;

                // A showing that has started a transaction is historical evidence.
                // Keep its parties and transaction links stable when editing feedback.
                if ($showing?->deal_id) {
                    foreach (['property_id', 'property_request_id', 'deal_id', 'lead_id', 'agent_id'] as $field) {
                        if ($this->has($field) && (int) $this->input($field) !== (int) $showing->{$field}) {
                            $validator->errors()->add($field, 'لا يمكن تغيير أطراف معاينة مرتبطة بصفقة.');
                        }
                    }
                }

                if ($dealId) {
                    $deal = Deal::query()->find($dealId);
                    if (! $deal || ! $this->user()->can('update', $deal)
                        || ($deal->property_id && (int) $deal->property_id !== (int) $propertyId)
                        || ($deal->property_request_id && (int) $deal->property_request_id !== (int) $requestId)) {
                        $validator->errors()->add('deal_id', 'الصفقة لا تطابق العقار والطلب أو صلاحيات الوسيط.');
                    }
                }

                if (! $requestId || ! $propertyId || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $linkChanged = $creating
                    || (int) $requestId !== (int) ($showing?->property_request_id)
                    || (int) $propertyId !== (int) ($showing?->property_id);

                if (! $linkChanged) {
                    return;
                }

                $tenantId = $this->user()->tenant_id;

                $propertyRequest = PropertySearchRequest::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($requestId);

                $property = Property::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($propertyId);

                if (! $propertyRequest || ! $property) {
                    return;
                }

                if ($creating && $propertyRequest->status !== 'active') {
                    $validator->errors()->add('property_request_id', 'لا يمكن جدولة معاينة لطلب غير نشط.');

                    return;
                }

                $result = app(PropertyMatchingService::class)->evaluate($propertyRequest, $property);

                if (! $result['hard_constraints_passed']) {
                    $validator->errors()->add(
                        'property_id',
                        'العقار لا يطابق القيود الأساسية للطلب العقاري المحدد.'
                    );
                }

                $leadId = $this->input('lead_id');
                if ($leadId && $propertyRequest->lead_id && (int) $leadId !== (int) $propertyRequest->lead_id) {
                    $validator->errors()->add('lead_id', 'العميل لا يطابق العميل المرتبط بالطلب العقاري.');
                }
            },
        ];
    }
}
