<?php

namespace Tests\Feature;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskStatus;
use App\Models\PlannedTask;
use App\Models\User;
use App\Notifications\PlannedTaskDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlannedTaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_notification_picks_tightest_enabled_offset(): void
    {
        $task = PlannedTask::create([
            'title' => 'Soon',
            'kind' => PlannedTaskKind::Relocation,
            'status' => PlannedTaskStatus::Planned,
            'due_on' => now()->addDays(12)->toDateString(),
        ]);
        $this->assertSame(14, $task->dueNotification());

        $task->forceFill(['notify_14' => false])->save();
        $this->assertSame(30, $task->fresh()->dueNotification());

        $task->forceFill(['notify_14' => true, 'notify_30' => true])->save();
        $task->fresh()->markNotified(14);
        $this->assertNull($task->fresh()->dueNotification());
    }

    public function test_expired_toggle_controls_overdue_mail(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $on = PlannedTask::create([
            'title' => 'Overdue on',
            'due_on' => now()->subDay()->toDateString(),
            'notify_expired' => true,
        ]);
        $off = PlannedTask::create([
            'title' => 'Overdue off',
            'due_on' => now()->subDay()->toDateString(),
            'notify_expired' => false,
        ]);
        $done = PlannedTask::create([
            'title' => 'Already done',
            'status' => PlannedTaskStatus::Done,
            'due_on' => now()->subDay()->toDateString(),
            'notify_expired' => true,
        ]);

        $this->artisan('planning:send-deadline-reminders')->assertSuccessful();

        Notification::assertSentTo($admin, PlannedTaskDeadlineReminder::class, function ($n) use ($on) {
            return $n->task->is($on) && $n->which === 'expired';
        });
        Notification::assertNotSentTo($admin, PlannedTaskDeadlineReminder::class, function ($n) use ($off) {
            return $n->task->is($off);
        });
        Notification::assertNotSentTo($admin, PlannedTaskDeadlineReminder::class, function ($n) use ($done) {
            return $n->task->is($done);
        });
        $this->assertNotNull($on->fresh()->expired_notified_at);

        Notification::fake();
        $this->artisan('planning:send-deadline-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_assignee_also_receives_reminder(): void
    {
        Notification::fake();
        User::factory()->admin()->create();
        $assignee = User::factory()->sales()->create(['email' => 'tech@example.com']);

        PlannedTask::create([
            'title' => 'On-site Friday',
            'assigned_user_id' => $assignee->id,
            'due_on' => now()->addDays(7)->toDateString(),
        ]);

        $this->artisan('planning:send-deadline-reminders')->assertSuccessful();

        Notification::assertSentTo($assignee, PlannedTaskDeadlineReminder::class);
    }
}
