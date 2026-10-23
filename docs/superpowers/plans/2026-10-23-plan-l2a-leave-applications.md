# Leave Module — Plan L2a: Leave Applications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** HR files leave for any employee who earns credits. The system counts the days against the employee's schedule, charges VL, SL, SPL or Wellness with the rest as leave without pay, and lets HR approve or cancel. Leave without pay lowers that month's credit.

**Architecture:** `ScheduleResolver` (in `app/Services/Attendance/`, for Plan 3 to reuse) says what each date is for an employee. In `app/Services/Leave/`, `LeaveDayCounter` turns dates into days, `LeaveCharger` (pure) splits them across balances and leave without pay, and `LeaveApplications` files, approves and cancels inside a lock. `LeavePosting` gains days without pay and credit corrections. A **Leave applications** resource in the HR panel, a **File leave** link on the Leave tab, and a dashboard count.

**Tech Stack:** Laravel 13, PHP 8.4 (bcmath), Filament 5.9 (Livewire 4), spatie/laravel-activitylog 5.1, PHPUnit 12 on SQLite in memory, MySQL 8 in use.

**Spec:** `docs/superpowers/specs/2026-10-23-leave-module-design.md`, sections **Counting the days of an application**, **Charging an application**, **Monthly credits**, **Year end**, **Leave applications**, **Data model**. Plan L1 is built (commit `4e7c3ee`).

## Changes to the spec

To be made in the spec together with this plan, for the user to review with it:

- **L2 is split.** L2a (this plan) is everything HR does with applications. L2b is the employees' side: accounts, the employee panel, sign-in by employee number, the policy changes, the printed form, `employees.position`, and the leave type descriptions. L2b waits on the scan of HR's leave form.
- An application's `breakdown` keeps every date of the range, rest days and holidays at `0.000`, so the deduction explains itself.
- A date can be split between with pay and without pay (VL 1.250 on hand against two 1.000 days: 1.000 + 0.250 with pay, 0.750 without).
- An application's range is at most 366 days.
- A credit correction is posted only for a month the employee was credited, which follows from "who earns is judged when a period is posted".

## Global Constraints

- Laravel 13, PHP 8.4, Livewire 4, Filament 5, PHPUnit. Timezone `Asia/Manila`; `Date::use(CarbonImmutable::class)` is on.
- **No new packages and no npm build.**
- **Days are decimal strings with three places**, worked with bcmath at scale 3, never floats. Sums read from the database go through `number_format((float) $sum, 3, '.', '')` once. A typed number is checked against `LeaveLedger::SIGNED_DAYS` (or an unsigned twin) before it reaches bcmath, which throws on anything else.
- **A ledger row is never updated or deleted.** A cancellation adds rows. Application and cancellation rows are written through the model, so the activity log records them with the causer; credit corrections, like other posted rows, go through `insertOrIgnore` with no log.
- **An application is never edited.** No edit page, and the policy refuses update and delete.
- **Everything that checks a balance calls `LeavePosting::catchUp()` first**: preview, filing, and cancelling.
- Filing and cancelling lock the employee row (`lockForUpdate`) inside one transaction.
- Who may have leave filed for them: `employment_status->earnsLeaveCredits()` and `is_active`.
- Compare dates in queries with `whereDate()` / `whereYear()`.
- Both roles (`admin`, `hr`) file, approve and cancel in L2a. Every action authorizes with `->authorize()`; services do not trust the screen.
- The **Security Guidelines** in `CLAUDE.md` apply. Names, remarks, details and reasons are user text: never `HtmlString` or `{!! !!}`.
- Create files with `php artisan make:* --no-interaction`, then edit them. Test helpers must not be named after `TestCase` methods (`post`, `get`, `put`, `call`, …).
- Tests: `php artisan test --compact <path>`. Before handing off a task, run `vendor/bin/pint --dirty --format agent`.
- **Never run `git commit`.** The user commits. Each task ends with its changed files and a suggested commit message.

## Review Focus

1. **A range typed backwards, or of two years.** Refused with a message on the field, not a loop or a 500. Pinned by Task 2, `test_dates_backwards_or_over_a_year_are_refused`.
2. **Half a day picked for a range of several dates.** Refused on the coverage field, not charged as half of everything. Pinned by Task 2, `test_half_a_day_needs_one_date`.
3. **A double click on Cancel, or Approve after Cancel.** The second is refused and the balance comes back once. Pinned by Task 4, `test_a_cancelled_application_cannot_be_cancelled_or_approved_again`.
4. **A balance already below zero from history** (a legacy adjustment, or a pre-L1 import). It draws nothing; everything goes without pay. Pinned by Task 3, `test_a_negative_balance_draws_nothing`.
5. **Filing while `LEAVE_START_DATE` is not set.** Refused with a message and nothing written, because no date could be checked against the start. Pinned by Task 4, `test_filing_without_a_start_date_is_refused`.

---

## Before you start

- Work in `C:\laragon\www\dtr-system` on `main`. `php artisan test --compact` passes (157 tests). Laragon's MySQL has the L1 table.
- `.ai/rules` does not exist; `CLAUDE.md` is the rule set.
- Read `app/Services/Leave/LeavePosting.php` and `LeaveLedger.php` (L1) before Task 3.

## File map

| File | Responsibility | Task |
|---|---|---|
| `app/Enums/DayKind.php`, `app/Services/Attendance/ResolvedDay.php`, `app/Services/Attendance/ScheduleResolver.php` | What a date is for an employee | 1 |
| `database/migrations/*_add_leave_days_to_schedules_table.php`, `app/Models/Schedule.php`, `app/Filament/Resources/Schedules/Schemas/ScheduleForm.php` | Days charged to VL/SL for a day of leave | 2 |
| `app/Enums/LeaveCoverage.php`, `app/Services/Leave/LeaveDayCounter.php` | Dates → days, and the refusals | 2 |
| `app/Services/Leave/Charge.php`, `app/Services/Leave/LeaveCharger.php` | Days → draws, with pay, without pay | 3 |
| `app/Enums/LeaveApplicationStatus.php`, `app/Enums/LeaveDetail.php`, `app/Models/LeaveApplication.php` + migrations + factory, `app/Policies/LeaveApplicationPolicy.php`, `app/Enums/LedgerKind.php` | An application and its ledger rows | 4 |
| `app/Services/Leave/LeaveApplications.php` | Preview, file, approve, cancel | 4, 5 |
| `app/Services/Leave/LeavePosting.php` | Days without pay in credits, corrections, VL taken | 5 |
| `app/Filament/Resources/LeaveApplications/*`, `app/Filament/Widgets/PendingLeaveApplications.php`, the Leave tab | HR's screens | 6 |

---

### Task 1: `ScheduleResolver`

**Files:**
- Create: `app/Enums/DayKind.php`, `app/Services/Attendance/ResolvedDay.php`, `app/Services/Attendance/ScheduleResolver.php`
- Test: `tests/Feature/Attendance/ScheduleResolverTest.php`

**Interfaces:**
- Consumes: `Employee`, `Schedule`, `ScheduleAssignment`, `RosterEntry`, `Holiday`, `HolidayType` (Plans 1 and 2a).
- Produces:
  - `App\Enums\DayKind: string`: `NotEmployed = 'not_employed'`, `RestDay = 'rest_day'`, `Holiday = 'holiday'`, `Suspended = 'suspended'`, `WorkDay = 'work_day'`, `NoSchedule = 'no_schedule'`.
  - `App\Services\Attendance\ResolvedDay` (`final readonly`): `public DayKind $kind`, `public ?Schedule $schedule = null` (set for `WorkDay`).
  - `ScheduleResolver::days(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array<string, ResolvedDay>`: every date from `$from` to `$to`, keyed `Y-m-d`, in order. Three queries (roster entries, assignments, holidays in the range), each with its schedule eager-loaded.

The rules, first match wins (the DTR spec's **Schedule resolution**): before `date_hired` or after `date_separated` → `NotEmployed`; a roster entry → `WorkDay` with its schedule, or `RestDay` when the entry has none (OFF); an assignment in effect → `RestDay` when the weekday is not in `work_days`, `Holiday` on a regular or special holiday, `Suspended` on a suspension without a time, otherwise `WorkDay` (a timed suspension is a work day; Plan 3 cuts its hours); neither → `NoSchedule`. Holidays never apply to roster entries.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:enum DayKind --no-interaction
php artisan make:class Services/Attendance/ResolvedDay --no-interaction
php artisan make:class Services/Attendance/ScheduleResolver --no-interaction
php artisan make:test Attendance/ScheduleResolverTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`setUp`: an employee; `$this->regular` = `Schedule::factory()->create(['code' => 'REG'])` (Mon–Fri); an assignment of REG from `2026-11-01`, open-ended; `$this->night` = `Schedule::factory()->rosterOnly()->create(['code' => 'N'])`. `day(string $date): ResolvedDay` returns `days($employee, $date, $date)[$date]`. 1 November 2026 is a Sunday; 30 November is Bonifacio Day.

```php
public function test_a_weekday_on_a_fixed_schedule_is_a_work_day(): void
{
    $day = $this->day('2026-11-03');

    $this->assertSame(DayKind::WorkDay, $day->kind);
    $this->assertTrue($day->schedule->is($this->regular));
}

public function test_a_weekend_on_a_fixed_schedule_is_a_rest_day(): void
{
    $this->assertSame(DayKind::RestDay, $this->day('2026-11-07')->kind);
}

public function test_holidays_and_suspensions_on_a_fixed_schedule(): void
{
    Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);
    Holiday::factory()->create(['date' => '2026-11-04', 'type' => HolidayType::Suspension]);
    Holiday::factory()->create(['date' => '2026-11-05', 'type' => HolidayType::Suspension, 'suspension_starts_at' => '15:00:00']);

    $this->assertSame(DayKind::Holiday, $this->day('2026-11-30')->kind);
    $this->assertSame(DayKind::Suspended, $this->day('2026-11-04')->kind);
    $this->assertSame(DayKind::WorkDay, $this->day('2026-11-05')->kind);
}

public function test_the_roster_wins_over_the_assignment(): void
{
    RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-07', 'schedule_id' => $this->night->id]);
    RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-03', 'schedule_id' => null]);

    $this->assertTrue($this->day('2026-11-07')->schedule->is($this->night));
    $this->assertSame(DayKind::RestDay, $this->day('2026-11-03')->kind);
}

public function test_a_rostered_nurse_works_on_a_holiday(): void
{
    Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);
    RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-30', 'schedule_id' => $this->night->id]);

    $this->assertSame(DayKind::WorkDay, $this->day('2026-11-30')->kind);
}

public function test_a_date_with_neither_has_no_schedule(): void
{
    $this->assertSame(DayKind::NoSchedule, $this->day('2026-10-30')->kind);
}

public function test_an_ended_assignment_no_longer_applies(): void
{
    $this->employee->scheduleAssignments()->update(['effective_to' => '2026-11-15']);

    $this->assertSame(DayKind::NoSchedule, $this->day('2026-11-16')->kind);
}

public function test_dates_outside_employment(): void
{
    $this->employee->update(['date_hired' => '2026-11-02', 'date_separated' => '2026-11-20']);

    $this->assertSame(DayKind::NotEmployed, $this->day('2026-11-01')->kind);
    $this->assertSame(DayKind::NotEmployed, $this->day('2026-11-21')->kind);
}

public function test_every_date_in_the_range_comes_back_in_order(): void
{
    $days = app(ScheduleResolver::class)->days($this->employee, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-07'));

    $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07'], array_keys($days));
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Attendance/ScheduleResolverTest.php`
Expected: FAIL, `days()` is not defined.

- [ ] **Step 4: Implement `DayKind`, `ResolvedDay`, `ScheduleResolver`**

- [ ] **Step 5: Run them to verify they pass**

Run: `php artisan test --compact tests/Feature/Attendance/ScheduleResolverTest.php`
Expected: PASS (9 tests).

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: schedule resolver`

---

### Task 2: Counting the days

**Files:**
- Create: `database/migrations/*_add_leave_days_to_schedules_table.php`, `app/Enums/LeaveCoverage.php`, `app/Services/Leave/LeaveDayCounter.php`
- Modify: `app/Models/Schedule.php` (fillable, cast `decimal:3`, default `'1.000'`), `app/Filament/Resources/Schedules/Schemas/ScheduleForm.php`, `app/Filament/Resources/Schedules/Tables/SchedulesTable.php`
- Test: `tests/Feature/Leave/LeaveDayCounterTest.php`; add to `tests/Feature/Schedules/ScheduleResourceTest.php`

**Interfaces:**
- Consumes: `ScheduleResolver::days()`, `DayKind` (Task 1); `LeaveType` (L1).
- Produces:
  - `schedules.leave_days` `decimal(4,3)` default `1.000`.
  - `App\Enums\LeaveCoverage: string implements HasLabel`: `WholeDay = 'whole_day'` "Whole day", `FirstHalf = 'first_half'` "First half (morning)", `SecondHalf = 'second_half'` "Second half (afternoon)".
  - `LeaveDayCounter::count(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage, CarbonImmutable $start): array<string, string>`: `Y-m-d` → days for every date of the range. Throws `ValidationException` keyed `date_from`, `date_to` or `coverage`.

The rules:
- `date_to` before `date_from`, or a range over 366 days → refused on `date_to`.
- Half a day with more than one date → refused on `coverage`.
- A date before `$start` → refused on `date_from`: "Leave before {start, M j, Y} belongs in the opening balance."
- **Tracked types** (VL, FL, SL, SPL, WL) go through the resolver. `NotEmployed` → refused on `date_from` ("{name} was not employed on {M j, Y}."); `NoSchedule` → refused on `date_from` ("No schedule on {M j, Y}. Set it on the employee or in the duty roster first."); `RestDay`, `Holiday`, `Suspended` → `'0.000'`; `WorkDay` → the schedule's `leave_days` for VL, FL and SL, `'1.000'` for SPL and WL.
- SPL and WL: every date in the current year, else refused on `date_from` ("{type} is filed for dates in {year} only.").
- **Untracked types** count calendar days, `'1.000'` each, with no schedule; only `date_hired`/`date_separated` and `$start` are checked.
- Half a day is half the value (`bcdiv($value, '2', 3)`).
- A total of zero → refused on `date_from`: "These dates have no work days for {name}."

- [ ] **Step 1: Generate the files**

```bash
php artisan make:migration add_leave_days_to_schedules_table --no-interaction
php artisan make:enum LeaveCoverage --no-interaction
php artisan make:class Services/Leave/LeaveDayCounter --no-interaction
php artisan make:test Leave/LeaveDayCounterTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`LeaveDayCounterTest` (`RefreshDatabase`; `travelTo('2026-11-02 08:00')`; an employee; `$this->regular` = REG (Mon–Fri, `leave_days` default), `$this->fourDay` = CWW (`work_days` `[1,2,3,4]`, `leave_days` `'1.250'`), `$this->duty16` = D16 roster-only (`leave_days` default); `assign(Schedule)` gives the employee that schedule from `2026-11-01`). `count(string $type, string $from, ?string $to = null, LeaveCoverage $coverage = LeaveCoverage::WholeDay)` calls the counter with start `2026-11-01`; `assertRefused(string $errorKey, string $type, string $from, ?string $to = null, LeaveCoverage $coverage = LeaveCoverage::WholeDay, ?string $message = null)` expects the `ValidationException`, the key, and the message fragment when given.

```php
public function test_a_regular_day_is_one(): void
{
    $this->assign($this->regular);

    $this->assertSame(['2026-11-03' => '1.000', '2026-11-04' => '1.000'], $this->count('VL', '2026-11-03', '2026-11-04'));
}

public function test_a_four_day_week_day_is_one_and_a_quarter(): void
{
    $this->assign($this->fourDay);

    $this->assertSame(['2026-11-03' => '1.250'], $this->count('VL', '2026-11-03'));
}

public function test_a_16_hour_duty_is_one(): void
{
    RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-07', 'schedule_id' => $this->duty16->id]);

    $this->assertSame(['2026-11-07' => '1.000'], $this->count('SL', '2026-11-07'));
}

public function test_spl_and_wellness_are_one_a_day_on_any_schedule(): void
{
    $this->assign($this->fourDay);

    $this->assertSame(['2026-11-03' => '1.000'], $this->count('SPL', '2026-11-03'));
    $this->assertSame(['2026-11-03' => '1.000'], $this->count('WL', '2026-11-03'));
}

public function test_rest_days_and_holidays_count_zero(): void
{
    $this->assign($this->regular);
    Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);

    $this->assertSame(
        ['2026-11-27' => '1.000', '2026-11-28' => '0.000', '2026-11-29' => '0.000', '2026-11-30' => '0.000'],
        $this->count('VL', '2026-11-27', '2026-11-30'),
    );
}

public function test_a_rostered_duty_on_a_holiday_counts(): void
{
    Holiday::factory()->create(['date' => '2026-11-30', 'type' => HolidayType::Regular]);
    RosterEntry::factory()->for($this->employee)->create(['date' => '2026-11-30', 'schedule_id' => $this->duty16->id]);

    $this->assertSame(['2026-11-30' => '1.000'], $this->count('VL', '2026-11-30'));
}

public function test_half_a_day_is_half_the_value(): void
{
    $this->assign($this->fourDay);

    $this->assertSame(['2026-11-03' => '0.625'], $this->count('VL', '2026-11-03', coverage: LeaveCoverage::FirstHalf));
}

public function test_half_a_day_needs_one_date(): void
{
    $this->assign($this->regular);

    $this->assertRefused('coverage', 'VL', '2026-11-03', '2026-11-04', LeaveCoverage::SecondHalf);
}

public function test_a_date_without_a_schedule_is_refused_by_name(): void
{
    $this->assertRefused('date_from', 'VL', '2026-11-03', message: 'No schedule on Nov 3, 2026');
}

public function test_dates_before_the_start_or_outside_employment_are_refused(): void
{
    $this->assign($this->regular);
    $this->employee->update(['date_separated' => '2026-11-10']);

    $this->assertRefused('date_from', 'VL', '2026-10-30', message: 'opening balance');
    $this->assertRefused('date_from', 'VL', '2026-11-11', message: 'not employed on Nov 11, 2026');
}

public function test_only_rest_days_are_refused(): void
{
    $this->assign($this->regular);

    $this->assertRefused('date_from', 'VL', '2026-11-07', '2026-11-08', message: 'no work days');
}

public function test_spl_and_wellness_are_for_the_current_year_only(): void
{
    $this->assign($this->regular);

    $this->assertRefused('date_from', 'SPL', '2027-01-04', message: '2026 only');
}

public function test_untracked_types_count_calendar_days_without_a_schedule(): void
{
    $this->assertSame(
        ['2026-11-03' => '1.000', '2026-11-04' => '1.000', '2026-11-05' => '1.000', '2026-11-06' => '1.000', '2026-11-07' => '1.000', '2026-11-08' => '1.000', '2026-11-09' => '1.000'],
        $this->count('ML', '2026-11-03', '2026-11-09'),
    );
}

public function test_dates_backwards_or_over_a_year_are_refused(): void
{
    $this->assertRefused('date_to', 'ML', '2026-11-04', '2026-11-03');
    $this->assertRefused('date_to', 'ML', '2026-11-01', '2027-11-02');
}
```

Add to `ScheduleResourceTest`: `test_hr_sets_the_days_charged_for_a_day_of_leave` (create with `leave_days` `'1.25'`, saved as `'1.250'`) and `test_days_charged_must_be_a_positive_number` (`'0'` and `'abc'` give form errors).

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/LeaveDayCounterTest.php tests/Feature/Schedules/ScheduleResourceTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the migration, `Schedule` changes, the form field, `LeaveCoverage`, `LeaveDayCounter`**

The form field: `TextInput::make('leave_days')`, label "Days charged to VL/SL for a day of leave", required, `->regex('/^\d(\.\d{1,3})?\z/')`, a rule refusing zero, default `'1.000'`, helper text "1 for most schedules. 1.25 for the 4-Day Week (10-hour days). SPL and Wellness always charge 1." The table gets a `leave_days` column labelled "Leave day".

- [ ] **Step 5: Run them to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/LeaveDayCounterTest.php tests/Feature/Schedules/`
Expected: PASS (14 counter tests, the schedule tests).

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: count leave days against the schedule`

---

### Task 3: Charging

**Files:**
- Create: `app/Services/Leave/Charge.php`, `app/Services/Leave/LeaveCharger.php`
- Test: `tests/Unit/Leave/LeaveChargerTest.php` (extends `PHPUnit\Framework\TestCase`; nothing here touches Laravel)

**Interfaces:**
- Consumes: `LeaveType` (L1).
- Produces:
  - `App\Services\Leave\Charge` (`final readonly`): `string $days`, `string $withPay`, `string $withoutPay`, `array<string, string> $draws` (code → days drawn, only non-zero, in drawing order), `array<string, array{days: string, without_pay: string}> $breakdown`; `describe(): string`.
  - `LeaveCharger::charge(LeaveType $type, array<string, string> $breakdown, array<string, string> $balances): Charge`. `$balances` is `LeaveLedger::balances()` output.

Drawing order: VL and FL from `['VL']`; SL from `['SL', 'VL']`; SPL from `['SPL']`; WL from `['WL']`; untracked types draw nothing and are all with pay. A negative balance counts as zero. With-pay days go to the earliest dates first, so days without pay are the last dates, and one date may be split.

`describe()` copy, exactly: `"3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay"`; with two sources `"9.000 days: 7.000 with pay (SL 2.000, VL 5.000), 2.000 without pay"`; with nothing drawn `"2.000 days: 0.000 with pay, 2.000 without pay"`; untracked `"7.000 days with pay; no balance is deducted"`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:class Services/Leave/Charge --no-interaction
php artisan make:class Services/Leave/LeaveCharger --no-interaction
php artisan make:test Leave/LeaveChargerTest --unit --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`days(string ...$values): array` builds a breakdown on `2026-11-03`, `-04`, … in order; `balances(array $given): array` fills the missing tracked codes with `'0.000'`.

```php
public function test_vl_draws_vl_then_goes_without_pay(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000', '1.000', '1.000'), $this->balances(['VL' => '2.000']));

    $this->assertSame(['VL' => '2.000'], $charge->draws);
    $this->assertSame(['3.000', '2.000', '1.000'], [$charge->days, $charge->withPay, $charge->withoutPay]);
    $this->assertSame(['0.000', '0.000', '1.000'], array_column($charge->breakdown, 'without_pay'));
}

public function test_sl_draws_sl_then_vl_then_goes_without_pay(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Sick, $this->days('1.000', '1.000', '1.000'), $this->balances(['SL' => '1.000', 'VL' => '1.500']));

    $this->assertSame(['SL' => '1.000', 'VL' => '1.500'], $charge->draws);
    $this->assertSame(['0.000', '0.000', '0.500'], array_column($charge->breakdown, 'without_pay'));
}

public function test_vl_never_draws_sl(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000'), $this->balances(['SL' => '10.000']));

    $this->assertSame([], $charge->draws);
    $this->assertSame('1.000', $charge->withoutPay);
}

public function test_forced_leave_draws_vl(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Forced, $this->days('1.000'), $this->balances(['VL' => '5.000']));

    $this->assertSame(['VL' => '1.000'], $charge->draws);
}

public function test_spl_and_wellness_go_without_pay_when_short(): void
{
    $spl = (new LeaveCharger)->charge(LeaveType::SpecialPrivilege, $this->days('1.000', '1.000'), $this->balances(['SPL' => '1.000']));
    $wellness = (new LeaveCharger)->charge(LeaveType::Wellness, $this->days('1.000'), $this->balances([]));

    $this->assertSame([['SPL' => '1.000'], '1.000'], [$spl->draws, $spl->withoutPay]);
    $this->assertSame([[], '1.000'], [$wellness->draws, $wellness->withoutPay]);
}

public function test_untracked_types_draw_nothing_and_are_with_pay(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Maternity, $this->days('1.000', '1.000'), $this->balances([]));

    $this->assertSame([[], '2.000', '0.000'], [$charge->draws, $charge->withPay, $charge->withoutPay]);
}

public function test_a_negative_balance_draws_nothing(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000'), $this->balances(['VL' => '-0.500']));

    $this->assertSame([[], '1.000'], [$charge->draws, $charge->withoutPay]);
}

public function test_zero_days_stay_in_the_breakdown(): void
{
    $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000', '0.000'), $this->balances(['VL' => '5.000']));

    $this->assertSame(['days' => '0.000', 'without_pay' => '0.000'], $charge->breakdown['2026-11-04']);
}

public function test_the_summary_reads_the_way_the_form_shows_it(): void
{
    $charger = new LeaveCharger;

    $this->assertSame('3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay', $charger->charge(LeaveType::Vacation, $this->days('1.000', '1.000', '1.000'), $this->balances(['VL' => '5.000']))->describe());
    $this->assertSame('2.000 days: 0.000 with pay, 2.000 without pay', $charger->charge(LeaveType::Vacation, $this->days('1.000', '1.000'), $this->balances([]))->describe());
    $this->assertSame('2.000 days with pay; no balance is deducted', $charger->charge(LeaveType::Paternity, $this->days('1.000', '1.000'), $this->balances([]))->describe());
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Unit/Leave/LeaveChargerTest.php`
Expected: FAIL, `charge()` is not defined.

- [ ] **Step 4: Implement `Charge` and `LeaveCharger`**

- [ ] **Step 5: Run them to verify they pass**

Run: `php artisan test --compact tests/Unit/Leave/LeaveChargerTest.php`
Expected: PASS (9 tests).

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: charge leave to balances and leave without pay`

---

### Task 4: Applications: file, approve, cancel

**Files:**
- Create: `app/Enums/LeaveApplicationStatus.php`, `app/Enums/LeaveDetail.php`
- Create: `database/migrations/*_create_leave_applications_table.php`, `database/migrations/*_add_leave_application_id_to_leave_ledger_entries_table.php`, `app/Models/LeaveApplication.php`, `database/factories/LeaveApplicationFactory.php`, `app/Policies/LeaveApplicationPolicy.php`
- Create: `app/Services/Leave/LeaveApplications.php`
- Modify: `app/Enums/LedgerKind.php`, `app/Models/LeaveLedgerEntry.php` (fillable + `application()` relation), `app/Models/Employee.php` (`leaveApplications()`)
- Test: `tests/Feature/Leave/LeaveApplicationsTest.php`

**Interfaces:**
- Consumes: `LeaveDayCounter::count()` (Task 2), `LeaveCharger::charge()` (Task 3), `LeaveLedger::balances()`, `LeavePosting::catchUp()`, `LeavePosting::configuredStart()` (L1).
- Produces:
  - `LeaveApplicationStatus: string implements HasLabel, HasColor`: `Pending = 'pending'` (warning), `Approved = 'approved'` (success), `Cancelled = 'cancelled'` (gray).
  - `LeaveDetail: string implements HasLabel`, CS Form No. 6 item 6.B: `WithinPhilippines` "Within the Philippines", `Abroad` "Abroad", `InHospital` "In hospital", `OutPatient` "Out patient", `MastersDegree` "Completion of master's degree", `BoardReview` "BAR/board examination review"; `static forType(LeaveType $type): list<self>` (VL and SPL: the first two; SL: the hospital pair; STUDY: the last two; others: none); `needsText(): bool` (Abroad, InHospital, OutPatient). SLBW has no choice but needs `detail_text` (the illness).
  - `LedgerKind` gains `Application = 'application'` "Leave application", `Cancellation = 'cancellation'` "Cancelled application", `CreditCorrection = 'credit_correction'` "Credit correction".
  - `LeaveApplication` model: columns and casts per the spec's **Data model** (`leave_type` → `LeaveType`, `coverage` → `LeaveCoverage`, `detail` → `LeaveDetail`, `status` → `LeaveApplicationStatus`, dates `date`, days `decimal:3`, `breakdown` → `array`, `commutation_requested` → `boolean`); `employee()`, `filedBy()`, `approvedBy()`, `cancelledBy()`, `ledgerEntries()`; `dates(): string` ("Nov 3–4, 2026", "Nov 3, 2026 (first half)", "Dec 28, 2026 – Jan 2, 2027"); `LogsActivity` on fillable.
  - `leave_ledger_entries.leave_application_id` nullable, `constrained()->restrictOnDelete()`.
  - `LeaveApplicationPolicy`: `viewAny`, `view`, `create` → true; `approve(User, LeaveApplication)` → pending; `cancel(User, LeaveApplication)` → not cancelled; the rest → false.
  - `App\Services\Leave\LeaveApplications`:
    - `preview(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeaveCoverage $coverage): Charge`
    - `file(Employee $employee, array $data): LeaveApplication`. `$data`: `leave_type`, `date_from`, `date_to`, `coverage`, optional `detail`, `detail_text`, `commutation_requested`. Throws `ValidationException` keyed by those names or `employee_id`.
    - `approve(LeaveApplication $application): void`
    - `cancel(LeaveApplication $application, string $reason): void`. Throws keyed `cancel_reason` or `status`.

`file()`: refuses an employee who does not earn (`employee_id`: "{name} earns no leave credits."), and a blank start (`date_from`: "Set LEAVE_START_DATE first."); validates `detail` against `LeaveDetail::forType()` and requires `detail_text` where `needsText()` or for SLBW (`detail_text`, at most 255). Then `catchUp()`. In one transaction with the employee locked: overlap check (not cancelled, dates overlapping; allowed only when both are one date with a first and a second half) refused on `date_from` naming the other application's `dates()`; count; charge against `balances()`; create the application (`pending`, `filed_by_user_id` = auth); one `Application` ledger row per draw: minus the days, dated today, `leave_application_id`, remarks `"#{id}, {dates()}"`.

`cancel()`: refuses a cancelled one (`status`: "This application is already cancelled.") and a blank reason. `catchUp()`, then with the employee locked: one `Cancellation` row per `Application` row of the application, giving its days back, dated today, except SPL and WL rows when the year of `date_from` is closed (a `YearlyGrant` row with a later period exists): those lapsed and are not given back. Sets `status`, `cancelled_at`, `cancelled_by_user_id`, `cancel_reason`.

`approve()`: refuses anything but pending (`status`: "Only a pending application can be approved."); sets `approved_at`, `approved_by_user_id`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:enum LeaveApplicationStatus --no-interaction
php artisan make:enum LeaveDetail --no-interaction
php artisan make:model LeaveApplication --migration --factory --no-interaction
php artisan make:migration add_leave_application_id_to_leave_ledger_entries_table --no-interaction
php artisan make:policy LeaveApplicationPolicy --model=LeaveApplication --no-interaction
php artisan make:class Services/Leave/LeaveApplications --no-interaction
php artisan make:test Leave/LeaveApplicationsTest --phpunit --no-interaction
```

The create migration's file name must sort before the add-column one.

- [ ] **Step 2: Write the failing tests**

`setUp`: `config(['leave.start_date' => '2026-11-01'])`; `travelTo('2026-11-02 08:00')`; acts as a user; an employee on REG from `2026-11-01`; opening rows VL `5.000` and SL `2.000` dated `2026-10-31`. `fileLeave(array $overrides = []): LeaveApplication` files VL `2026-11-03`–`2026-11-04` whole day, merged with `$overrides`; `balances()` returns `LeaveLedger::balances($this->employee)`; `assertRefused(Closure $call, string $errorKey)`.

```php
public function test_filing_deducts_the_balance_at_once(): void
{
    $application = $this->fileLeave();

    $this->assertSame(LeaveApplicationStatus::Pending, $application->status);
    $this->assertSame(['2.000', '2.000', '0.000'], [$application->days, $application->days_with_pay, $application->days_without_pay]);
    $this->assertSame('3.000', $this->balances()['VL']);
    $this->assertSame(auth()->id(), $application->filed_by_user_id);
    $this->assertSame('-2.000', $application->ledgerEntries()->sole()->days);
}

public function test_sick_leave_runs_into_vl_then_goes_without_pay(): void
{
    // Nov 3–13 has 9 weekdays: SL 2 + VL 5 with pay, 2 without.
    $application = $this->fileLeave(['leave_type' => 'SL', 'date_to' => '2026-11-13']);

    $this->assertSame(['9.000', '7.000', '2.000'], [$application->days, $application->days_with_pay, $application->days_without_pay]);
    $this->assertSame(['VL' => '0.000', 'SL' => '0.000', 'SPL' => '0.000', 'WL' => '0.000'], $this->balances());
    $this->assertSame('1.000', $application->breakdown['2026-11-13']['without_pay']);
}

public function test_approving_records_who_and_when(): void
{
    $application = $this->fileLeave();

    app(LeaveApplications::class)->approve($application);

    $this->assertSame(LeaveApplicationStatus::Approved, $application->fresh()->status);
    $this->assertSame(auth()->id(), $application->fresh()->approved_by_user_id);
    $this->assertSame('3.000', $this->balances()['VL']);
}

public function test_cancelling_gives_back_exactly_what_was_drawn(): void
{
    $application = $this->fileLeave(['leave_type' => 'SL', 'date_to' => '2026-11-13']);

    app(LeaveApplications::class)->cancel($application, 'Disapproved by the Chief of Hospital');

    $this->assertSame(['VL' => '5.000', 'SL' => '2.000', 'SPL' => '0.000', 'WL' => '0.000'], $this->balances());
    $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
    $this->assertSame('Disapproved by the Chief of Hospital', $application->fresh()->cancel_reason);
    $this->assertSame(2, $application->ledgerEntries()->where('kind', LedgerKind::Cancellation)->count());
}

public function test_a_cancelled_application_cannot_be_cancelled_or_approved_again(): void
{
    $application = $this->fileLeave();
    app(LeaveApplications::class)->cancel($application, 'Withdrawn');

    $this->assertRefused(fn () => app(LeaveApplications::class)->cancel($application->fresh(), 'Withdrawn'), 'status');
    $this->assertRefused(fn () => app(LeaveApplications::class)->approve($application->fresh()), 'status');
    $this->assertSame('5.000', $this->balances()['VL']);
}

public function test_cancelling_needs_a_reason(): void
{
    $application = $this->fileLeave();

    $this->assertRefused(fn () => app(LeaveApplications::class)->cancel($application, ' '), 'cancel_reason');
}

public function test_overlapping_dates_are_refused_but_two_halves_are_not(): void
{
    $first = $this->fileLeave();

    $this->assertRefused(fn () => $this->fileLeave(['date_from' => '2026-11-04', 'date_to' => '2026-11-04']), 'date_from');

    $this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'coverage' => 'first_half']);
    $this->fileLeave(['date_from' => '2026-11-05', 'date_to' => '2026-11-05', 'coverage' => 'second_half']);

    app(LeaveApplications::class)->cancel($first, 'Wrong dates');
    $this->fileLeave();
    $this->assertSame(4, LeaveApplication::count());
}

public function test_an_employee_who_earns_no_credits_cannot_have_leave_filed(): void
{
    $this->employee->update(['employment_status' => EmploymentStatus::JobOrder]);

    $this->assertRefused(fn () => $this->fileLeave(), 'employee_id');
}

public function test_filing_without_a_start_date_is_refused(): void
{
    config(['leave.start_date' => null]);

    $this->assertRefused(fn () => $this->fileLeave(), 'date_from');
    $this->assertSame(0, LeaveApplication::count());
}

public function test_details_follow_the_leave_form(): void
{
    $this->assertRefused(fn () => $this->fileLeave(['detail' => 'in_hospital']), 'detail');
    $this->assertRefused(fn () => $this->fileLeave(['detail' => 'abroad']), 'detail_text');

    $application = $this->fileLeave(['detail' => 'abroad', 'detail_text' => 'Japan', 'commutation_requested' => true]);
    $this->assertSame([LeaveDetail::Abroad, 'Japan', true], [$application->detail, $application->detail_text, $application->commutation_requested]);
}

public function test_spl_that_has_lapsed_is_not_given_back(): void
{
    LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'SPL', 'days' => '3.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
    $this->travelTo('2026-12-01 08:00');
    $application = $this->fileLeave(['leave_type' => 'SPL', 'date_from' => '2026-12-28', 'date_to' => '2026-12-28']);

    $this->travelTo('2027-01-04 08:00');
    app(LeaveApplications::class)->cancel($application, 'Did not go');

    $this->assertSame(0, $application->ledgerEntries()->where('kind', LedgerKind::Cancellation)->count());
    $this->assertSame('3.000', $this->balances()['SPL']);   // 2027's grant only
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/LeaveApplicationsTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the enums, migrations, model, factory, policy and `LeaveApplications`**

The factory: an employee, VL, `2026-11-03` to `2026-11-03`, whole day, days `1.000` with pay, breakdown `['2026-11-03' => ['days' => '1.000', 'without_pay' => '0.000']]`, pending, `filed_by_user_id` a `User::factory()`.

- [ ] **Step 5: Run them to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/`
Expected: PASS (the L1 leave tests and these 11).

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: file, approve and cancel leave applications`

---

### Task 5: Leave without pay in the credits, and VL taken

**Files:**
- Modify: `app/Services/Leave/LeavePosting.php`, `app/Services/Leave/LeaveApplications.php`
- Test: `tests/Feature/Leave/LeaveWithoutPayCreditsTest.php`

**Interfaces:**
- Consumes: `LeaveApplication` and its `breakdown` (Task 4); `LeavePosting::creditFor()` (L1).
- Produces:
  - Monthly credits read each employee's days without pay in the month: the `without_pay` of every breakdown date in it, from applications that are not cancelled (one query per month for everyone).
  - `LeavePosting::correctCredits(Employee $employee, list<string> $months): int`, `$months` as `Y-m`. For each month in which the employee has a `MonthlyCredit` row: the credit it should be now, minus what VL's `MonthlyCredit` and `CreditCorrection` rows for that period add up to; a non-zero difference becomes a `CreditCorrection` row for VL and for SL, dated today, `period` the month, remarks "Correction for {F Y}", no posting key.
  - `vlTakenIn()` adds the VL drawn (`Application` rows on VL, negated) by VL and FL applications that are not cancelled and whose `date_from` is in the year.
  - `LeaveApplications::file()` and `cancel()` call `correctCredits()` with the months of the application's dates without pay, inside their transaction.

- [ ] **Step 1: Write the failing tests**

`setUp` as in Task 4 but with no opening rows, and `config(['leave.start_date' => '2026-11-01'])`. `fileLeave(array $data)` files `$data` with `coverage` `whole_day`; `creditRows(string $period)` returns the employee's VL `MonthlyCredit` and `CreditCorrection` rows for the period, in id order, as `"{kind} {days}"`.

```php
public function test_days_without_pay_reduce_the_months_credit(): void
{
    $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03']);   // nothing on hand: 1 day without pay

    $this->travelTo('2026-12-01 08:00');
    app(LeavePosting::class)->catchUp();

    $this->assertSame(['monthly_credit 1.208'], $this->creditRows('2026-11'));
}

public function test_without_pay_filed_into_a_credited_month_adds_a_correction(): void
{
    $this->travelTo('2026-12-02 08:00');
    // Posts November (1.250), then 5 days with VL 1.250 on hand: 3.750 without pay.
    $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-23', 'date_to' => '2026-11-27']);

    $this->assertSame(['monthly_credit 1.250', 'credit_correction -0.156'], $this->creditRows('2026-11'));
}

public function test_cancelling_without_pay_reverses_the_correction(): void
{
    $this->travelTo('2026-12-02 08:00');
    $application = $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-23', 'date_to' => '2026-11-27']);

    app(LeaveApplications::class)->cancel($application, 'Withdrawn');

    $this->assertSame(['monthly_credit 1.250', 'credit_correction -0.156', 'credit_correction 0.156'], $this->creditRows('2026-11'));
}

public function test_a_month_the_employee_was_not_credited_gets_no_correction(): void
{
    // Someone else is credited, so November counts as posted for the watermark.
    Employee::factory()->create();
    $this->employee->update(['is_active' => false]);
    $this->travelTo('2026-12-02 08:00');
    app(LeavePosting::class)->catchUp();
    $this->employee->update(['is_active' => true]);

    $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-23', 'date_to' => '2026-11-27']);

    $this->assertSame([], $this->creditRows('2026-11'));
}

public function test_vl_and_fl_applications_count_toward_the_five_days(): void
{
    LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '20.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
    $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05']);
    $this->fileLeave(['leave_type' => 'FL', 'date_from' => '2026-11-09', 'date_to' => '2026-11-09']);

    $this->travelTo('2027-01-01 08:00');
    app(LeavePosting::class)->catchUp();

    $this->assertSame('-1.000', $this->employee->leaveLedgerEntries()->where('kind', LedgerKind::ForcedLeave)->sole()->days);
}

public function test_a_cancelled_application_does_not_count_toward_the_five_days(): void
{
    LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '20.000', 'kind' => LedgerKind::Opening, 'posted_on' => '2026-10-31']);
    $application = $this->fileLeave(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05']);
    app(LeaveApplications::class)->cancel($application, 'Withdrawn');

    $this->travelTo('2027-01-01 08:00');
    app(LeavePosting::class)->catchUp();

    $this->assertSame('-5.000', $this->employee->leaveLedgerEntries()->where('kind', LedgerKind::ForcedLeave)->sole()->days);
}
```

The −0.156: 1.25 × (30 − 3.75) ÷ 30 = 1.094, less the 1.250 posted.

- [ ] **Step 2: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/LeaveWithoutPayCreditsTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the changes**

- [ ] **Step 4: Run the leave tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/`
Expected: PASS.

- [ ] **Step 5: Hand off**

Pint. Suggested message: `feat: leave without pay lowers the month's credit`

---

### Task 6: HR's screens

**Files:**
- Create: `app/Filament/Resources/LeaveApplications/LeaveApplicationResource.php`, `Schemas/LeaveApplicationForm.php`, `Schemas/LeaveApplicationInfolist.php`, `Tables/LeaveApplicationsTable.php`, `Pages/ListLeaveApplications.php`, `Pages/CreateLeaveApplication.php`, `Pages/ViewLeaveApplication.php`
- Create: `app/Filament/Widgets/PendingLeaveApplications.php`
- Modify: `app/Filament/Resources/Employees/RelationManagers/LeaveLedgerEntriesRelationManager.php` (a **File leave** header action)
- Test: `tests/Feature/Leave/LeaveApplicationResourceTest.php`

**Interfaces:**
- Consumes: `LeaveApplications` (Task 4), `Charge::describe()` (Task 3), `LeaveDetail::forType()` (Task 4).
- Produces: `LeaveApplicationResource` with pages `index`, `create`, `view` only (no `edit`); table actions `approve`, `cancel`; widget `PendingLeaveApplications`.

The screen:
- **List**: filed on (`created_at`), employee (`full_name`, searchable by surname and first name), type (code), dates (`dates()`), days, without pay, status (badge). Filters: status, type, office (through the employee). Default sort newest first. Actions: view; `approve` (pending only, confirmation, `->authorize('approve')`); `cancel` (not cancelled, a required `cancel_reason` textarea, `->authorize('cancel')`). Both call the service and notify.
- **Create** ("File leave"): employee (searchable select of employees who earn credits, "Dela Cruz, Juan S. — 2020-0001", defaults to the `employee` query parameter), type (all types, labels), from, to (defaults to from), coverage (shown only when from equals to), detail (shown when `forType()` has options), detail text (shown when needed, label by context: "Country", "Illness"), commutation requested (toggle). A live **preview** line under the dates showing `Charge::describe()` from `LeaveApplications::preview()`, or the refusal's message. `handleRecordCreation()` calls `file()` and re-throws a `ValidationException` with its keys prefixed `data.`, so each message lands on its field.
- **View**: the application's fields, the breakdown as one line per date ("Tue, Nov 3, 2026: 1.000 (0.250 without pay)"), who filed, approved or cancelled and when, the reason; header actions approve and cancel.
- **Leave tab**: header action `fileLeave` ("File leave"), a link to the create page with `?employee={id}`.
- **Widget**: a stats overview, "Pending leave applications" with the count, linking to the list filtered to pending.
- Navigation: icon `Heroicon::OutlinedDocumentText`, sort 7, label "Leave applications".

- [ ] **Step 1: Generate the files**

```bash
php artisan make:filament-resource LeaveApplication --view --no-interaction
php artisan make:filament-widget PendingLeaveApplications --stats-overview --no-interaction
php artisan make:test Leave/LeaveApplicationResourceTest --phpunit --no-interaction
```

Delete the generated edit page and its route.

- [ ] **Step 2: Write the failing tests**

`setUp` as in Task 4 (start date, travel to `2026-11-02`, an employee on REG with VL `5.000`).

```php
public function test_hr_files_leave_for_an_employee(): void
{
    Livewire::test(CreateLeaveApplication::class)
        ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05'])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertSame('3.000', LeaveApplication::sole()->days);
}

public function test_the_form_shows_the_day_count_and_pay_split(): void
{
    Livewire::test(CreateLeaveApplication::class)
        ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05'])
        ->assertSee('3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay');
}

public function test_a_refused_date_shows_on_its_field(): void
{
    Livewire::test(CreateLeaveApplication::class)
        ->fillForm(['employee_id' => $this->employee->id, 'leave_type' => 'VL', 'date_from' => '2026-10-30', 'date_to' => '2026-10-30'])
        ->call('create')
        ->assertHasFormErrors(['date_from']);
}

public function test_hr_approves_a_pending_application(): void
{
    $application = app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);

    Livewire::test(ListLeaveApplications::class)->callAction(TestAction::make('approve')->table($application));

    $this->assertSame(LeaveApplicationStatus::Approved, $application->fresh()->status);
}

public function test_hr_cancels_with_a_reason(): void
{
    $application = app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);

    Livewire::test(ListLeaveApplications::class)
        ->callAction(TestAction::make('cancel')->table($application), ['cancel_reason' => 'Disapproved'])
        ->assertHasNoFormErrors();

    $this->assertSame('5.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
}

public function test_a_cancelled_application_offers_neither_action(): void
{
    $application = LeaveApplication::factory()->for($this->employee)->create(['status' => LeaveApplicationStatus::Cancelled]);

    Livewire::test(ListLeaveApplications::class)
        ->assertActionHidden(TestAction::make('approve')->table($application))
        ->assertActionHidden(TestAction::make('cancel')->table($application));
}

public function test_applications_are_never_edited_or_deleted(): void
{
    $application = LeaveApplication::factory()->create();

    $this->assertFalse(LeaveApplicationResource::hasPage('edit'));
    $this->assertFalse(auth()->user()->can('update', $application));
    $this->assertFalse(auth()->user()->can('delete', $application));
}

public function test_filing_from_the_leave_tab_picks_the_employee(): void
{
    Livewire::withQueryParams(['employee' => $this->employee->id])
        ->test(CreateLeaveApplication::class)
        ->assertSchemaStateSet(['employee_id' => $this->employee->id]);
}

public function test_the_dashboard_counts_pending_applications(): void
{
    LeaveApplication::factory()->count(2)->create();
    LeaveApplication::factory()->create(['status' => LeaveApplicationStatus::Approved]);

    Livewire::test(PendingLeaveApplications::class)->assertSee('Pending leave applications')->assertSee('2');
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/LeaveApplicationResourceTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the resource, the widget and the Leave tab action**

- [ ] **Step 5: Run them to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/LeaveApplicationResourceTest.php tests/Feature/Employees/`
Expected: PASS.

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: leave applications screen for HR`

---

## Finish

- [ ] `php artisan test --compact`: all pass (157 + about 60).
- [ ] `vendor/bin/pint --dirty --format agent`: clean.
- [ ] `php artisan migrate` on Laragon's MySQL; file, approve and cancel one application inside a transaction that is rolled back, as in L1's smoke test; check the rows.
- [ ] Before L2b: the scan of HR's leave form in `storage/app/samples/`, and who signs 7.A, 7.B and 7.C.
