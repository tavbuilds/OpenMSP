<?php

namespace Tests\Feature;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskStatus;
use App\Models\Company;
use App\Models\PlannedTask;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannedTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_days_until_due_and_open_scope(): void
    {
        $open = PlannedTask::create([
            'title' => 'Soon',
            'kind' => PlannedTaskKind::Relocation,
            'status' => PlannedTaskStatus::Planned,
            'due_on' => now()->addDays(5)->toDateString(),
        ]);
        $done = PlannedTask::create([
            'title' => 'Finished',
            'status' => PlannedTaskStatus::Done,
            'due_on' => now()->subDays(1)->toDateString(),
        ]);

        $this->assertSame(5, $open->daysUntilDue());
        $this->assertFalse($open->isOverdue());
        $this->assertFalse($done->isOverdue());
        $this->assertTrue(PlannedTask::query()->open()->whereKey($open->id)->exists());
        $this->assertFalse(PlannedTask::query()->open()->whereKey($done->id)->exists());
    }

    public function test_demo_seed_creates_and_purge_removes_planned_tasks(): void
    {
        $realCompany = Company::create(['name' => 'Real Co', 'country' => 'NL', 'is_demo' => false]);
        $keep = PlannedTask::create([
            'company_id' => $realCompany->id,
            'title' => 'Keep me',
            'due_on' => now()->addMonth()->toDateString(),
            'is_demo' => false,
        ]);

        DemoData::seed();

        $this->assertGreaterThan(0, PlannedTask::query()->where('is_demo', true)->count());

        DemoData::purge();

        $this->assertSame(0, PlannedTask::query()->where('is_demo', true)->count());
        $this->assertTrue(PlannedTask::query()->whereKey($keep->id)->exists());
    }
}
