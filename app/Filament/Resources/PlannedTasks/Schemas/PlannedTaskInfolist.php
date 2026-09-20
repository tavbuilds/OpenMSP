<?php

namespace App\Filament\Resources\PlannedTasks\Schemas;

use App\Models\PlannedTask;
use App\Support\Breakpoints;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlannedTaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Task')
                ->columns(Breakpoints::THREE)
                ->schema([
                    TextEntry::make('title')->label(__('Title'))->weight('bold'),
                    TextEntry::make('company.name')->label(__('Customer'))->placeholder(__('Internal')),
                    TextEntry::make('kind')->label(__('Type'))->badge(),
                    TextEntry::make('status')->label(__('Status'))->badge(),
                    TextEntry::make('priority')->label(__('Priority'))->badge(),
                    TextEntry::make('due_on')->label(__('Deadline'))->date('M j, Y'),
                    TextEntry::make('days')
                        ->label(__('Days'))
                        ->badge()
                        ->color(fn (PlannedTask $record) => $record->dueColor())
                        ->state(function (PlannedTask $record): string {
                            $days = $record->daysUntilDue();
                            if ($days < 0) {
                                return __('Overdue').' ('.abs($days).' d)';
                            }

                            return (string) $days;
                        }),
                    TextEntry::make('assignedUser.name')->label(__('Assignee'))->placeholder(__('Unassigned')),
                    TextEntry::make('location_from')->label(__('From'))->placeholder(__('—')),
                    TextEntry::make('location_to')->label(__('To'))->placeholder(__('—')),
                    TextEntry::make('notes')->label(__('Notes'))->placeholder(__('—'))->columnSpanFull(),
                ]),
            Section::make('Notifications')
                ->columns(Breakpoints::THREE)
                ->schema([
                    IconEntry::make('notify_30')->label(__('30 days'))->boolean(),
                    IconEntry::make('notify_14')->label(__('14 days'))->boolean(),
                    IconEntry::make('notify_7')->label(__('7 days'))->boolean(),
                    IconEntry::make('notify_1')->label(__('1 day'))->boolean(),
                    IconEntry::make('notify_expired')->label(__('Expired'))->boolean(),
                ]),
        ]);
    }
}
