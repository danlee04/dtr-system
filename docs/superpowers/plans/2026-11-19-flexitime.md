# Flexitime Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An employee HR checks as flexitime, on an 8–5 day, is late only after 9:00 and owes a time out 9 hours after a time in clamped to 7:00–9:00.

**Architecture:** A boolean on `employees`. `DtrCalculator::day()` gives the morning half a later late cutoff and the afternoon half a different time out due, leaving the halves' own times alone. A checkbox on the employee form, and "Flexi" on the DTR's schedule line.

**Tech Stack:** Laravel 13 / PHP 8.4, Filament 5.9, PHPUnit, SQLite in tests.

**Spec:** `docs/superpowers/specs/2026-11-19-flexitime-design.md`

## Global Constraints

- Claude never runs `git commit`; the user gets one commit message at the end.
- TDD: write the test first and watch it fail. Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- `php artisan make:migration ... --no-interaction` for the migration.
- Times are minutes of the day in the calculator: 7:00 = 420, 8:00 = 480, 9:00 = 540, 12:00 = 720, 1:00 PM = 780, 5:00 PM = 1020, 6:00 PM = 1080.
- Every screen must still fit 768 px; update `docs/design.md` with the look change.
- Update `C:\Users\Administrator\.claude\projects\c--laragon-www-dtr-system\memory\flexitime-deferred.md`: flexitime now exists.

## Review Focus

1. **A suspension that starts before the flexi time out due.** The time out due must not pass the suspension's start. Test in Task 1: `test_a_suspension_cuts_the_flexi_time_out_due`.
2. **The morning on an OB or leave while the afternoon is tapped.** There is no time in, so the time out due is 5:00. Test in Task 1: `test_flexi_with_no_time_in_owes_five_oclock`. It covers the blank case; the covered morning reads the same `$taps[0][0]`.
3. **Flexi checked on an employee whose day uses another schedule** (four-day week, roster). The usual rules apply. Test in Task 1.

---

### Task 1: The flexitime rule in the calculator

**Files:**
- Create: a migration, `add_flexitime_to_employees`: `$table->boolean('flexitime')->default(false)->after('is_active')`.
- Modify: `app/Models/Employee.php`. Add `'flexitime'` to `#[Fillable]`, cast it `'boolean'`, and add `'flexitime' => false` to `$attributes` beside `is_active`.
- Modify: `app/Services/Attendance/DtrCalculator.php`, `day()` and the class docblock.
- Test: `tests/Feature/Attendance/DtrCalculatorTest.php`.

**Interfaces:**
- Produces: `Employee::$flexitime` (bool). Also `DtrCalculator::isFlexiDay(Employee $employee, int $start, array $offsets): bool`, public static so Task 2 can say "Flexi" on the same condition: `$employee->flexitime && $start === 480 && $offsets === [0, 240, 300, 540]`.

- [ ] **Step 1: Write the failing tests**

Each uses `$this->regular(['flexitime' => true])` unless it says otherwise. `figures()` returns `[status, late, lateCount, undertime, undertimeCount]`.

- `test_flexi_counts_eight_hours_from_a_time_in_between_seven_and_nine`, on these dates (all with lunch 12:00 / 13:00):

| Date | In | Out | Expected |
|---|---|---|---|
| 2026-10-05 | 06:30 | 15:30 | `[Present, 0, 0, 30, 1]` |
| 2026-10-06 | 07:15 | 16:15 | `[Present, 0, 0, 0, 0]` |
| 2026-10-07 | 08:30 | 17:30 | `[Present, 0, 0, 0, 0]` |
| 2026-10-08 | 09:10 | 18:10 | `[Present, 10, 1, 0, 0]` |

- `test_flexi_keeps_the_break_at_twelve_to_one`: 08:00, 12:00, 13:10, 17:00 gives `[Present, 10, 1, 0, 0]`.
- `test_flexi_with_no_time_in_owes_five_oclock`: am_arrival null, 12:00, 13:00, 16:30 gives undertime 30, and `missing` contains `'time in'`.
- `test_flexi_still_counts_an_untapped_afternoon_as_four_hours`: 07:00, 12:00, and no afternoon taps, gives `[Present, 0, 0, 240, 1]`.
- `test_a_suspension_cuts_the_flexi_time_out_due`: a `Holiday` of type `HolidayType::Suspension` on 2026-10-14 with `suspension_starts_at` 15:00 (follow the existing suspension test's factory use). Taps 09:00, 12:00, 13:00, 15:00 give undertime 0. The due out would be 18:00, but the suspension caps it at 15:00.
- `test_flexi_does_not_apply_unchecked_or_on_another_schedule`:
  - `regular()` (not flexi) with 08:30 in is late 30.
  - A flexi employee on `Schedule::factory()->create(['block2_out' => '19:00'])` with 08:30 in is late 30.

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact tests/Feature/Attendance/DtrCalculatorTest.php`
Expected: the new tests fail. The column `flexitime` does not exist.

- [ ] **Step 3: Implement**

- Migration, model, then in `day()`, after `$halves` and `$taps` are built and before coverage, when `self::isFlexiDay($employee, $start, $offsets)`:
  - `$halves[0]['lateAfter'] = 540`
  - `$halves[1]['dueOut'] = min(1080, max(420, min(540, $arrivedMinute ?? 480)) + 540)`, where `$arrivedMinute` is `self::minute($taps[0][0])` or null.
- After `cutBySuspension`, cap it: `$halves[1]['dueOut'] = min($halves[1]['dueOut'], $suspendedAt)` when both are set.
- In the loop, the late check uses `$half['lateAfter'] ?? $half['in']`, and the undertime check uses `$half['dueOut'] ?? $half['out']`. The untapped-half undertime and the absent minutes keep `out - in`.
- `day()` needs the employee. Pass it in from `compute()`, which has `$employee`.
- Add a docblock line for the rule (HR, 2026-11-19).

- [ ] **Step 4: Run the calculator tests, then the whole suite**

Run: `php artisan test --compact tests/Feature/Attendance/DtrCalculatorTest.php`, then `php artisan test --compact`
Expected: PASS.

---

### Task 2: The checkbox and "Flexi" on the DTR

**Files:**
- Modify: `app/Filament/Resources/Employees/Schemas/EmployeeForm.php`, the Attendance section, after `is_active`:

```php
Toggle::make('flexitime')
    ->label('Flexitime')
    ->helperText('8 AM – 5 PM only: in from 7:00 to 9:00, out 9 hours later.')
    ->inline(false),
```

- Modify: `app/Filament/Resources/DtrUploads/Schemas/DtrUploadInfolist.php`, `schedule()`. In the one-schedule case, append `' · Flexi'` when the employee is flexitime and the schedule's start and offsets pass `DtrCalculator::isFlexiDay`.
- Modify: `docs/design.md`, the DTR record's schedule line.
- Test: `tests/Feature/Employees/EmployeeResourceTest.php` and `tests/Feature/DtrUploads/DtrUploadResourceTest.php`.

**Interfaces:**
- Consumes: `Employee::$flexitime` and `DtrCalculator::isFlexiDay()` from Task 1.

- [ ] **Step 1: Write the failing tests**

- `EmployeeResourceTest::test_hr_marks_an_employee_flexitime`: `callAction('edit', ['flexitime' => true])` on `ViewEmployee`, then `assertTrue($employee->fresh()->flexitime)`.
- `DtrUploadResourceTest::test_a_flexitime_employees_dtr_says_flexi`: `computableUpload()` with its employee updated to `flexitime => true`; the view modal shows `'· Flexi'`.

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact --filter='flexitime|flexi' tests/Feature/Employees/EmployeeResourceTest.php tests/Feature/DtrUploads/DtrUploadResourceTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement, then run the whole suite and Pint**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 4: Check the employee form and DTR modal at 1024 and 768 px with the scratchpad scan**

Expected: `ok` at each width.
