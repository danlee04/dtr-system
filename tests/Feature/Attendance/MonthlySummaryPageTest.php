<?php

namespace Tests\Feature\Attendance;

use App\Filament\Pages\MonthlySummary;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlySummaryPageTest extends TestCase
{
    use RefreshDatabase;

    private Office $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-03 09:00');
        $this->actingAs(User::factory()->create());
        $this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
    }

    public function test_hr_opens_the_summary_on_last_month(): void
    {
        $this->get(MonthlySummary::getUrl())->assertOk();

        Livewire::test(MonthlySummary::class)->assertSet('month', '2026-10');
    }

    public function test_every_office_is_listed_until_one_is_chosen(): void
    {
        $juan = Employee::factory()->for($this->nursing)->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => null]);
        ScheduleAssignment::factory()->for($juan)->for(Schedule::factory())->create(['effective_from' => '2026-10-01']);
        DtrUpload::factory()->for($juan)->create(['period_start' => '2026-10-01', 'period_end' => '2026-10-01'])
            ->days()->create(['date' => '2026-10-01', 'am_arrival' => '08:14', 'am_departure' => '12:00', 'pm_arrival' => '13:00', 'pm_departure' => '16:50']);
        Employee::factory()->for(Office::factory()->create(['name' => 'Pharmacy']))
            ->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => null]);

        // 14 minutes late and 10 under: 24 minutes, 0.050 of a day.
        Livewire::test(MonthlySummary::class)
            ->assertSeeHtmlInOrder(['Dela Cruz, Juan', 'Nursing Service', '0.050', 'Santos, Maria', 'Pharmacy', 'No DTR uploaded'])
            ->set('officeId', $this->nursing->id)
            ->assertSee('Dela Cruz, Juan')
            ->assertDontSee('Santos, Maria');
    }

    public function test_the_list_comes_a_page_at_a_time_but_counts_everyone(): void
    {
        $this->elevenInNursing();

        Livewire::test(MonthlySummary::class)
            ->set('perPage', 10)
            ->assertSee(['Ason, Pat', 'Json, Pat', '11 employees who earn leave credits'])
            ->assertDontSee('Kson, Pat')
            ->call('nextPage')
            ->assertSee('Kson, Pat')
            ->assertDontSee('Ason, Pat');
    }

    public function test_a_new_filter_starts_again_on_the_first_page(): void
    {
        $this->elevenInNursing();

        Livewire::test(MonthlySummary::class)
            ->set('perPage', 10)
            ->call('nextPage')
            ->set('officeId', $this->nursing->id)
            ->assertSee('Ason, Pat');
    }

    /** Ason to Kson, one more than a page of ten. */
    private function elevenInNursing(): void
    {
        foreach (range('A', 'K') as $letter) {
            Employee::factory()->for($this->nursing)->create(['last_name' => "{$letter}son", 'first_name' => 'Pat', 'middle_name' => null]);
        }
    }

    public function test_the_download_covers_every_office_unless_one_is_chosen(): void
    {
        Livewire::test(MonthlySummary::class)
            ->callAction('download')
            ->assertFileDownloaded('monthly-summary-2026-10.xlsx')
            ->set('officeId', $this->nursing->id)
            ->callAction('download')
            ->assertFileDownloaded('monthly-summary-2026-10-nursing-service.xlsx');
    }

    public function test_nothing_downloads_without_a_month(): void
    {
        Livewire::test(MonthlySummary::class)
            ->set('month', '')
            ->assertActionHidden('download');
    }
}
