<?php

namespace App\Console\Commands;

use App\Models\PlannedTask;
use App\Models\User;
use App\Notifications\PlannedTaskDeadlineReminder;
use App\Notifications\ReminderDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class SendPlannedTaskReminders extends Command
{
    protected $signature = 'planning:send-deadline-reminders';

    protected $description = 'Send one digest of the planned tasks due (30/14/7/1 days and overdue), honoring per-task toggles.';

    public function handle(): int
    {
        $staff = User::query()->whereIn('role', ['admin', 'manager'])->get();

        /** @var Collection<int, PlannedTask> $due */
        $due = new Collection;
        $context = [];

        /** @var array<int, Collection<int, PlannedTask>> $byAssignee */
        $byAssignee = [];
        $assignees = [];

        $tasks = PlannedTask::query()
            ->with(['company', 'assignedUser'])
            ->open()
            ->get();

        foreach ($tasks as $task) {
            $which = $task->dueNotification();
            if ($which === null) {
                continue;
            }

            $due->push($task);
            $context[$task->getKey()] = ['days' => $which === 'expired' ? null : (int) $which];

            // The bell in the panel stays one entry per task — that is a
            // list you click through, not an inbox you drown in.
            if ($staff->isNotEmpty()) {
                Notification::send($staff, new PlannedTaskDeadlineReminder($task, $which));
            }

            // Someone outside admin and manager can still own a task, and
            // they are told about theirs rather than about the whole board.
            $owner = $task->assignedUser;
            if ($owner && ! $staff->contains('id', $owner->getKey())) {
                $byAssignee[$owner->getKey()] ??= new Collection;
                $byAssignee[$owner->getKey()]->push($task);
                $assignees[$owner->getKey()] = $owner;
            }

            $task->markNotified($which);
        }

        if ($due->isEmpty()) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        if ($staff->isNotEmpty()) {
            Notification::send($staff, new ReminderDigest(ReminderDigest::TASKS, $due, $context));
        }

        foreach ($byAssignee as $id => $theirs) {
            $assignees[$id]->notify(new ReminderDigest(ReminderDigest::TASKS, $theirs, $context));
        }

        $this->info($due->count().' task(s) digested to '.$staff->count().' staff and '.count($byAssignee).' assignee(s).');

        return self::SUCCESS;
    }
}
