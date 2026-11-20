# Without Pay, Month-Close, Activity Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Payroll's days without pay on the Monthly Summary, month-close progress on the dashboard, and an admin-only activity log.

**Architecture:** Each feature reuses what exists: `DtrDeduction` and `LeavePosting` for without pay, `DashboardFigures` for progress, Filament's resource on Spatie's `Activity` model for the log.

**Tech Stack:** Laravel 13 / PHP 8.4, Filament 5.9, spatie/laravel-activitylog 5.1, PHPUnit on SQLite.

**Spec:** `docs/superpowers/specs/2026-11-20-month-close-and-audit-design.md`

## Global Constraints

- Claude never commits; the user gets one commit message at the end.
- TDD; `vendor/bin/pint --dirty --format agent` after PHP changes.
- `config(['leave.start_date' => '2026-10-01'])` in tests that post deductions.
- Every screen fits 768 px; `docs/design.md` updated with the look changes.
- Output escaped; the activity log's values are user data, printed with `{{ }}` only.

## Review Focus

1. **A DTR reopened and finalized again after a monthly credit:** without pay follows the latest Final only (Task 1 test).
2. **An employee whose VL was already negative before the Final:** all of the VL taken is without pay (Task 1 test).
3. **An activity whose subject was deleted, or whose causer is gone:** the log still renders, with "System" or the record's id (Task 3 test).

---

### Task 1: Without pay on the Monthly Summary

**Files:** `app/Services/Leave/DtrDeduction.php`, `app/Services/Leave/LeavePosting.php` (make `daysWithoutPayIn` public), `app/Services/Attendance/MonthlyTotals.php`, `app/Filament/Pages/MonthlySummary.php`, `resources/views/filament/pages/monthly-summary.blade.php`. Tests: `tests/Feature/Leave/DtrDeductionTest.php`, `tests/Feature/Attendance/MonthlyTotalsTest.php`, `tests/Feature/Attendance/MonthlySummaryPageTest.php`.

**Interfaces:**
- Produces: `DtrDeduction::withoutPay(DtrUpload $upload): string`.
- Produces: `MonthlyTotals` rows gain `withoutPay` (`['dtr' => '0.100', 'leave' => '1.000', 'total' => '1.100']`).

- [ ] **Step 1: Write the failing tests**
  - `DtrDeductionTest::test_without_pay_is_how_far_the_final_took_vl_below_zero`. The existing `upload()` gives 0.100. With an opening VL of 0.050, it is `'0.050'`. With an opening VL of −0.200 (an adjustment row), it is `'0.100'`. With an opening of 5.000, it is `'0.000'`. An open DTR gives `'0.000'`.
  - `DtrDeductionTest::test_without_pay_follows_the_latest_final`:
    1. Opening VL 0.050, then Final: `'0.050'`.
    2. Reopen, add a VL adjustment of +1.000, then Final again: `'0.000'`.
  - `MonthlyTotalsTest::test_rows_carry_the_days_without_pay_from_the_dtr_and_from_leave`: a final DTR with 0.050 without pay, plus a leave application for that employee with `days_without_pay` dated in the month (see `LeaveWithoutPayCreditsTest` for building one). The row's `withoutPay` equals the three strings, and the Excel header has `Without pay: DTR`, `Without pay: leave` and `Without pay: total`.
  - `MonthlySummaryPageTest::test_without_pay_only_lists_the_employees_with_any`: the page shows `'DTR 0.050 · leave 0.000'` and, with `set('withoutPayOnly', true)`, only that employee.
- [ ] **Step 2: Run them and see them fail.**
- [ ] **Step 3: Implement**
  - The `withoutPay` formula is in the spec.
  - In `MonthlyTotals::rows()`, call `LeavePosting::daysWithoutPayIn($month)` once.
  - Add a header cell `Without pay<span class="dtr-grid-sub">days</span>` after Equivalent. The cell shows the total, with `DTR x · leave y` as a sub-line, or "—" without a DTR and without leave.
  - Add the checkbox beside the Office filter.
- [ ] **Step 4: Run the whole suite.**

### Task 2: Month-close progress on the dashboard

**Files:** `app/Services/DashboardFigures.php`, `app/Filament/Pages/Dashboard.php`, `resources/views/filament/dashboard.blade.php`. Tests: `tests/Feature/DashboardFiguresTest.php`, `tests/Feature/DashboardPageTest.php`.

**Interfaces:** `DashboardFigures::finals(CarbonImmutable $month): array<string, int>`. The month figures lose `officesBehind` and gain `offices: list<array{office: string, uploaded: int, staff: int}>`, all of them.

- [ ] **Step 1: Write the failing tests**
  - `DashboardFiguresTest::test_finals_count_the_final_dtrs_by_office`.
  - `DashboardPageTest::test_the_dashboard_shows_how_many_dtrs_are_final_and_the_offices_not_done`: two uploads, one final, so the page shows `'1 final of 2 uploaded'`, and the office with the open one appears with `'final 1'`.
- [ ] **Step 2: Run them and see them fail.**
- [ ] **Step 3: Implement**
  - Card D gets a second bar labelled "DTRs final".
  - Card H is headed "Offices not done": an office is listed when uploaded < staff or final < uploaded, sorted by staff − final, five at most.
  - Its empty text reads "Every office's DTRs for <month> are in and final."
- [ ] **Step 4: Run the whole suite; scan the dashboard at 1024 and 768.**

### Task 3: Activity log

**Files:** create `app/Filament/Resources/ActivityLogs/ActivityLogResource.php` with a `Pages/ListActivityLogs.php`, and `app/Policies/ActivityPolicy.php`. Register it in `AppServiceProvider` with `Gate::policy(Activity::class, ActivityPolicy::class)`. Test: `tests/Feature/ActivityLogResourceTest.php`.

**Interfaces:** the resource uses the model `Spatie\Activitylog\Models\Activity`, navigation group `NavigationGroup::Administration`, label "Activity log", and a read-only list page.

- [ ] **Step 1: Write the failing tests**
  - `test_an_admin_sees_who_changed_what`: an admin edits an employee's last name, then opens the list and sees the causer's name, `'Employee'` and `'Updated'`. The view modal shows the old and new last names.
  - `test_hr_cannot_open_the_activity_log`: HR gets 403 on the resource URL.
  - `test_an_activity_without_a_causer_shows_system`.
- [ ] **Step 2: Run them and see them fail.**
- [ ] **Step 3: Implement**
  - Columns:
    - `created_at`, formatted `M j, Y g:i A`;
    - `causer.name`, with placeholder "System";
    - `subject_type`, shown as the class basename made headline;
    - `subject_id`;
    - `event`, as a badge;
    - the changed fields, the keys of `attribute_changes['attributes']`.
  - Filters: causer, subject type, and `created_at` from and until.
  - The view modal lists each field with its old → new value.
  - No create, edit or delete.
- [ ] **Step 4: Run the whole suite; scan the list at 1024 and 768; update `docs/design.md`.**
