<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContractStatus;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\PlannedTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use AuthorizesAgentApi;

    /**
     * Portfolio metrics matching Filament PortfolioStats + UpcomingRenewals widgets.
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $active = Contract::query()
            ->where('status', ContractStatus::Active->value)
            ->get();

        $arr = (float) $active->sum(fn (Contract $c) => $c->annual_revenue);
        $annualMargin = (float) $active->sum(fn (Contract $c) => $c->annual_margin);
        $mrr = $arr / 12;

        $upcoming30Count = Contract::query()
            ->withUpcomingRenewalTerm()
            ->whereBetween('renewal_date', [now(), now()->addDays(30)])
            ->count();

        // Same horizon as Filament UpcomingRenewals widget (60 days).
        $upcomingList = Contract::query()
            ->with('company')
            ->withUpcomingRenewalTerm()
            ->whereBetween('renewal_date', [now(), now()->addDays(60)])
            ->orderBy('renewal_date')
            ->limit(25)
            ->get()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'company_id' => $c->company_id,
                'company_name' => $c->company?->name,
                'name' => $c->name,
                'renewal_date' => $c->renewal_date?->toDateString(),
                'notice_deadline' => $c->notice_deadline?->toDateString(),
                'total_sale' => round((float) $c->total_sale, 2),
                'auto_renew' => (bool) $c->auto_renew,
                'status' => $c->status?->value ?? $c->status,
            ])
            ->values();

        // Active contracts whose notice deadline falls within 60 days (cancel-before-renew awareness).
        $upcomingNoticeDeadlines = Contract::query()
            ->with('company')
            ->withUpcomingRenewalTerm()
            ->get()
            ->filter(function (Contract $c) {
                $deadline = $c->notice_deadline;
                if ($deadline === null) {
                    return false;
                }

                return $deadline->betweenIncluded(now()->startOfDay(), now()->addDays(60)->endOfDay());
            })
            ->sortBy(fn (Contract $c) => $c->notice_deadline)
            ->take(25)
            ->values()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'company_id' => $c->company_id,
                'company_name' => $c->company?->name,
                'name' => $c->name,
                'renewal_date' => $c->renewal_date?->toDateString(),
                'notice_deadline' => $c->notice_deadline?->toDateString(),
                'notice_period_days' => $c->notice_period_days,
                'auto_renew' => (bool) $c->auto_renew,
                'status' => $c->status?->value ?? $c->status,
            ]);

        $upcomingPlanning = PlannedTask::query()
            ->with('company')
            ->open()
            ->whereDate('due_on', '<=', now()->addDays(60)->toDateString())
            ->orderBy('due_on')
            ->limit(25)
            ->get()
            ->map(fn (PlannedTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'company_id' => $task->company_id,
                'company_name' => $task->company?->name,
                'kind' => $task->kind?->value ?? $task->kind,
                'status' => $task->status?->value ?? $task->status,
                'priority' => $task->priority?->value ?? $task->priority,
                'due_on' => $task->due_on?->toDateString(),
                'days_until_due' => $task->daysUntilDue(),
                'overdue' => $task->isOverdue(),
            ]);

        return response()->json([
            'data' => [
                'active_contracts_count' => $active->count(),
                'mrr' => round($mrr, 2),
                'arr' => round($arr, 2),
                'annual_margin' => round($annualMargin, 2),
                'upcoming_renewals_30d_count' => $upcoming30Count,
                'upcoming_renewals' => $upcomingList,
                'upcoming_notice_deadlines' => $upcomingNoticeDeadlines,
                'upcoming_planned_tasks' => $upcomingPlanning,
            ],
        ]);
    }
}
