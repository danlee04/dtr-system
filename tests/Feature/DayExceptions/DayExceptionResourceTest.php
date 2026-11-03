<?php

namespace Tests\Feature\DayExceptions;

use App\Enums\ExceptionType;
use App\Enums\LeaveCoverage;
use App\Filament\Resources\DayExceptions\Pages\ListDayExceptions;
use App\Models\DayException;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class DayExceptionResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $data */
    private function record(array $data): Testable
    {
        return Livewire::test(ListDayExceptions::class)
            ->mountAction('create')
            ->fillForm($data)
            ->callMountedAction();
    }

    public function test_hr_records_an_official_business_from_the_slip(): void
    {
        $hr = User::factory()->create();
        $this->actingAs($hr);
        $employee = Employee::factory()->create();

        $this->record([
            'employee_id' => $employee->id,
            'type' => ExceptionType::OfficialBusiness->value,
            'date_from' => '2026-10-26',
            'date_to' => '2026-10-30',
            'coverage' => LeaveCoverage::WholeDay->value,
            'reference_no' => 'OB-2026-114',
        ])->assertHasNoFormErrors();

        $entry = DayException::sole();
        $this->assertSame(
            [$employee->id, ExceptionType::OfficialBusiness, '2026-10-26', '2026-10-30', LeaveCoverage::WholeDay, 'OB-2026-114', $hr->id],
            [$entry->employee_id, $entry->type, $entry->date_from->toDateString(), $entry->date_to->toDateString(), $entry->coverage, $entry->reference_no, $entry->created_by_user_id],
        );
    }

    public function test_a_half_day_covers_one_date_only(): void
    {
        $this->actingAs(User::factory()->create());

        $this->record([
            'employee_id' => Employee::factory()->create()->id,
            'type' => ExceptionType::OfficialBusiness->value,
            'date_from' => '2026-10-26',
            'date_to' => '2026-10-27',
            'coverage' => LeaveCoverage::FirstHalf->value,
        ])->assertHasFormErrors(['date_to' => 'A morning or an afternoon covers one date only.']);
    }

    public function test_the_last_date_cannot_come_before_the_first(): void
    {
        $this->actingAs(User::factory()->create());

        $this->record([
            'employee_id' => Employee::factory()->create()->id,
            'type' => ExceptionType::TravelOrder->value,
            'date_from' => '2026-10-27',
            'date_to' => '2026-10-26',
            'coverage' => LeaveCoverage::WholeDay->value,
        ])->assertHasFormErrors(['date_to' => 'after_or_equal']);
    }

    public function test_dates_already_covered_for_the_employee_are_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $existing = DayException::factory()->create(['date_from' => '2026-10-26', 'date_to' => '2026-10-27']);

        $this->record([
            'employee_id' => $existing->employee_id,
            'type' => ExceptionType::TravelOrder->value,
            'date_from' => '2026-10-27',
            'date_to' => '2026-10-28',
            'coverage' => LeaveCoverage::WholeDay->value,
        ])->assertHasFormErrors(['date_from' => 'These dates overlap an OB or travel order already recorded for this employee.']);
    }

    public function test_a_morning_and_an_afternoon_can_share_a_date(): void
    {
        $this->actingAs(User::factory()->create());
        $morning = DayException::factory()->create(['date_from' => '2026-10-13', 'date_to' => '2026-10-13', 'coverage' => LeaveCoverage::FirstHalf]);

        $this->record([
            'employee_id' => $morning->employee_id,
            'type' => ExceptionType::OfficialBusiness->value,
            'date_from' => '2026-10-13',
            'date_to' => '2026-10-13',
            'coverage' => LeaveCoverage::SecondHalf->value,
        ])->assertHasNoFormErrors();

        $this->assertSame(2, DayException::query()->count());
    }
}
