<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyRequest as PropertySearchRequest;
use App\Services\BusinessModeService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (mb_strlen($q, 'UTF-8') < 2) {
            if ($request->expectsJson()) {
                return response()->json(['results' => []]);
            }

            return view('search.results', ['query' => $q, 'results' => collect()]);
        }

        $user = auth()->user();
        $isSaudi = BusinessModeService::isRealEstate();
        $results = collect();

        // Clients / leads
        if ($user->canManageLeads()) {
            $leads = Lead::where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
                ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
                ->limit(5)
                ->get()
                ->map(fn ($lead) => [
                    'type' => 'lead',
                    'title' => $lead->full_name,
                    'subtitle' => $lead->phone ?? $lead->email ?? '',
                    'url' => route('leads.show', $lead),
                ]);

            $results = $results->merge($leads);
        }

        // Transactions / deals
        if (! $user->isFieldScout()) {
            $deals = Deal::where(function ($outer) use ($q, $isSaudi) {
                $outer->where('title', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");

                if ($isSaudi) {
                    $outer->orWhereHas('property', function ($propertyQuery) use ($q) {
                        $propertyQuery
                            ->where('address', 'like', "%{$q}%")
                            ->orWhere('city', 'like', "%{$q}%")
                            ->orWhere('district', 'like', "%{$q}%")
                            ->orWhere('plan_number', 'like', "%{$q}%");
                    })->orWhereHas('propertyRequest.lead', function ($clientQuery) use ($q) {
                        $clientQuery->where(function ($inner) use ($q) {
                            $inner->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%");
                        });
                    });

                    return;
                }

                $outer->orWhereHas('lead', function ($leadQuery) use ($q) {
                    $leadQuery->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
                ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
                ->with($isSaudi ? ['property', 'propertyRequest.lead'] : ['lead'])
                ->limit(5)
                ->get()
                ->map(function ($deal) use ($isSaudi) {
                    if ($isSaudi) {
                        $client = $deal->propertyRequest?->lead?->full_name;
                        $propertyAddress = $deal->property?->address;
                        $subtitle = Deal::stageLabel($deal->stage);

                        if ($propertyAddress) {
                            $subtitle .= ' — '.$propertyAddress;
                        }

                        return [
                            'type' => 'deal',
                            'title' => $client ?: ($deal->title ?: 'صفقة #'.$deal->id),
                            'subtitle' => $subtitle,
                            'url' => route('deals.show', $deal),
                        ];
                    }

                    return [
                        'type' => 'deal',
                        'title' => $deal->lead?->full_name ?? $deal->title,
                        'subtitle' => Deal::stageLabel($deal->stage),
                        'url' => route('deals.show', $deal),
                    ];
                });

            $results = $results->merge($deals);
        }

        // Legacy wholesale buyer database. Saudi V1 represents searching
        // clients through Lead + PropertyRequest instead.
        if (! $isSaudi && $user->canManageBuyers()) {
            $buyers = Buyer::where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
                ->limit(5)
                ->get()
                ->map(fn ($buyer) => [
                    'type' => 'buyer',
                    'title' => $buyer->full_name,
                    'subtitle' => $buyer->company ?? $buyer->email ?? '',
                    'url' => route('buyers.show', $buyer),
                ]);

            $results = $results->merge($buyers);
        }

        // Properties
        $properties = Property::where(function ($query) use ($q, $isSaudi) {
            $query->where('address', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%");

            if ($isSaudi) {
                $query->orWhere('district', 'like', "%{$q}%")
                    ->orWhere('plan_number', 'like', "%{$q}%");
            } else {
                $query->orWhere('zip_code', 'like', "%{$q}%");
            }
        })
            ->when(
                $isSaudi && ! $user->isAdmin(),
                fn ($query) => $query->whereHas('lead', fn ($leadQuery) => $leadQuery->where('agent_id', $user->id))
            )
            ->limit(5)
            ->get()
            ->map(function ($property) use ($isSaudi) {
                $subtitle = $isSaudi
                    ? collect([$property->district, $property->city])->filter()->implode('، ')
                    : trim(($property->city ?? '').', '.($property->state ?? '').' '.($property->zip_code ?? ''), ', ');

                return [
                    'type' => 'property',
                    'title' => $property->address,
                    'subtitle' => $subtitle,
                    'url' => route('properties.show', $property),
                ];
            });

        $results = $results->merge($properties);

        // Saudi buyer/renter requests
        if ($isSaudi && ! $user->isFieldScout() && ! $user->isDispositionAgent()) {
            $propertyRequests = PropertySearchRequest::where(function ($outer) use ($q) {
                $outer->where('city', 'like', "%{$q}%")
                    ->orWhere('property_type', 'like', "%{$q}%")
                    ->orWhereHas('lead', function ($clientQuery) use ($q) {
                        $clientQuery->where(function ($inner) use ($q) {
                            $inner->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%");
                        });
                    });
            })
                ->when(! $user->isAdmin(), fn ($query) => $query->where('agent_id', $user->id))
                ->with('lead')
                ->limit(5)
                ->get()
                ->map(function ($propertyRequest) {
                    $client = $propertyRequest->lead?->full_name ?? 'بدون عميل';
                    $transaction = $propertyRequest->transaction_type === 'rent' ? 'إيجار' : 'شراء';

                    return [
                        'type' => 'request',
                        'title' => 'طلب #'.$propertyRequest->id.' — '.$client,
                        'subtitle' => $transaction.' — '.$propertyRequest->city,
                        'url' => route('property-requests.show', $propertyRequest),
                    ];
                });

            $results = $results->merge($propertyRequests);
        }

        if ($request->expectsJson()) {
            return response()->json(['results' => $results->values()]);
        }

        return view('search.results', ['query' => $q, 'results' => $results]);
    }
}
