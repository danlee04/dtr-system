<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_an_active_user_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk();
    }

    public function test_a_deactivated_user_is_refused_even_with_a_live_session(): void
    {
        // Deactivating someone who is already signed in has to take effect on
        // their next click, not whenever their session happens to expire.
        $this->actingAs(User::factory()->inactive()->create())
            ->get('/')
            ->assertForbidden();
    }

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_an_active_user_can_sign_in(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }
}
