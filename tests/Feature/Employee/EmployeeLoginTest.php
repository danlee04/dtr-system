<?php

namespace Tests\Feature\Employee;

use App\Filament\Employee\Pages\Auth\ChangePassword;
use App\Filament\Employee\Pages\Auth\Login;
use App\Models\Employee;
use App\Models\User;
use Filament\Facades\Filament;
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

        Filament::setCurrentPanel(Filament::getPanel('employee'));

        $this->user = User::factory()
            ->employee(Employee::factory()->create(['employee_number' => '2020-0001']))
            ->create(['password' => 'secret-pass-1']);
    }

    public function test_an_employee_signs_in_with_their_employee_number(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['employee_number' => ' 2020-0001 ', 'password' => 'secret-pass-1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_number_or_password_is_refused_on_the_number_field(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['employee_number' => '2020-0001', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['employee_number']);

        Livewire::test(Login::class)
            ->fillForm(['employee_number' => '9999-9999', 'password' => 'secret-pass-1'])
            ->call('authenticate')
            ->assertHasFormErrors(['employee_number']);

        $this->assertGuest();
    }

    public function test_a_deactivated_employee_cannot_sign_in(): void
    {
        $this->user->employee->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->fillForm(['employee_number' => '2020-0001', 'password' => 'secret-pass-1'])
            ->call('authenticate')
            ->assertHasFormErrors(['employee_number']);

        $this->assertGuest();
    }

    public function test_a_temporary_password_must_be_changed_first(): void
    {
        $this->user->update(['must_change_password' => true]);

        $this->actingAs($this->user)->get('/employee')->assertRedirect(Filament::getPanel('employee')->getProfileUrl());
        $this->get(Filament::getPanel('employee')->getProfileUrl())->assertOk();
    }

    public function test_only_linked_accounts_reach_the_employee_panel(): void
    {
        $this->actingAs($this->user)->get('/employee')->assertOk();
        $this->actingAs(User::factory()->create())->get('/employee')->assertForbidden();
    }

    public function test_an_employee_account_stops_with_the_employee(): void
    {
        $this->actingAs($this->user)->get('/employee')->assertOk();

        $this->user->employee->update(['is_active' => false]);
        $this->get('/employee')->assertForbidden();

        $this->user->employee->update(['is_active' => true]);
        $this->user->employee->delete();
        $this->get('/employee')->assertForbidden();
    }

    public function test_changing_the_password_clears_the_flag(): void
    {
        $this->user->update(['must_change_password' => true]);
        $this->actingAs($this->user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'password' => 'a-new-pass-123',
                'passwordConfirmation' => 'a-new-pass-123',
                'currentPassword' => 'secret-pass-1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('a-new-pass-123', $this->user->fresh()->password));
    }
}
