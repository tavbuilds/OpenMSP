<?php

namespace App\Console\Commands;

use App\Models\PlannedTask;
use App\Models\User;
use App\Notifications\PlannedTaskDeadlineReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendPlannedTaskReminders extends Command
{
    protected $signature = 'planning:send-deadline-reminders';

    protected $description = 'Send planned-task deadline reminders (30/14/7/1 days and overdue), honoring per-task toggles.';

    public function handle(): int
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();
        $sent = 0;

        $tasks = PlannedTask::query()
            ->with(['company', 'assignedUser'])
            ->open()
            ->get();

        foreach ($tasks as $task) {
            $which = $task->dueNotification();
            if ($which === null) {
                continue;
            }

            $recipients = $staff;
            if ($task->assignedUser && ! $recipients->contains('id', $task->assigned_user_id)) {
                $recipients = $recipients->push($task->assignedUser);
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PlannedTaskDeadlineReminder($task, $which));
            }

            $task->markNotified($which);
            $sent++;
        }

        $this->info("{$sent} planned-task reminder(s) sent.");

        return self::SUCCESS;
    }
};
