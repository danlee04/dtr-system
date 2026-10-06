<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmployeeResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create();
        $this->actingAs($this->hr);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'employee_number' => '2019-0042',
            'office_id' => Office::factory()->create()->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'employment_status' => EmploymentStatus::Permanent->value,
            'biometric_id' => '0042',
            'date_hired' => '2019-03-01',
        ], $overrides);
    }

    public function test_hr_sees_the_employees(): void
    {
        $employees = Employee::factory()->count(3)->create();

        Livewire::test(ListEmployees::class)
            ->assertCanSeeTableRecords($employees);
    }

    public function test_hr_adds_an_employee(): void
    {
        Livewire::test(CreateEmployee::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $employee = Employee::firstWhere('employee_number', '2019-0042');

        // Kept as text: to the device, "0042" and "42" are different people.
        $this->assertSame('0042', $employee->biometric_id);
        $this->assertSame('Dela Cruz, Juan S.', $employee->full_name);
        $this->assertSame('2019-03-01', $employee->date_hired->toDateString());
    }

    public function test_a_biometric_id_belongs_to_one_employee_only(): void
    {
        Employee::factory()->withBiometricId('0042')->create();

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasFormErrors(['biometric_id' => 'unique']);
    }

    public function test_a_deleted_employee_still_holds_their_biometric_id(): void
    {
        // Their punches still point at them. The ID has to be cleared on
        // purpose before anyone else may use it.
        Employee::factory()->withBiometricId('0042')->create()->delete();

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasFormErrors(['biometric_id' => 'unique']);
    }

    public function test_a_separation_date_cannot_come_before_the_hire_date(): void
    {
        $employee = Employee::factory()->create(['date_hired' => '2020-01-15']);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['date_separated' => '2019-12-31'])
            ->call('save')
            ->assertHasFormErrors(['date_separated']);
    }

    public function test_a_separation_date_is_accepted_when_the_hire_date_is_unknown(): void
    {
        $employee = Employee::factory()->create(['date_hired' => null]);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['date_separated' => '2026-09-30'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-09-30', $employee->fresh()->date_separated->toDateString());
    }

    public function test_deleting_an_employee_is_soft_and_can_be_undone(): void
    {
        $employee = Employee::factory()->create();

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($employee);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted($employee);
    }

    public function test_an_office_with_employees_cannot_be_deleted(): void
    {
        // Deleted employees count too: their rows still point at the office.
        $office = Office::factory()->create();
        Employee::factory()->for($office)->create()->delete();

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_editing_an_employee_records_who_changed_what(): void
    {
        $employee = Employee::factory()->create(['last_name' => 'Dela Cruz']);
        $employee->update(['last_name' => 'Dela Cruz-Reyes']);

        $activity = Activity::query()
            ->where('subject_type', $employee->getMorphClass())
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertSame($this->hr->id, $activity->causer_id);
        $this->assertSame('Dela Cruz', $activity->attribute_changes['old']['last_name']);
        $this->assertSame('Dela Cruz-Reyes', $activity->attribute_changes['attributes']['last_name']);
    }
}
