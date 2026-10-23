# Leave Module — Plan L1: Balances Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** HR has every employee's VL, SL, SPL and Wellness balance in the system, kept current without hand computation, and can answer "meron pa ba akong SL?" from it.

**Architecture:** One ledger table, `leave_ledger_entries`, where a balance is the sum of an employee's rows for a type. Three services in `app/Services/Leave/`: `LeaveLedger` reads balances and records HR adjustments, `LeavePosting` posts the monthly credits and the year-end rows (`php artisan leave:post`, hourly), and `OpeningBalances` reads and saves the opening grid. Filament adds an **Opening balances** page and a **Leave** tab on the employee.

**Tech Stack:** Laravel 13, PHP 8.4 (bcmath on), Filament 5.9 (Livewire 4), spatie/laravel-activitylog 5.1, PHPUnit 12 on SQLite in memory, MySQL 8 in use.

**Spec:** `docs/superpowers/specs/2026-10-23-leave-module-design.md`, plan **L1** under "Implementation shape". The DTR spec (`2026-10-06-dtr-system-design.md`) still governs everything it already covers.

## Changes to the spec

To be made in the spec together with this plan, for the user to review with it:

- `ScheduleResolver` and `schedules.leave_days` move from L1 to L2. Nothing in L1 counts the days of an application, so building them here would leave them unused until L2.
- `employees.position` moves to L2, with the form that prints it.
- The opening grid accepts an employee's **first** save at any time, even after credits have posted (HR may enter an office late). Only a **re-save** is refused once the employee has a row of another kind.
- Days without pay arrive with applications in L2, so in L1 the monthly credit is prorated for hiring and separation only. L2 feeds days without pay into the same formula and adds `credit_correction` rows.

## Global Constraints

- Laravel 13, PHP 8.4, Livewire 4, Filament 5, PHPUnit. Timezone `Asia/Manila`; `Date::use(CarbonImmutable::class)` is already on.
- **No new packages and no npm build.** The opening grid uses Filament's own CSS classes, like the duty roster.
- **Days are decimal strings with three places** (`'1.250'`, `'-3.000'`). Add, subtract and compare them with bcmath at scale 3 (`bcadd`, `bcsub`, `bccomp`), never with floats. A sum read from the database goes through `number_format((float) $sum, 3, '.', '')` once, because SQLite returns a float and MySQL a string.
- **A ledger row is never updated or deleted**, except opening rows through `OpeningBalances::save()` before the employee is locked (Task 4). Policies refuse update and delete.
- **Posted rows** (monthly credit, yearly grant, lapse, forced leave) are written with `LeaveLedgerEntry::query()->insertOrIgnore()`, each with its `posting_key`, so a second or overlapping run posts nothing. They are not written to the activity log. **HR's rows** (opening, adjustment) are written through the model, so `LogsActivity` records them with the causer.
- Posting keys: `credit:{employee_id}:{type}:{Y-m}`, `grant:{employee_id}:{type}:{Y}`, `lapse:{employee_id}:{type}:{Y}`, `forced:{employee_id}:{Y}`, `opening:{employee_id}:{type}`.
- An employee **earns** on a date when `employment_status` is permanent or co-terminous, `is_active` is true, the row is not soft-deleted, `date_hired` is null or on or before the date, and `date_separated` is null or on or after it.
- `LEAVE_START_DATE` (`config('leave.start_date')`) is always the 1st of a month. Opening rows are dated the day before it.
- Compare dates in queries with `whereDate()`. SQLite stores a `date` cast as `Y-m-d 00:00:00`, MySQL as `Y-m-d`.
- Both roles (`admin`, `hr`) manage balances in L1. Pages authorize inside their save method; actions authorize with `->authorize()`.
- The **Security Guidelines** in `CLAUDE.md` apply. Employee names and remarks are user-entered text: never `HtmlString` or `{!! !!}`.
- Create files with `php artisan make:* --no-interaction`, then edit them.
- Tests: `php artisan test --compact <path>`. Before handing off a task, run `vendor/bin/pint --dirty --format agent`.
- **Never run `git commit`.** The user commits. Each task ends with its changed files and a suggested commit message.

## Review Focus

1. **`LEAVE_START_DATE` missing, or not the 1st of a month.** `leave:post` must refuse with a message that says what to set, and post nothing, rather than crediting from an arbitrary date. Pinned by Task 2, `test_the_command_refuses_without_a_start_date` and `test_the_command_refuses_a_start_date_that_is_not_the_first`.
2. **A cell typed as `1.2505`, `abc`, `-1` or `4` SPL.** The grid saves nothing and marks that cell, rather than rounding or storing nonsense. Pinned by Task 4, `test_a_bad_value_saves_nothing_and_names_the_cell`.
3. **An adjustment that would take a balance below zero** (L2 charges leave without pay from a balance assumed never negative). Refused with the balance it would leave. Pinned by Task 1, `test_an_adjustment_cannot_take_a_balance_below_zero`.
4. **The PC off over a year end, turned on 3 January.** The forced-leave rule and the lapse must read the balance as of 31 December, not as of the day the run happens. Pinned by Task 3, `test_a_late_run_reads_the_balance_as_of_31_december`.
5. **An employee with no posting yet whose hire date is after the month, or who left before it.** Gets no row at all, not a `0.000` row. Pinned by Task 2, `test_no_row_for_a_month_the_employee_did_not_serve`.

---

## Before you start

- Work in `C:\laragon\www\dtr-system` on `main`. `php artisan test --compact` passes (110 tests, 23 October 2026).
- `.ai/rules` does not exist; `CLAUDE.md` is the rule set.
- Read the spec sections **Approach**, **Leave types**, **Balances over time** and **Data model** before Task 1.

## File map

| File | Responsibility | Task |
|---|---|---|
| `app/Enums/LeaveType.php` | The 14 leave types, their labels, which are tracked | 1 |
| `app/Enums/LedgerKind.php` | What a ledger row is: opening, credit, grant, lapse, forced leave, adjustment | 1 |
| `app/Models/LeaveLedgerEntry.php` + migration + factory, `app/Policies/LeaveLedgerEntryPolicy.php` | One credit or deduction | 1 |
| `config/leave.php` | `start_date` from `LEAVE_START_DATE` | 1 |
| `app/Services/Leave/LeaveLedger.php` | Balances, and HR adjustments | 1 |
| `app/Enums/EmploymentStatus.php` | `earnsLeaveCredits()` | 2 |
| `app/Services/Leave/LeavePosting.php` | Monthly credits and year-end rows, idempotent | 2, 3 |
| `app/Console/Commands/PostLeaveCredits.php`, `routes/console.php` | `leave:post`, hourly | 2 |
| `app/Services/Leave/OpeningBalances.php` | Read and save an office's opening grid | 4 |
| `app/Filament/Pages/LeaveOpeningBalances.php`, `resources/views/filament/pages/leave-opening-balances.blade.php` | The Opening balances page | 4 |
| `app/Filament/Resources/Employees/RelationManagers/LeaveLedgerEntriesRelationManager.php` | The Leave tab: balances, history, adjustments | 5 |

---

### Task 1: Leave types, the ledger, balances and adjustments

**Files:**
- Create: `app/Enums/LeaveType.php`, `app/Enums/LedgerKind.php`
- Create: `database/migrations/*_create_leave_ledger_entries_table.php`, `app/Models/LeaveLedgerEntry.php`, `database/factories/LeaveLedgerEntryFactory.php`, `app/Policies/LeaveLedgerEntryPolicy.php`
- Create: `config/leave.php`, `app/Services/Leave/LeaveLedger.php`
- Modify: `app/Models/Employee.php` (relation), `.env.example` (`LEAVE_START_DATE=`)
- Test: `tests/Feature/Leave/LeaveLedgerTest.php`

**Interfaces:**
- Consumes: `Employee` and `User` factories; `LogsActivity` set-up as on `App\Models\Office`.
- Produces:
  - `App\Enums\LeaveType: string implements HasLabel`. Cases, values and labels, in this order: `Vacation = 'VL'` "Vacation Leave", `Forced = 'FL'` "Mandatory/Forced Leave", `Sick = 'SL'` "Sick Leave", `Maternity = 'ML'` "Maternity Leave", `Paternity = 'PL'` "Paternity Leave", `SpecialPrivilege = 'SPL'` "Special Privilege Leave", `SoloParent = 'SOLO'` "Solo Parent Leave", `Study = 'STUDY'` "Study Leave", `Vawc = 'VAWC'` "10-Day VAWC Leave", `Rehabilitation = 'RP'` "Rehabilitation Privilege", `SpecialLeaveBenefitsForWomen = 'SLBW'` "Special Leave Benefits for Women", `SpecialEmergency = 'SEL'` "Special Emergency (Calamity) Leave", `Adoption = 'ADOPT'` "Adoption Leave", `Wellness = 'WL'` "Wellness Leave". `static tracked(): list<self>` returns `[Vacation, Sick, SpecialPrivilege, Wellness]`; `isTracked(): bool`.
  - `App\Enums\LedgerKind: string implements HasLabel`: `Opening = 'opening'` "Opening balance", `MonthlyCredit = 'monthly_credit'` "Monthly credit", `YearlyGrant = 'yearly_grant'` "Yearly grant", `Lapse = 'lapse'` "Lapsed", `ForcedLeave = 'forced_leave'` "Forced leave not taken", `Adjustment = 'adjustment'` "Adjustment". L2 adds cases.
  - `App\Models\LeaveLedgerEntry`: fillable `employee_id, leave_type, posted_on, days, kind, period, posting_key, remarks, created_by_user_id`; casts `leave_type` → `LeaveType`, `kind` → `LedgerKind`, `posted_on` → `date`, `days` → `decimal:3`; `employee(): BelongsTo`, `createdBy(): BelongsTo<User>`; `LogsActivity` with `logFillable()->logOnlyDirty()->dontLogEmptyChanges()`.
  - `Employee::leaveLedgerEntries(): HasMany<LeaveLedgerEntry>`.
  - `LeaveLedgerEntryPolicy`: `viewAny`, `view`, `create` → `true`; every other ability → `false`.
  - `App\Services\Leave\LeaveLedger`:
    - `balances(Employee $employee, ?CarbonImmutable $on = null): array<string, string>`: every tracked type's value as key (`'VL'`, `'SL'`, `'SPL'`, `'WL'`), in that order, `'0.000'` where there are no rows; only rows with `posted_on` on or before `$on` when given.
    - `balance(Employee $employee, LeaveType $type, ?CarbonImmutable $on = null): string`.
    - `adjust(Employee $employee, LeaveType $type, string $days, string $reason): LeaveLedgerEntry`: posted today, kind `Adjustment`, `remarks` = `$reason`, `created_by_user_id` = the signed-in user. In a transaction, with the employee row locked (`lockForUpdate`). Throws `ValidationException` keyed `days` or `leave_type`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:enum LeaveType --no-interaction
php artisan make:enum LedgerKind --no-interaction
php artisan make:model LeaveLedgerEntry --migration --factory --no-interaction
php artisan make:policy LeaveLedgerEntryPolicy --model=LeaveLedgerEntry --no-interaction
php artisan make:class Services/Leave/LeaveLedger --no-interaction
php artisan make:test Leave/LeaveLedgerTest --phpunit --no-interaction
```

`config/leave.php` returns `['start_date' => env('LEAVE_START_DATE')]`, with a docblock saying it is always the 1st of a month and opening balances are as of the day before.

- [ ] **Step 2: Write the migration**

`leave_ledger_entries`: `id`; `foreignId('employee_id')->constrained()` (restrict on delete: employees are only soft-deleted); `string('leave_type', 8)`; `date('posted_on')`; `decimal('days', 7, 3)`; `string('kind', 32)`; `string('period', 7)->nullable()`; `string('posting_key')->nullable()->unique()`; `text('remarks')->nullable()`; `foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete()`; `timestamps()`; `index(['employee_id', 'leave_type', 'posted_on'])`.

The factory's definition: an `Employee::factory()`, `leave_type` VL, `posted_on` today, `days` `'1.250'`, kind `Adjustment`, `remarks` `'Test'`.

- [ ] **Step 3: Write the failing tests**

`tests/Feature/Leave/LeaveLedgerTest.php` (`RefreshDatabase`; `setUp` acts as `User::factory()->create()` and makes `$this->employee`):

```php
public function test_a_balance_is_the_sum_of_its_rows(): void
{
    $this->row('VL', '10.000', '2026-10-31');
    $this->row('VL', '1.250', '2026-12-01');
    $this->row('SL', '-0.500', '2026-12-03');

    $this->assertSame(['VL' => '11.250', 'SL' => '-0.500', 'SPL' => '0.000', 'WL' => '0.000'], app(LeaveLedger::class)->balances($this->employee));
}

public function test_a_balance_on_a_date_ignores_later_rows(): void
{
    $this->row('VL', '10.000', '2026-10-31');
    $this->row('VL', '1.250', '2026-12-01');

    $this->assertSame('10.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation, CarbonImmutable::parse('2026-11-30')));
}

public function test_hr_adjusts_a_balance_with_a_reason(): void
{
    $this->travelTo('2026-11-20 09:00');

    $entry = app(LeaveLedger::class)->adjust($this->employee, LeaveType::Vacation, '2.5', 'Credits from the old leave card');

    $this->assertSame('2.500', $entry->days);
    $this->assertSame('2026-11-20', $entry->posted_on->toDateString());
    $this->assertSame(LedgerKind::Adjustment, $entry->kind);
    $this->assertSame(auth()->id(), $entry->created_by_user_id);
    $this->assertSame(auth()->id(), Activity::query()->whereMorphedTo('subject', $entry)->sole()->causer_id);
}

public function test_an_adjustment_cannot_take_a_balance_below_zero(): void
{
    $this->row('VL', '1.000', '2026-10-31');

    try {
        app(LeaveLedger::class)->adjust($this->employee, LeaveType::Vacation, '-1.500', 'Tardiness, October');
        $this->fail('The adjustment was saved.');
    } catch (ValidationException $exception) {
        $this->assertStringContainsString('-0.500', $exception->errors()['days'][0]);
    }

    $this->assertSame('1.000', app(LeaveLedger::class)->balance($this->employee, LeaveType::Vacation));
}

public function test_an_adjustment_of_zero_or_of_an_untracked_type_is_refused(): void
{
    $this->assertAdjustRefused(LeaveType::Vacation, '0', 'days');
    $this->assertAdjustRefused(LeaveType::Vacation, '1.2505', 'days');
    $this->assertAdjustRefused(LeaveType::Forced, '1.000', 'leave_type');
    $this->assertAdjustRefused(LeaveType::Maternity, '1.000', 'leave_type');
}

public function test_ledger_rows_cannot_be_edited_or_deleted(): void
{
    $entry = $this->row('VL', '1.250', '2026-12-01');

    $this->assertFalse(auth()->user()->can('update', $entry));
    $this->assertFalse(auth()->user()->can('delete', $entry));
}
```

`row(string $type, string $days, string $postedOn): LeaveLedgerEntry` makes a factory row for `$this->employee`; `assertAdjustRefused(LeaveType, string $days, string $errorKey)` expects the `ValidationException` and that key.

- [ ] **Step 4: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/LeaveLedgerTest.php`
Expected: FAIL, `LeaveLedger` has no methods yet.

- [ ] **Step 5: Implement the enums, model, policy and `LeaveLedger`**

`balances()` is one grouped query (`selectRaw('leave_type, sum(days) as total')->groupBy('leave_type')`). `adjust()` validates `$days` against `/^-?\d{1,3}(\.\d{1,3})?$/` and non-zero, the type against `isTracked()`, then checks `bcadd(balance, days, 3)` is not below zero inside the lock. The negative-balance message names the result: "That would leave VL at -0.500." The days are stored normalized (`'2.5'` → `'2.500'`).

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/LeaveLedgerTest.php`
Expected: PASS (6 tests).

- [ ] **Step 7: Hand off**

Run `vendor/bin/pint --dirty --format agent`. Changed files: the two enums, the model, migration, factory, policy, `config/leave.php`, `LeaveLedger`, `Employee.php`, `.env.example`, the test. Suggested message: `feat: leave ledger, balances and adjustments`

---

### Task 2: Monthly credits and `leave:post`

**Files:**
- Modify: `app/Enums/EmploymentStatus.php`
- Create: `app/Services/Leave/LeavePosting.php`, `app/Console/Commands/PostLeaveCredits.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Leave/LeavePostingTest.php`, `tests/Feature/Console/PostLeaveCreditsTest.php`

**Interfaces:**
- Consumes: `LeaveLedgerEntry`, `LeaveType`, `LedgerKind` (Task 1).
- Produces:
  - `EmploymentStatus::earnsLeaveCredits(): bool`: true for `Permanent` and `Coterminous`.
  - `App\Services\Leave\LeavePosting`:
    - `public static function creditFor(int $serviceDays, string $daysWithoutPay = '0'): string`: the CSC credit for a month.
    - `post(CarbonImmutable $start, CarbonImmutable $today): int`: posts every monthly credit due from `$start` to `$today` (Task 3 adds year-end rows to the same call); returns the number of rows inserted.
  - Command `leave:post` (class `PostLeaveCredits`), scheduled `->hourly()->withoutOverlapping()`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:class Services/Leave/LeavePosting --no-interaction
php artisan make:command PostLeaveCredits --command=leave:post --no-interaction
php artisan make:test Leave/LeavePostingTest --phpunit --no-interaction
php artisan make:test Console/PostLeaveCreditsTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Leave/LeavePostingTest.php` (`RefreshDatabase`). `post('2026-11-01', $today)` below is `app(LeavePosting::class)->post(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse($today))`; `credits(Employee)` returns the employee's rows as `"{posted_on} {type} {days}"` strings, ordered by `posted_on`, then `leave_type`.

```php
public function test_the_credit_follows_the_csc_table(): void
{
    $this->assertSame('1.250', LeavePosting::creditFor(30));
    $this->assertSame('1.208', LeavePosting::creditFor(30, '1.000'));
    $this->assertSame('1.188', LeavePosting::creditFor(30, '1.500'));
    $this->assertSame('1.224', LeavePosting::creditFor(30, '0.625'));
    $this->assertSame('1.000', LeavePosting::creditFor(30, '6.000'));
    $this->assertSame('0.667', LeavePosting::creditFor(16));
    $this->assertSame('0.417', LeavePosting::creditFor(10));
    $this->assertSame('0.000', LeavePosting::creditFor(30, '30.000'));
}

public function test_the_first_of_the_month_credits_the_month_just_ended(): void
{
    $employee = Employee::factory()->create();

    $this->assertSame(2, $this->post('2026-12-01'));
    $this->assertSame(['2026-12-01 SL 1.250', '2026-12-01 VL 1.250'], $this->credits($employee));
    $this->assertSame('2026-11', $employee->leaveLedgerEntries()->first()->period);
}

public function test_nothing_is_due_before_the_month_has_ended(): void
{
    Employee::factory()->create();

    $this->assertSame(0, $this->post('2026-11-30'));
}

public function test_a_second_run_posts_nothing(): void
{
    Employee::factory()->create();
    $this->post('2026-12-01');

    $this->assertSame(0, $this->post('2026-12-01'));
}

public function test_missed_months_are_caught_up_with_their_own_dates(): void
{
    $employee = Employee::factory()->create();

    $this->post('2027-03-05');

    $this->assertSame(
        ['2026-12-01', '2027-01-01', '2027-02-01', '2027-03-01'],
        $employee->leaveLedgerEntries()->where('leave_type', 'VL')->orderBy('posted_on')->get()->map(fn (LeaveLedgerEntry $row): string => $row->posted_on->toDateString())->all(),
    );
}

public function test_a_month_of_hiring_or_separation_is_prorated(): void
{
    $hired = Employee::factory()->create(['date_hired' => '2026-11-15']);
    $separated = Employee::factory()->create(['date_separated' => '2026-11-10']);

    $this->post('2026-12-01');

    $this->assertSame(['2026-12-01 SL 0.667', '2026-12-01 VL 0.667'], $this->credits($hired));
    $this->assertSame(['2026-12-01 SL 0.417', '2026-12-01 VL 0.417'], $this->credits($separated));
}

public function test_no_row_for_a_month_the_employee_did_not_serve(): void
{
    $later = Employee::factory()->create(['date_hired' => '2026-12-02']);
    $gone = Employee::factory()->create(['date_separated' => '2026-10-31']);

    $this->post('2026-12-01');

    $this->assertSame([], $this->credits($later));
    $this->assertSame([], $this->credits($gone));
}

public function test_only_employees_who_earn_credits_are_credited(): void
{
    $jobOrder = Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);
    $service = Employee::factory()->create(['employment_status' => EmploymentStatus::ContractOfService]);
    $inactive = Employee::factory()->create(['is_active' => false]);
    $deleted = tap(Employee::factory()->create())->delete();
    $coterminous = Employee::factory()->create(['employment_status' => EmploymentStatus::Coterminous]);

    $this->post('2026-12-01');

    $this->assertSame(2, LeaveLedgerEntry::count());
    $this->assertCount(2, $this->credits($coterminous));
}

public function test_posted_rows_stay_out_of_the_activity_log(): void
{
    Employee::factory()->create();
    $before = Activity::count();

    $this->post('2026-12-01');

    $this->assertSame($before, Activity::count());
}
```

`tests/Feature/Console/PostLeaveCreditsTest.php` (`RefreshDatabase`, one `Employee::factory()->create()` in `setUp`):

```php
public function test_the_command_posts_what_is_due(): void
{
    config(['leave.start_date' => '2026-11-01']);
    $this->travelTo('2026-12-01 08:00');

    $this->artisan('leave:post')->expectsOutputToContain('Posted 2 rows.')->assertSuccessful();
}

public function test_the_command_refuses_without_a_start_date(): void
{
    config(['leave.start_date' => null]);

    $this->artisan('leave:post')->expectsOutputToContain('Set LEAVE_START_DATE')->assertFailed();
    $this->assertSame(0, LeaveLedgerEntry::count());
}

public function test_the_command_refuses_a_start_date_that_is_not_the_first(): void
{
    config(['leave.start_date' => '2026-11-15']);

    $this->artisan('leave:post')->expectsOutputToContain('1st of a month')->assertFailed();
    $this->assertSame(0, LeaveLedgerEntry::count());
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/LeavePostingTest.php tests/Feature/Console/PostLeaveCreditsTest.php`
Expected: FAIL, `LeavePosting::creditFor` is not defined.

- [ ] **Step 4: Implement `creditFor()`**

Integer thousandths, so the rounding is exact. With `S` = service days × 1000 and `L` = days without pay × 1000, `1.25 × (S − L) ÷ 30000` thousandths is `(S − L) ÷ 24`; rounding half up:

```php
$thousandths = intdiv(max(0, $serviceDays * 1000 - (int) bcmul($daysWithoutPay, '1000', 0)) + 12, 24);

return bcdiv((string) $thousandths, '1000', 3);
```

- [ ] **Step 5: Implement `post()`, the command and the schedule**

`post()` loads the employees who could earn (status earning, `is_active`, not deleted) once. For each month `M` from `$start` while the 1st of `M + 1` is on or before `$today`: each employee's service days in `M` are 30 if employed the whole month, otherwise the calendar days employed in it (at most 30); none means no row. Rows for VL and SL are dated the 1st of `M + 1`, `period` `M` as `Y-m`, kind `MonthlyCredit`, key `credit:{id}:{type}:{Y-m}`, `created_by_user_id` null, `remarks` null, with `created_at`/`updated_at` set by hand (insertOrIgnore skips Eloquent). Sum the `insertOrIgnore` results.

The command reads `config('leave.start_date')`: blank → error "Set LEAVE_START_DATE in .env (the 1st of the month the system takes over)." and `FAILURE`; not a `Y-m-d` date on the 1st → error "LEAVE_START_DATE must be the 1st of a month." and `FAILURE`; otherwise `post($start, CarbonImmutable::today())` and "Posted {n} rows." In `routes/console.php`: `Schedule::command('leave:post')->hourly()->withoutOverlapping();`

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/LeavePostingTest.php tests/Feature/Console/PostLeaveCreditsTest.php`
Expected: PASS (12 tests).

- [ ] **Step 7: Hand off**

Pint. Changed files: `EmploymentStatus.php`, `LeavePosting.php`, `PostLeaveCredits.php`, `routes/console.php`, the two tests. Suggested message: `feat: monthly leave credits and leave:post`

---

### Task 3: Year end

**Files:**
- Modify: `app/Services/Leave/LeavePosting.php`
- Test: `tests/Feature/Leave/YearEndPostingTest.php`

**Interfaces:**
- Consumes: `LeavePosting::post()` (Task 2), `LeaveLedger::balance()` (Task 1).
- Produces: `post()` also posts, for every 31 December on or after `$start` with 1 January after it on or before `$today`, the forced-leave and lapse rows; and for every 1 January after `$start` and on or before `$today`, the yearly grant. L2 extends `vlTakenIn()`.
  - `private function vlTakenIn(Employee $employee, int $year): string`: in L1, the sum of the employee's `Opening` rows of type FL with `posted_on` in `$year`. L2 changes it to add the VL drawn by VL and FL applications whose first date is in `$year`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Leave/YearEndPostingTest.php` (`RefreshDatabase`). Start is `2026-11-01` unless a test says otherwise; `opening(Employee, string $type, string $days)` makes an `Opening` row dated `2026-10-31`; `rows(Employee, LedgerKind)` returns `"{posted_on} {type} {days}"` strings.

```php
public function test_forced_leave_deducts_the_days_not_taken(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'VL', '8.750');   // + Nov credit 1.250 on Dec 1 = 10.000 on Dec 31
    $this->opening($employee, 'FL', '2.000');

    $this->post('2027-01-01');

    $this->assertSame(['2026-12-31 VL -3.000'], $this->rows($employee, LedgerKind::ForcedLeave));
}

public function test_forced_leave_needs_ten_days_of_vl(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'VL', '8.749');   // 9.999 on Dec 31

    $this->post('2027-01-01');

    $this->assertSame([], $this->rows($employee, LedgerKind::ForcedLeave));
}

public function test_five_days_taken_means_no_forced_leave(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'VL', '20.000');
    $this->opening($employee, 'FL', '5.000');

    $this->post('2027-01-01');

    $this->assertSame([], $this->rows($employee, LedgerKind::ForcedLeave));
}

public function test_unused_spl_and_wellness_lapse_on_31_december(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'SPL', '2.000');
    $this->opening($employee, 'WL', '0.000');

    $this->post('2027-01-01');

    $this->assertSame(['2026-12-31 SPL -2.000'], $this->rows($employee, LedgerKind::Lapse));
}

public function test_1_january_grants_spl_and_wellness(): void
{
    $employee = Employee::factory()->create();

    $this->post('2027-01-01');

    $this->assertSame(['2027-01-01 SPL 3.000', '2027-01-01 WL 5.000'], $this->rows($employee, LedgerKind::YearlyGrant));
    $this->assertSame(['VL' => '2.500', 'SL' => '2.500', 'SPL' => '3.000', 'WL' => '5.000'], app(LeaveLedger::class)->balances($employee));
}

public function test_the_start_year_has_no_grant(): void
{
    $employee = Employee::factory()->create();

    $this->post('2028-01-01', start: '2027-01-01');

    $this->assertSame(['2028-01-01 SPL 3.000', '2028-01-01 WL 5.000'], $this->rows($employee, LedgerKind::YearlyGrant));
}

public function test_31_december_is_not_closed_until_it_is_over(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'SPL', '2.000');

    $this->post('2026-12-31');

    $this->assertSame([], $this->rows($employee, LedgerKind::Lapse));
}

public function test_a_late_run_reads_the_balance_as_of_31_december(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'VL', '8.750');
    // Read as of 3 January, VL would be 8.750 + 1.250 + 1.250 - 2.000 = 9.250: no forced leave.
    LeaveLedgerEntry::factory()->for($employee)->create(['leave_type' => 'VL', 'days' => '-2.000', 'posted_on' => '2027-01-02']);

    $this->post('2027-01-03');

    $this->assertSame(['2026-12-31 VL -5.000'], $this->rows($employee, LedgerKind::ForcedLeave));
}

public function test_a_second_run_posts_no_year_end_rows(): void
{
    $employee = Employee::factory()->create();
    $this->opening($employee, 'VL', '20.000');
    $this->opening($employee, 'SPL', '1.000');
    $this->post('2027-01-01');

    $this->assertSame(0, $this->post('2027-01-01'));
}
```

`post(string $today, string $start = '2026-11-01'): int` calls `LeavePosting::post()` with both dates.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/YearEndPostingTest.php`
Expected: FAIL, no forced-leave, lapse or grant rows.

- [ ] **Step 3: Implement the year-end rows in `post()`**

Monthly credits first, then the year-end rows, so a catch-up run has December's 1st already in place when it reads 31 December. Each year-end computation reads balances with `$on` = 31 December, so it does not matter on which day the run happens. For employees who earn on 31 December:
- **Forced leave:** if `bccomp(VL on Dec 31, '10', 3) >= 0` and `bcsub('5', vlTakenIn(), 3)` is above zero, a VL row of minus that, kind `ForcedLeave`, period `Y`, key `forced:{id}:{Y}`, dated 31 December.
- **Lapse:** for SPL and WL, if the balance on 31 December is above zero, a row of minus it, kind `Lapse`, period `Y`, key `lapse:{id}:{type}:{Y}`.

For employees who earn on 1 January of a year `G` with 1 January `G` after `$start`: SPL `'3.000'` and WL `'5.000'`, kind `YearlyGrant`, period `G`, key `grant:{id}:{type}:{G}`, dated 1 January. The 3 and 5 are class constants.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/`
Expected: PASS (Task 1 to 3 tests: 24).

- [ ] **Step 5: Hand off**

Pint. Changed files: `LeavePosting.php`, the test. Suggested message: `feat: forced leave, lapse and yearly grant at year end`

---

### Task 4: Opening balances

**Files:**
- Create: `app/Services/Leave/OpeningBalances.php`
- Create: `app/Filament/Pages/LeaveOpeningBalances.php`, `resources/views/filament/pages/leave-opening-balances.blade.php`
- Test: `tests/Feature/Leave/OpeningBalancesTest.php`, `tests/Feature/Leave/LeaveOpeningBalancesPageTest.php`

**Interfaces:**
- Consumes: `LeaveLedgerEntry`, `LedgerKind`, `LeaveType` (Task 1); `EmploymentStatus::earnsLeaveCredits()` (Task 2).
- Produces:
  - `App\Services\Leave\OpeningBalances`:
    - `public const array COLUMNS = ['VL' => 'VL', 'SL' => 'SL', 'SPL' => 'SPL', 'WL' => 'Wellness', 'FL' => 'VL/FL taken this year']`: code → column heading, in grid order.
    - `employees(Office $office): EloquentCollection<int, Employee>`: the office's active employees who earn credits, surname first.
    - `read(Office $office): array<int, array<string, string>>`: employee id → code → days, `''` where there is no opening row.
    - `save(Office $office, CarbonImmutable $start, array $rows): int`: `$rows` is employee id → code → typed value; returns the number of rows added, changed or removed. Throws `ValidationException` keyed `rows.{employee id}.{code}`.
  - Page `App\Filament\Pages\LeaveOpeningBalances` at the panel, title "Opening balances", public `$officeId`, `$rows`; `save(OpeningBalances $openings): void`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:class Services/Leave/OpeningBalances --no-interaction
php artisan make:filament-page LeaveOpeningBalances --no-interaction
php artisan make:test Leave/OpeningBalancesTest --phpunit --no-interaction
php artisan make:test Leave/LeaveOpeningBalancesPageTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing service tests**

`tests/Feature/Leave/OpeningBalancesTest.php` (`RefreshDatabase`; acts as a user; `$this->office`, `$this->juan` in it; `save(array $rows)` calls the service with start `2026-11-01`):

```php
public function test_saving_writes_opening_rows_dated_the_day_before_the_start(): void
{
    $this->assertSame(5, $this->save([$this->juan->id => ['VL' => '12.5', 'SL' => '30.125', 'SPL' => '2', 'WL' => '5', 'FL' => '1']]));

    $vl = $this->juan->leaveLedgerEntries()->where('leave_type', 'VL')->sole();
    $this->assertSame('12.500', $vl->days);
    $this->assertSame('2026-10-31', $vl->posted_on->toDateString());
    $this->assertSame(LedgerKind::Opening, $vl->kind);
    $this->assertSame("opening:{$this->juan->id}:VL", $vl->posting_key);
}

public function test_a_blank_or_zero_cell_writes_no_row(): void
{
    $this->save([$this->juan->id => ['VL' => '10', 'SL' => '', 'SPL' => '0', 'WL' => '', 'FL' => '']]);

    $this->assertSame(1, $this->juan->leaveLedgerEntries()->count());
}

public function test_saving_again_replaces_the_opening_rows_and_logs_the_old_value(): void
{
    $this->save([$this->juan->id => ['VL' => '10', 'SL' => '5']]);

    $this->assertSame(2, $this->save([$this->juan->id => ['VL' => '11', 'SL' => '']]));

    $this->assertSame(['VL' => '11.000', 'SL' => '0.000', 'SPL' => '0.000', 'WL' => '0.000'], app(LeaveLedger::class)->balances($this->juan));
    $this->assertSame('10.000', Activity::query()->where('event', 'updated')->sole()->attribute_changes['old']['days']);
}

public function test_a_bad_value_saves_nothing_and_names_the_cell(): void
{
    foreach (['1.2505', 'abc', '-1', '1000'] as $typed) {
        $this->assertSaveRefused([$this->juan->id => ['VL' => '10', 'SL' => $typed]], "rows.{$this->juan->id}.SL");
    }

    $this->assertSaveRefused([$this->juan->id => ['SPL' => '4']], "rows.{$this->juan->id}.SPL");
    $this->assertSaveRefused([$this->juan->id => ['WL' => '5.5']], "rows.{$this->juan->id}.WL");
    $this->assertSame(0, LeaveLedgerEntry::count());
}

public function test_a_first_save_is_accepted_after_credits_have_posted(): void
{
    LeaveLedgerEntry::factory()->for($this->juan)->create(['kind' => LedgerKind::MonthlyCredit, 'posted_on' => '2026-12-01']);

    $this->assertSame(1, $this->save([$this->juan->id => ['VL' => '10']]));
}

public function test_a_resave_is_refused_once_the_employee_has_other_rows(): void
{
    $this->save([$this->juan->id => ['VL' => '10']]);
    LeaveLedgerEntry::factory()->for($this->juan)->create(['kind' => LedgerKind::MonthlyCredit, 'posted_on' => '2026-12-01']);

    $this->assertSaveRefused([$this->juan->id => ['VL' => '11']], "rows.{$this->juan->id}.VL");
}

public function test_employees_outside_the_office_or_without_credits_are_ignored(): void
{
    $other = Employee::factory()->create();
    $jobOrder = Employee::factory()->for($this->office)->create(['employment_status' => EmploymentStatus::JobOrder]);

    $this->assertSame(0, $this->save([$other->id => ['VL' => '10'], $jobOrder->id => ['VL' => '10']]));
}
```

`assertSaveRefused(array $rows, string $errorKey)` expects the `ValidationException` and that key. The re-save refusal message: "{full name} already has credits or leave after the opening balance; use an adjustment on the Leave tab." Limits: every cell matches `/^\d{1,3}(\.\d{1,3})?$/`; SPL at most 3, WL at most 5.

- [ ] **Step 3: Run them to verify they fail, implement `OpeningBalances`, run them to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/OpeningBalancesTest.php`
Expected first: FAIL. `save()` checks every cell before it writes any, then in one transaction, per employee sent: creates, updates or deletes the opening row of each code (through the model, so each change is logged), matched by `posting_key`. Only the codes sent change. Expected after: PASS (7 tests).

- [ ] **Step 4: Write the failing page tests**

`tests/Feature/Leave/LeaveOpeningBalancesPageTest.php` (`RefreshDatabase`; `setUp` acts as a user, sets `config(['leave.start_date' => '2026-11-01'])`, and makes `$this->office` with `$this->juan` in it):

```php
public function test_hr_opens_the_page_and_sees_the_as_of_date(): void
{
    $this->get(LeaveOpeningBalances::getUrl())->assertOk()->assertSee('Balances as of Oct 31, 2026');
}

public function test_choosing_an_office_loads_its_grid(): void
{
    LeaveLedgerEntry::factory()->for($this->juan)->create(['leave_type' => 'VL', 'days' => '10', 'kind' => LedgerKind::Opening, 'posting_key' => "opening:{$this->juan->id}:VL"]);

    Livewire::test(LeaveOpeningBalances::class)
        ->set('officeId', $this->office->id)
        ->assertSet("rows.{$this->juan->id}.VL", '10.000')
        ->assertSee($this->juan->full_name);
}

public function test_saving_the_grid_writes_the_rows(): void
{
    Livewire::test(LeaveOpeningBalances::class)
        ->set('officeId', $this->office->id)
        ->set("rows.{$this->juan->id}.SL", '7.5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('Opening balances saved: 1 change');
}

public function test_a_bad_cell_is_marked(): void
{
    Livewire::test(LeaveOpeningBalances::class)
        ->set('officeId', $this->office->id)
        ->set("rows.{$this->juan->id}.SL", 'abc')
        ->call('save')
        ->assertHasErrors(["rows.{$this->juan->id}.SL"]);
}

public function test_without_a_start_date_the_page_says_so_and_saves_nothing(): void
{
    config(['leave.start_date' => null]);

    $this->get(LeaveOpeningBalances::getUrl())->assertOk()->assertSee('Set LEAVE_START_DATE');
}
```

- [ ] **Step 5: Implement the page and its view**

Model the page on `DutyRoster`: an office select (`wire:model.live`), the as-of line ("Balances as of {start − 1 day, M j, Y}"), and a grid of `OpeningBalances::employees()` × `COLUMNS`, each cell a Filament-styled `<input inputmode="decimal" wire:model="rows.{id}.{code}">` with an `aria-label` of "{full name}, {heading}" and `fi-invalid` on error. `save()` calls `Gate::authorize('create', LeaveLedgerEntry::class)`, refuses when `start_date` is blank, and notifies "Opening balances saved: N change(s)" (or "No changes to save"). `canAccess()` is `can('create', LeaveLedgerEntry::class)`. Navigation sort 6, icon `Heroicon::OutlinedBanknotes`.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Leave/`
Expected: PASS (36 tests).

- [ ] **Step 7: Hand off**

Pint. Changed files: `OpeningBalances.php`, the page, its view, the two tests. Suggested message: `feat: opening leave balances grid`

---

### Task 5: The Leave tab on an employee

**Files:**
- Create: `app/Filament/Resources/Employees/RelationManagers/LeaveLedgerEntriesRelationManager.php`
- Modify: `app/Filament/Resources/Employees/EmployeeResource.php` (`getRelations()`)
- Test: `tests/Feature/Employees/LeaveLedgerEntriesRelationManagerTest.php`

**Interfaces:**
- Consumes: `LeaveLedger::balances()` and `adjust()` (Task 1).
- Produces: `LeaveLedgerEntriesRelationManager` (relationship `leaveLedgerEntries`, title "Leave"), with header action `adjust`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:filament-relation-manager EmployeeResource leaveLedgerEntries leave_type --no-interaction
php artisan make:test Employees/LeaveLedgerEntriesRelationManagerTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

Set up like `ScheduleAssignmentsRelationManagerTest` (`manager()` helper with `ownerRecord` and `EditEmployee::class`):

```php
public function test_the_tab_shows_the_balances_and_the_rows(): void
{
    $row = LeaveLedgerEntry::factory()->for($this->employee)->create(['leave_type' => 'VL', 'days' => '10.000', 'kind' => LedgerKind::Opening]);

    $this->manager()
        ->assertSee('VL 10.000 · SL 0.000 · SPL 0.000 · Wellness 0.000')
        ->assertCanSeeTableRecords([$row]);
}

public function test_hr_adds_an_adjustment(): void
{
    $this->manager()
        ->callAction(TestAction::make('adjust')->table(), ['leave_type' => 'SL', 'days' => '-0.5', 'remarks' => 'Undertime, October'])
        ->assertHasNoFormErrors();

    $row = $this->employee->leaveLedgerEntries()->sole();
    $this->assertSame('-0.500', $row->days);
    $this->assertSame('Undertime, October', $row->remarks);
}

public function test_an_adjustment_below_zero_is_refused_on_the_form(): void
{
    $this->manager()
        ->callAction(TestAction::make('adjust')->table(), ['leave_type' => 'SL', 'days' => '-0.5', 'remarks' => 'Undertime'])
        ->assertHasFormErrors(['days']);

    $this->assertSame(0, LeaveLedgerEntry::count());
}

public function test_rows_have_no_edit_or_delete_action(): void
{
    $row = LeaveLedgerEntry::factory()->for($this->employee)->create();

    $this->manager()
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Employees/LeaveLedgerEntriesRelationManagerTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the relation manager**

Table: `posted_on` (`M j, Y`), `leave_type` (the code), `kind` (its label), `days` (right-aligned, three places, sign shown), `remarks` (placeholder "—"), `createdBy.name` (placeholder "System"); default sort `posted_on` desc, then `id` desc; a `leave_type` select filter over `LeaveType::tracked()`. `->description()` lists the balances as "VL 10.000 · SL 0.000 · SPL 0.000 · Wellness 0.000". No record actions.

Header action `adjust` ("Add adjustment", `->authorize('create', LeaveLedgerEntry::class)`): a `leave_type` select over the tracked types, `days` (`TextInput`, required, regex `/^-?\d{1,3}(\.\d{1,3})?$/`, a closure rule refusing zero and refusing a result below zero with "That would leave {code} at {result}.", the way `ScheduleAssignmentsRelationManager` checks overlaps), `remarks` (`Textarea`, required, label "Reason"). Its action calls `LeaveLedger::adjust()`, which checks again under the lock.

Add the manager to `EmployeeResource::getRelations()` after `ScheduleAssignmentsRelationManager`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Employees/LeaveLedgerEntriesRelationManagerTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Hand off**

Pint. Changed files: the relation manager, `EmployeeResource.php`, the test. Suggested message: `feat: leave tab with balances and adjustments`

---

## Finish

- [ ] `php artisan test --compact`: all pass (110 + 43 = 153).
- [ ] `vendor/bin/pint --dirty --format agent`: clean.
- [ ] Smoke test on MySQL (Laragon running): set `LEAVE_START_DATE=2026-11-01` in `.env`, `php artisan migrate`, open **Opening balances**, save one office, run `php artisan leave:post` (posts nothing before 1 December), open an employee's **Leave** tab, add an adjustment. Then put `.env` back as it was.
- [ ] Before go-live (not in this plan): every office's opening balances are entered **before the first 1st after the start date**, so the first year end reads complete balances.
