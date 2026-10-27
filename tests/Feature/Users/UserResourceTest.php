<?php

namespace Tests\Feature\Users;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_cannot_open_the_accounts_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(UserResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_an_admin_creates_an_hr_account(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListUsers::class)->mountAction('create')
            ->fillForm([
                'name' => 'Ana Reyes',
                'email' => 'ana@dtrc.test',
                'role' => UserRole::Hr->value,
                'is_active' => true,
                'password' => 'a-long-password',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'ana@dtrc.test');
        $this->assertSame(UserRole::Hr, $user->role);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
    }

    public function test_a_blank_password_on_edit_keeps_the_old_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $before = $user->password;

        Livewire::test(ListUsers::class)->mountAction(TestAction::make('edit')->table($user))
            ->fillForm(['name' => 'Renamed', 'password' => ''])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_the_users_screen_lists_every_account(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $hr = User::factory()->create();
        $employeeAccount = User::factory()->employee()->create();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$hr, $employeeAccount]);
    }

    public function test_an_admin_creates_an_employee_account(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $employee = Employee::factory()->create();

        Livewire::test(ListUsers::class)->mountAction('create')
            ->fillForm([
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@dtrc.test',
                'role' => UserRole::Employee->value,
                'employee_id' => $employee->id,
                'is_active' => true,
                'password' => 'a-long-password',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'juan@dtrc.test');
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertSame($employee->id, $user->employee_id);
    }

    public function test_an_employee_account_needs_its_employee(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListUsers::class)->mountAction('create')
            ->fillForm([
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@dtrc.test',
                'role' => UserRole::Employee->value,
                'is_active' => true,
                'password' => 'a-long-password',
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['employee_id' => 'required']);
    }

    public function test_only_employees_who_earn_leave_are_linked(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $jobOrder = Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);

        Livewire::test(ListUsers::class)->mountAction('create')
            ->fillForm([
                'name' => 'Pedro Santos',
                'email' => 'pedro@dtrc.test',
                'role' => UserRole::Employee->value,
                'employee_id' => $jobOrder->id,
                'is_active' => true,
                'password' => 'a-long-password',
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['employee_id']);
    }

    public function test_an_admin_links_an_account_to_an_employee(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $hr = User::factory()->create();
        $employee = Employee::factory()->create();

        Livewire::test(ListUsers::class)->mountAction(TestAction::make('edit')->table($hr))
            ->fillForm(['employee_id' => $employee->id])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame($employee->id, $hr->fresh()->employee_id);
    }

    public function test_a_password_reset_is_logged_without_the_password(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $user = User::factory()->employee()->create();

        Livewire::test(ListUsers::class)->mountAction(TestAction::make('edit')->table($user))
            ->fillForm(['password' => 'a-new-long-password'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $entry = Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->where('event', 'password_changed')
            ->sole();

        $this->assertSame($admin->id, $entry->causer_id);
        $this->assertStringNotContainsString('a-new-long-password', json_encode($entry->toArray()));
    }

    public function test_an_admin_cannot_demote_or_deactivate_themselves(): void
    {
        // The last admin doing so would leave nobody able to undo it.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)->mountAction(TestAction::make('edit')->table($admin))
            ->assertFormFieldDisabled('role')
            ->assertFormFieldDisabled('is_active');
    }
}
