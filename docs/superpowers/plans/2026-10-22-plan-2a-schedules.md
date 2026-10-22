# DTR System — Plan 2a: Schedules Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** HR can record every schedule the hospital works: fixed templates, who is on which template from when, the monthly duty roster for staff who rotate, and the year's holidays and work suspensions.

**Architecture:** Three Filament resources (Schedules, Holidays & suspensions, and a Schedules tab on the employee page) and one custom page (Duty roster). The rules sit in `app/Services/Attendance/`: `ScheduleTimes` reads a schedule's times across midnight, `ScheduleAssigner` keeps an employee's assignments from overlapping, and `Roster` reads and saves the roster grid. Nothing here computes attendance; Plan 3 reads these tables.

**Tech Stack:** Laravel 13.34, PHP 8.4, Filament 5.9 (Livewire 4), spatie/laravel-activitylog 5.1, PHPUnit 12 on SQLite in memory, MySQL 8 in use.

**Spec:** `docs/superpowers/specs/2026-10-06-dtr-system-design.md`. This is Plan 2a under "Implementation shape". Plan 2b (sync) waits on the device probe.

## Changes to the spec

Made in the spec together with this plan, for the user to review with it:

- Plan 2 is split. 2a (this plan) does not read the device; 2b (Devices, `DeviceSync`, Unmatched logs, dashboard) waits on Plan 1, Task 1, Step 7.
- A schedule's two blocks are four columns on `schedules` (`block1_in`, `block1_out`, `block2_in`, `block2_out`) instead of a `schedule_blocks` table. There are never more than two, and one row records a change to a schedule's hours in one activity-log entry.
- A new assignment ends the open-ended assignment that began before it, on the day before. Any other overlap is refused.
- `OFF` is reserved for the roster's day off and cannot be a schedule code.
- The roster is a grid. An Excel import can be added beside it if HR's roster turns out to live in Excel.

## Global Constraints

- Laravel 13, PHP 8.4, Livewire 4, Filament 5, PHPUnit. Timezone `Asia/Manila`; `Date::use(CarbonImmutable::class)` is already on.
- **No new packages and no npm build.** The duty roster page uses Filament's own CSS classes; the panel has no custom theme.
- Both roles (`admin`, `hr`) manage schedules, assignments, the roster and holidays. Every model gets a Policy that says so. The roster page authorizes inside `save()`.
- Every change to schedules, assignments, roster entries and holidays is written to the activity log: `LogsActivity` with `logFillable()->logOnlyDirty()->dontLogEmptyChanges()`, as on `Office`. Write through models, never a query-builder `update()`, so the log sees each change.
- Times are whole minutes. Every time picker uses `->seconds(false)->format('H:i:s')`. The saved value then matches what MySQL's `TIME` column returns, so an unchanged time is not logged as a change.
- Compare dates in queries with `whereDate()`. SQLite (tests) stores a `date` cast as `Y-m-d 00:00:00`, MySQL as `Y-m-d`, so a plain `where`/`whereBetween` on a date string misses rows in tests.
- Schedule codes are stored in capitals. `OFF` is the roster's day off.
- All attendance logic lives in `app/Services/Attendance/`; Filament pages and actions only call it.
- Nothing in this plan computes attendance or recomputes days. Plan 3 adds `ScheduleResolver` and `AttendanceRecomputer`.
- The **Security Guidelines** in `CLAUDE.md` apply. Employee names are user-entered text: they never go into `HtmlString` or `{!! !!}`.
- Create files with `php artisan make:* --no-interaction`, then edit them.
- Tests: `php artisan test --compact <path>`. Before handing off a task, run `vendor/bin/pint --dirty --format agent`.
- **Never run `git commit`.** The user commits. Each task ends with its changed files and a suggested commit message.

## Review Focus

1. **A night shift that crosses midnight** (22:00 → 02:00 → 03:00 → 07:00) saves with no "next day" box, while a typo that stretches the day past 24 hours is refused. Pinned by Task 1, `test_a_night_shift_runs_past_midnight` and `test_times_that_run_past_24_hours_are_refused`.
2. **Codes typed in small letters on the grid** ("n", " off ") match their schedules. An unknown code saves nothing and says which employee and date. Pinned by Task 5, `test_saving_sets_codes_and_days_off_ignoring_case` and `test_an_unknown_code_saves_nothing`.
3. **Moving someone to a new schedule from 1 November** ends the old open-ended assignment on 31 October and leaves October as it was. Pinned by Task 3, `test_a_new_schedule_ends_the_open_one_the_day_before`.
4. **A roster-only schedule assigned as a fixed one** would make every day a rest day, so nobody would ever be late. It is not offered. Pinned by Task 3, `test_a_schedule_without_work_days_is_not_offered`.
5. **A grid saved while someone else moved an employee into the office** must not wipe the roster of a row the page never showed. Only the cells sent change. Pinned by Task 5, `test_a_cell_that_was_not_sent_is_left_alone`.

---

## Before you start

- Work in `C:\laragon\www\dtr-system` on `main`, as in Plan 1. `php artisan test --compact` passes (66 tests).
- `.ai/rules` does not exist; `CLAUDE.md` is the rule set.
- The tests need no database server. The smoke test in **Finish** needs Laragon's MySQL running.

## File map

| File | Responsibility | Task |
|---|---|---|
| `app/Services/Attendance/ScheduleTimes.php` | Minutes from a schedule's first time to each time, across midnight | 1 |
| `app/Models/Schedule.php` + migration + factory, `app/Policies/SchedulePolicy.php` | A schedule template | 1, 3, 5 |
| `app/Filament/Resources/Schedules/*` | The Schedules screen | 1 |
| `app/Enums/HolidayType.php`, `app/Models/Holiday.php` + migration + factory, `app/Policies/HolidayPolicy.php` | A holiday or suspension | 2 |
| `app/Filament/Resources/Holidays/*` | The Holidays & suspensions screen | 2 |
| `app/Models/ScheduleAssignment.php` + migration + factory, `app/Policies/ScheduleAssignmentPolicy.php` | An employee on a schedule from a date | 3 |
| `app/Services/Attendance/ScheduleAssigner.php` | No overlapping assignments; a new one ends the open one | 3, 4 |
| `app/Filament/Resources/Employees/RelationManagers/ScheduleAssignmentsRelationManager.php` | The Schedules tab on an employee | 3 |
| `app/Filament/Resources/Employees/Actions/AssignScheduleBulkAction.php` | Assign a schedule to the selected employees | 4 |
| `app/Models/RosterEntry.php` + migration + factory, `app/Policies/RosterEntryPolicy.php` | One employee's rostered day | 5 |
| `app/Services/Attendance/Roster.php` | Read and save an office's month as a grid of codes | 5 |
| `app/Filament/Pages/DutyRoster.php`, `resources/views/filament/pages/duty-roster.blade.php` | The Duty roster page | 5 |

---

### Task 1: Schedules

**Files:**
- Create: `app/Services/Attendance/ScheduleTimes.php`
- Create: `database/migrations/*_create_schedules_table.php`, `app/Models/Schedule.php`, `database/factories/ScheduleFactory.php`, `app/Policies/SchedulePolicy.php`
- Create: `app/Filament/Resources/Schedules/ScheduleResource.php`, `Schemas/ScheduleForm.php`, `Tables/SchedulesTable.php`, `Pages/ListSchedules.php`, `Pages/CreateSchedule.php`, `Pages/EditSchedule.php` (pages as generated)
- Test: `tests/Feature/Schedules/ScheduleTimesTest.php`, `tests/Feature/Schedules/ScheduleResourceTest.php`

**Interfaces:**
- Consumes: `User` factory, `LogsActivity` set-up as on `App\Models\Office`.
- Produces:
  - `App\Services\Attendance\ScheduleTimes::DAY` (`1440`) and `ScheduleTimes::offsets(list<string> $times): list<int>`.
  - `App\Models\Schedule`: fillable `code, name, work_days, block1_in, block1_out, block2_in, block2_out, is_active`; casts `work_days` → `array`, `is_active` → `boolean`; `Schedule::WEEKDAYS` (`[1 => 'Mon', …, 7 => 'Sun']`); `times(): list<string>` ("H:i", 2 or 4 of them); the `hours` attribute ("08:00–12:00, 13:00–17:00"); `static assignableOptions(?int $current = null): array<int, string>`.
  - `ScheduleFactory` (Mon–Fri, 08:00–12:00 and 13:00–17:00, code `S###`) with state `rosterOnly()` (no work days).
  - `SchedulePolicy`, and `ScheduleResource` with `CreateSchedule`, `EditSchedule` (keeps the generated `DeleteAction`), `ListSchedules`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:model Schedule --migration --factory --no-interaction
php artisan make:policy SchedulePolicy --model=Schedule --no-interaction
php artisan make:class Services/Attendance/ScheduleTimes --no-interaction
php artisan make:filament-resource Schedule --no-interaction
php artisan make:test Schedules/ScheduleTimesTest --phpunit --no-interaction
php artisan make:test Schedules/ScheduleResourceTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Schedules/ScheduleTimesTest.php` (extends `Tests\TestCase`, no database):

```php
public function test_times_later_in_the_day_follow_on(): void
{
    $this->assertSame([0, 240, 300, 540], ScheduleTimes::offsets(['08:00', '12:00', '13:00', '17:00']));
}

public function test_a_time_earlier_than_the_one_before_falls_on_the_next_day(): void
{
    // Night: 22:00, 02:00, 03:00, 07:00. HR never ticks a "next day" box.
    $this->assertSame([0, 240, 300, 540], ScheduleTimes::offsets(['22:00', '02:00', '03:00', '07:00']));
}

public function test_a_time_equal_to_the_one_before_is_a_whole_day_later(): void
{
    $this->assertSame([0, 1440], ScheduleTimes::offsets(['08:00:00', '08:00:00']));
}
```

`tests/Feature/Schedules/ScheduleResourceTest.php` (`RefreshDatabase`; `setUp()` signs in `User::factory()->create()`, an hr user, as in `OfficeResourceTest`). A helper gives the Regular schedule's form:

```php
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
    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular(['code' => 'reg']))
        ->call('create')
        ->assertHasNoFormErrors();

    $schedule = Schedule::sole();

    // Codes are typed into the duty roster, so they are kept in capitals.
    $this->assertSame('REG', $schedule->code);
    $this->assertSame([1, 2, 3, 4, 5], $schedule->work_days);
    $this->assertSame(['08:00', '12:00', '13:00', '17:00'], $schedule->times());
}

public function test_a_night_shift_runs_past_midnight(): void
{
    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular([
            'code' => 'N',
            'name' => 'Night',
            'work_days' => [],
            'block1_in' => '22:00',
            'block1_out' => '02:00',
            'block2_in' => '03:00',
            'block2_out' => '07:00',
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertSame('22:00–02:00, 03:00–07:00', Schedule::sole()->hours);
}

public function test_times_that_run_past_24_hours_are_refused(): void
{
    // 07:00 typed for 13:00: read as the next morning, the day would end at
    // 17:00 the day after it began.
    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular(['block2_in' => '07:00']))
        ->call('create')
        ->assertHasFormErrors(['block1_in']);
}

public function test_a_one_block_schedule_needs_no_second_block(): void
{
    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular([
            'code' => 'AM',
            'block1_in' => '06:00',
            'block1_out' => '14:00',
            'block2_in' => null,
            'block2_out' => null,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertSame(['06:00', '14:00'], Schedule::sole()->times());
}

public function test_a_second_block_needs_both_times(): void
{
    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular(['block2_out' => null]))
        ->call('create')
        ->assertHasFormErrors(['block2_out' => 'required_with']);
}

public function test_off_is_kept_for_the_roster(): void
{
    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular(['code' => 'off']))
        ->call('create')
        ->assertHasFormErrors(['code' => 'not_in']);
}

public function test_two_schedules_cannot_share_a_code(): void
{
    Schedule::factory()->create(['code' => 'REG']);

    Livewire::test(CreateSchedule::class)
        ->fillForm($this->regular(['code' => 'reg']))
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
}

public function test_an_unused_schedule_can_be_deleted(): void
{
    $schedule = Schedule::factory()->create();

    Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
        ->callAction(DeleteAction::class);

    $this->assertModelMissing($schedule);
}

public function test_changing_a_schedules_hours_is_recorded(): void
{
    $schedule = Schedule::factory()->create();

    Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
        ->fillForm(['block1_in' => '07:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = Activity::query()
        ->where('subject_type', $schedule->getMorphClass())
        ->where('event', 'updated')
        ->sole();

    // Only the changed time: the others came back exactly as they were saved.
    $this->assertSame(['block1_in' => '07:00:00'], $activity->attribute_changes['attributes']);
}
```

- [ ] **Step 3: Run the tests to make sure they fail**

Run: `php artisan test --compact tests/Feature/Schedules`
Expected: FAIL (`ScheduleTimes::offsets` and the `Schedule` columns do not exist yet).

- [ ] **Step 4: Implement**

- `ScheduleTimes` (`final`, static like `DeviceTime`): `public const int DAY = 1440;` and `offsets(array $times): array`, documented as in the spec: each time is later than the one before it; a time that is not later falls on the next day. Read only hours and minutes, so "08:00" and "08:00:00" are the same. A step of 0 counts as a whole day.
- Migration `schedules`: `id`; `code` string(10) unique; `name` string(100); `work_days` json; `block1_in`, `block1_out` time; `block2_in`, `block2_out` time nullable; `is_active` boolean default true; timestamps.
- `Schedule` model, written like `Office` (`#[Fillable]`, `HasFactory`, `LogsActivity` with the same options, `$attributes` defaults `is_active => true`, `work_days => '[]'`):
  - `public const array WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];`
  - `times(): array`: the filled block times in order, cut to "H:i".
  - `hours(): Attribute` (getter): `times()` in pairs joined with an en dash, pairs joined with ", ".
  - `assignableOptions(?int $current = null): array`: active schedules with at least one work day, ordered by code, as `id => "REG — Regular"`. `$current` stays in the list even when it no longer qualifies, so an old assignment can still be edited. Filter `work_days` in PHP, not with a JSON query; the table holds a handful of rows.
- `SchedulePolicy`: `viewAny`, `view`, `create`, `update`, `delete` return `true`; `deleteAny` returns `false`. Docblock: both roles manage schedules; Task 3 and Task 5 restrict `delete` to unused schedules.
- `ScheduleFactory`: `code` `fake()->unique()->numerify('S###')`, `name` `fake()->words(2, true)`, `work_days` `[1, 2, 3, 4, 5]`, times `'08:00:00'`, `'12:00:00'`, `'13:00:00'`, `'17:00:00'`, `is_active` true. State `rosterOnly()`: `work_days` `[]`.
- `ScheduleResource`: `$navigationIcon = Heroicon::OutlinedClock`, `$navigationSort = 3`, `$recordTitleAttribute = 'code'`.
- `ScheduleForm`:
  - `code`: `TextInput`, required, `maxLength(10)`, `alphaDash()`, `notIn(['OFF'])`, `unique()`. `mutateStateForValidationUsing()` and `mutateDehydratedStateUsing()` both apply `fn (?string $state): ?string => $state === null ? null : mb_strtoupper(trim($state))`, so "reg" is checked and saved as "REG". Helper text: `Short, because it is typed in the duty roster: REG, CWW, AM, PM, N. OFF is the roster's day off.`
  - `name`: required, `maxLength(100)`.
  - `work_days`: `CheckboxList`, `options(Schedule::WEEKDAYS)`, `columns(7)`. Helper text: `For employees assigned this schedule. A schedule used only in the duty roster needs none.`
  - A `Section::make('Hours')` with `columns(4)`, holding four `TimePicker`s labelled "First in", "First out", "Second in", "Second out", each `->seconds(false)->format('H:i:s')`. Block 1 is required. `block2_in` is `requiredWith('block2_out')` and `block2_out` is `requiredWith('block2_in')`. Section description: `A time earlier than the one before it is the next day, so a night shift needs nothing extra.`
  - The 24-hour rule sits on `block1_in`:

    ```php
    ->rules([
        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $times = array_values(array_filter([$value, $get('block1_out'), $get('block2_in'), $get('block2_out')], filled(...)));
            $offsets = ScheduleTimes::offsets($times);

            if (end($offsets) >= ScheduleTimes::DAY) {
                $fail('From the first in to the last out must be under 24 hours. A time earlier than the one before it counts as the next day.');
            }
        },
    ])
    ```

  - `is_active`: `Toggle`, label "Active", default true.
- `SchedulesTable`: `code` (searchable, sortable), `name` (searchable), `hours`, `work_days` (label "Work days", `formatStateUsing` each day through `Schedule::WEEKDAYS`, placeholder `Roster only`), `is_active` (`IconColumn` boolean); `defaultSort('code')`; `TernaryFilter::make('is_active')`; record action `EditAction`.

- [ ] **Step 5: Run the tests to make sure they pass**

Run: `php artisan test --compact tests/Feature/Schedules`
Expected: PASS, 12 tests.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS, 78 tests). Report `git status --short` and suggest: `feat: schedule templates with one or two blocks, across midnight`.

---

### Task 2: Holidays and suspensions

**Files:**
- Create: `app/Enums/HolidayType.php`
- Create: `database/migrations/*_create_holidays_table.php`, `app/Models/Holiday.php`, `database/factories/HolidayFactory.php`, `app/Policies/HolidayPolicy.php`
- Create: `app/Filament/Resources/Holidays/HolidayResource.php`, `Schemas/HolidayForm.php`, `Tables/HolidaysTable.php`, `Pages/ListHolidays.php`, `Pages/CreateHoliday.php`, `Pages/EditHoliday.php` (pages as generated)
- Test: `tests/Feature/Holidays/HolidayResourceTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `App\Enums\HolidayType` (`Regular = 'regular'`, `Special = 'special'`, `Suspension = 'suspension'`, `HasLabel`); `App\Models\Holiday` with fillable `date, name, type, suspension_starts_at`, casts `date` → `date`, `type` → `HolidayType`; `HolidayFactory` (a regular holiday in 2026); `HolidayResource` with `CreateHoliday`, `EditHoliday`, `ListHolidays`. Plan 3 reads `holidays` by date.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:enum HolidayType --string --no-interaction
php artisan make:model Holiday --migration --factory --no-interaction
php artisan make:policy HolidayPolicy --model=Holiday --no-interaction
php artisan make:filament-resource Holiday --no-interaction
php artisan make:test Holidays/HolidayResourceTest --phpunit --no-interaction
```

`app/Enums` exists, so `make:enum` puts the file there without a prefix (Plan 1, Task 7).

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Holidays/HolidayResourceTest.php` (`RefreshDatabase`; signs in an hr user):

```php
public function test_hr_adds_a_regular_holiday(): void
{
    Livewire::test(CreateHoliday::class)
        ->fillForm([
            'date' => '2026-12-25',
            'name' => 'Christmas Day',
            'type' => HolidayType::Regular->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $holiday = Holiday::sole();

    $this->assertSame('2026-12-25', $holiday->date->toDateString());
    $this->assertSame(HolidayType::Regular, $holiday->type);
    $this->assertNull($holiday->suspension_starts_at);
}

public function test_a_suspension_keeps_the_time_it_starts(): void
{
    Livewire::test(CreateHoliday::class)
        ->fillForm([
            'date' => '2026-11-03',
            'name' => 'Typhoon',
            'type' => HolidayType::Suspension->value,
            'suspension_starts_at' => '15:00',
        ])
        ->call('create')
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

    Livewire::test(EditHoliday::class, ['record' => $holiday->getRouteKey()])
        ->fillForm(['type' => HolidayType::Special->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertNull($holiday->fresh()->suspension_starts_at);
}

public function test_a_date_holds_one_entry(): void
{
    Holiday::factory()->create(['date' => '2026-12-25']);

    Livewire::test(CreateHoliday::class)
        ->fillForm([
            'date' => '2026-12-25',
            'name' => 'Christmas Day',
            'type' => HolidayType::Regular->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['date']);
}

public function test_an_entry_keeps_its_own_date_when_edited(): void
{
    $holiday = Holiday::factory()->create(['date' => '2026-12-25', 'name' => 'Christmas']);

    Livewire::test(EditHoliday::class, ['record' => $holiday->getRouteKey()])
        ->fillForm(['name' => 'Christmas Day'])
        ->call('save')
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
```

- [ ] **Step 3: Run the tests to make sure they fail**

Run: `php artisan test --compact tests/Feature/Holidays`
Expected: FAIL (`HolidayType` and the `holidays` columns do not exist yet).

- [ ] **Step 4: Implement**

- `HolidayType` implements `HasLabel` like `EmploymentStatus`. Labels: `Regular holiday`, `Special non-working day`, `Work suspension`.
- Migration `holidays`: `id`; `date` date unique; `name` string(150); `type` string(20); `suspension_starts_at` time nullable; timestamps.
- `Holiday` model like `Office` (`#[Fillable]`, `LogsActivity` with the same options). In `booted()`, a `saving` hook sets `suspension_starts_at` to null unless `type` is `HolidayType::Suspension`. Docblock: a time only means something on a suspension.
- `HolidayPolicy`: everything `true` except `deleteAny`, which is `false`.
- `HolidayFactory`: `date` `fake()->unique()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d')`, `name` `fake()->words(3, true)`, `type` `HolidayType::Regular`, `suspension_starts_at` null.
- `HolidayResource`: `$navigationIcon = Heroicon::OutlinedFlag`, `$navigationSort = 5`, `$navigationLabel = 'Holidays & suspensions'`, `$recordTitleAttribute = 'name'`.
- `HolidayForm`:
  - `date`: `DatePicker`, required. Its uniqueness is a closure rule rather than `unique()`, which compares the plain date string and would miss the SQLite-stored `Y-m-d 00:00:00`:

    ```php
    ->rules([
        fn (?Holiday $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $taken = Holiday::query()
                ->whereDate('date', $value)
                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($taken) {
                $fail('This date already has an entry.');
            }
        },
    ])
    ```

  - `name`: required, `maxLength(150)`.
  - `type`: `Select`, `options(HolidayType::class)`, required, `live()`. Helper text: `Enter non-working days only. A special working day is an ordinary work day.`
  - `suspension_starts_at`: `TimePicker`, label "Suspended from", `->seconds(false)->format('H:i:s')`, `visible(fn (Get $get): bool => $get->enum('type', HolidayType::class) === HolidayType::Suspension)`. Helper text: `Leave blank for the whole day. Work before this time is still expected.`
- `HolidaysTable`: `date` (`date('M j, Y')`, sortable), `name` (searchable), `type` (badge), `suspension_starts_at` (`time('g:i A')`, placeholder `—`); `defaultSort('date', 'desc')`; `SelectFilter::make('type')->options(HolidayType::class)`; record action `EditAction`.

- [ ] **Step 5: Run the tests to make sure they pass**

Run: `php artisan test --compact tests/Feature/Holidays`
Expected: PASS, 6 tests.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS, 84 tests). Report `git status --short` and suggest: `feat: holidays and work suspensions, with a suspension's start time`.

---

### Task 3: Schedule assignments

**Files:**
- Create: `database/migrations/*_create_schedule_assignments_table.php`, `app/Models/ScheduleAssignment.php`, `database/factories/ScheduleAssignmentFactory.php`, `app/Policies/ScheduleAssignmentPolicy.php`
- Create: `app/Services/Attendance/ScheduleAssigner.php`
- Create: `app/Filament/Resources/Employees/RelationManagers/ScheduleAssignmentsRelationManager.php`
- Modify: `app/Models/Employee.php`, `app/Models/Schedule.php`, `app/Policies/SchedulePolicy.php`, `app/Filament/Resources/Employees/EmployeeResource.php`, `app/Filament/Resources/Employees/Pages/EditEmployee.php`
- Test: `tests/Feature/Schedules/ScheduleAssignerTest.php`, `tests/Feature/Employees/ScheduleAssignmentsRelationManagerTest.php`; modify `tests/Feature/Schedules/ScheduleResourceTest.php`

**Interfaces:**
- Consumes: `Schedule`, `ScheduleFactory::rosterOnly()`, `Schedule::assignableOptions()` (Task 1); `Employee` and its factory (Plan 1).
- Produces:
  - `App\Models\ScheduleAssignment`: fillable `employee_id, schedule_id, effective_from, effective_to`; casts both dates → `date`; `employee(): BelongsTo`, `schedule(): BelongsTo`; `describe(): string` (`REG from Nov 1, 2026 to Nov 30, 2026`, or `REG from Dec 1, 2026, with no end date`).
  - `Employee::scheduleAssignments(): HasMany`, `Schedule::assignments(): HasMany`.
  - `ScheduleAssignmentFactory`: an Employee, a Schedule, `effective_from` `'2026-01-01'`, `effective_to` null.
  - `App\Services\Attendance\ScheduleAssigner`:
    - `conflict(Employee $employee, CarbonImmutable $from, ?CarbonImmutable $to, ?ScheduleAssignment $editing = null): ?ScheduleAssignment`
    - `assign(Employee $employee, Schedule $schedule, CarbonImmutable $from, ?CarbonImmutable $to): ScheduleAssignment`
    - `assignAll(Collection $employees, Schedule $schedule, CarbonImmutable $from, ?CarbonImmutable $to): list<string>` (`Illuminate\Support\Collection` of `Employee`)
  - `ScheduleAssignmentsRelationManager` on `EmployeeResource`, shown as a tab beside the employee form.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:model ScheduleAssignment --migration --factory --no-interaction
php artisan make:policy ScheduleAssignmentPolicy --model=ScheduleAssignment --no-interaction
php artisan make:class Services/Attendance/ScheduleAssigner --no-interaction
php artisan make:filament-relation-manager EmployeeResource scheduleAssignments id --no-interaction
php artisan make:test Schedules/ScheduleAssignerTest --phpunit --no-interaction
php artisan make:test Employees/ScheduleAssignmentsRelationManagerTest --phpunit --no-interaction
```

Generate the relation manager after the model and its migration exist. Its generated form and table are replaced in Step 4.

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Schedules/ScheduleAssignerTest.php` (`RefreshDatabase`; signs in an hr user so the activity log has a causer). Fixtures in `setUp()`: `$this->employee = Employee::factory()->create()`, `$this->regular = Schedule::factory()->create(['code' => 'REG'])`, `$this->fourDay = Schedule::factory()->create(['code' => 'CWW'])`, `$this->assigner = app(ScheduleAssigner::class)`, and a helper `day(string $date): CarbonImmutable` returning `CarbonImmutable::parse($date)`.

```php
public function test_a_new_schedule_ends_the_open_one_the_day_before(): void
{
    $old = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-01-01', 'effective_to' => null]);

    $new = $this->assigner->assign($this->employee, $this->fourDay, $this->day('2026-11-01'), null);

    // October stays on Regular; November onwards is the 4-Day Week.
    $this->assertSame('2026-10-31', $old->fresh()->effective_to->toDateString());
    $this->assertSame('2026-11-01', $new->effective_from->toDateString());
    $this->assertNull($new->effective_to);
}

public function test_ending_the_open_assignment_is_recorded(): void
{
    $old = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-01-01', 'effective_to' => null]);

    $this->assigner->assign($this->employee, $this->fourDay, $this->day('2026-11-01'), null);

    // Through the model, not a query-builder update, or the log never sees it.
    $activity = Activity::query()
        ->where('subject_type', $old->getMorphClass())
        ->where('subject_id', $old->id)
        ->where('event', 'updated')
        ->sole();

    $this->assertArrayHasKey('effective_to', $activity->attribute_changes['attributes']);
}

public function test_a_period_inside_a_closed_assignment_is_a_conflict(): void
{
    $closed = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-12-31']);

    $conflict = $this->assigner->conflict($this->employee, $this->day('2026-11-01'), null);

    $this->assertTrue($conflict->is($closed));
}

public function test_an_open_assignment_that_starts_later_is_a_conflict(): void
{
    $later = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);

    $conflict = $this->assigner->conflict($this->employee, $this->day('2026-11-01'), null);

    $this->assertTrue($conflict->is($later));
}

public function test_a_period_that_ends_before_a_later_assignment_fits(): void
{
    ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);

    $this->assertNull($this->assigner->conflict($this->employee, $this->day('2026-11-01'), $this->day('2026-11-30')));
}

public function test_an_edit_may_overlap_nothing_but_itself(): void
{
    $regular = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-10-31']);
    $fourDay = ScheduleAssignment::factory()->for($this->employee)->for($this->fourDay)
        ->create(['effective_from' => '2026-11-01', 'effective_to' => null]);

    // Stretching Regular into November runs into the 4-Day Week.
    $conflict = $this->assigner->conflict($this->employee, $this->day('2026-01-01'), $this->day('2026-11-15'), editing: $regular);
    $this->assertTrue($conflict->is($fourDay));

    // An open-ended assignment is not cut short by editing it.
    $this->assertNull($this->assigner->conflict($this->employee, $this->day('2026-11-01'), null, editing: $fourDay));
}

public function test_one_conflict_assigns_nobody(): void
{
    $free = Employee::factory()->create();
    $busy = Employee::factory()->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
    ScheduleAssignment::factory()->for($busy)->for($this->regular)
        ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);

    $problems = $this->assigner->assignAll(collect([$free, $busy]), $this->fourDay, $this->day('2026-11-01'), null);

    $this->assertSame(['Dela Cruz, Juan S.: already on REG from Dec 1, 2026, with no end date.'], $problems);
    $this->assertSame(0, $free->scheduleAssignments()->count());
}
```

`tests/Feature/Employees/ScheduleAssignmentsRelationManagerTest.php` (`RefreshDatabase`; signs in an hr user). Fixtures: `$this->employee`, `$this->regular` (code `REG`), `$this->fourDay` (code `CWW`), and a helper:

```php
private function manager(): Testable
{
    return Livewire::test(ScheduleAssignmentsRelationManager::class, [
        'ownerRecord' => $this->employee,
        'pageClass' => EditEmployee::class,
    ]);
}

public function test_hr_gives_an_employee_a_schedule(): void
{
    $this->manager()
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'schedule_id' => $this->regular->id,
            'effective_from' => '2026-11-01',
        ])
        ->assertHasNoFormErrors();

    $assignment = $this->employee->scheduleAssignments()->sole();

    $this->assertTrue($assignment->schedule->is($this->regular));
    $this->assertSame('2026-11-01', $assignment->effective_from->toDateString());
    $this->assertNull($assignment->effective_to);
}

public function test_a_schedule_without_work_days_is_not_offered(): void
{
    // Every day of it would be a rest day: nobody would ever be late.
    $night = Schedule::factory()->rosterOnly()->create(['code' => 'N']);

    $this->manager()
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'schedule_id' => $night->id,
            'effective_from' => '2026-11-01',
        ])
        ->assertHasFormErrors(['schedule_id' => 'in']);

    $this->assertSame(0, ScheduleAssignment::count());
}

public function test_an_overlapping_edit_is_refused(): void
{
    $regular = ScheduleAssignment::factory()->for($this->employee)->for($this->regular)
        ->create(['effective_from' => '2026-01-01', 'effective_to' => '2026-10-31']);
    ScheduleAssignment::factory()->for($this->employee)->for($this->fourDay)
        ->create(['effective_from' => '2026-11-01', 'effective_to' => null]);

    $this->manager()
        ->callAction(TestAction::make(EditAction::class)->table($regular), [
            'effective_to' => '2026-11-15',
        ])
        ->assertHasFormErrors(['effective_from']);

    $this->assertSame('2026-10-31', $regular->fresh()->effective_to->toDateString());
}
```

Add to `tests/Feature/Schedules/ScheduleResourceTest.php`:

```php
public function test_a_schedule_in_use_cannot_be_deleted(): void
{
    $schedule = Schedule::factory()->create();
    ScheduleAssignment::factory()->for($schedule)->create();

    Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);
}
```

- [ ] **Step 3: Run the tests to make sure they fail**

Run: `php artisan test --compact tests/Feature/Schedules tests/Feature/Employees`
Expected: FAIL (`ScheduleAssigner` and the `schedule_assignments` columns do not exist yet).

- [ ] **Step 4: Implement**

- Migration `schedule_assignments`: `id`; `employee_id` and `schedule_id` `foreignId()->constrained()->restrictOnDelete()`; `effective_from` date; `effective_to` date nullable; timestamps; index `(employee_id, effective_from)`.
- `ScheduleAssignment` model like `Office` (`LogsActivity` with the same options). `describe()` formats dates with `'M j, Y'`.
- `Employee::scheduleAssignments()` and `Schedule::assignments()`: `HasMany` with the generic PHPDoc used on `Office::employees()`.
- `SchedulePolicy::delete()`: `! $schedule->assignments()->exists()`. Docblock: a schedule in use is deactivated, not deleted.
- `ScheduleAssignmentPolicy`: everything `true` except `deleteAny`, which is `false`.
- `ScheduleAssigner` (`final`):
  - `conflict()`: the employee's earliest assignment, other than `$editing`, whose period overlaps `$from`–`$to` (`null` is open-ended): it starts on or before `$to` and ends on or after `$from`. When `$editing` is null, an open-ended assignment that began before `$from` is left out, because `assign()` ends it. Compare with `whereDate()`.
  - `assign()`: in a transaction, throw `InvalidArgumentException` when `conflict()` finds one. Otherwise end the open-ended assignment that began before `$from` on `$from->subDay()`, updating it through the model so the activity log records it, and create the new assignment.
  - `assignAll()`: in a transaction, collect `"{$employee->full_name}: already on {$conflict->describe()}."` for each employee with a conflict. Return the list unsaved if it is not empty; otherwise `assign()` each employee and return `[]`.
- `ScheduleAssignmentsRelationManager`: `$relationship = 'scheduleAssignments'`, `$title = 'Schedules'`, `$modelLabel = 'schedule'`.
  - Form: `schedule_id` `Select`, label "Schedule", `options(fn (?ScheduleAssignment $record): array => Schedule::assignableOptions($record?->schedule_id))`, required; `effective_from` `DatePicker`, label "From", required, with the conflict rule below; `effective_to` `DatePicker`, label "To", `afterOrEqual('effective_from')`, helper text `Leave blank while it is still in effect.`

    ```php
    ->rules([
        fn (Get $get, RelationManager $livewire, ?ScheduleAssignment $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $livewire, $record): void {
            $conflict = app(ScheduleAssigner::class)->conflict(
                $livewire->getOwnerRecord(),
                CarbonImmutable::parse($value),
                filled($get('effective_to')) ? CarbonImmutable::parse($get('effective_to')) : null,
                $record,
            );

            if ($conflict !== null) {
                $fail("Overlaps {$conflict->describe()}.");
            }
        },
    ])
    ```

  - Table: `schedule.code` (label "Code"), `schedule.name`, `effective_from` (`date('M j, Y')`, label "From"), `effective_to` (`date('M j, Y')`, label "To", placeholder `No end date`); `recordTitle(fn (ScheduleAssignment $record): string => $record->describe())`; `defaultSort('effective_from', 'desc')`.
  - Header action `CreateAction`, label "Assign schedule", modal description `An open-ended schedule that started earlier ends the day before.`, saving through the service:

    ```php
    ->using(fn (array $data, RelationManager $livewire, ScheduleAssigner $assigner): ScheduleAssignment => $assigner->assign(
        $livewire->getOwnerRecord(),
        Schedule::findOrFail($data['schedule_id']),
        CarbonImmutable::parse($data['effective_from']),
        filled($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to']) : null,
    ))
    ```

  - Record actions `EditAction`, `DeleteAction`.
- `EmployeeResource::getRelations()`: `[ScheduleAssignmentsRelationManager::class]`.
- `EditEmployee`: `hasCombinedRelationManagerTabsWithContent()` returns `true`, and `getContentTabLabel()` returns `'Employee'`, so the page shows **Employee** and **Schedules** tabs.

- [ ] **Step 5: Run the tests to make sure they pass**

Run: `php artisan test --compact tests/Feature/Schedules tests/Feature/Employees`
Expected: PASS. Task 3 adds 11 tests.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS, 95 tests). Report `git status --short` and suggest: `feat: schedule assignments on the employee page, without overlaps`.

---

### Task 4: Assign a schedule to many employees

**Files:**
- Create: `app/Filament/Resources/Employees/Actions/AssignScheduleBulkAction.php`
- Modify: `app/Filament/Resources/Employees/Tables/EmployeesTable.php`
- Test: `tests/Feature/Employees/AssignScheduleBulkActionTest.php`

**Interfaces:**
- Consumes: `ScheduleAssigner::assignAll()`, `Schedule::assignableOptions()`, `ScheduleAssignment` (Task 3).
- Produces: bulk action `assignSchedule` on the Employees list.

- [ ] **Step 1: Generate the test**

```bash
php artisan make:test Employees/AssignScheduleBulkActionTest --phpunit --no-interaction
```

The action is a plain class next to `ImportEmployeesAction`; there is no generator for it.

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Employees/AssignScheduleBulkActionTest.php` (`RefreshDatabase`; signs in an hr user):

```php
public function test_hr_assigns_a_schedule_to_the_selected_employees(): void
{
    [$first, $second, $notSelected] = Employee::factory()->count(3)->create()->all();
    $fourDay = Schedule::factory()->create(['code' => 'CWW']);

    Livewire::test(ListEmployees::class)
        ->selectTableRecords([$first->id, $second->id])
        ->callAction(TestAction::make('assignSchedule')->table()->bulk(), [
            'schedule_id' => $fourDay->id,
            'effective_from' => '2026-11-01',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Schedule assigned to 2 employees');

    $this->assertSame(1, $first->scheduleAssignments()->count());
    $this->assertSame(1, $second->scheduleAssignments()->count());
    $this->assertSame(0, $notSelected->scheduleAssignments()->count());
}

public function test_a_conflict_assigns_nobody(): void
{
    $free = Employee::factory()->create();
    $busy = Employee::factory()->create();
    ScheduleAssignment::factory()->for($busy)
        ->create(['effective_from' => '2026-12-01', 'effective_to' => null]);
    $fourDay = Schedule::factory()->create(['code' => 'CWW']);

    Livewire::test(ListEmployees::class)
        ->selectTableRecords([$free->id, $busy->id])
        ->callAction(TestAction::make('assignSchedule')->table()->bulk(), [
            'schedule_id' => $fourDay->id,
            'effective_from' => '2026-11-01',
        ])
        ->assertNotified('Nobody was assigned');

    $this->assertSame(0, $free->scheduleAssignments()->count());
}
```

- [ ] **Step 3: Run the tests to make sure they fail**

Run: `php artisan test --compact tests/Feature/Employees/AssignScheduleBulkActionTest.php`
Expected: FAIL (no `assignSchedule` action).

- [ ] **Step 4: Implement**

- `AssignScheduleBulkAction extends BulkAction`, written like `ImportEmployeesAction`: `getDefaultName()` returns `'assignSchedule'`; in `setUp()`:
  - label `Assign schedule`, icon `Heroicon::OutlinedClock`, `authorize('create', ScheduleAssignment::class)`, `authorizeIndividualRecords('update')`, `deselectRecordsAfterCompletion()`;
  - modal description `An open-ended schedule that started earlier ends the day before. If anyone selected already has a schedule in this period, nobody is assigned.`;
  - schema: the same three fields as the relation manager's form, without the conflict rule (`assignAll()` checks each employee): `schedule_id` with `options(Schedule::assignableOptions())`, `effective_from` required, `effective_to` `afterOrEqual('effective_from')`;
  - `action(function (Collection $records, array $data, ScheduleAssigner $assigner): void { … })` calls `assignAll()`. When problems come back: a `danger()`, `persistent()` notification titled `Nobody was assigned` whose body is the first five problems joined by a space, plus `…and N more.` past five, as plain text (it carries names, so no `HtmlString`). Otherwise: `success()`, titled `Schedule assigned to {n} employees` (`str('employee')->plural($n)` for one).
- `EmployeesTable`: add `->toolbarActions([AssignScheduleBulkAction::make()])`.

- [ ] **Step 5: Run the tests to make sure they pass**

Run: `php artisan test --compact tests/Feature/Employees`
Expected: PASS.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS, 97 tests). Report `git status --short` and suggest: `feat: assign a schedule to the selected employees, all or none`.

---

### Task 5: Duty roster

**Files:**
- Create: `database/migrations/*_create_roster_entries_table.php`, `app/Models/RosterEntry.php`, `database/factories/RosterEntryFactory.php`, `app/Policies/RosterEntryPolicy.php`
- Create: `app/Services/Attendance/Roster.php`
- Create: `app/Filament/Pages/DutyRoster.php`, `resources/views/filament/pages/duty-roster.blade.php`
- Modify: `app/Models/Employee.php`, `app/Models/Schedule.php`, `app/Policies/SchedulePolicy.php`
- Test: `tests/Feature/Roster/RosterTest.php`, `tests/Feature/Roster/DutyRosterPageTest.php`; modify `tests/Feature/Schedules/ScheduleResourceTest.php`

**Interfaces:**
- Consumes: `Schedule`, `ScheduleFactory::rosterOnly()` (Task 1); `Office`, `Employee` (Plan 1).
- Produces:
  - `App\Models\RosterEntry`: fillable `employee_id, date, schedule_id` (`schedule_id` null is a day off); casts `date` → `date`; `employee(): BelongsTo`, `schedule(): BelongsTo`.
  - `Employee::rosterEntries(): HasMany`, `Schedule::rosterEntries(): HasMany`.
  - `RosterEntryFactory`: an Employee, `date` `'2026-11-01'`, a `rosterOnly()` Schedule.
  - `App\Services\Attendance\Roster`:
    - `public const string OFF = 'OFF';`
    - `employees(Office $office): Collection<int, Employee>`: the office's active employees, surname first.
    - `read(Office $office, CarbonImmutable $month): array<int, array<int, string>>`: employee id → day of the month → code, `OFF` or `''`, for every employee and every day.
    - `save(Office $office, CarbonImmutable $month, array $cells): int`, throwing `ValidationException`.
  - Page `App\Filament\Pages\DutyRoster` at `/duty-roster`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:model RosterEntry --migration --factory --no-interaction
php artisan make:policy RosterEntryPolicy --model=RosterEntry --no-interaction
php artisan make:class Services/Attendance/Roster --no-interaction
php artisan make:filament-page DutyRoster --no-interaction
php artisan make:test Roster/RosterTest --phpunit --no-interaction
php artisan make:test Roster/DutyRosterPageTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Roster/RosterTest.php` (`RefreshDatabase`; signs in an hr user). Fixtures in `setUp()`:

```php
$this->nursing = Office::factory()->create(['name' => 'Nursing Service']);
$this->juan = Employee::factory()->for($this->nursing)->create(['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_name' => 'Santos']);
$this->maria = Employee::factory()->for($this->nursing)->create(['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => 'Lopez']);
$this->reyes = Employee::factory()->create(['last_name' => 'Reyes']); // another office
$this->am = Schedule::factory()->rosterOnly()->create(['code' => 'AM', 'block1_in' => '06:00:00', 'block1_out' => '14:00:00', 'block2_in' => null, 'block2_out' => null]);
$this->pm = Schedule::factory()->rosterOnly()->create(['code' => 'PM', 'block1_in' => '14:00:00', 'block1_out' => '22:00:00', 'block2_in' => null, 'block2_out' => null]);
$this->night = Schedule::factory()->rosterOnly()->create(['code' => 'N', 'block1_in' => '22:00:00', 'block1_out' => '06:00:00', 'block2_in' => null, 'block2_out' => null]);
$this->november = CarbonImmutable::parse('2026-11-01');
$this->roster = app(Roster::class);
```

```php
public function test_it_reads_the_month_as_a_grid_of_codes(): void
{
    RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);
    // The last day: a plain date comparison would miss it on SQLite.
    RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-30', 'schedule_id' => null]);

    $grid = $this->roster->read($this->nursing, $this->november);

    $this->assertSame([$this->juan->id, $this->maria->id], array_keys($grid));
    $this->assertCount(30, $grid[$this->juan->id]);
    $this->assertSame('AM', $grid[$this->juan->id][1]);
    $this->assertSame('', $grid[$this->juan->id][2]);
    $this->assertSame('OFF', $grid[$this->juan->id][30]);
}

public function test_saving_sets_codes_and_days_off_ignoring_case(): void
{
    $changes = $this->roster->save($this->nursing, $this->november, [
        $this->juan->id => [1 => 'am', 2 => ' n ', 3 => 'off'],
    ]);

    $grid = $this->roster->read($this->nursing, $this->november);

    $this->assertSame(3, $changes);
    $this->assertSame(['AM', 'N', 'OFF'], [$grid[$this->juan->id][1], $grid[$this->juan->id][2], $grid[$this->juan->id][3]]);
}

public function test_a_blank_cell_removes_its_entry(): void
{
    // Back to the employee's fixed schedule, if they have one.
    RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-05', 'schedule_id' => $this->pm->id]);

    $changes = $this->roster->save($this->nursing, $this->november, [$this->juan->id => [5 => '']]);

    $this->assertSame(1, $changes);
    $this->assertSame(0, RosterEntry::count());
}

public function test_a_cell_that_was_not_sent_is_left_alone(): void
{
    // Maria joined the office after the page was opened: her row was never
    // on it, so saving the page must not empty her month.
    RosterEntry::factory()->for($this->maria)->create(['date' => '2026-11-05', 'schedule_id' => $this->pm->id]);

    $this->roster->save($this->nursing, $this->november, [$this->juan->id => [5 => 'AM']]);

    $this->assertSame(2, RosterEntry::count());
}

public function test_an_unknown_code_saves_nothing(): void
{
    try {
        $this->roster->save($this->nursing, $this->november, [
            $this->juan->id => [1 => 'AM', 2 => 'NN'],
        ]);
        $this->fail('An unknown code was accepted.');
    } catch (ValidationException $exception) {
        $this->assertSame([
            "cells.{$this->juan->id}.2" => ['"NN" for Dela Cruz, Juan S. on Nov 2 is not a schedule code.'],
        ], $exception->errors());
    }

    $this->assertSame(0, RosterEntry::count());
}

public function test_saving_an_unchanged_grid_changes_nothing(): void
{
    RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);
    $logged = Activity::count();

    $changes = $this->roster->save($this->nursing, $this->november, $this->roster->read($this->nursing, $this->november));

    $this->assertSame(0, $changes);
    $this->assertSame($logged, Activity::count());
}

public function test_cells_outside_the_office_or_the_month_are_ignored(): void
{
    $changes = $this->roster->save($this->nursing, $this->november, [
        $this->reyes->id => [1 => 'AM'],
        $this->juan->id => [31 => 'AM'],
    ]);

    $this->assertSame(0, $changes);
    $this->assertSame(0, RosterEntry::count());
}

public function test_changing_a_cell_is_recorded(): void
{
    $entry = RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);

    $this->roster->save($this->nursing, $this->november, [$this->juan->id => [1 => 'PM']]);

    $activity = Activity::query()
        ->where('subject_type', $entry->getMorphClass())
        ->where('event', 'updated')
        ->sole();

    $this->assertSame($this->pm->id, $activity->attribute_changes['attributes']['schedule_id']);
}
```

`tests/Feature/Roster/DutyRosterPageTest.php` (`RefreshDatabase`; signs in an hr user). Fixtures: `$this->nursing` office, `$this->juan` in it (Dela Cruz, Juan Santos), `$this->am` and `$this->night` (`rosterOnly()`, codes `AM` and `N`).

```php
public function test_hr_opens_the_duty_roster(): void
{
    $this->get(DutyRoster::getUrl())->assertOk();
}

public function test_choosing_an_office_and_a_month_loads_its_grid(): void
{
    RosterEntry::factory()->for($this->juan)->create(['date' => '2026-11-01', 'schedule_id' => $this->am->id]);

    Livewire::test(DutyRoster::class)
        ->set('officeId', $this->nursing->id)
        ->set('month', '2026-11')
        ->assertSet("cells.{$this->juan->id}.1", 'AM')
        ->assertSee('Dela Cruz, Juan S.');
}

public function test_saving_the_grid_writes_the_roster(): void
{
    Livewire::test(DutyRoster::class)
        ->set('officeId', $this->nursing->id)
        ->set('month', '2026-11')
        ->set("cells.{$this->juan->id}.2", 'n')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('Roster saved: 1 change');

    $entry = RosterEntry::sole();

    $this->assertSame('2026-11-02', $entry->date->toDateString());
    $this->assertSame($this->night->id, $entry->schedule_id);
}

public function test_an_unknown_code_marks_its_cell(): void
{
    Livewire::test(DutyRoster::class)
        ->set('officeId', $this->nursing->id)
        ->set('month', '2026-11')
        ->set("cells.{$this->juan->id}.2", 'NN')
        ->call('save')
        ->assertHasErrors(["cells.{$this->juan->id}.2"]);

    $this->assertSame(0, RosterEntry::count());
}
```

Add to `tests/Feature/Schedules/ScheduleResourceTest.php`:

```php
public function test_a_schedule_on_the_roster_cannot_be_deleted(): void
{
    $schedule = Schedule::factory()->rosterOnly()->create();
    RosterEntry::factory()->for($schedule)->create();

    Livewire::test(EditSchedule::class, ['record' => $schedule->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);
}
```

- [ ] **Step 3: Run the tests to make sure they fail**

Run: `php artisan test --compact tests/Feature/Roster tests/Feature/Schedules`
Expected: FAIL (`Roster`, `RosterEntry` columns and the page do not exist yet).

- [ ] **Step 4: Implement**

- Migration `roster_entries`: `id`; `employee_id` `foreignId()->constrained()->restrictOnDelete()`; `date` date; `schedule_id` `foreignId()->nullable()->constrained()->restrictOnDelete()`; timestamps; unique `(employee_id, date)`.
- `RosterEntry` model like `Office` (`LogsActivity` with the same options). `Employee::rosterEntries()` and `Schedule::rosterEntries()`: `HasMany`.
- `SchedulePolicy::delete()`: also `! $schedule->rosterEntries()->exists()`.
- `RosterEntryPolicy`: everything `true` except `deleteAny`, which is `false`.
- `Roster` (`final`):
  - `employees()`: `$office->employees()->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get()`.
  - `read()`: one query for the month's entries of those employees (`whereDate()` on both ends, with `schedule`), then a full grid: every employee, days `1` to `$month->daysInMonth`, `''` where there is no entry.
  - `save()`: codes are every schedule's code, active or not, so an older month still saves. Process only the office's employees whose id is a key of `$cells`, and only days of the month that are keys of their row. Trim and upper-case each cell: `''` removes the entry, `OFF` is a day off (`schedule_id` null), a known code is that schedule, anything else is an error keyed `cells.{employee id}.{day}` with the message `"{typed}" for {full name} on {M j} is not a schedule code.` (the cell's text as typed, trimmed). Check every cell first and throw `ValidationException::withMessages()` while there is an error. Then, in a transaction, compare each cell with the stored entry and create, update or delete only what differs, through the model. Return the number of rows written.
- `DutyRoster` page (`extends Page`):
  - `protected string $view = 'filament.pages.duty-roster';`, `$navigationIcon = Heroicon::OutlinedCalendarDays`, `$navigationSort = 4`, `$title = 'Duty roster'`.
  - `public int|string|null $officeId = null;` (the dropdown sends a string), `public string $month = '';` (`Y-m`, from `<input type="month">`), `/** @var array<int, array<int, string>> */ public array $cells = [];`.
  - `canAccess()`: `auth()->user()?->can('viewAny', RosterEntry::class) ?? false`.
  - `mount()`: `$this->month = now()->format('Y-m')`.
  - `updatedOfficeId()` and `updatedMonth()` call `loadCells()`. Comment on them: `// shortcut: switching office or month drops unsaved cells; add a confirm if HR loses work this way.`
  - `loadCells()`: empty `$cells` and reset the error bag first, return if no office is chosen, then validate `officeId` (`required`, `exists:offices,id`) and `month` (`required`, `date_format:Y-m`) with `$this->validate()`, and fill `$cells` from `Roster::read()`. Emptying first means a failed validation never leaves the old month's cells under a new month.
  - `save(Roster $roster): void`: `Gate::authorize('create', RosterEntry::class)`, validate as in `loadCells()`, call `$roster->save(Office::findOrFail($this->officeId), CarbonImmutable::createFromFormat('!Y-m', $this->month), $this->cells)`. Livewire turns the service's `ValidationException` into errors on the cells. Notify `success()`: `Roster saved: {n} change(s)` (`str('change')->plural($n)`), or `No changes to save` for 0, then `loadCells()`.
  - `getViewData()`: `offices` (active, by name, `id => name`), `schedules` (active, by code, for the legend), `employees` (`Roster::employees()` of the chosen office, or an empty collection), `days` (one `CarbonImmutable` per day of `$this->month`, or `[]` when `CarbonImmutable::hasFormat($this->month, 'Y-m')` is false). Built from the chosen office and month on the server, not from the keys of `$cells`.
- `duty-roster.blade.php`. Filament's compiled CSS has no utilities for app views, so layout is inline and the cells use Filament's input classes, which follow dark mode:

  ```blade
  <x-filament-panels::page>
      <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
          <x-filament::input.wrapper>
              <x-filament::input.select wire:model.live="officeId" aria-label="Office">
                  <option value="">Choose an office</option>
                  @foreach ($offices as $id => $name)
                      <option value="{{ $id }}">{{ $name }}</option>
                  @endforeach
              </x-filament::input.select>
          </x-filament::input.wrapper>
          <x-filament::input.wrapper>
              <x-filament::input type="month" wire:model.live="month" aria-label="Month" />
          </x-filament::input.wrapper>
      </div>

      @if ($errors->any())
          <ul role="alert"> {{-- one <li> per $errors->all() message, in the danger colour --}} </ul>
      @endif

      @if ($employees->isNotEmpty() && $days !== [])
          <x-filament::section>
              {{-- Legend: each $schedules code with its $schedule->hours; "OFF day off"; "blank: the employee's fixed schedule". --}}
              {{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
              <div style="overflow-x: auto; background-color: inherit;">
                  <table style="border-collapse: collapse; background-color: inherit;">
                      {{-- <thead>: a sticky "Employee" header cell, then per day $day->format('j') over mb_substr($day->format('D'), 0, 2); weekend columns shaded with rgba(127, 127, 127, 0.08), which reads in both themes. --}}
                      <tbody style="background-color: inherit;">
                          @foreach ($employees as $employee)
                              <tr style="background-color: inherit;">
                                  <th scope="row" style="position: sticky; left: 0; z-index: 1; background-color: inherit; text-align: start; white-space: nowrap; padding: 0.25rem 0.5rem;">{{ $employee->full_name }}</th>
                                  @foreach ($days as $day)
                                      @php($cell = "cells.{$employee->id}.{$day->day}")
                                      <td style="padding: 0.125rem;">
                                          <div @class(['fi-input-wrp', 'fi-invalid' => $errors->has($cell)])>
                                              <div class="fi-input-wrp-content-ctn">
                                                  <input
                                                      wire:model="{{ $cell }}"
                                                      class="fi-input"
                                                      maxlength="10"
                                                      aria-label="{{ $employee->full_name }}, {{ $day->format('M j') }}"
                                                      style="width: 3rem; padding-inline: 0.25rem; text-align: center; text-transform: uppercase;"
                                                  />
                                              </div>
                                          </div>
                                      </td>
                                  @endforeach
                              </tr>
                          @endforeach
                      </tbody>
                  </table>
              </div>
              <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem;">
                  <x-filament::button wire:click="save">Save roster</x-filament::button>
                  <span wire:dirty>Unsaved changes</span>
              </div>
          </x-filament::section>
      @elseif (filled($officeId))
          <p>This office has no active employees.</p>
      @endif
  </x-filament-panels::page>
  ```

  The `background-color: inherit` chain carries the section's background up to the sticky name column, so the names stay readable over the scrolled cells in light and dark mode.

- [ ] **Step 5: Run the tests to make sure they pass**

Run: `php artisan test --compact tests/Feature/Roster tests/Feature/Schedules`
Expected: PASS. Task 5 adds 13 tests.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS, 110 tests). Report `git status --short` and suggest: `feat: duty roster grid per office and month`.

---

## Finish

- [ ] Run `php artisan test --compact`. Expected: PASS, 110 tests (66 from Plan 1, 44 from this plan).
- [ ] Run `vendor/bin/pint --dirty --format agent`. Expected: no changes left.
- [ ] With Laragon's MySQL running, run `php artisan migrate` on the development database.
- [ ] Smoke test at `http://dtr-system.test`, signed in as the admin:
  1. **Schedules:** add REG (Mon–Fri, 08:00–12:00 and 13:00–17:00), AM (06:00–14:00, no work days) and N (22:00–02:00 and 03:00–07:00, no work days). The list shows N's hours as `22:00–02:00, 03:00–07:00`.
  2. **Employees:** open one employee, then the **Schedules** tab, and assign REG from the 1st of this month. Select two employees in the list and run **Assign schedule**.
  3. **Holidays & suspensions:** add 25 December (regular holiday) and a suspension at 3:00 PM.
  4. **Duty roster:** choose an office and this month, type `am`, `n` and `off` in a few cells, then **Save roster**. Reload: the cells show `AM`, `N` and `OFF`. Scroll right: the names stay in view. Switch the panel to dark mode: the grid stays readable.
- [ ] Tell the user:
  - which steps passed, with the test count;
  - that Plan 2b waits on the probe of the four devices, and on IT's answer to whether each person has the same biometric ID on every device;
  - whether the nursing roster lives in Excel, which decides if an import is worth adding beside the grid;
  - that this year's holidays can now be entered.
