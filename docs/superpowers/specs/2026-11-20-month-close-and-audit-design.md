# Without-pay report, month-close progress, activity log: design

2026-11-20. Agreed with the user in conversation.

## Why

After go-live, HR closes each month by marking every DTR final, and payroll
needs the days without pay. Today HR finds those days one by one in each
employee's ledger, cannot see how many DTRs are still open, and cannot see who
changed what, although the app already records it.

## What was decided

1. **Without pay, on the Monthly Summary** (page and Excel), not a new page:
   - **From the DTR:** how far its Final took VL below zero. VL 0.050 and a
     0.150 deduction is 0.100 without pay. VL already at −0.200 and a 0.150
     deduction is 0.150. Only the latest Final counts: a Reopen cancels it,
     and the next Final is judged on the VL of that moment, leaving out any
     later month's DTR rows, so reopening October after November's Final does
     not count November's days again. A later monthly credit that fills the
     negative does not change it.
   - **From leave applications:** their days without pay dated in the month,
     as the monthly credit already reads them (not cancelled ones).
   - The summary keeps staff separated in or after the month even once they
     are set inactive, so a resignation on the 31st still has the row payroll
     needs for the final pay (found in review, 2026-11-20).
   - The page shows one column, "Without pay", with the total and "DTR x ·
     leave y" under it, so it still fits 768 px. The Excel has three columns:
     DTR, leave, total. A checkbox "Without pay only" lists only those with
     any.
2. **Month-close progress on the dashboard:** the "DTRs for <month>" card gets
   a second bar, "N final of M uploaded". "Offices still missing DTRs" becomes
   the offices not done: a DTR not uploaded, or uploaded and not final,
   furthest behind first, each with "uploaded a of s · final f". The final
   counts are read fresh on every load, not from the ten-minute cache, so HR
   sees a Final at once.
3. **Activity log**, admin only, read-only, under Administration:
   - Table: when, who ("System" when no one), which record (its kind and id),
     what happened (created, updated, deleted), and the fields changed.
     Filters: who, kind of record, date range. Newest first.
   - A row opens to each field's old and new value.
   - Rows the app posts without its models (monthly credits, grants, lapses,
     forced leave) are not recorded, so they do not appear. The ledger shows
     them.

## Approaches

- **Without pay:** a column on the Monthly Summary (picked: HR already reads
  it for payroll, and its Excel goes to payroll) or a new page (one more
  screen to open and keep in step).
- **Activity log:** a read-only Filament resource on the activity log's own
  model with a policy (picked: the panel's table, filters and modal as they
  are) or a hand-written page (more code for the same table).

## Design

- `DtrDeduction::withoutPay(DtrUpload): string`: 0 unless final. It takes
  the VL `DtrDeduction` rows after the upload's last `DtrReopened` row, and
  the employee's VL balance from the rows before the first of them (by id).
  The result is max(0, −after) − max(0, −before).
- `LeavePosting::daysWithoutPayIn()` becomes public, unchanged.
- `MonthlyTotals::rows()` adds `withoutPay: array{dtr: string, leave:
  string, total: string}` per row; `writeXlsx()` adds the three columns.
- `MonthlySummary` gets `public bool $withoutPayOnly`, applied before paging.
- `DashboardFigures::finals(CarbonImmutable $month): array<string, int>`:
  office name → final DTRs covering the month among the staff counted;
  uncached. `computeMonth()` returns every office (`offices`) instead of the
  five behind on uploads. The dashboard page picks the five not done.
- `ActivityLogResource` on `Spatie\Activitylog\Models\Activity`, list page
  only, view as a modal; `ActivityPolicy` (viewAny, view: admin; the rest
  false), registered with `Gate::policy`.

## Tests

Without pay: a partial dip below zero, an employee already negative, a
Reopen then a new Final after a credit, leave applications by month, the
column, the filter, the Excel header. Dashboard: the final count and an
office with an open DTR listed. Activity log: an admin sees it and its
old/new values; HR is refused.

## Left to the builder

Copy, icons, the record-kind labels.
