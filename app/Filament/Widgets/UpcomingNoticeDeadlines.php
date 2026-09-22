<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingNoticeDeadlines extends BaseWidget
{
    protected static ?string $heading = 'Notice deadlines (60 days)';

    public function getHeading(): ?string
    {
        return __('Notice deadlines (60 days)');
    }

    public function getTableDescription(): ?string
    {
        return __('Quarterly and yearly contracts only — a monthly contract can be cancelled every month.');
    }

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $ids = Contract::query()
            ->withUpcomingRenewalTerm()
            ->get()
            ->filter(function (Contract $contract): bool {
                $deadline = $contract->notice_deadline;
                if (! $deadline) {
                    return false;
                }

                return $deadline->betweenIncluded(now()->startOfDay(), now()->addDays(60)->endOfDay());
            })
            ->sortBy(fn (Contract $c) => $c->notice_deadline)
            ->pluck('id')
            ->values();

        if ($ids->isEmpty()) {
            return Contract::query()->whereRaw('0 = 1');
        }

        return Contract::query()
            ->with('company')
            ->whereIn('id', $ids)
            ->orderByRaw("CASE id {$ids->map(fn ($id, $i) => "WHEN {$id} THEN {$i}")->implode(' ')} END");
    }

    public function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (Contract $record) => ContractResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('company.name')->label(__('Customer'))->weight('bold'),
            TextColumn::make('name')->label(__('Service'))->visibleFrom('md'),
            TextColumn::make('notice_deadline')->label(__('Notice deadline'))->date('M j, Y'),
            TextColumn::make('renewal_date')->label(__('Renewal'))->date('M j, Y')->visibleFrom('md'),
            TextColumn::make('notice_period_days')->label(__('Notice (days)'))->alignEnd()->visibleFrom('lg'),
        ];
    }
}
