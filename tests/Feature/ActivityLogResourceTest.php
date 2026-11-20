<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_who_changed_what(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Ana Admin']));
        $employee = Employee::factory()->create(['last_name' => 'Dela Cruz']);
        $employee->update(['last_name' => 'Dela Cruz-Reyes']);
        $activity = Activity::query()->where('event', 'updated')->latest('id')->firstOrFail();

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords([$activity])
            ->assertSee(['Ana Admin', 'Employee', 'Updated', 'Last name'])
            ->mountAction(TestAction::make('view')->table($activity))
            ->assertMountedActionModalSee(['Last name', 'Dela Cruz', 'Dela Cruz-Reyes']);
    }

    public function test_hr_cannot_open_the_activity_log(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(ActivityLogResource::getUrl())->assertForbidden();
    }

    public function test_a_change_no_one_signed_in_made_shows_system(): void
    {
        // Made with no one signed in, as a console command would.
        Employee::factory()->create(['last_name' => 'Santos']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListActivityLogs::class)->assertSee('System');
    }
}
