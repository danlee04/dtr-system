<?php

namespace Tests\Feature\Offices;

use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Models\Office;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OfficeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_hr_sees_the_offices(): void
    {
        $offices = Office::factory()->count(3)->create();

        Livewire::test(ListOffices::class)
            ->assertCanSeeTableRecords($offices);
    }

    public function test_hr_adds_an_office_with_the_head_who_signs_form_48(): void
    {
        Livewire::test(CreateOffice::class)
            ->fillForm([
                'name' => 'Nursing Service',
                'head_name' => 'Maria L. Santos',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offices', [
            'name' => 'Nursing Service',
            'head_name' => 'Maria L. Santos',
            'is_active' => true,
        ]);
    }

    public function test_two_offices_cannot_share_a_name(): void
    {
        Office::factory()->create(['name' => 'Nursing Service']);

        Livewire::test(CreateOffice::class)
            ->fillForm(['name' => 'Nursing Service'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_an_office_keeps_its_own_name_when_edited(): void
    {
        // unique() has to ignore the record being edited, or every save of an
        // existing office would fail.
        $office = Office::factory()->create(['name' => 'Nursing Service']);

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->fillForm(['head_name' => 'Jose P. Rizal'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Jose P. Rizal', $office->fresh()->head_name);
    }

    public function test_an_office_nobody_belongs_to_can_be_deleted(): void
    {
        $office = Office::factory()->create();

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($office);
    }

    public function test_renaming_an_office_is_recorded(): void
    {
        $office = Office::factory()->create(['name' => 'Nursing']);
        $office->update(['name' => 'Nursing Service']);

        $activity = Activity::query()
            ->where('subject_type', $office->getMorphClass())
            ->where('event', 'updated')
            ->first();

        $this->assertSame('Nursing', $activity->attribute_changes['old']['name']);
    }
}
