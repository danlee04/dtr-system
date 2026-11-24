# DTR Final and the VL deduction: design

2026-11-19. Decided with HR in conversation on this date.

## Why

The leave spec (2026-10-23) records that HR confirmed tardiness and
undertime are deducted from VL, deferred until the DTR computation existed.
It exists now: every DTR shows its late and undertime as days of leave by the
CSC table ("equivalent to leave credits"), but nothing reaches the ledger.
Until this is built, HR would enter an Adjustment by hand for every employee
every month.

## What HR decided

1. **Deduct when HR marks the DTR Final**, after checking it. Not on upload:
   the total changes while HR fills blanks from the signed DTR.
2. **A short VL balance goes negative.** The whole deduction is posted even
   when VL is not enough ("without pay na iyan"). The next month's 1.25
   credit fills the negative first.
3. **An absent day is undertime for all its hours, and that part comes off
   SL, then VL** (HR, 2026-11-19, after an earlier "without pay" reading and
   a "separate leave days" design the same day). A day covered by a filed
   leave application is already shown as Leave by the calculator. A work
   day with no tap and no leave filed is undertime for all its scheduled
   hours, as on 2026-11-18 (8 on 8–5, 10 on the four-day week), shown in its
   Undertime column and in every total, with no extra note (HR had one
   removed, 2026-11-19). At Final, the CSC days of the absent minutes
   come off SL first; once SL is used up, VL, which may go negative like the
   late and undertime deduction: negative is HR's mark of without pay. SL
   never goes below zero. The rest of the month's CSC days come off VL. The
   amount follows the hours, not the schedule's `leave_days`: a 7-hour day
   (11:00–7:00 or 4:00–12:00 with an hour's break) is 0.875 day. The 8:00 AM
   – 4:00 PM schedule has no break, so it is 8 hours (HR, 2026-11-19).
4. **A half day with no in and no out stays undertime, taken from VL**, as
   now: 4 hours, or 4 (morning) and 6 (afternoon) on the four-day week.
5. **An absent day does not lower the monthly credit.** 1.25 VL and
   1.25 SL stay whole. A leave application's days without pay still lower
   it, as today; HR was told that stays unless they say otherwise.
6. **Reopen posts a reversing row** (approach A below), the way cancelling a
   leave application does.
7. **Final is one DTR at a time**, on the DTR, once HR has checked it. No
   bulk Final on the list. Changed 2026-11-24, with 93 DTRs waiting: HR checks
   rows on the list and marks them final at once, but only those with no
   blank to fill, no day not computed and no day without a schedule; the
   rest are named and stay open. The employee is now told in the app when
   their DTR is marked final or reopened.
8. **Only DTRs from LEAVE_START_DATE on are deducted.** Everything before
   the start is in the opening balance; deducting October or November again
   would deduct those months twice. Those DTRs can still be marked Final.
   (Proposed and not objected to.)
9. **The CSC table in `CscConversion` is right as it is.** HR checked its
   values against their copy. Its `shortcut:` comment, which asked for that
   check, gives way to a note that HR checked it on 2026-11-19.
10. **The four-day work week uses the same table**: an 8-hour day, 0.125 an
    hour, although its work days are 10 hours.

## Goals, non-goals, anti-goals

- **Goals:** one click after checking posts the month's VL deduction; the
  employee's leave history shows it; Reopen undoes it, so a late leave
  application or a correction can be taken in.
- **Non-goals:** pesos for days without pay (the DTR and the Monthly Summary
  show the days; payroll does the rest); bulk Final; changing how leave
  applications charge balances; notifying the employee.
- **Anti-goals:** the same month deducted twice (a second click, Final again
  without the reversal, a month before the start date); a posted deduction
  that no longer matches the DTR with nothing telling HR; a Final DTR deleted
  or replaced so its deduction loses its DTR.

## Approaches

- **A. Reopen posts a reversing row (picked by HR).** Final posts one VL row,
  e.g. "−1.250 · Late & undertime, December 2026". Reopen posts "+1.250 · DTR
  reopened"; Final again posts a new deduction. The history shows every step,
  as a cancelled leave does now. Cost: rows pile up if a DTR is reopened
  often.
- **B. Reopen deletes the row.** One row per DTR, a clean history. Cost: the
  ledger loses the trace that something was deducted and given back (only
  the activity log keeps it), and it breaks the ledger rule that every
  deduction is a row.

A, because cancellation already works this way and nothing is ever deleted
from the ledger.

## Design

### Data

- `dtr_uploads`: `finalized_at` (nullable timestamp) and
  `finalized_by_user_id` (nullable FK to users, null on delete).
- `leave_ledger_entries`: `dtr_upload_id` (nullable FK, **null on delete**).
  A DTR can only be deleted once reopened, when its rows net to zero; the
  rows stay in the history with their remarks.
- `LedgerKind`: `DtrDeduction` (`dtr_deduction`, "Late & undertime") and
  `DtrReopened` (`dtr_reopened`, "DTR reopened"). `kind` is `string(32)`, so
  no column change.

### Computation

- `DtrCalculator`, absent branch: undertime for all the uncovered minutes,
  as on 2026-11-18, also kept as `absentMinutes`; `absent` (1, or 0.5 when
  the other half is covered) is unchanged.
- The deduction is `ComputedDtr::dayEquivalent`, `CscConversion::days(late +
  undertime)`, unchanged as a formula and the same for every schedule.
- `ComputedDtr::$absentCharge`: `CscConversion::days(absentMinutes)`, the part
  of `dayEquivalent` that comes off SL first. `DtrDeduction::lateAndUndertime()`
  is the rest, so the two always add up to the total shown.
- The Absent card shows the days, with "2.000 days from SL, then VL" under
  them. The Monthly Summary and its Excel keep "Absent (days)".

### Final and Reopen

One service does both, each in a transaction that locks the upload row and
re-checks its state, so a second click posts nothing.

- **Final:** refuse when already final or not computed (no employee, job
  order, contract of service). Set `finalized_at` and `finalized_by_user_id`.
  When `LeavePosting::configuredStart()` is set, the DTR's `period_start` is
  on or after it, and `lateAndUndertime()` is above zero, insert one VL row:
  `days` = −lateAndUndertime(), `posted_on` = today, `period` = the DTR's month
  (`Y-m`), `kind` = DtrDeduction, `dtr_upload_id`, `created_by_user_id`, and
  remarks naming the month and the hours, e.g. "December 2026 DTR: 9 hrs 48
  min late and undertime".
  When `absentCharge` is above zero, it also locks the employee row, posts
  what is due first (`LeavePosting::catchUp()`), reads the SL balance, and
  inserts an SL row for as much of the charge as SL holds (never below
  zero) and a VL row for the rest, both DtrDeduction, remarks e.g.
  "December 2026 DTR: 2 absent days".
- **Reopen:** refuse when not final. Require a reason, as cancelling a leave
  does. Sum the upload's ledger rows per leave type; for each type not at
  zero, insert the opposite as a DtrReopened row with the reason in its
  remarks. Clear `finalized_at` and `finalized_by_user_id`.
- What Final would deduct now (`due`) is `dayEquivalent`; the
  changed-since-Final notice compares it with the total the rows took.

### What Final locks

- `DtrUploadPolicy`: `update` and `delete` return false while final. New
  `finalize` and `reopen` abilities, staff only.
- `FillDayAction` already authorizes `update`, so it is blocked.
  `AddDayExceptionAction` also checks that the DTR is not final.
- `DtrImporter::assign`: refuse when the older upload it would replace is
  final ("…is final; reopen it first"), instead of deleting it.
- Not locked: OB, leave and gate pass records, leave applications,
  schedules, holidays. They can change the figures after Final. When the
  live `dayEquivalent` differs from what the DTR's rows deducted, the DTR
  says so: "Changed since Final: deducted 1.250, now 1.375. Reopen and Final
  again."

### Screens

- **DTR modal (HR):** a Final button. Its confirmation states what will
  happen: "Deduct 1.250 days from VL for late and undertime, and 2.000 days
  for 2 absent days from SL, then VL", or why nothing is deducted (before the
  leave start date; no late, undertime or absence). When blanks are still
  empty it says how many, without blocking.
- Once final: a line "Final · Dec 5, 2026 by <name> · SL −2.000 · VL −1.250", and a
  Reopen button that asks for the reason.
- **DTR list:** shows which DTRs are final, with a filter for the ones that
  are not. It must still fit 768 px.
- **My DTR (employee):** the same Final line, no buttons. The employee's
  leave history lists the rows by their kind labels, as it does now.

### Edge cases

- A DTR before the leave start date, or no start date set: Final allowed,
  no row, and the confirmation says why.
- Nothing late or under: Final posts no row; Reopen then posts none.
- Final with an employee since deactivated or separated: still posted.

### Tests

- The calculator: an absent day has no undertime and charges its schedule's leave days; month totals.
- Final posts the row; VL can go negative; no row before the start date or
  with nothing to deduct; a second Final is refused.
- Reopen posts the reversal; Final again posts a new deduction; Reopen needs
  a reason.
- Locks: filling a blank, adding an OB, deleting, and assigning over a final
  DTR are all refused.
- The changed-since-Final notice.

## Calls made by Claude (correct any)

- `posted_on` is the day HR clicks Final, with the DTR's month in `period`,
  as credit corrections do.
- Reopen requires a reason.
- Final warns about empty blanks but does not block.
- The changed-since-Final notice (HR agreed to Reopen and Final again when
  something changes; this is how they would know).

## Left to the builder

The service's name and folder, button placement in the modal, exact copy,
and how the list shows the Final state within the 768 px rule.
