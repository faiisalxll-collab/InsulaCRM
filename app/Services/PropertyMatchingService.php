<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PropertyMatchingService
{
    public function evaluate(PropertyRequest $request, Property $property): array
    {
        if ((int) $request->tenant_id !== (int) $property->tenant_id) {
            return $this->rejected('tenant_mismatch');
        }

        $rejections = $this->hardConstraintFailures($request, $property);

        if ($rejections !== []) {
            return $this->rejected(...$rejections);
        }

        $weights = config('property_matching.weights');
        $scores = [
            'location_score' => (int) $weights['location'],
            'price_score' => (int) $weights['price'],
            'area_score' => (int) $weights['area'],
            'features_score' => 0,
            'finance_score' => (int) $weights['finance'],
        ];

        $reasons = ['transaction_type', 'property_type', 'city'];
        $warnings = [];

        $districts = $this->normalizedList($request->districts);
        if ($districts !== []) {
            $reasons[] = 'district';
        }

        $price = $this->propertyPrice($property);
        if ($price !== null) {
            $reasons[] = 'price';
        }

        if ($property->area_sqm !== null) {
            $reasons[] = 'area';
        }

        $scores['features_score'] += $this->scoreOptionalMinimum(
            $property->bedrooms,
            $request->min_bedrooms,
            (int) $weights['rooms'],
            'bedrooms',
            $reasons,
            $warnings
        );

        $scores['features_score'] += $this->scoreOptionalMinimum(
            $property->street_width_m,
            $request->min_street_width_m,
            (int) $weights['street_width'],
            'street_width',
            $reasons,
            $warnings
        );

        $preferredFacings = $this->normalizedList($request->preferred_facings);
        if ($preferredFacings === []) {
            $scores['features_score'] += (int) ($weights['facing'] ?? $weights['frontage'] ?? 5);
        } elseif ($property->facing !== null && in_array($this->normalize($property->facing), $preferredFacings, true)) {
            $scores['features_score'] += (int) ($weights['facing'] ?? $weights['frontage'] ?? 5);
            $reasons[] = 'facing';
        } else {
            $warnings[] = 'facing_preference_not_met';
        }

        if ($request->max_property_age_years === null) {
            $scores['features_score'] += (int) $weights['age'];
        } elseif ($property->property_age_years !== null && $property->property_age_years <= $request->max_property_age_years) {
            $scores['features_score'] += (int) $weights['age'];
            $reasons[] = 'age';
        } else {
            $warnings[] = 'age_preference_not_met';
        }

        if ($request->finance_required) {
            $reasons[] = 'finance';
        }

        return [
            'match_score' => min(100, array_sum($scores)),
            'hard_constraints_passed' => true,
            ...$scores,
            'match_reasons' => array_values(array_unique($reasons)),
            'rejection_reasons' => $warnings,
        ];
    }

    public function candidatesForRequest(PropertyRequest $request): Builder
    {
        $query = Property::query()
            ->where('tenant_id', $request->tenant_id)
            ->where('transaction_type', $request->transaction_type)
            ->where('property_type', $request->property_type)
            ->whereRaw('LOWER(TRIM(city)) = ?', [$this->normalize($request->city)]);

        $statuses = config('property_matching.active_property_statuses', []);
        if ($statuses !== []) {
            $query->whereIn('listing_status', $statuses);
        }

        $districts = $this->normalizedList($request->districts);
        if ($districts !== []) {
            $query->where(function (Builder $q) use ($districts) {
                foreach ($districts as $index => $district) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $q->{$method}('LOWER(TRIM(district)) = ?', [$district]);
                }
            });
        }

        if ($request->max_price !== null) {
            $query->where(function (Builder $q) use ($request) {
                $q->where('list_price', '<=', $request->max_price)
                    ->orWhere(function (Builder $fallback) use ($request) {
                        $fallback->whereNull('list_price')->where('asking_price', '<=', $request->max_price);
                    });
            });
        }

        if ($request->min_price !== null) {
            $query->where(function (Builder $q) use ($request) {
                $q->where('list_price', '>=', $request->min_price)
                    ->orWhere(function (Builder $fallback) use ($request) {
                        $fallback->whereNull('list_price')->where('asking_price', '>=', $request->min_price);
                    });
            });
        }

        if ($request->min_area_sqm !== null) {
            $query->where('area_sqm', '>=', $request->min_area_sqm);
        }

        if ($request->finance_required) {
            $query->where('finance_eligible', true);
        }

        return $query->limit((int) config('property_matching.candidate_limit', 500));
    }

    public function candidatesForProperty(Property $property): Builder
    {
        $price = $this->propertyPrice($property);

        $query = PropertyRequest::query()
            ->where('tenant_id', $property->tenant_id)
            ->where('transaction_type', $property->transaction_type)
            ->where('property_type', $property->property_type)
            ->whereRaw('LOWER(TRIM(city)) = ?', [$this->normalize($property->city)])
            ->whereIn('status', config('property_matching.active_request_statuses', ['active']));

        if ($price !== null) {
            $query->where(function (Builder $q) use ($price) {
                $q->whereNull('max_price')->orWhere('max_price', '>=', $price);
            })->where(function (Builder $q) use ($price) {
                $q->whereNull('min_price')->orWhere('min_price', '<=', $price);
            });
        }

        if ($property->area_sqm !== null) {
            $query->where(function (Builder $q) use ($property) {
                $q->whereNull('min_area_sqm')->orWhere('min_area_sqm', '<=', $property->area_sqm);
            });
        }

        if ($property->finance_eligible !== true) {
            $query->where('finance_required', false);
        }

        return $query->limit((int) config('property_matching.candidate_limit', 500));
    }

    public function refreshForRequest(PropertyRequest $request): Collection
    {
        if (! in_array($request->status, config('property_matching.active_request_statuses', ['active']), true)) {
            PropertyMatch::withoutGlobalScopes()
                ->where('tenant_id', $request->tenant_id)
                ->where('property_request_id', $request->id)
                ->delete();

            return collect();
        }

        $properties = $this->candidatesForRequest($request)->get();
        $propertyIds = $properties->pluck('id')->all();

        $stale = PropertyMatch::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->where('property_request_id', $request->id);

        $propertyIds === []
            ? $stale->delete()
            : $stale->whereNotIn('property_id', $propertyIds)->delete();

        return $properties->map(
            fn (Property $property) => $this->persist($request, $property)
        );
    }

    public function refreshForProperty(Property $property): Collection
    {
        if (! in_array($property->listing_status, config('property_matching.active_property_statuses', ['active']), true)) {
            PropertyMatch::withoutGlobalScopes()
                ->where('tenant_id', $property->tenant_id)
                ->where('property_id', $property->id)
                ->delete();

            return collect();
        }

        $requests = $this->candidatesForProperty($property)->get()->filter(function (PropertyRequest $request) use ($property) {
            $districts = $this->normalizedList($request->districts);

            return $districts === [] || in_array($this->normalize($property->district), $districts, true);
        })->values();

        $requestIds = $requests->pluck('id')->all();

        $stale = PropertyMatch::withoutGlobalScopes()
            ->where('tenant_id', $property->tenant_id)
            ->where('property_id', $property->id);

        $requestIds === []
            ? $stale->delete()
            : $stale->whereNotIn('property_request_id', $requestIds)->delete();

        return $requests->map(
            fn (PropertyRequest $request) => $this->persist($request, $property)
        );
    }

    public function persist(PropertyRequest $request, Property $property): PropertyMatch
    {
        $result = $this->evaluate($request, $property);

        return PropertyMatch::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $request->tenant_id,
                'property_request_id' => $request->id,
                'property_id' => $property->id,
            ],
            [
                ...$result,
                'status' => $result['hard_constraints_passed'] ? 'eligible' : 'rejected',
                'matched_at' => now(),
                'evaluated_at' => now(),
            ]
        );
    }

    private function hardConstraintFailures(PropertyRequest $request, Property $property): array
    {
        $rejections = [];

        if ($request->transaction_type !== $property->transaction_type) {
            $rejections[] = 'transaction_type';
        }
        if ($request->property_type !== $property->property_type) {
            $rejections[] = 'property_type';
        }
        if ($this->normalize($request->city) !== $this->normalize($property->city)) {
            $rejections[] = 'city';
        }

        $districts = $this->normalizedList($request->districts);
        if ($districts !== [] && ! in_array($this->normalize($property->district), $districts, true)) {
            $rejections[] = 'district';
        }

        $price = $this->propertyPrice($property);
        if ($request->max_price !== null && ($price === null || $price > (float) $request->max_price)) {
            $rejections[] = 'max_price';
        }
        if ($request->min_price !== null && ($price === null || $price < (float) $request->min_price)) {
            $rejections[] = 'min_price';
        }
        if ($request->min_area_sqm !== null && ($property->area_sqm === null || (float) $property->area_sqm < (float) $request->min_area_sqm)) {
            $rejections[] = 'min_area';
        }
        if ($request->finance_required && $property->finance_eligible !== true) {
            $rejections[] = 'finance';
        }

        return $rejections;
    }

    private function scoreOptionalMinimum($actual, $preferredMinimum, int $weight, string $reason, array &$reasons, array &$warnings): int
    {
        if ($preferredMinimum === null) {
            return $weight;
        }

        if ($actual !== null && (float) $actual >= (float) $preferredMinimum) {
            $reasons[] = $reason;

            return $weight;
        }

        $warnings[] = $reason.'_preference_not_met';

        return 0;
    }

    private function propertyPrice(Property $property): ?float
    {
        $price = $property->list_price ?? $property->asking_price;

        return $price === null ? null : (float) $price;
    }

    private function normalizedList(?array $values): array
    {
        return array_values(array_unique(array_map(
            fn ($value) => $this->normalize($value),
            array_filter($values ?? [], fn ($value) => $value !== null && trim((string) $value) !== '')
        )));
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function rejected(string ...$reasons): array
    {
        return [
            'match_score' => 0,
            'hard_constraints_passed' => false,
            'location_score' => 0,
            'price_score' => 0,
            'area_score' => 0,
            'features_score' => 0,
            'finance_score' => 0,
            'match_reasons' => [],
            'rejection_reasons' => array_values(array_unique($reasons)),
        ];
    }
}
