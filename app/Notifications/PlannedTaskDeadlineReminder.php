<?php

namespace App\Notifications;

use App\Models\PlannedTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlannedTaskDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PlannedTask $task,
        public int|string $which,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->task;
        $when = $this->which === 'expired'
            ? 'the deadline has passed'
            : "the deadline is in {$this->which} day(s)";
        $customer = $task->company?->name ?? 'internal';

        return (new MailMessage)
            ->subject("Planning due soon: {$task->title}")
            ->greeting('Reminder')
            ->line("The planned task '{$task->title}' ({$customer}) — {$when}.")
            ->line('Deadline: '.($task->due_on?->format('M j, Y') ?? 'unknown'))
            ->line('Type: '.($task->kind?->getLabel() ?? '—'))
            ->line('Status: '.($task->status?->getLabel() ?? '—'))
            ->action('View task', url('/admin/planned-tasks/'.$task->getKey()));
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
};
