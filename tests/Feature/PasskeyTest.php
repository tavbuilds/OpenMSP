<?php

namespace Tests\Feature;

use App\Filament\Auth\MultiFactor\PasskeyAuthentication;
use App\Filament\Pages\Passkeys as PasskeysPage;
use App\Models\User;
use App\Support\DemoAccount;
use App\Support\Passkeys;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Models\WebAuthnCredential;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A passkey sits beside the password, not in place of it, and counts as the
 * second factor when 2FA is required. The ceremonies themselves are driven
 * by the browser and covered separately; these pin the rules around them.
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    private function credentialFor(User $user, string $alias = 'Work laptop'): WebAuthnCredential
    {
        $credential = $user->makeWebAuthnCredential([
            'alias' => $alias,
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'public_key' => 'test-key',
            'attestation_format' => 'none',
        ]);

        $credential->forceFill([
            'id' => bin2hex(random_bytes(16)),
            'user_id' => Str::uuid()->toString(),
        ])->save();

        return $credential;
    }

    public function test_a_passkey_counts_as_the_second_factor(): void
    {
        $user = User::factory()->admin()->create();
        $provider = PasskeyAuthentication::make();

        // Without one, 2FA is not satisfied and Filament sends them to set up.
        $this->assertFalse($provider->isEnabled($user));

        $this->credentialFor($user);

        $this->assertTrue($provider->isEnabled($user->fresh()));
    }

    /**
     * The enrolment form has to live on the "set up 2FA" screen too: with
     * 2FA required and no factor yet, that is the only page the operator can
     * reach, so without it a passkey could never be their first factor.
     */
    public function test_the_set_up_screen_can_enrol_a_passkey(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $components = PasskeyAuthentication::make()->getManagementSchemaComponents();

        $this->assertNotEmpty($components);
    }

    public function test_the_password_is_required_to_add_one(): void
    {
        $user = User::factory()->admin()->create(['password' => bcrypt('het-echte-wachtwoord')]);
        $this->actingAs($user);

        // No ceremony payload, so this never reaches the credential — what
        // it pins is that a session alone does not add a key.
        $this->postJson(route('passkeys.register'), ['alias' => 'Laptop'])
            ->assertStatus(422);

        $this->assertSame(0, Passkeys::count($user->fresh()));
    }

    /** A shared account must not keep a key the next visitor cannot remove. */
    public function test_the_demo_account_cannot_hold_a_passkey(): void
    {
        $demo = DemoAccount::ensure();

        $this->assertFalse(Passkeys::canRegister($demo));

        $this->actingAs($demo)
            ->postJson(route('passkeys.register.options'))
            ->assertStatus(403);
    }

    public function test_an_account_cannot_hoard_passkeys(): void
    {
        $user = User::factory()->admin()->create();

        for ($i = 0; $i < Passkeys::LIMIT; $i++) {
            $this->credentialFor($user, "Key {$i}");
        }

        $this->assertFalse(Passkeys::canRegister($user->fresh()));
    }

    public function test_removing_a_passkey_is_recorded(): void
    {
        $user = User::factory()->admin()->create();
        $credential = $this->credentialFor($user);

        $this->assertTrue(Passkeys::forget($user, $credential->getKey()));
        $this->assertSame(0, Passkeys::count($user->fresh()));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'passkey.removed',
        ]);
    }

    /** One operator must not be able to remove another's key. */
    public function test_a_passkey_can_only_be_removed_by_its_owner(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $credential = $this->credentialFor($owner);

        $this->assertFalse(Passkeys::forget($other, $credential->getKey()));
        $this->assertSame(1, Passkeys::count($owner->fresh()));
    }

    public function test_the_management_page_lists_and_removes(): void
    {
        $user = User::factory()->admin()->create();
        $credential = $this->credentialFor($user, 'Telefoon');
        PlatformSettings::markOnboardingComplete();
        $this->actingAs($user);

        Livewire::test(PasskeysPage::class)
            ->assertSee('Telefoon')
            ->callAction('remove', arguments: ['credential' => $credential->getKey()]);

        $this->assertSame(0, Passkeys::count($user->fresh()));
    }

    public function test_the_sign_in_endpoint_refuses_rubbish(): void
    {
        $this->postJson(route('passkeys.login'), ['nonsense' => true])
            ->assertStatus(422);

        $this->assertGuest();
    }

    /** Enrolment is for signed-in operators; the options are not public. */
    public function test_enrolment_options_need_a_session(): void
    {
        $this->postJson(route('passkeys.register.options'))->assertUnauthorized();

        $this->assertGuest();
    }
}
