<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeLoginsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_hr_creates_logins_for_the_selected_employees(): void
    {
        $employees = Employee::factory()->count(2)->create();

        Livewire::test(ListEmployees::class)
            ->selectTableRecords($employees->modelKeys())
            ->callAction(TestAction::make('createLogins')->table()->bulk())
            ->assertRedirect(route('employee-logins.slips'));

        foreach ($employees as $employee) {
            $user = $employee->fresh()->user;
            $this->assertSame(UserRole::Employee, $user->role);
            $this->assertTrue($user->must_change_password);
            $this->assertNull($user->email);
        }
    }

    public function test_employees_with_an_account_are_skipped(): void
    {
        $withAccount = User::factory()->employee()->create()->employee;
        $jobOrder = Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);

        Livewire::test(ListEmployees::class)
            ->selectTableRecords([$withAccount->id, $jobOrder->id])
            ->callAction(TestAction::make('createLogins')->table()->bulk());

        $this->assertSame(1, User::where('employee_id', $withAccount->id)->count());
        $this->assertNull($jobOrder->fresh()->user);
    }

    public function test_the_slips_show_once(): void
    {
        $employee = Employee::factory()->create(['employee_number' => '2020-0001']);

        Livewire::test(ListEmployees::class)
            ->selectTableRecords([$employee->id])
            ->callAction(TestAction::make('createLogins')->table()->bulk());

        $first = $this->get(route('employee-logins.slips'))->assertOk()->assertSee('2020-0001');
        preg_match('/data-password="([^"]+)"/', $first->getContent(), $match);
        $this->assertTrue(Hash::check($match[1], $employee->fresh()->user->password));

        $this->get(route('employee-logins.slips'))
            ->assertOk()
            ->assertDontSee('2020-0001')
            ->assertSee('Passwords are shown once');
    }

    public function test_hr_resets_a_forgotten_password(): void
    {
        $user = User::factory()->employee()->create(['must_change_password' => false]);
        $old = $user->password;

        Livewire::test(EditEmployee::class, ['record' => $user->employee->getRouteKey()])
            ->callAction('resetLogin')
            ->assertRedirect(route('employee-logins.slips'));

        $this->assertNotSame($old, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_a_staff_account_is_not_reset_from_the_employees_screen(): void
    {
        // An admin's password is changed on the Users screen, by an admin.
        $admin = User::factory()->admin()->create(['employee_id' => Employee::factory()->create()->id]);

        Livewire::test(EditEmployee::class, ['record' => $admin->employee->getRouteKey()])
            ->assertActionHidden('resetLogin');
    }

    public function test_the_slips_page_is_for_staff(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get(route('employee-logins.slips'))
            ->assertForbidden();

        auth()->logout();

        $this->get(route('employee-logins.slips'))->assertRedirect('/login');
    }
}
