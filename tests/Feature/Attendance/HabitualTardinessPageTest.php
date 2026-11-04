<?php

namespace Tests\Feature\Attendance;

use App\Filament\Pages\HabitualTardiness;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabitualTardinessPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 09:00');
        $this->actingAs(User::factory()->create());
    }

    /** Juan, ten times late in August and again in October. */
    private function juan(): Employee
    {
        $juan = Employee::factory()->for(Office::factory())->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => null]);
        ScheduleAssignment::factory()->for($juan)->for(Schedule::factory())->create(['effective_from' => '2026-01-01']);

        foreach (['2026-08', '2026-10'] as $month) {
            $first = CarbonImmutable::parse("{$month}-01");
            $upload = DtrUpload::factory()->for($juan)->create(['period_start' => $first, 'period_end' => $first->endOfMonth()]);
            $count = 10;

            foreach (CarbonPeriod::create($first, $first->endOfMonth()) as $date) {
                if ($date->isWeekday() && $count-- > 0) {
                    $upload->days()->create(['date' => $date->toDateString(), 'am_arrival' => '08:05', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '17:00']);
                }
            }
        }

        return $juan;
    }

    public function test_hr_opens_the_page_on_last_months_half_year(): void
    {
        $this->get(HabitualTardiness::getUrl())->assertOk();

        Livewire::test(HabitualTardiness::class)
            ->assertSet('year', 2026)
            ->assertSet('half', 2);

        // In January, the half year just ended, and its year is in the list.
        $this->travelTo('2027-01-04 09:00');

        Livewire::test(HabitualTardiness::class)
            ->assertSet('year', 2026)
            ->assertSet('half', 2)
            ->assertSeeHtml('<option value="2026">');
    }

    public function test_the_half_year_lists_who_was_late_ten_times_in_a_month_and_who_is_habitual(): void
    {
        $this->juan();

        Livewire::test(HabitualTardiness::class)
            ->assertSeeHtmlInOrder(['Dela Cruz, Juan', '10', '10', 'Habitual', 'Aug and Oct']);
    }

    public function test_a_half_year_with_no_one_ten_times_late_says_so(): void
    {
        $this->juan();

        // As text, the way the select sends it.
        Livewire::test(HabitualTardiness::class)
            ->set('half', '1')
            ->assertSet('half', 1)
            ->assertSee('No one late 10 or more times');
    }

    public function test_the_list_downloads_as_an_excel_sheet(): void
    {
        Livewire::test(HabitualTardiness::class)
            ->callAction('download')
            ->assertFileDownloaded('habitual-tardiness-2026-jul-dec.xlsx');
    }
}
