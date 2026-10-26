<?php

namespace Tests\Feature\Employee;

use App\Filament\Pages\Auth\ChangePassword;
use App\Models\Employee;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()
            ->employee(Employee::factory()->create())
            ->create(['email' => 'juan@dtrc.test', 'password' => 'secret-pass-1']);
    }

    public function test_an_employee_signs_in_with_email_and_password(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'juan@dtrc.test', 'password' => 'secret-pass-1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_deactivated_employee_cannot_sign_in(): void
    {
        $this->user->employee->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'juan@dtrc.test', 'password' => 'secret-pass-1'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_an_employee_account_stops_with_the_employee(): void
    {
        $this->actingAs($this->user)->get('/')->assertOk();

        $this->user->employee->update(['is_active' => false]);
        $this->get('/')->assertForbidden();

        $this->user->employee->update(['is_active' => true]);
        $this->user->employee->delete();
        $this->get('/')->assertForbidden();
    }

    public function test_anyone_changes_their_own_password(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'password' => 'a-new-pass-123',
                'passwordConfirmation' => 'a-new-pass-123',
                'currentPassword' => 'secret-pass-1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('a-new-pass-123', $this->user->fresh()->password));
    }
}
