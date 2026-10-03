<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyRequest;

class PropertyMatchingService
{
    public function evaluate(PropertyRequest $request, Property $property): array
    {
        if ((int) $request->tenant_id !== (int) $property->tenant_id) {
            return $this->rejected('tenant_mismatch');
        }

        $rejections = [];
        if ($request->transaction_type !== $property->transaction_type) $rejections[] = 'transaction_type';
        if ($request->property_type !== $property->property_type) $rejections[] = 'property_type';
        if (mb_strtolower(trim($request->city)) !== mb_strtolower(trim($property->city))) $rejections[] = 'city';

        $districts = array_values(array_filter($request->districts ?? []));
        if ($districts && !in_array($property->district, $districts, true)) $rejections[] = 'district';

        $price = $property->list_price ?? $property->asking_price;
        if ($request->max_price !== null && ($price === null || (float) $price > (float) $request->max_price)) $rejections[] = 'max_price';
        if ($request->min_price !== null && ($price === null || (float) $price < (float) $request->min_price)) $rejections[] = 'min_price';
        if ($request->min_area_sqm !== null && ($property->area_sqm === null || (float) $property->area_sqm < (float) $request->min_area_sqm)) $rejections[] = 'min_area';
        if ($request->min_bedrooms !== null && ($property->bedrooms === null || $property->bedrooms < $request->min_bedrooms)) $rejections[] = 'min_bedrooms';
        if ($request->min_street_width_m !== null && ($property->street_width_m === null || (float) $property->street_width_m < (float) $request->min_street_width_m)) $rejections[] = 'street_width';
        if ($request->finance_required && $property->finance_eligible !== true) $rejections[] = 'finance';

        if ($rejections) {
            return [
                'match_score' => 0, 'hard_constraints_passed' => false,
                'location_score' => 0, 'price_score' => 0, 'area_score' => 0,
                'features_score' => 0, 'finance_score' => 0,
                'match_reasons' => [], 'rejection_reasons' => $rejections,
            ];
        }

        $location = 30;
        $priceScore = 20;
        $area = 15;
        $features = 25;
        $finance = 10;
        $reasons = ['transaction_type','property_type','city'];

        if ($districts) $reasons[] = 'district';
        if ($price !== null) $reasons[] = 'price';
        if ($property->area_sqm !== null) $reasons[] = 'area';

        $preferred = array_values(array_filter($request->preferred_facings ?? []));
        if ($preferred && !in_array($property->facing, $preferred, true)) $features -= 5;
        elseif ($preferred) $reasons[] = 'facing';

        if ($request->max_property_age_years !== null && $property->property_age_years !== null && $property->property_age_years > $request->max_property_age_years) $features -= 5;
        else if ($request->max_property_age_years !== null) $reasons[] = 'age';

        return [
            'match_score' => max(0, min(100, $location + $priceScore + $area + $features + $finance)),
            'hard_constraints_passed' => true,
            'location_score' => $location, 'price_score' => $priceScore,
            'area_score' => $area, 'features_score' => $features, 'finance_score' => $finance,
            'match_reasons' => $reasons, 'rejection_reasons' => [],
        ];
    }

    private function rejected(string $reason): array
    {
        return [
            'match_score' => 0, 'hard_constraints_passed' => false,
            'location_score' => 0, 'price_score' => 0, 'area_score' => 0,
            'features_score' => 0, 'finance_score' => 0,
            'match_reasons' => [], 'rejection_reasons' => [$reason],
        ];
    }
}
