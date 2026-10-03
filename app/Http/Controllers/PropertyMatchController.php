<?php

namespace App\Http\Controllers;

use App\Models\PropertyMatch;
use App\Models\PropertyRequest as PropertySearchRequest;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;

class PropertyMatchController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PropertyMatch::class);

        $query = PropertyMatch::with(['property', 'request.lead', 'request.agent'])
            ->where('status', 'eligible')
            ->where('hard_constraints_passed', true);

        if (! auth()->user()->isAdmin()) {
            $query->whereHas('request', fn ($q) => $q->where('agent_id', auth()->id()));
        }

        if ($request->filled('min_score')) {
            $query->where('match_score', '>=', max(0, min(100, $request->integer('min_score'))));
        }

        if ($request->filled('transaction_type')) {
            $query->whereHas('request', fn ($q) => $q->where(
                'transaction_type',
                $request->string('transaction_type')->toString()
            ));
        }

        if ($request->filled('property_type')) {
            $query->whereHas('request', fn ($q) => $q->where(
                'property_type',
                $request->string('property_type')->toString()
            ));
        }

        if ($request->filled('city')) {
            $city = mb_strtolower(trim($request->string('city')->toString()));
            $query->whereHas('property', fn ($q) => $q->whereRaw('LOWER(TRIM(city)) = ?', [$city]));
        }

        $matches = $query
            ->orderByDesc('match_score')
            ->orderByDesc('evaluated_at')
            ->paginate(30)
            ->withQueryString();

        return view('property-matches.index', [
            'matches' => $matches,
            'propertyTypes' => CustomFieldService::getOptions('property_type'),
            'transactionTypes' => PropertySearchRequest::TRANSACTION_TYPES,
        ]);
    }
}
