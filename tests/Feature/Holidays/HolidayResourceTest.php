<?php

namespace Tests\Feature\Holidays;

use App\Enums\HolidayType;
use App\Filament\Resources\Holidays\Pages\ListHolidays;
use App\Models\Holiday;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class HolidayResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_hr_adds_a_regular_holiday(): void
    {
        Livewire::test(ListHolidays::class)->mountAction('create')
            ->fillForm([
                'date' => '2026-12-25',
                'name' => 'Christmas Day',
                'type' => HolidayType::Regular->value,
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $holiday = Holiday::sole();

        $this->assertSame('2026-12-25', $holiday->date->toDateString());
        $this->assertSame(HolidayType::Regular, $holiday->type);
        $this->assertNull($holiday->suspension_starts_at);
    }

    public function test_a_suspension_keeps_the_time_it_starts(): void
    {
        Livewire::test(ListHolidays::class)->mountAction('create')
            ->fillForm([
                'date' => '2026-11-03',
                'name' => 'Typhoon',
                'type' => HolidayType::Suspension->value,
                'suspension_starts_at' => '15:00',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame('15:00:00', Holiday::sole()->suspension_starts_at);
    }

    public function test_a_holiday_does_not_keep_a_suspension_time(): void
    {
        // The time field hides once the type changes, and a hidden field is not
        // saved, so the model has to clear the old time itself.
        $holiday = Holiday::factory()->create([
            'type' => HolidayType::Suspension,
            'suspension_starts_at' => '15:00:00',
        ]);

        Livewire::test(ListHolidays::class)->mountAction(TestAction::make('edit')->table($holiday))
            ->fillForm(['type' => HolidayType::Special->value])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertNull($holiday->fresh()->suspension_starts_at);
    }

    public function test_a_date_holds_one_entry(): void
    {
        Holiday::factory()->create(['date' => '2026-12-25']);

        Livewire::test(ListHolidays::class)->mountAction('create')
            ->fillForm([
                'date' => '2026-12-25',
                'name' => 'Christmas Day',
                'type' => HolidayType::Regular->value,
            ])
            ->callMountedAction()
            ->assertHasFormErrors(['date']);
    }

    public function test_an_entry_keeps_its_own_date_when_edited(): void
    {
        $holiday = Holiday::factory()->create(['date' => '2026-12-25', 'name' => 'Christmas']);

        Livewire::test(ListHolidays::class)->mountAction(TestAction::make('edit')->table($holiday))
            ->fillForm(['name' => 'Christmas Day'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame('Christmas Day', $holiday->fresh()->name);
    }

    public function test_adding_a_holiday_is_recorded(): void
    {
        $holiday = Holiday::factory()->create(['name' => 'Christmas Day']);

        $activity = Activity::query()
            ->where('subject_type', $holiday->getMorphClass())
            ->where('event', 'created')
            ->sole();

        $this->assertSame('Christmas Day', $activity->attribute_changes['attributes']['name']);
    }
}
