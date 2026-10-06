<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Office;
use App\Services\EmployeeImporter;
use App\Services\EmployeeImportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeImporterTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'employee_number,last_name,first_name,middle_name,suffix,office,employment_status,biometric_id,date_hired';

    protected function setUp(): void
    {
        parent::setUp();

        Office::factory()->create(['name' => 'Nursing Service']);
    }

    /** Writes the lines the way Excel does, with CRLF endings. */
    private function csv(string ...$lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'employees');
        file_put_contents($path, implode("\r\n", $lines)."\r\n");

        return $path;
    }

    private function import(string ...$lines): EmployeeImportResult
    {
        return app(EmployeeImporter::class)->import($this->csv(...$lines));
    }

    public function test_it_creates_the_employees_in_the_file(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,Santos,,Nursing Service,Permanent,0042,2019-03-01',
            '2020-0007,Reyes,Ana,,,nursing service,Co-terminous,,10/06/2020',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
        $this->assertSame(2, $result->created);

        $juan = Employee::firstWhere('employee_number', '2019-0042');
        $this->assertSame('0042', $juan->biometric_id);
        $this->assertSame('2019-03-01', $juan->date_hired->toDateString());

        $ana = Employee::firstWhere('employee_number', '2020-0007');
        $this->assertSame(EmploymentStatus::Coterminous, $ana->employment_status);
        $this->assertNull($ana->biometric_id);
        $this->assertSame('2020-10-06', $ana->date_hired->toDateString());
    }

    public function test_one_bad_row_stops_the_whole_file(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
            '2020-0007,Reyes,Ana,,,Radiology,Permanent,,',
        );

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(
            ['Row 3: office "Radiology" does not exist. Add it under Offices first.'],
            $result->errors,
        );
        $this->assertSame(0, Employee::count());
    }

    public function test_blank_lines_do_not_shift_the_row_numbers(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
            '',
            '2020-0007,Reyes,Ana,,,Radiology,Permanent,,',
        );

        $this->assertSame(
            ['Row 4: office "Radiology" does not exist. Add it under Offices first.'],
            $result->errors,
        );
    }

    public function test_an_existing_employee_number_updates_that_employee(): void
    {
        $employee = Employee::factory()->create([
            'employee_number' => '2019-0042',
            'office_id' => Office::firstWhere('name', 'Nursing Service')->id,
        ]);

        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz-Reyes,Juan,,,Nursing Service,Permanent,,');

        $this->assertSame([0, 1, 0], [$result->created, $result->updated, $result->unchanged]);
        $this->assertSame('Dela Cruz-Reyes', $employee->fresh()->last_name);
        $this->assertSame(1, Employee::count());
    }

    public function test_a_blank_cell_keeps_a_biometric_id_already_set(): void
    {
        // Re-importing last month's file must not undo the IDs HR mapped since.
        $employee = Employee::factory()->withBiometricId('0042')->create(['employee_number' => '2019-0042']);

        $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,');

        $this->assertSame('0042', $employee->fresh()->biometric_id);
    }

    public function test_the_same_employee_number_twice_is_refused(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
            '2019-0042,Reyes,Ana,,,Nursing Service,Permanent,,',
        );

        $this->assertSame(['Row 3: employee_number 2019-0042 already appears on row 2.'], $result->errors);
    }

    public function test_a_biometric_id_held_by_another_employee_is_refused(): void
    {
        Employee::factory()->withBiometricId('0042')->create(['employee_number' => '2018-0001']);

        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,0042,');

        $this->assertSame(['Row 2: biometric_id 0042 already belongs to another employee.'], $result->errors);
    }

    public function test_a_deleted_employees_number_is_refused(): void
    {
        Employee::factory()->create(['employee_number' => '2019-0042'])->delete();

        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,');

        $this->assertSame(
            ['Row 2: employee_number 2019-0042 belongs to a deleted employee. Restore that employee first.'],
            $result->errors,
        );
    }

    public function test_an_impossible_date_is_refused(): void
    {
        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,2026-02-30');

        $this->assertSame(['Row 2: date_hired "2026-02-30" is not a date. Use YYYY-MM-DD.'], $result->errors);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Casual,,');

        $this->assertSame(
            ['Row 2: employment_status "Casual" is not one of: Permanent, Co-terminous, Job Order, Contract of Service.'],
            $result->errors,
        );
    }

    public function test_columns_may_come_in_any_order_and_any_case(): void
    {
        $result = $this->import(
            'Office,Employee Number,Last Name,First Name,Middle Name,Suffix,Employment Status,Biometric ID,Date Hired',
            'Nursing Service,2019-0042,Dela Cruz,Juan,,,Permanent,,',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
    }

    public function test_trailing_empty_columns_from_excel_are_ignored(): void
    {
        $result = $this->import(
            self::HEADER.',,',
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,,,',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
    }

    public function test_a_missing_column_is_named(): void
    {
        $result = $this->import('employee_number,last_name,first_name', '2019-0042,Dela Cruz,Juan');

        $this->assertSame(
            ['Row 1: missing column(s): middle_name, suffix, office, employment_status, biometric_id, date_hired.'],
            $result->errors,
        );
    }

    public function test_an_excel_byte_order_mark_is_ignored(): void
    {
        $result = $this->import(
            "\u{FEFF}".self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
    }

    public function test_a_windows_1252_file_keeps_its_enye(): void
    {
        // Excel's plain "CSV (Comma delimited)" is not UTF-8.
        $row = mb_convert_encoding('2019-0042,Peñaflor,Niño,,,Nursing Service,Permanent,,', 'Windows-1252', 'UTF-8');

        $this->import(self::HEADER, $row);

        $this->assertSame('Peñaflor', Employee::first()->last_name);
        $this->assertSame('Niño', Employee::first()->first_name);
    }
}
