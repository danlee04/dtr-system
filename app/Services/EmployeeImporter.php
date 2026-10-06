<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Office;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use SplTempFileObject;

/**
 * Loads HR's employee spreadsheet, all or nothing.
 *
 * Every row is checked before any is saved, and one bad row stops the whole
 * file: a half-imported list is harder to put right than a rejected one,
 * because nobody can tell which half made it in.
 *
 * An employee number already on file updates that employee. A blank optional
 * cell leaves the current value alone, so re-importing an old file cannot
 * erase a biometric ID that HR has set since.
 */
class EmployeeImporter
{
    /** @var list<string> */
    public const COLUMNS = [
        'employee_number',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'office',
        'employment_status',
        'biometric_id',
        'date_hired',
    ];

    /** @var list<string> */
    private const REQUIRED = [
        'employee_number',
        'last_name',
        'first_name',
        'office',
        'employment_status',
    ];

    /** @var list<string> */
    private const OPTIONAL = [
        'middle_name',
        'suffix',
        'biometric_id',
        'date_hired',
    ];

    /** @var array<string, int> */
    private const MAX_LENGTHS = [
        'employee_number' => 30,
        'last_name' => 100,
        'first_name' => 100,
        'middle_name' => 100,
        'suffix' => 10,
        'biometric_id' => 20,
    ];

    /**
     * ISO first, then the month-first forms Excel writes on a Philippine
     * Windows PC.
     *
     * @var list<string>
     */
    private const DATE_FORMATS = ['Y-m-d', 'm/d/Y', 'n/j/Y'];

    public function import(string $path): EmployeeImportResult
    {
        $records = $this->records($path);

        if ($records === []) {
            return EmployeeImportResult::failed(['The file is empty.']);
        }

        $headerLine = array_key_first($records);
        $header = array_map($this->normalizeHeading(...), $records[$headerLine]);
        unset($records[$headerLine]);

        $headerErrors = $this->headerErrors($header);

        if ($headerErrors !== []) {
            return EmployeeImportResult::failed(array_map(
                fn (string $error): string => "Row {$headerLine}: {$error}",
                $headerErrors,
            ));
        }

        $offices = $this->officeIdsByName();
        $rows = [];
        $errors = [];
        $seenNumbers = [];
        $seenBiometricIds = [];

        foreach ($records as $line => $values) {
            $row = $this->combine($header, $values);

            foreach ($this->rowErrors($row, $offices, $seenNumbers, $seenBiometricIds) as $error) {
                $errors[] = "Row {$line}: {$error}";
            }

            $seenNumbers[$row['employee_number']] ??= $line;

            if ($row['biometric_id'] !== '') {
                $seenBiometricIds[$row['biometric_id']] ??= $line;
            }

            $rows[] = $row;
        }

        if ($errors !== []) {
            return EmployeeImportResult::failed($errors);
        }

        if ($rows === []) {
            return EmployeeImportResult::failed(['The file has no employee rows.']);
        }

        return DB::transaction(fn (): EmployeeImportResult => $this->save($rows, $offices));
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, int>  $offices
     */
    private function save(array $rows, array $offices): EmployeeImportResult
    {
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $employee = Employee::firstOrNew(['employee_number' => $row['employee_number']]);
            $employee->fill($this->attributes($row, $offices, $employee->exists));

            if (! $employee->exists) {
                $employee->save();
                $created++;
            } elseif ($employee->isDirty()) {
                $employee->save();
                $updated++;
            }
        }

        return EmployeeImportResult::succeeded($created, $updated, count($rows) - $created - $updated);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $offices
     * @return array<string, mixed>
     */
    private function attributes(array $row, array $offices, bool $exists): array
    {
        $attributes = [
            'employee_number' => $row['employee_number'],
            'last_name' => $row['last_name'],
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'suffix' => $row['suffix'],
            'office_id' => $offices[mb_strtolower($row['office'])],
            'employment_status' => EmploymentStatus::fromLoose($row['employment_status']),
            'biometric_id' => $row['biometric_id'],
            'date_hired' => $row['date_hired'] === ''
                ? ''
                : $this->parseDate($row['date_hired'])?->format('Y-m-d'),
        ];

        foreach (self::OPTIONAL as $column) {
            if ($attributes[$column] !== '') {
                continue;
            }

            if ($exists) {
                unset($attributes[$column]);
            } else {
                $attributes[$column] = null;
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $offices
     * @param  array<string, int>  $seenNumbers  the row each employee number first appeared on
     * @param  array<string, int>  $seenBiometricIds  the row each biometric ID first appeared on
     * @return list<string>
     */
    private function rowErrors(array $row, array $offices, array $seenNumbers, array $seenBiometricIds): array
    {
        $errors = [];

        foreach (self::REQUIRED as $column) {
            if ($row[$column] === '') {
                $errors[] = "{$column} is required.";
            }
        }

        foreach (self::MAX_LENGTHS as $column => $max) {
            if (mb_strlen($row[$column]) > $max) {
                $errors[] = "{$column} is longer than {$max} characters.";
            }
        }

        $number = $row['employee_number'];

        if ($number !== '' && isset($seenNumbers[$number])) {
            $errors[] = "employee_number {$number} already appears on row {$seenNumbers[$number]}.";
        } elseif ($number !== '' && Employee::onlyTrashed()->where('employee_number', $number)->exists()) {
            $errors[] = "employee_number {$number} belongs to a deleted employee. Restore that employee first.";
        }

        if ($row['office'] !== '' && ! isset($offices[mb_strtolower($row['office'])])) {
            $errors[] = "office \"{$row['office']}\" does not exist. Add it under Offices first.";
        }

        if ($row['employment_status'] !== '' && EmploymentStatus::fromLoose($row['employment_status']) === null) {
            $errors[] = "employment_status \"{$row['employment_status']}\" is not one of: Permanent, Co-terminous, Job Order, Contract of Service.";
        }

        $biometricId = $row['biometric_id'];

        if ($biometricId !== '' && preg_match('/^[A-Za-z0-9]+$/', $biometricId) !== 1) {
            $errors[] = 'biometric_id may contain only letters and digits.';
        } elseif ($biometricId !== '' && isset($seenBiometricIds[$biometricId])) {
            $errors[] = "biometric_id {$biometricId} already appears on row {$seenBiometricIds[$biometricId]}.";
        } elseif ($biometricId !== '' && Employee::withTrashed()
            ->where('biometric_id', $biometricId)
            ->where('employee_number', '!=', $number)
            ->exists()) {
            $errors[] = "biometric_id {$biometricId} already belongs to another employee.";
        }

        if ($row['date_hired'] !== '' && $this->parseDate($row['date_hired']) === null) {
            $errors[] = "date_hired \"{$row['date_hired']}\" is not a date. Use YYYY-MM-DD.";
        }

        return $errors;
    }

    /**
     * @param  list<string>  $header
     * @return list<string>
     */
    private function headerErrors(array $header): array
    {
        $named = array_values(array_filter($header, fn (string $heading): bool => $heading !== ''));
        $missing = array_diff(self::COLUMNS, $named);
        $unknown = array_diff($named, self::COLUMNS);
        $errors = [];

        if ($missing !== []) {
            $errors[] = 'missing column(s): '.implode(', ', $missing).'.';
        }

        if ($unknown !== []) {
            $errors[] = 'unknown column(s): '.implode(', ', $unknown).'.';
        }

        if (count($named) !== count(array_unique($named))) {
            $errors[] = 'a column appears more than once.';
        }

        return $errors;
    }

    /**
     * "Employee Number", "employee number" and "EMPLOYEE_NUMBER" all mean
     * employee_number.
     */
    private function normalizeHeading(?string $heading): string
    {
        $snake = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim((string) $heading))) ?? '';

        return trim($snake, '_');
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $values
     * @return array<string, string>
     */
    private function combine(array $header, array $values): array
    {
        $row = array_fill_keys(self::COLUMNS, '');

        foreach ($header as $position => $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = trim((string) ($values[$position] ?? ''));
            }
        }

        return $row;
    }

    /**
     * The file's non-blank lines, keyed by line number.
     *
     * @return array<int, list<string|null>>
     */
    private function records(string $path): array
    {
        $file = new SplTempFileObject;
        $file->fwrite($this->utf8Contents($path));
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV);
        // An empty escape turns off PHP's own backslash escaping, which no
        // spreadsheet writes. PHP 8.4 deprecates leaving it unset.
        $file->setCsvControl(',', '"', '');

        $records = [];

        foreach ($file as $index => $values) {
            if (! is_array($values) || implode('', array_map(fn (?string $value): string => trim((string) $value), $values)) === '') {
                continue;
            }

            $records[$index + 1] = $values;
        }

        return $records;
    }

    private function utf8Contents(string $path): string
    {
        $contents = (string) file_get_contents($path);

        // Excel's "CSV UTF-8" begins with a byte-order mark, which would
        // otherwise stick to the first column name.
        if (str_starts_with($contents, "\u{FEFF}")) {
            $contents = substr($contents, 3);
        }

        // Excel's plain "CSV (Comma delimited)" is Windows-1252. Read as
        // UTF-8, every ñ in Peñaflor or Niño would turn into garbage.
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return $contents;
    }

    /** @return array<string, int> office ID by lower-cased name */
    private function officeIdsByName(): array
    {
        return Office::query()
            ->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [mb_strtolower($name) => $id])
            ->all();
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            // The round trip rejects what PHP would otherwise roll over, such
            // as 2026-02-30 quietly becoming 2 March.
            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
