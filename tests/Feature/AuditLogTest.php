<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_company_writes_an_audit_row(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $company = Company::create(['name' => 'Audit Co', 'country' => 'NL']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'created',
            'auditable_type' => Company::class,
            'auditable_id' => $company->id,
        ]);

        $log = AuditLog::query()->first();
        $this->assertSame('Company #'.$company->id, $log->subjectLabel());
    }
}
