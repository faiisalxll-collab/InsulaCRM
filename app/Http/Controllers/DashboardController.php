<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyRequest;
use App\Models\Showing;
use App\Models\Task;
use App\Models\User;
use App\Services\BusinessModeService;
use App\Services\DashboardWidgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the admin/agent dashboard with KPI widgets.
     */
    public function index()
    {
        $user = auth()->user();

        // Field scouts get a simplified dashboard with only the property submission form
        if ($user->isFieldScout()) {
            return view('dashboard.field-scout');
        }

        if (BusinessModeService::isRealEstate($user->tenant)) {
            return $this->saudiRealEstateDashboard($user);
        }

        $leadQuery = Lead::query();
        $dealQuery = Deal::query();
        $taskQuery = Task::query();

        if ($user->isAgent() || $user->isDispositionAgent()) {
            $leadQuery->where('agent_id', $user->id);
            $dealQuery->where('agent_id', $user->id);
            $taskQuery->where('agent_id', $user->id);
        }

        $totalLeads = (clone $leadQuery)->count();
        $leadsThisMonth = (clone $leadQuery)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        $activeDeals = (clone $dealQuery)->whereNotIn('stage', ['closed_won', 'closed_lost'])->count();
        $closedThisMonth = (clone $dealQuery)->where('stage', 'closed_won')->whereMonth('created_at', now()->month)->count();
        $feeColumn = \App\Services\BusinessModeService::getDashboardKpiConfig()['fee_column'];
        $feesThisMonth = (clone $dealQuery)->where('stage', 'closed_won')->whereMonth('created_at', now()->month)->sum($feeColumn);
        $totalPipelineValue = (clone $dealQuery)->whereNotIn('stage', ['closed_won', 'closed_lost'])->sum('contract_price');

        $hotLeads = (clone $leadQuery)->where('temperature', 'hot')->whereNotIn('status', ['closed', 'dead'])->count();
        $overdueTasks = (clone $taskQuery)->where('is_completed', false)->where('due_date', '<', now())->count();

        $upcomingTasks = (clone $taskQuery)
            ->where('is_completed', false)
            ->where('due_date', '<=', now()->addDays(7))
            ->with('lead')
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        // Recent leads (last 5)
        $recentLeads = (clone $leadQuery)
            ->with('agent')
            ->latest()
            ->limit(5)
            ->get();

        // Pipeline Bottleneck (admin only)
        $pipelineBottleneck = collect();
        if ($user->isAdmin()) {
            $avgDaysExpr = DB::getDriverName() === 'sqlite'
                ? DB::raw("avg(julianday('now') - julianday(created_at)) as avg_days")
                : DB::raw('avg(DATEDIFF(NOW(), created_at)) as avg_days');
            $pipelineBottleneck = Deal::whereNotIn('stage', ['closed_won', 'closed_lost'])
                ->select('stage', DB::raw('count(*) as deal_count'), $avgDaysExpr)
                ->groupBy('stage')
                ->orderByDesc('avg_days')
                ->get();
        }

        // Team Performance Leaderboard (admin only)
        $teamPerformance = collect();
        if ($user->isAdmin()) {
            $teamPerformance = User::assignable($user->tenant)
                ->get()
                ->map(function ($agent) use ($feeColumn) {
                    $dealsClosed = Deal::where('agent_id', $agent->id)->where('stage', 'closed_won')
                        ->whereMonth('created_at', now()->month)->count();
                    $feesGenerated = Deal::where('agent_id', $agent->id)->where('stage', 'closed_won')
                        ->whereMonth('created_at', now()->month)->sum($feeColumn);
                    return (object) compact('agent', 'dealsClosed', 'feesGenerated');
                })
                ->sortByDesc('dealsClosed')
                ->take(5);
        }

        $activeWidgets = DashboardWidgetService::getActiveWidgets($user);

        return view('dashboard.index', compact(
            'totalLeads', 'leadsThisMonth', 'activeDeals', 'closedThisMonth',
            'feesThisMonth', 'totalPipelineValue', 'hotLeads', 'overdueTasks',
            'upcomingTasks', 'recentLeads', 'pipelineBottleneck', 'teamPerformance',
            'activeWidgets'
        ));
    }

    private function saudiRealEstateDashboard(User $user)
    {
        $properties = Property::query();
        $requests = PropertyRequest::query();
        $matches = PropertyMatch::visibleTo($user);
        $showings = Showing::query();
        $deals = Deal::query();

        if (! $user->isAdmin()) {
            $properties->whereHas('lead', fn ($query) => $query->where('agent_id', $user->id));
            $requests->where('agent_id', $user->id);
            $matches->whereHas('request', fn ($query) => $query->where('agent_id', $user->id));
            $showings->where('agent_id', $user->id);
            $deals->where('agent_id', $user->id);
        }

        $activeProperties = (clone $properties)->where('listing_status', 'active')->count();
        $activeRequests = (clone $requests)->where('status', 'active')->count();
        $eligibleMatches = (clone $matches)
            ->where('status', 'eligible')
            ->where('hard_constraints_passed', true)
            ->count();
        $todayShowings = (clone $showings)
            ->whereDate('showing_date', now()->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();
        $activeDeals = (clone $deals)
            ->whereNotIn('stage', ['closed_won', 'closed_lost'])
            ->count();
        $closedThisMonth = (clone $deals)
            ->where('stage', 'closed_won')
            ->whereMonth('stage_changed_at', now()->month)
            ->whereYear('stage_changed_at', now()->year)
            ->count();
        $commissionDue = (clone $deals)
            ->where('commission_status', 'due')
            ->sum('total_commission');

        $topMatches = (clone $matches)
            ->with(['property', 'request.lead'])
            ->where('status', 'eligible')
            ->where('hard_constraints_passed', true)
            ->orderByDesc('match_score')
            ->orderByDesc('evaluated_at')
            ->limit(6)
            ->get();

        $upcomingShowings = (clone $showings)
            ->with(['property', 'lead', 'propertyRequest.lead'])
            ->whereDate('showing_date', '>=', now()->toDateString())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->orderBy('showing_date')
            ->orderBy('showing_time')
            ->limit(6)
            ->get();

        $openDeals = (clone $deals)
            ->with(['property', 'propertyRequest.lead', 'agent'])
            ->whereNotIn('stage', ['closed_won', 'closed_lost'])
            ->latest('stage_changed_at')
            ->limit(6)
            ->get();

        return view('dashboard.saudi', compact(
            'activeProperties',
            'activeRequests',
            'eligibleMatches',
            'todayShowings',
            'activeDeals',
            'closedThisMonth',
            'commissionDue',
            'topMatches',
            'upcomingShowings',
            'openDeals',
        ));
    }

    public function updateWidgets(Request $request)
    {
        $request->validate(['widgets' => 'required|array']);

        $user = auth()->user();
        $eligible = array_keys(DashboardWidgetService::getEligibleWidgets($user));
        $widgets = array_values(array_intersect($request->widgets, $eligible));

        $user->dashboard_widgets = $widgets;
        $user->save();

        return response()->json(['success' => true]);
    }
}
