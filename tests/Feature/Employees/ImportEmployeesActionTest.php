<?php

namespace Tests\Feature\Employees;

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImportEmployeesActionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'employee_number,last_name,first_name,middle_name,suffix,office,employment_status,biometric_id,date_hired';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Office::factory()->create(['name' => 'Nursing Service']);
    }

    private function upload(string ...$lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('employees.csv', implode("\r\n", $lines)."\r\n");
    }

    public function test_hr_imports_a_csv_from_the_employee_list(): void
    {
        Livewire::test(ListEmployees::class)
            ->callAction('importEmployees', data: [
                'file' => $this->upload(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,0042,'),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Imported: 1 new, 0 updated, 0 unchanged');

        $this->assertDatabaseHas('employees', ['employee_number' => '2019-0042', 'biometric_id' => '0042']);
    }

    public function test_the_uploaded_file_is_not_kept(): void
    {
        Livewire::test(ListEmployees::class)
            ->callAction('importEmployees', data: [
                'file' => $this->upload(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,'),
            ]);

        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_a_file_path_forged_in_the_browser_is_refused(): void
    {
        // The upload's state lives in the browser. A path typed into it must
        // not let anyone read, or delete, some other file on the private disk.
        Storage::disk('local')->put('someone-elses/file.csv', self::HEADER."\r\n");

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->set('mountedActions.0.data.file', ['forged' => 'someone-elses/file.csv'])
            ->callMountedAction()
            ->assertHasActionErrors(['file']);

        $this->assertTrue(Storage::disk('local')->exists('someone-elses/file.csv'));
    }

    public function test_a_rejected_file_imports_nothing_and_says_so(): void
    {
        Livewire::test(ListEmployees::class)
            ->callAction('importEmployees', data: [
                'file' => $this->upload(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Radiology,Permanent,,'),
            ])
            ->assertNotified('Nothing was imported');

        $this->assertSame(0, Employee::count());
    }
}
