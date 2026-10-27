<?php

namespace Tests\Feature\Schedules;

use App\Filament\Resources\Schedules\Pages\ListSchedules;
use App\Models\RosterEntry;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ScheduleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function regular(array $overrides = []): array
    {
        return array_merge([
            'code' => 'REG',
            'name' => 'Regular',
            'work_days' => [1, 2, 3, 4, 5],
            'block1_in' => '08:00',
            'block1_out' => '12:00',
            'block2_in' => '13:00',
            'block2_out' => '17:00',
        ], $overrides);
    }

    public function test_hr_adds_a_two_block_schedule(): void
    {
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular(['code' => 'reg']))
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $schedule = Schedule::sole();

        // Codes are typed into the duty roster, so they are kept in capitals.
        $this->assertSame('REG', $schedule->code);
        $this->assertSame([1, 2, 3, 4, 5], $schedule->work_days);
        $this->assertSame(['08:00', '12:00', '13:00', '17:00'], $schedule->times());
    }

    public function test_hr_sets_the_days_charged_for_a_day_of_leave(): void
    {
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular(['code' => 'CWW', 'work_days' => [1, 2, 3, 4], 'block2_out' => '19:00', 'leave_days' => '1.25']))
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame('1.250', Schedule::sole()->leave_days);
    }

    public function test_days_charged_must_be_a_positive_number(): void
    {
        foreach (['0', 'abc', '1.2505'] as $typed) {
            Livewire::test(ListSchedules::class)->mountAction('create')
                ->fillForm($this->regular(['leave_days' => $typed]))
                ->callMountedAction()
                ->assertHasFormErrors(['leave_days']);
        }

        $this->assertSame(0, Schedule::count());
    }

    public function test_a_night_shift_runs_past_midnight(): void
    {
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular([
                'code' => 'N',
                'name' => 'Night',
                'work_days' => [],
                'block1_in' => '22:00',
                'block1_out' => '02:00',
                'block2_in' => '03:00',
                'block2_out' => '07:00',
            ]))
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame('22:00–02:00, 03:00–07:00', Schedule::sole()->hours);
    }

    public function test_times_that_run_past_24_hours_are_refused(): void
    {
        // 07:00 typed for 13:00: read as the next morning, the day would end at
        // 17:00 the day after it began.
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular(['block2_in' => '07:00']))
            ->callMountedAction()
            ->assertHasFormErrors(['block1_in']);
    }

    public function test_a_one_block_schedule_needs_no_second_block(): void
    {
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular([
                'code' => 'AM',
                'block1_in' => '06:00',
                'block1_out' => '14:00',
                'block2_in' => null,
                'block2_out' => null,
            ]))
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(['06:00', '14:00'], Schedule::sole()->times());
    }

    public function test_a_second_block_needs_both_times(): void
    {
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular(['block2_out' => null]))
            ->callMountedAction()
            ->assertHasFormErrors(['block2_out' => 'required_with']);
    }

    public function test_off_is_kept_for_the_roster(): void
    {
        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular(['code' => 'off']))
            ->callMountedAction()
            ->assertHasFormErrors(['code' => 'not_in']);
    }

    public function test_two_schedules_cannot_share_a_code(): void
    {
        Schedule::factory()->create(['code' => 'REG']);

        Livewire::test(ListSchedules::class)->mountAction('create')
            ->fillForm($this->regular(['code' => 'reg']))
            ->callMountedAction()
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_an_unused_schedule_can_be_deleted(): void
    {
        $schedule = Schedule::factory()->create();

        Livewire::test(ListSchedules::class)->callAction(TestAction::make('delete')->table($schedule));

        $this->assertModelMissing($schedule);
    }

    public function test_a_schedule_in_use_cannot_be_deleted(): void
    {
        $schedule = Schedule::factory()->create();
        ScheduleAssignment::factory()->for($schedule)->create();

        Livewire::test(ListSchedules::class)->assertActionHidden(TestAction::make('delete')->table($schedule));
    }

    public function test_a_schedule_on_the_roster_cannot_be_deleted(): void
    {
        $schedule = Schedule::factory()->rosterOnly()->create();
        RosterEntry::factory()->for($schedule)->create();

        Livewire::test(ListSchedules::class)->assertActionHidden(TestAction::make('delete')->table($schedule));
    }

    public function test_changing_a_schedules_hours_is_recorded(): void
    {
        $schedule = Schedule::factory()->create();

        Livewire::test(ListSchedules::class)->mountAction(TestAction::make('edit')->table($schedule))
            ->fillForm(['block1_in' => '07:00'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $activity = Activity::query()
            ->where('subject_type', $schedule->getMorphClass())
            ->where('event', 'updated')
            ->sole();

        // Only the changed time: the others came back exactly as they were saved.
        $this->assertSame(['block1_in' => '07:00:00'], $activity->attribute_changes['attributes']);
    }
}
