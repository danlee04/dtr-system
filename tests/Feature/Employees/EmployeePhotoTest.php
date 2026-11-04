<?php

namespace Tests\Feature\Employees;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_hr_adds_a_photo_and_the_page_shows_it_in_place_of_the_initials(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();

        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction('edit', ['photo_path' => UploadedFile::fake()->image('photo.jpg', 600, 600)])
            ->assertHasNoFormErrors();

        $path = $employee->fresh()->photo_path;
        $this->assertStringStartsWith('employee-photos/', $path);
        Storage::disk('local')->assertExists($path);

        $this->get(EmployeeResource::getUrl('view', ['record' => $employee]))
            ->assertOk()
            ->assertSee($path);
    }

    public function test_a_photo_must_be_a_jpeg_png_or_webp(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();

        // An SVG is an image too, and can carry script.
        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
            ->mountAction('edit')
            ->fillForm(['photo_path' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->callMountedAction()
            ->assertHasFormErrors(['photo_path']);

        $this->assertNull($employee->fresh()->photo_path);
    }

    public function test_a_replaced_or_removed_photo_leaves_no_file_behind(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::disk('local')->put('employee-photos/old.jpg', 'old');
        $employee = Employee::factory()->create(['photo_path' => 'employee-photos/old.jpg']);

        // As the browser does it: the old photo leaves the field, then the new one goes in.
        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
            ->mountAction('edit')
            ->fillForm(['photo_path' => []])
            ->fillForm(['photo_path' => UploadedFile::fake()->image('new.jpg')])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        Storage::disk('local')->assertMissing('employee-photos/old.jpg');
        $new = $employee->fresh()->photo_path;
        Storage::disk('local')->assertExists($new);

        $employee->fresh()->update(['photo_path' => null]);

        Storage::disk('local')->assertMissing($new);
    }

    public function test_an_employee_puts_up_their_own_photo_without_touching_the_password(): void
    {
        $user = User::factory()->employee()->create();
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm(['employee' => ['photo_path' => UploadedFile::fake()->image('me.png')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $user->employee->fresh()->photo_path;
        Storage::disk('local')->assertExists($path);
        // Their own photo in the user menu, too.
        $this->assertStringContainsString($path, $user->fresh()->getFilamentAvatarUrl());
    }

    public function test_staff_without_an_employee_have_no_photo_and_still_must_give_a_password(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff);

        Livewire::test(ChangePassword::class)
            ->assertDontSee('Photo')
            ->call('save')
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertNull($staff->getFilamentAvatarUrl());
    }
}
