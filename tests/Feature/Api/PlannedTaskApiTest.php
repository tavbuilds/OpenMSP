<?php

namespace Tests\Feature\Api;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskStatus;
use App\Models\Company;
use App\Models\PlannedTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlannedTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/planned-tasks')->assertUnauthorized();
        $this->postJson('/api/v1/planned-tasks', [
            'title' => 'Move',
            'due_on' => now()->addWeek()->toDateString(),
        ])->assertUnauthorized();
    }

    public function test_viewer_can_list_but_cannot_write(): void
    {
        $viewer = User::factory()->viewer()->create();
        Sanctum::actingAs($viewer);

        $company = Company::create(['name' => 'Move Co', 'country' => 'NL']);
        $task = PlannedTask::create([
            'company_id' => $company->id,
            'title' => 'Office relocation',
            'kind' => PlannedTaskKind::Relocation,
            'status' => PlannedTaskStatus::Planned,
            'due_on' => now()->addDays(10)->toDateString(),
        ]);

        $this->getJson('/api/v1/planned-tasks')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Office relocation')
            ->assertJsonPath('data.0.company_name', 'Move Co');

        $this->getJson("/api/v1/planned-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.overdue', false);

        $this->postJson('/api/v1/planned-tasks', [
            'title' => 'Nope',
            'due_on' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    public function test_sales_can_create_update_and_list_upcoming(): void
    {
        $sales = User::factory()->sales()->create();
        Sanctum::actingAs($sales);

        $company = Company::create(['name' => 'Migratie BV', 'country' => 'NL']);

        $create = $this->postJson('/api/v1/planned-tasks', [
            'title' => 'M365 tenant migration',
            'company_id' => $company->id,
            'kind' => 'migration',
            'priority' => 'urgent',
            'due_on' => now()->addDays(12)->toDateString(),
            'location_from' => 'Old tenant',
            'location_to' => 'New tenant',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'M365 tenant migration')
            ->assertJsonPath('data.kind', 'migration');

        $id = $create->json('data.id');

        $this->patchJson("/api/v1/planned-tasks/{$id}", [
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        PlannedTask::create([
            'title' => 'Far away',
            'due_on' => now()->addDays(90)->toDateString(),
            'status' => PlannedTaskStatus::Planned,
        ]);

        $this->getJson('/api/v1/planned-tasks/upcoming?days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->deleteJson("/api/v1/planned-tasks/{$id}")
            ->assertForbidden();
    }

    public function test_manager_can_delete_and_overdue_is_flagged(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $task = PlannedTask::create([
            'title' => 'Disk swap',
            'kind' => PlannedTaskKind::Onsite,
            'status' => PlannedTaskStatus::Blocked,
            'due_on' => now()->subDays(3)->toDateString(),
        ]);

        $this->getJson("/api/v1/planned-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.overdue', true);

        $this->deleteJson("/api/v1/planned-tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('planned_tasks', ['id' => $task->id]);
    }
}
