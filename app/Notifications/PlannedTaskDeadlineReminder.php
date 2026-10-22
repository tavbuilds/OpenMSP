<?php

namespace App\Notifications;

use App\Models\PlannedTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One entry in the panel's notification bell.
 *
 * The mail went out as part of the daily digest — a list you read once,
 * rather than a dozen separate letters. This is the clickable record
 * that survives it.
 */
class PlannedTaskDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PlannedTask $task,
        public int|string $which,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'planned_task_id' => $this->task->getKey(),
            'title' => $this->task->title,
            'company' => $this->task->company?->name,
            'due_on' => $this->task->due_on?->toDateString(),
            'which' => $this->which,
        ];
    }
}
