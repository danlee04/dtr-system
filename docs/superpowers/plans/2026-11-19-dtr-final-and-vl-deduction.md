# DTR Final and the VL Deduction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** HR marks a checked DTR Final, which deducts its late and undertime from VL by the CSC table. Reopen gives the VL back. An absent day is without pay instead of undertime.

**Architecture:** One service, `App\Services\Leave\DtrDeduction`, does Final and Reopen in transactions and writes ledger rows linked to the DTR by a new `dtr_upload_id`. `DtrUploadPolicy` locks a final DTR, which the actions that already authorize `update` obey. Two Filament actions in the DTR view modal's footer call the service. The shared DTR view shows the Final line and a changed-since-Final notice.

**Tech Stack:** Laravel 13 / PHP 8.4, Filament 5.9, PHPUnit, SQLite in tests.

**Spec:** `docs/superpowers/specs/2026-11-19-dtr-final-and-vl-deduction-design.md`

## Global Constraints

- Claude never runs `git commit`. Each task ends by giving the user a one-line conventional commit message with no co-author trailer.
- Write the test first and watch it fail (TDD).
- Run `vendor/bin/pint --dirty --format agent` after PHP changes.
- Create files with `php artisan make:... --no-interaction`.
- Models use `#[Fillable]`. Escape output with `{{ }}`. Authorize inside every action through the policy.
- Days are strings with 3 decimals, handled with `bc*` functions, as `LeavePosting` does.
- Tests set `config(['leave.start_date' => ...])` explicitly, because `.env`'s `LEAVE_START_DATE=2026-12-01` reaches the test run.
- No sideways scroll at 1536, 1366, 1280, 1024 or 768 px.
- Copy, comments and docs state only what code, data, HR or IT confirmed.
- Look changes go into `docs/design.md`. Rule changes go into `docs/superpowers/specs/2026-10-06-dtr-system-design.md`, where the 2026-11-18 absent-as-undertime rule is replaced, and into `docs/superpowers/specs/2026-10-23-leave-module-design.md`, where "Not in this module" lists the tardiness deduction.

## Review Focus

1. **Double click on Final or Reopen.** A second request that waited on the first must post nothing. Task 2 test: `test_a_second_final_is_refused_and_posts_nothing`.
2. **Final on a DTR from before `LEAVE_START_DATE`, or with the start date blank.** It must post no row and say why. Task 2 tests: `test_a_dtr_before_the_leave_start_is_final_without_a_deduction` and `test_no_start_date_means_no_deduction`.
3. **Choosing an employee for an unassigned upload while that employee's DTR for the month is final.** Today `replaceOlder` deletes the older upload; it must refuse instead. Task 3 test: `test_choosing_an_employee_does_not_replace_a_final_dtr`.
4. **A reopened DTR deleted afterwards.** Its ledger rows must stay and net to zero, with `dtr_upload_id` nulled. Task 2 test: `test_deleting_a_reopened_dtr_keeps_its_ledger_rows`.
5. **The figures change after Final**, for example a leave application filed late or a printed time added. The DTR must say so. Task 4 test: `test_a_final_dtr_says_when_its_figures_changed_since`.

---

### Task 1: An absent day is without pay, not undertime

**Files:**
- Modify: `app/Services/Attendance/DtrCalculator.php`: the absent branch, about line 181, and the class docblock rule on absent days.
- Modify: `app/Filament/Resources/DtrUploads/Schemas/DtrUploadInfolist.php:129`: the Absent card detail.
- Modify: `resources/views/filament/pages/monthly-summary.blade.php:52`: the Absent header sub-line.
- Modify: `app/Services/Attendance/MonthlyTotals.php:88`: the Excel header.
- Modify: `app/Services/Attendance/CscConversion.php`: the `shortcut:` comment on `MINUTES`.
- Test: `tests/Feature/Attendance/DtrCalculatorTest.php`, `tests/Feature/DtrUploads/DtrUploadResourceTest.php`.

**Interfaces:**
- Produces: `ComputedDtr::$dayEquivalent` no longer counts absent hours. Tasks 2 to 4 deduct exactly this value.

- [ ] **Step 1: Change the tests to the new rule**

In `DtrCalculatorTest`:
- Rename `test_a_work_day_without_a_tap_is_absent_and_undertime_for_all_its_hours` to `test_a_work_day_without_a_tap_is_absent_without_pay_and_not_undertime`.
- Expect `[AttendanceStatus::Absent, 0, 0, 0, 0]` from `figures($day)`, and keep `1.0` for `$day->absent`.
- The four-day week check expects `0` undertime on `2026-10-15`.
- In `test_the_month_adds_up_and_converts_to_days_with_the_csc_table`, expect `[29, 2, 240, 1, 20.0, 1, '0.560']`. Change its comment: 269 minutes, the absent days without pay.

In `DtrUploadResourceTest`:
- `test_the_view_shows_the_late_and_undertime_of_each_day_and_the_month` expects `['0 hrs 23 min', '2 times', '0 hrs 25 min', '0.100 days', 'Late + undertime · 0 hrs 48 min', 'equivalent to leave credits', '21 days', 'without pay', 'Present', 'Absent']`.
- Also add `->assertMountedActionModalDontSee("480 (8\u{00A0}hrs)")`.
- Change its comment: the 21 absent days are without pay.
- `test_the_list_shows_each_dtrs_late_and_undertime` expects `['48 min', '0.100 day']`.

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact tests/Feature/Attendance/DtrCalculatorTest.php tests/Feature/DtrUploads/DtrUploadResourceTest.php`
Expected: the four changed tests FAIL. The old values, 480, 600 and 20.560, still come out.

- [ ] **Step 3: Implement**

- In the absent branch, return `new ComputedDay(AttendanceStatus::Absent, absent: count($halves) / $scheduledHalves, note: $note, fillable: $fillable, awayTimes: $away)`. Drop `$hours`.
- Replace the comment with: an absent day with no leave filed is without pay, not VL (HR, 2026-11-19).
- Leave the untapped-half rule in the loop unchanged.
- Absent card detail: `'without pay'`.
- Monthly Summary header: `Absent<span class="dtr-grid-sub">days without pay</span>`.
- Excel header: `'Absent (days without pay)'`.
- `CscConversion`: replace the `shortcut:` line with `HR checked these values against their copy (2026-11-19).`

- [ ] **Step 4: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS.
If another test fails, change its expected value only when absent days no longer adding 480 or 600 minutes explains the new value. Name each such change in the report.

- [ ] **Step 5: Update the specs**

- In `2026-10-06-dtr-system-design.md`, replace the 2026-11-18 rule (an absent day is 8 or 10 hours of undertime) with the 2026-11-19 rule: an absent day with no leave filed is without pay, and its hours are not undertime.
- Keep the untapped-half rule.

- [ ] **Step 6: Pint, then give the commit message**

Run: `vendor/bin/pint --dirty --format agent`
Message: `feat: an absent day is without pay, not undertime`

---

### Task 2: Final and Reopen post and reverse the VL deduction

**Files:**
- Create: a migration (`php artisan make:migration add_final_to_dtr_uploads --no-interaction`).
  - `dtr_uploads`: `finalized_at` (nullable timestamp) and `finalized_by_user_id` (nullable FK `users`, `nullOnDelete`).
  - `leave_ledger_entries`: `dtr_upload_id` (nullable FK, `nullOnDelete`), after `leave_application_id`.
- Modify: `app/Enums/LedgerKind.php`: add `DtrDeduction = 'dtr_deduction'` ("Late & undertime") and `DtrReopened = 'dtr_reopened'` ("DTR reopened").
- Modify: `app/Models/DtrUpload.php`:
  - add `finalized_at` and `finalized_by_user_id` to `#[Fillable]`, so the activity log records Final and Reopen;
  - cast `finalized_at` to `datetime`;
  - add `isFinal(): bool`, `finalizedBy(): BelongsTo<User>` and `ledgerEntries(): HasMany<LeaveLedgerEntry>`.
- Modify: `app/Models/LeaveLedgerEntry.php`: add `dtr_upload_id` to `#[Fillable]` and `dtrUpload(): BelongsTo<DtrUpload>`.
- Create: `app/Services/Leave/DtrDeduction.php` (`php artisan make:class Services/Leave/DtrDeduction --no-interaction`).
- Test: `tests/Feature/Leave/DtrDeductionTest.php` (`php artisan make:test --phpunit Leave/DtrDeductionTest --no-interaction`).

**Interfaces:**
- Consumes: `ComputedDtr::$dayEquivalent` (Task 1), `LeavePosting::configuredStart(): ?CarbonImmutable`, `DtrUploadInfolist::month(DtrUpload): string` and `DtrUploadInfolist::hours(int): string`.
- Produces, in `final class DtrDeduction`:
  - `due(DtrUpload $upload): string`: the days Final would deduct now. It is `'0.000'` when the DTR is not computed, `configuredStart()` is null, or `period_start` is before the start. Otherwise it is `computed()->dayEquivalent`.
  - `whyNothing(DtrUpload $upload): ?string`: null when `due()` is above zero, otherwise one of:
    - `'No VL is deducted: the leave start date is not set.'`
    - `'No VL is deducted: this DTR is from before the leave credits start ({M j, Y}).'`
    - `'No VL is deducted: no late or undertime.'`
  - `deducted(DtrUpload $upload): string`: the net of the upload's ledger rows, negated, as a positive amount.
  - `finalize(DtrUpload $upload, User $by): void`
  - `reopen(DtrUpload $upload, User $by, ?string $reason): void`
  - Both throw `ValidationException`:
    - `['dtr' => 'This DTR is already final.']`
    - `['dtr' => 'This DTR is not computed, so it cannot be marked final.']`
    - `['dtr' => 'This DTR is not final.']`
    - `['reopen_reason' => 'Give the reason for reopening.']`

- [ ] **Step 1: Write the failing tests**

Setup:
- `travelTo('2026-11-03 09:00')`.
- An `upload()` helper builds the same DTR as `DtrUploadResourceTest::computableUpload()`: a permanent employee, `Schedule::factory()` from 2026-10-01, and Oct 1 at 08:14, 11:45, 13:09 and 16:50. That comes to 48 minutes, `'0.100'`.
- `config(['leave.start_date' => '2026-10-01'])` unless a test says otherwise.
- `$hr = User::factory()->create()`.

Tests:
- `test_final_deducts_the_months_late_and_undertime_from_vl`
  - The upload's only ledger row has `leave_type` VL, `days` `'-0.100'`, `kind` `LedgerKind::DtrDeduction`, `period` `'2026-10'`, `posted_on` `'2026-11-03'`, `created_by_user_id` `$hr->id`, and remarks `'October 2026 DTR: 0 hrs 48 min late and undertime'`.
  - `finalized_at` is now, and `finalized_by_user_id` is `$hr->id`.
- `test_vl_goes_negative_when_it_is_short`
  - Given a `LeaveLedgerEntry` opening VL of `0.050`, `LeaveLedger::balances($employee)['VL']` after Final is `'-0.050'`. Match the string format `balances()` returns.
- `test_a_dtr_before_the_leave_start_is_final_without_a_deduction`
  - Start `2026-12-01`: no ledger rows and `isFinal()` is true.
  - `whyNothing()` returns `'No VL is deducted: this DTR is from before the leave credits start (Dec 1, 2026).'`
- `test_no_start_date_means_no_deduction`
  - Start `null`: no rows, and the not-set message.
- `test_nothing_late_or_under_posts_no_row`
  - The Oct 1 taps are on time: 08:00, 12:00, 13:00, 17:00.
- `test_a_second_final_is_refused_and_posts_nothing`
  - The second `finalize` throws `ValidationException`, and there is still one row.
- `test_a_job_order_dtr_cannot_be_final`
  - `EmploymentStatus::JobOrder` throws, and the DTR is not final.
- `test_reopen_gives_the_vl_back_and_final_again_deducts_anew`
  - Ordered by id, the rows' `days` are `['-0.100', '0.100', '-0.100']` and their kinds are `[DtrDeduction, DtrReopened, DtrDeduction]`.
  - The reopen row's remarks are `'October 2026 DTR reopened: Leave filed late'`.
  - After the reopen, `finalized_at` and `finalized_by_user_id` were null.
- `test_reopen_needs_a_reason_and_a_final_dtr`
  - `reopen($upload, $hr, '  ')` throws on `reopen_reason`.
  - Reopening an open DTR throws on `dtr`.
- `test_deleting_a_reopened_dtr_keeps_its_ledger_rows`
  - Final, reopen, then `$upload->delete()`. Two rows remain, with `dtr_upload_id` null, summing to 0.

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact tests/Feature/Leave/DtrDeductionTest.php`
Expected: FAIL. The class `DtrDeduction` does not exist.

- [ ] **Step 3: Implement the migration, enum, models and service**

- `finalize` and `reopen` each run in `DB::transaction`.
  - They re-read the upload with `DtrUpload::query()->whereKey($upload->getKey())->lockForUpdate()->firstOrFail()` and check its state on that fresh row. This is how a waiting second click is refused.
  - `finalize` inserts the VL row through `$upload->ledgerEntries()->create([...])` only when `bccomp(due, '0', 3) > 0`. The row's `days` is `bcsub('0', due, 3)`.
- `reopen` trims the reason.
  - When `deducted()` is not `'0.000'`, it inserts the opposite of the net as a `DtrReopened` row.
  - Its remarks are `"{month} DTR reopened: {reason}"`.
- `deducted()` sums with `number_format((float) $sum, 3, '.', '')`, as `LeavePosting::balancesOn` does.
- Each class gets a docblock. `DtrDeduction` says why Final waits for HR (the total changes while HR fills blanks), why VL may go negative (HR: without pay), and why months before the start are skipped (the opening balance holds them).

- [ ] **Step 4: Run the tests and see them pass**

Run: `php artisan test --compact tests/Feature/Leave/DtrDeductionTest.php`
Expected: PASS.

- [ ] **Step 5: Update the leave spec**

In `2026-10-23-leave-module-design.md`, change the "Deducting tardiness and undertime from VL" bullet to point to the 2026-11-19 spec. Add the two kinds where the spec lists ledger kinds.

- [ ] **Step 6: Pint, then give the commit message**

Message: `feat: marking a DTR final deducts its late and undertime from VL`

---

### Task 3: The Final and Reopen buttons, and what Final locks

**Files:**
- Modify: `app/Policies/DtrUploadPolicy.php`:
  - `update` and `delete` become `$user->isStaff() && ! $dtrUpload->isFinal()`;
  - new `finalize(User, DtrUpload): bool` = staff, not final, and `isComputed()`;
  - new `reopen(User, DtrUpload): bool` = staff and final.
- Create: `app/Filament/Resources/DtrUploads/Actions/FinalizeDtrAction.php` (default name `finalize`) and `ReopenDtrAction.php` (default name `reopen`). Follow `app/Filament/Resources/LeaveApplications/Actions/CancelLeaveApplicationAction.php`.
- Modify: `app/Filament/Resources/DtrUploads/Tables/DtrUploadsTable.php`: `ViewAction::make()->extraModalFooterActions([FinalizeDtrAction::make(), ReopenDtrAction::make()])`.
- Modify: `app/Filament/Resources/DtrUploads/Actions/AddDayExceptionAction.php`: `authorize` also requires `auth()->user()->can('update', $record)`.
- Modify: `app/Services/Attendance/DtrImporter.php`: before deleting anything, `replaceOlder` throws `ValidationException::withMessages(['employee_id' => "This employee's DTR for these dates is final. Reopen it first."])` when any upload it would delete is final.
- Test: `tests/Feature/DtrUploads/DtrUploadResourceTest.php`.

**Interfaces:**
- Consumes: `DtrDeduction::due`, `whyNothing`, `finalize` and `reopen` (Task 2), and `DtrUpload::isFinal()`.
- Produces: the actions `finalize` and `reopen`, nested under the table's `view` action. Tests reach them as `[TestAction::make('view')->table($upload), TestAction::make('finalize')]`.

Copy:
- **Finalize:**
  - label `'Mark final'`, icon `Heroicon::OutlinedLockClosed`, `->authorize('finalize')`, `->requiresConfirmation()`;
  - heading `'Mark this DTR final'`;
  - description `"Deduct {due} days from VL for late and undertime."`, or `whyNothing()`;
  - followed by `" {n} blank(s) still empty."` when the DTR's `missing` taps add up to more than zero (`Str::plural('blank', n)`);
  - submit `'Mark final'`;
  - notification `'DTR marked final'`.
- **Reopen:**
  - label `'Reopen'`, icon `Heroicon::OutlinedLockOpen`, `->authorize('reopen')`;
  - heading `'Reopen this DTR'`;
  - description `'The VL it deducted is given back, and the DTR can be changed again.'`;
  - `Textarea::make('reopen_reason')->label('Reason')->required()->maxLength(500)`;
  - notification `'DTR reopened'`.

- [ ] **Step 1: Write the failing tests**

Each test uses `config(['leave.start_date' => '2026-10-01'])` and `computableUpload()`.
- `test_hr_marks_a_checked_dtr_final_and_vl_is_deducted`
  - `mountAction([view, finalize])` shows `'Deduct 0.100 days from VL for late and undertime.'` in the modal.
  - `callAction` notifies `'DTR marked final'`.
  - One `DtrDeduction` row of `'-0.100'` exists.
- `test_the_final_confirmation_counts_the_blanks_still_empty`
  - Use `uploadWithoutTimeOut()` (one missing tap). The modal shows `'1 blank still empty.'`.
- `test_a_final_dtr_is_locked`
  - After `app(DtrDeduction::class)->finalize($upload, $hr)`, these are hidden: `assertActionHidden($this->fillIn($upload, '2026-10-02'))`, `assertActionHidden($this->addException($upload, '2026-10-29'))`, `assertActionHidden(TestAction::make('delete')->table($upload))` and `finalize`.
  - `reopen` is visible.
  - Pick dates where the action shows on an open DTR, as the existing tests do.
- `test_hr_reopens_a_final_dtr_with_a_reason`
  - Calling `reopen` with `['reopen_reason' => 'Leave filed late']` leaves the DTR not final and adds a `DtrReopened` row.
  - Calling it with an empty reason gives `assertHasActionErrors(['reopen_reason' => 'required'])`.
- `test_final_is_not_offered_on_a_job_order_dtr`
  - `computableUpload(EmploymentStatus::JobOrder)`: `finalize` is hidden.
- `test_choosing_an_employee_does_not_replace_a_final_dtr`
  - Dave's October DTR is final, and an upload with `employee_id` null has the same dates.
  - `chooseEmployee` with Dave gives `assertHasActionErrors(['employee_id'])`.
  - Both uploads still exist, and the second one's `employee_id` is still null. The transaction rolls back the update.

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact --filter='final|reopen|locked' tests/Feature/DtrUploads/DtrUploadResourceTest.php`
Expected: FAIL. There is no action `finalize`.

- [ ] **Step 3: Implement the policy, actions, footer, lock and importer guard**

The actions call the service. If the service's error keys need to appear in the modal, map them as `FileLeaveAction` does. The `dtr` key surfaces as a danger notification and halts the action.

- [ ] **Step 4: Run the DTR tests and the leave tests**

Run: `php artisan test --compact tests/Feature/DtrUploads tests/Feature/Leave tests/Feature/Attendance/DtrImporterTest.php`
Expected: PASS.

- [ ] **Step 5: Pint, then give the commit message**

Message: `feat: mark final and reopen buttons on the DTR, and a final DTR is locked`

---

### Task 4: Show what Final did, and when it is out of date

**Files:**
- Modify: `app/Filament/Resources/DtrUploads/Schemas/DtrUploadInfolist.php`: `viewData()` gains `'final' => ?array{line: string, changed: ?string}`.
- Modify: `resources/views/filament/dtr-uploads/dtr.blade.php`: under the cards, render `final.line` as a `dtr-record-sub` line and `final.changed` as `dtr-record-warning`. Use the existing classes. Both are escaped.
- Modify: `app/Filament/Resources/DtrUploads/Tables/DtrUploadsTable.php`:
  - the `late_and_undertime` description appends `' · Final'` when final;
  - a new `Filter::make('not_final')->label('Not final yet')->query(fn (Builder $query): Builder => $query->whereNull('finalized_at'))`.
- Modify: `resources/views/filament/dtr-uploads/toolbar-filters.blade.php`: a checkbox bound to `tableFilters.not_final.isActive`, like Needs an employee.
- Modify: `docs/design.md`: the Final line, the changed notice and the list's "· Final" mark.
- Test: `tests/Feature/DtrUploads/DtrUploadResourceTest.php` and `tests/Feature/Employee/MyDtrTest.php`.

**Interfaces:**
- Consumes: `DtrDeduction::due` and `deducted` (Task 2), `DtrUpload::finalized_at` and `finalizedBy`.
- Copy:
  - **line:** `"Final · {finalized_at M j, Y} by {finalizedBy->name} · VL −{deducted}"`, using U+2212 for the minus, or `"… · no VL deducted"` when `deducted` is `'0.000'`. Omit `by …` when `finalizedBy` is null.
  - **changed:** `"Changed since Final: deducted {deducted}, now {due}. Reopen and mark it final again."`, only when final and `bccomp(due, deducted, 3) !== 0`.

- [ ] **Step 1: Write the failing tests**

- `test_a_final_dtr_shows_when_and_by_whom_and_what_it_deducted`
  - With `$hr` named `'Ana Reyes'`, the view shows `"Final · Nov 3, 2026 by Ana Reyes · VL \u{2212}0.100"`.
- `test_a_final_dtr_says_when_its_figures_changed_since`
  - After Final, `$upload->days()->create(['date' => '2026-10-16', 'am_arrival' => '09:13'])` adds 73 minutes late and 240 under.
  - Total 361 minutes: `'0.752'`.
  - The view shows `'Changed since Final: deducted 0.100, now 0.752. Reopen and mark it final again.'`
- `test_the_list_marks_final_dtrs_and_filters_the_rest`
  - The list shows `'0.100 day · Final'`.
  - `filterTable('not_final')` shows the open upload and not the final one.
- `MyDtrTest::test_an_employee_sees_that_their_dtr_is_final`
  - After Final, the employee's `MyDtr` page shows `'Final · Nov 3, 2026'` and `'VL'`, and no `'Mark final'` or `'Reopen'`.

- [ ] **Step 2: Run them and see them fail**

Run: `php artisan test --compact --filter='final' tests/Feature/DtrUploads/DtrUploadResourceTest.php tests/Feature/Employee/MyDtrTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

- [ ] **Step 4: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 5: Check the screens**

- Use the scratchpad `scan.mjs` with a staff session. Check the DTR list, the DTR modal of a final DTR and the toolbar at 1536, 1366, 1280, 1024 and 768 px.
- Expected: no sideways scroll.

- [ ] **Step 6: Pint, then give the commit message**

Message: `feat: the DTR shows when it was marked final and when its figures changed since`
