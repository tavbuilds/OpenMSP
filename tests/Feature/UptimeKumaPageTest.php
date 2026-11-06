<?php

namespace Tests\Feature;

use App\Filament\Pages\UptimeKuma;
use App\Filament\Resources\Endpoints\Pages\ListEndpoints;
use App\Filament\Resources\Endpoints\Tables\EndpointsTable;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformSettings;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UptimeKumaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_page_and_save_the_connection(): void
    {
        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/uptime-kuma')
            ->assertOk()
            ->assertSee('The API key is for certificate days', false)
            ->assertSee('Kuma login', false);

        Livewire::test(UptimeKuma::class)
            ->set('data.base_url', 'https://status.example.com/metrics')
            ->set('data.api_key', 'uk2_secret')
            ->set('data.username', 'ops')
            ->set('data.password', 's3cret')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://status.example.com', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_BASE_URL));
        $this->assertSame('uk2_secret', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY));
        $this->assertSame('ops', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME));
        $this->assertSame('s3cret', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD));

        $row = Setting::query()->find(PlatformSettings::UPTIME_KUMA_PASSWORD);
        $this->assertNotNull($row);
        $this->assertTrue($row->encrypted);
        $this->assertStringNotContainsString('s3cret', (string) $row->value);
    }

    public function test_an_empty_password_keeps_the_stored_one(): void
    {
        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_API_KEY, 'keep-me');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_USERNAME, 'kuma');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_PASSWORD, 'secret-pass');

        Livewire::test(UptimeKuma::class)
            ->set('data.username', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://kuma.test', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_BASE_URL));
        $this->assertSame('keep-me', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_API_KEY));
        $this->assertNull(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_USERNAME));
        $this->assertSame('secret-pass', PlatformSettings::get(PlatformSettings::UPTIME_KUMA_PASSWORD));
    }

    public function test_a_bad_address_is_rejected(): void
    {
        PlatformSettings::markOnboardingComplete();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UptimeKuma::class)
            ->set('data.base_url', 'https://user:secret@kuma.test')
            ->call('save')
            ->assertHasErrors(['data.base_url']);

        $this->assertNull(PlatformSettings::get(PlatformSettings::UPTIME_KUMA_BASE_URL));
    }

    public function test_failed_domains_can_be_submitted_again_from_the_catalog(): void
    {
        PlatformSettings::markOnboardingComplete();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_BASE_URL, 'https://kuma.test');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_USERNAME, 'kuma');
        PlatformSettings::set(PlatformSettings::UPTIME_KUMA_PASSWORD, 'kuma-secret');

        $this->get('/admin/uptime-kuma')
            ->assertOk()
            ->assertSee('Submit failed domains again');

        Livewire::test(UptimeKuma::class)
            ->callAction('retryFailed');

        Notification::assertNotified('No domains are waiting for another try.');

        $this->assertTrue($this->retryActionVisibleFor($admin));
        $this->assertFalse($this->retryActionVisibleFor(User::factory()->sales()->create()));
        $this->assertTrue($this->selectionActionVisibleFor($admin));
        $this->assertFalse($this->selectionActionVisibleFor(User::factory()->sales()->create()));
    }

    private function retryActionVisibleFor(User $user): bool
    {
        $this->actingAs($user);

        $page = new class extends ListEndpoints
        {
            public function retryVisible(): bool
            {
                foreach ($this->getHeaderActions() as $action) {
                    if ($action->getName() === 'retryFailed') {
                        return $action->isVisible();
                    }
                }

                return false;
            }
        };

        return $page->retryVisible();
    }

    private function selectionActionVisibleFor(User $user): bool
    {
        $this->actingAs($user);

        $action = collect(EndpointsTable::configure(Table::make(new ListEndpoints))->getToolbarActions())
            ->first(fn ($action) => $action->getName() === 'pushKuma');

        return $action !== null && $action->isVisible();
    }

    public function test_sales_cannot_open_the_page(): void
    {
        PlatformSettings::markOnboardingComplete();

        $this->actingAs(User::factory()->sales()->create(), 'web')
            ->get('/admin/uptime-kuma')
            ->assertForbidden();
    }
}
