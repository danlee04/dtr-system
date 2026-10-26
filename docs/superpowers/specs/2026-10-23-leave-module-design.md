# Leave Module — Design

Status: draft for review · 2026-10-23
Repository: `C:\laragon\www\dtr-system` → `http://dtr-system.test`
Builds on: `2026-10-06-dtr-system-design.md` (the DTR spec)

## Context

Employees ask HR the same questions all the time: "Meron pa ba akong sick
leave?" and "Ano ang pwede kong gamitin na leave para sa ganito?" HR answers
from its own copy of every employee's balances, by hand.

This module puts the leave records in the DTR System. Each employee gets an
account, sees their own balances and where their leave went, and files leave
in the system. Filing deducts the balance at once and produces the leave form,
ready to print and sign. HR approves the application after the form is signed,
or cancels it and the balance comes back.

It was agreed in chat on 23 October 2026. HR also asked, on the same day, for
leave to come **before** the DTR computation: the device access the DTR spec
assumed is not available (the IT Unit will send a file instead, format not yet
known), so the DTR work waits and this module goes first.

## Goal

An employee opens their account and sees correct balances for VL, SL, SPL and
Wellness Leave, with every credit and deduction listed. They file a leave,
see how many days it costs and how much of it is with pay, and print the form.
HR no longer keeps balances by hand.

## Scope

**In this module**

- Leave types and their rules (see **Leave types**).
- Opening balances, typed in by HR from its current copy.
- Credits posted automatically: VL and SL every 1st of the month; SPL and
  Wellness every 1 January; the year-end lapse and the forced-leave deduction
  every 31 December.
- HR adjustments, each with a reason.
- Leave applications: filed by the employee or by HR for them, approved or
  cancelled by HR, cancelled by the employee while pending.
- The leave form (CS Form No. 6), filled in from the application, printed from
  the browser.
- Employee accounts in the existing panel, which shows each role its own
  screens.

**Not in this module**

- **Monetization**, and the salary it needs. HR deferred it on 23 October.
- **Deducting tardiness and undertime from VL.** HR confirmed it will be
  deducted, but it needs the DTR computation, which now comes later. Until
  then HR can enter it as an adjustment.
- Importing the attendance file from the IT Unit.
- Enforcing filing deadlines (e.g. VL filed in advance). HR judges these on
  the signed form.
- Terminal leave, and leave cards for past years.
- Email or SMS notifications.

## What HR told us

| Question | Answer |
|---|---|
| Where balances come from on day one | HR types them in from the copy it keeps |
| Leave types | 15 VL, 15 SL, 5 Wellness, 3 SPL, "at iba pa" |
| When VL and SL are credited | Every 1st of the month (15 a year, so 1.25 each) |
| Leave without pay, a partial first month | Both reduce the 1.25, by the CSC table |
| When the system starts | As soon as it is built, not 1 January |
| Unused SPL and Wellness | Lapse at year end; nothing carries over |
| Forced leave | An employee with 10 or more VL who did not take 5 days of it in the year has the shortfall deducted from VL |
| Not enough balance | The excess is leave without pay |
| Days charged for one day of VL/SL | 1 on 8–5, 1.25 on 8–7, 1 on a nurse's 16-hour duty |
| Days charged for one day of SPL/Wellness | Always 1, whatever the duty length |
| After the form is signed | HR marks the application approved in the system |
| If the form is rejected | The employee or HR cancels it, and the balance comes back |
| Who sees what | Every employee has an account and sees only their own leave |

## Approach

**Every credit and every deduction is a row in a ledger, and a balance is the
sum of its rows.** Nothing stores a balance that could drift from its history.

A row is never edited or deleted. A cancelled application adds a row that
gives the days back, so the history shows both: "Nov 3, −2.000 VL, leave
#14" and "Nov 5, +2.000 VL, #14 cancelled". This is how the DTR spec treats
raw punches, and for the same reason: it is a government record, and every
number on it has to be explainable.

The alternative, a balance column updated in place, is simpler to read but
cannot answer "why is my VL 11.250?", which is the employee's first question.

## Leave types

The types are fixed in code (an enum), in the order of CS Form No. 6, with
Wellness Leave added. They follow CSC rules rather than HR's preferences, so
a new type is a code change.

| Code | Leave | Balance |
|---|---|---|
| VL | Vacation Leave | Earned: +1.25 on the 1st of every month, carried over |
| FL | Mandatory/Forced Leave | None of its own: drawn from VL, and counts toward the year's 5 days |
| SL | Sick Leave | Earned: +1.25 on the 1st of every month, carried over; draws VL when it runs out |
| SPL | Special Privilege Leave | 3 every 1 January; lapses 31 December |
| WL | Wellness Leave | 5 every 1 January; lapses 31 December |
| ML, PL, SOLO, STUDY, VAWC, RP, SLBW, SEL, ADOPT | Maternity, Paternity, Solo Parent, Study, 10-Day VAWC, Rehabilitation Privilege, Special Leave Benefits for Women, Special Emergency (Calamity), Adoption | Not tracked |

**Not tracked** means the application is recorded, printed and approved like
any other, appears in the employee's history, and deducts nothing. Whether the
employee is entitled to it (a solo parent ID, a medical certificate) is checked
by HR on paper. These types count calendar days and need no schedule: a
105-day maternity leave cannot wait for three months of rosters. Rule 1 of
**Counting the days** still applies to them.

Each type carries a short description in code of when it applies and what it
needs. Claude drafts them from the CSC rules the form names; HR reads them
before go-live. The employee sees it when choosing a type and on a **Leave types**
page. That page answers "ano ang pwede kong gamitin para dito?"

Job order and contract of service personnel earn no leave credits (DTR spec,
**Data model**). They get no credits and no accounts.

## Counting the days of an application

A tracked type is counted date by date, against the employee's schedule. This
is the **Schedule resolution** of the DTR spec, which this module builds first
as `ScheduleResolver`; the DTR computation reuses it later.

| # | The date | Counts |
|---|---|---|
| 1 | Before `date_hired`, after `date_separated`, or before `LEAVE_START_DATE` | Refused |
| 2 | Has a roster entry with a schedule | The day's value (below) |
| 3 | Has a roster entry marked OFF | 0 |
| 4 | Falls in an assignment, on a weekday outside the schedule's work days | 0 |
| 5 | Falls in an assignment, on a regular or special holiday, or a whole-day suspension | 0 |
| 6 | Falls in an assignment, on an ordinary work day or a timed suspension | The day's value |
| 7 | Has neither | Refused: "No schedule on Nov 14. Ask HR to set it first." |

As in the DTR spec, holidays apply to fixed schedules only: a nurse rostered
on a holiday is expected at work, so leave that day counts.

**The day's value:**

- **VL, FL and SL:** the schedule's new `leave_days` value, set by HR on each
  schedule template. Regular = 1, 4-Day Week = 1.25, the nurses' 16-hour
  duty = 1. It is a value per template, not a formula, because none fits:
  hours ÷ 8 gives 2 for the 16-hour duty, and HR charges 1.
- **SPL and Wellness:** always 1.
- **Half a day** (first half or second half, allowed on a one-date application
  only): half the value. Half a day of VL on the 4-Day Week is 0.625.

An application covers at most 366 days. Every date of the range is kept in
its `breakdown`, rest days and holidays at 0, so the deduction explains
itself. An application whose dates all count 0 is refused. SPL and Wellness are
filed for dates in the current year only, since they draw on that year's
allotment; next year's can be filed from 1 January. Days are kept to three
decimals, as on a CSC leave card.

## Charging an application

At filing, the system charges the days in this order, against the balance at
that moment (pending applications already deducted):

| Type | Drawn from, in order |
|---|---|
| VL, FL | VL, then leave without pay |
| SL | SL, then VL, then leave without pay |
| SPL | SPL, then leave without pay |
| WL | Wellness, then leave without pay |
| Not tracked | Nothing; all days with pay |

VL is never drawn from SL. The form shows the result before the employee
submits: "3.000 days: 2.000 with pay from VL, 1.000 without pay". The employee
can still change the type or the dates.

The days with pay come first in date order, so the days without pay are the
last dates of the application. One date can be split: with 1.250 VL on hand
for two days of 1.000, the second day is 0.250 with pay and 0.750 without. `breakdown` records which dates they are,
because leave without pay reduces the credit of the month it falls in (see
**Monthly credits**).

The application keeps `days_with_pay` and `days_without_pay`. The ledger gets
one row per balance drawn, all in the same transaction. The employee's row is
locked while it runs, so two applications filed at the same moment cannot
both draw the last day.

## Balances over time

### Opening balances

`LEAVE_START_DATE` in `.env` is the date the system takes over, always the
1st of a month. HR enters each employee's balances **as of the day before**,
from its copy, after crediting the month before the start there as it always
has. If the start is 1 December, the copy includes November's credit, and
the system posts December's on 1 January.

- VL and SL balance;
- SPL and Wellness remaining for the start date's year (the full 3 and 5 when
  the start is 1 January);
- VL and forced-leave days already taken in that year (0 when the start is 1
  January), so the first 31 December can apply the forced-leave rule.

They are entered on an **Opening balances** page, one office at a time: a grid
of employees and those five numbers, saved together. Each becomes an `opening`
row dated the day before the start. The days-already-taken figure is stored as
an `opening` row of type FL; FL never has a balance, so it only feeds the
forced-leave rule.

An employee's first save is accepted at any time, even after credits have
posted, since HR may reach an office late. Saving again fixes a typo by
replacing that employee's opening rows (the activity log keeps the old
values), and is refused once the employee has a row of any other kind. After
that, corrections are adjustments. Every office should still be entered
before the first year end, which reads the opening balances.

Leave taken before the start date belongs in the opening balance. The system
refuses applications for earlier dates.

An employee added after the start date starts with zero, earns from the next
1st, and receives SPL and Wellness on the next 1 January. HR gives anything
else as an adjustment.

### Monthly credits

On every 1st of the month after `LEAVE_START_DATE`, each employee who earned
credits in **the month just ended** receives that month's VL and SL credit,
dated the 1st. A credit is for a month already served, because leave without
pay in it can only be known once it is over.

An employee earns on a date when they are permanent or co-terminous, active,
not deleted, hired on or before it (or with no hire date), and not separated
before it. The credit for a month, the same for VL and SL, follows the CSC
tables:

```
credit = 1.25 × (days of service − days without pay) ÷ 30, to three decimals
```

- **Days of service** are 30 for a full month. In the month of hiring or
  separation, they are the calendar days employed in it, at most 30. Hired on
  15 November: 16 days.
- **Days without pay** are the application days without pay dated in that
  month, in the same units as charged (a day on the 4-Day Week is 1.25).
- A full month with 1 day without pay earns 1.208; a month hired on the 15th,
  0.667; a month of 30 days without pay, 0.

The tables are checked against HR's printed copies before L1 is built, the way
the DTR spec checks the CSC conversion table (see **Open items**).

**A month already credited can still change.** Sick leave is filed on return,
so an application without pay can land in a month posted last week, and a
cancellation can remove one. Whenever an application with days without pay is
filed or cancelled, the system works out, for each month those days fall in
that has already been posted, what they change in its credit (the credit
with them less the credit without them, on the same days of service), and
adds that change as a `credit_correction` row, dated that day, for VL and SL.
Only the change from leave without pay is posted, so a hire date filled in
later does not slip into a correction. Only a month the
employee was credited is corrected; one they were not credited for stays
that way, as with any period already posted. The employee's history
shows "Dec 3, +0.042 VL, correction for November".

### Year end

For every 31 December on or after the start date:

1. **Forced leave, dated 31 December.** For an employee whose VL balance on
   that date is 10 or more: the days taken are the VL drawn by VL and FL
   applications that are not cancelled and whose first date falls in that
   year, plus the FL opening row. If that is under 5, the shortfall is
   deducted from VL.
2. **Lapse, dated 31 December.** What remains of SPL and Wellness is deducted.
3. **Grant, dated 1 January.** +3 SPL and +5 Wellness to everyone who earns
   on that date. The start date's own year has no grant: its SPL and Wellness
   come from the opening balance.
4. The 1 January monthly credit, like any other 1st.

"Balance on that date" counts only rows dated on or before it, so a run that
catches up on 3 January still sees 31 December correctly.

Cancelling an application after its year has closed does not reopen that
year: SPL and Wellness that already lapsed are not given back, and the forced
leave is not recomputed. HR corrects either with an adjustment if needed.

### Posting

`php artisan leave:post` posts everything that is due up to today and has not
been posted. The scheduler runs it every hour. Each posted row has a
`posting_key`, unique in the database (`credit:42:VL:2026-11`,
`lapse:42:SPL:2026`), so a second run posts nothing.

The server is an office PC that may be off on the 1st. It catches up the next
time it runs, and the rows carry the dates they were due, not the date they
were posted. Anything that checks a balance (an adjustment, and in L2 a
filing) runs the same posting first, so the first hour after the PC is
switched on never checks against a ledger that is missing a 1st.

**Who earns is judged when a period is posted.** The system keeps no history
of employment status or of the active flag, so a later change (a job order
appointed permanent, an employee reactivated or restored, a hire date filled
in) does not re-post a month or a year that is already posted. HR gives
anything owed for those periods as an adjustment.

### Adjustments

HR adds a positive or negative number of days to an employee's VL, SL, SPL or
Wellness, with a required reason. Adjustments cover corrections, leave HR
recorded on paper, and, until the DTR computation exists, tardiness deducted
from VL.

## Leave applications

**What is filed**, following CS Form No. 6:

- leave type;
- inclusive dates, from and to; for one date, whole day, first half or second
  half;
- details: for VL and SPL, within the Philippines or abroad (specify); for SL,
  in hospital or out patient (specify the illness); for Special Leave Benefits
  for Women, the illness; for Study Leave, completion of a master's degree or
  a bar or board review;
- commutation requested or not.

**Who does what:**

| | Employee | HR |
|---|---|---|
| File | For themselves | For any employee who earns credits |
| Approve | — | A pending application |
| Cancel | Their own, while pending | Pending or approved, with a reason |
| Print the form | Their own | Any |

An application is **pending** when filed, **approved** when HR marks it, and
**cancelled** when either cancels it. A cancellation gives back exactly the
days the application drew, as new ledger rows dated the day it is cancelled.
An application is never edited: a wrong one is cancelled and filed again.

An employee's applications that are not cancelled may not share a date,
except a first half and a second half on the same date.

## The leave form

The hospital's own version of CS Form No. 6 (Revised 2020), **HRM-07 Leave
Application**, as a print view of the application: the browser prints it, so
no PDF package is needed. A4, portrait, page 1 only: the form's second page is
not printed. The layout follows HR's Excel copy (`HRM-07 - Leave
Application.xlsx` in `storage/app/samples/`, git-ignored), received 23 October
2026.

- **Header:** the TRC Caraga logo (the picture in HR's copy), "Human Resource
  Management Section", "Leave Application", and the document box: Doc. Code
  HRM-07, Effectivity June 3, 2026, Revision No. 1, Page 1 of 1. The box's
  values come from `config/leave.php`, so a revised form is a config change.
- **1–5:** office, name (surname, first name, middle name), date of filing,
  position. Item 5, salary, is left blank: HR does not need it on the form.
- **6.A** the type ticked. Wellness Leave has no box of its own on the form,
  so it is printed under "Others". **6.B** the details, **6.C** the number of
  days and the inclusive dates, **6.D** commutation.
- **7.A Certification of leave credits** as of the filing date: VL and SL
  total earned (the balance just before this application), less this
  application, and the balance. Signed by the Human Resource Development
  Officer, whose name is printed: Mary Jane Lao Guico.
- **7.B Recommendation:** signed by the Division Head, whose name is typed on
  the application when it is filed, in a free-text field, for now; left blank
  when nobody is typed.
- **7.C Approved for:** days with pay and days without pay. 7.C and 7.D are
  signed by the Chief of Hospital III: Edhel S. Miro, MD, DPCAM, MM-PA.
- The names and titles under 7.A and 7.C come from `config/leave.php`, so a
  new officer or an OIC is a config change.
- 7.A prints the figures kept on the application when it was filed
  (`credits_at_filing`), so a reprint months later shows the same numbers.
- The form is a plain route, `/leave-applications/{id}/print`, outside both
  panels so it prints without them; HR may print any application, an
  employee only their own.

## Accounts

- `users.role` gains `employee`, and `users.employee_id` links an account to
  its employee. An employee has at most one account.
- **Everyone signs in the same way**, with email and password, on the one
  panel at `/`. The role decides what they see: admin and hr get HR's
  screens; an account linked to an employee who still works here gets that
  employee's own leave screens, under **My leave**. (Changed on 2026-10-26 at
  the user's direction. It replaces the separate employee panel at
  `/employee`, sign-in by employee number, and the printed password slips.)
- **The admin creates employee accounts on the Users screen**: name, email,
  password, the role Employee, and the employee the account belongs to, which
  that role requires. Only employees who earn leave credits can be linked.
- **HR staff are employees too.** An admin or hr account may also be linked to
  its owner's employee record, which shows them My leave as well. One account
  per person; nobody keeps a second password.
- Everyone can change their own password from the user menu. Nobody is forced
  to at the first sign-in; the admin resets a forgotten one on the Users
  screen.
- HR may approve or cancel its own application. The paper form carries the
  signatures that matter, and the activity log shows who clicked.
- An account stops working when the employee is deactivated, deleted or
  separated, as well as when the account itself is deactivated. A staff
  account linked to such an employee loses only My leave.

## Screens

**My leave** (accounts linked to an employee)

| Screen | Contents |
|---|---|
| Dashboard | Balances of VL, SL, SPL and Wellness; pending applications |
| History | Every ledger row, newest first, one tab per type with a running balance, the way a leave card reads |
| Applications | Own applications and their status; **File leave** with the live day count and pay split; cancel while pending; print |
| Leave types | Each type's description and, where tracked, the balance |

**Everyone:** Change password, in the user menu.

**HR's screens (additions)**

| Screen | Contents | Roles |
|---|---|---|
| Leave applications | All applications, filtered by status, type, office and date; view, approve, cancel, print; file for an employee | admin, hr |
| Employee → Leave tab | Balances, history, add adjustment, file for this employee | admin, hr |
| Employees | `position` field | admin, hr |
| Users | Every account; the role Admin, HR or Employee; the linked employee, required for Employee | admin |
| Opening balances | The grid, one office at a time | admin, hr |
| Schedules | The `leave_days` field ("Days charged to VL/SL for a day of leave") | admin, hr |
| Dashboard | Count of pending applications | admin, hr |

## Data model

```
users                     + employee_id (nullable, unique, FK employees),
                            must_change_password (default false);
                            role gains `employee`; email becomes nullable

employees                 + position (nullable)

schedules                 + leave_days decimal(4,3) default 1.000

leave_applications        id, employee_id, leave_type, date_from, date_to,
                          coverage (whole_day|first_half|second_half),
                          detail (nullable), detail_text (nullable),
                          division_head (nullable, L2b: typed for 7.B),
                          credits_at_filing (json, nullable, L2b: VL and SL
                          just before the charge, for 7.A),
                          commutation_requested, days, days_with_pay,
                          days_without_pay,
                          breakdown (json: date → {days, without_pay}),
                          status (pending|approved|cancelled),
                          filed_by_user_id, approved_at (nullable),
                          approved_by_user_id (nullable), cancelled_at (nullable),
                          cancelled_by_user_id (nullable), cancel_reason (nullable),
                          timestamps
                          index (employee_id, date_from)

leave_ledger_entries      id, employee_id, leave_type, posted_on (date),
                          days decimal(7,3) (signed),
                          kind (opening|monthly_credit|credit_correction|
                                yearly_grant|lapse|forced_leave|application|
                                cancellation|adjustment),
                          period (nullable), posting_key (nullable, unique),
                          leave_application_id (nullable), remarks (nullable),
                          created_by_user_id (nullable = system), timestamps
                          index (employee_id, leave_type, posted_on)
```

- `leave_type` holds the enum's code on both tables.
- `period` names the month or year a row is for: `2026-11` on a monthly credit
  and its corrections, `2026` on year-end rows. It is null on the rest.
- `posting_key` is set on opening, monthly credit, grant, lapse and
  forced-leave rows, and null on the rest. MySQL allows repeated nulls in a
  unique index, so only those rows are held to one each.
- `breakdown` keeps each date's value as counted at filing, so the deduction
  can be explained later even if the roster changes.
- The filing date on the form is `created_at`.
- Applications, adjustments, approvals, cancellations, opening balances, and
  account creation and resets go to the activity log.

## Security

The **Security Guidelines** in `CLAUDE.md` apply. In this module:

- **An employee sees only their own leave.** Every query on the My leave
  screens is scoped to the signed-in user's `employee_id`, and the application
  policy allows an employee only their own rows. Record IDs in Livewire
  components are `#[Locked]`. Tests try to open, print and cancel another
  employee's application.
- **HR's screens refuse employee accounts.** One panel serves everyone, so
  `canAccessPanel()` lets in an active account that is admin or hr, or that is
  linked to an employee who is active, not deleted and not separated. Every HR
  screen and widget then checks a staff-only policy; the My leave screens
  check the link. The existing policies, which allow every user today because
  only HR had accounts, are changed to allow only admin and hr. This also
  settles the open point that `CLAUDE.md` describes a role check the code did
  not have.
- **Passwords cross the LAN once employees sign in from their own PCs**, so
  HTTPS (`APP_FORCE_HTTPS`, DTR spec **Security**) must be on before the
  module goes live, not later.
- Balances and leave history are personal information under RA 10173.

## Testing

PHPUnit on SQLite in memory, as in the rest of the project. The rules live in
services (`ScheduleResolver`, `LeaveDayCounter`, `LeaveCharger`,
`LeavePosting`), and most tests are there.

- **Counting:** Regular (1 a day), 4-Day Week (1.25), a rostered 16-hour duty
  (1), SPL on the 4-Day Week (1), a rest day and a roster OFF (0), a holiday on
  a fixed schedule (0) and on the roster (counts), a timed suspension (counts),
  a date with no schedule and a date before the start (refused), half a day,
  an application of only rest days (refused), maternity counted in calendar
  days with no schedule.
- **Charging:** SL into VL into leave without pay; VL never into SL; FL from
  VL; SPL and Wellness into leave without pay; untracked types deducting
  nothing; two filings at once not both drawing the last day.
- **Lifecycle:** a cancellation restores exactly what was drawn; an employee
  cannot cancel an approved application; HR's cancel needs a reason;
  overlapping dates are refused, two halves of one date are not.
- **Posting:** a second run posts nothing; three missed months are caught up
  with their own dates; job order staff, inactive and separated employees get
  nothing; a full month earns 1.250, a month with 1 day without pay 1.208, a
  month hired on the 15th 0.667; filing sick leave without pay into a posted
  month adds a correction, and cancelling it adds the reverse; the forced-leave rule at 9.999 and at 10, with 2 days taken and with
  5, counting the opening FL row; the lapse and the grant across a year.
- **Access:** an employee cannot open HR's screens, nor another employee's
  application through the panel, the print view or a Livewire call; the
  account of a deactivated employee cannot sign in; HR can file for anyone.
- **The form:** tests assert on the data the view is built from (balances,
  days, signatories), not on the printed page.

**Acceptance:** for a handful of employees HR picks, the balances on the
system after one posted month match what HR computes by hand on its copy.

## Definition of done

1. HR enters opening balances for an office in one sitting.
2. On the 1st, and after the PC was off over the 1st, every earning employee
   has the past month's VL and SL credit, reduced for leave without pay, and
   running the posting again adds nothing.
3. 31 December applies forced leave and the lapse; 1 January grants SPL and
   Wellness.
4. An employee signs in with their email and password, sees their balances and
   history, files a leave, sees the day count and pay split, and prints the
   form.
5. HR approves or cancels; a cancellation restores the balance.
6. An employee can see nothing of anyone else's.
7. HR has stopped keeping balances by hand for the employees in the
   acceptance check.
8. `php artisan test` passes in full and `npm run build` succeeds.

## Implementation shape

Two plans, each leaving working software, then the go-live steps:

| Plan | Delivers |
|---|---|
| **L1. Balances** | Leave types, the ledger, opening balances, `leave:post`, adjustments, the Leave tab on an employee. HR has correct balances and can answer "meron pa ba akong SL?" from the system. |
| **L2a. Applications** | `ScheduleResolver`, `leave_days` on schedules, counting and charging, days without pay in the monthly credit and its corrections, applications filed, approved and cancelled by HR, the Leave applications screen, the dashboard count. HR files leave for anyone. |
| **L2b. Employees and the form** | Employee accounts and their My leave screens, made on the Users screen, the policy changes, `position` on employees, the leave type descriptions, the printed form. Employees file their own leave. Waits on the scan of HR's leave form. |
| **Go-live** | From DTR Plan 5, brought forward: the server on the LAN, the Task Scheduler entry (it now runs `leave:post`), `dtr:backup` off the machine, HTTPS. |

The DTR plans keep their order after this. Plan 3 reuses `ScheduleResolver`,
and approved leave applications become the leave days its computation reads,
in place of typing leave into `day_exceptions`. The tardiness deduction from
VL is designed then, once the computation exists.

## Decisions to review

These were settled while writing this document and were not discussed in
chat:

1. **A ledger**, with balances as sums and no row ever edited.
2. **Leave types fixed in code**; only VL, SL, SPL and Wellness are tracked,
   FL draws from VL, and the rest are recorded without a balance.
3. **Untracked types count calendar days** and need no schedule.
4. **Half-day leave** on a one-date application, at half the value.
5. **A timed suspension counts as a full day of leave.**
6. **A date without a schedule blocks filing** until HR sets it.
7. **SPL and Wellness also overflow into leave without pay**, the same rule as
   VL, shown to the employee before they submit.
8. **No editing.** The employee cancels only while pending; HR cancels an
   approved one with a reason.
9. **HR can file for an employee**, for staff who will not use the system
   themselves.
10. **One start date for everyone, on the 1st of a month**, with opening
    balances as of the day before, entered per office; earlier leave stays in
    the opening balance.
11. **The credit posted on the 1st is for the month just ended**, reduced for
    leave without pay and prorated for a partial month by the formula above;
    a later change to a posted month is posted as a correction.
    "Days of service" in a month of hiring are calendar days, at most 30.
12. **Forced leave** counts VL and FL drawn in the year, applies at a balance of
    10 or more on 31 December, and is not recomputed by a later cancellation.
13. **New employees after the start** begin at zero.
14. **One panel and one sign-in for everyone** (email and password); the role
    decides the screens. Changed on 2026-10-26 from a separate employee panel
    with sign-in by employee number and temporary passwords on printed slips.
15. **Accounts only for permanent and co-terminous staff.**
16. **The form printed from the browser**, signatory names in config.
17. **`position` added to employees**; the salary is not on the printed form
    (HR's answer), and waits for monetization.
18. **The existing policies tightened** to admin and hr.
19. **Leave type descriptions in code**, shown as the guide.
20. **HR staff use one account**, which shows My leave when linked to their
    employee record, and may approve their own application.
21. **SPL and Wellness are filed for the current year only.**
22. **Opening balances can be re-saved** until the employee has a row of any
    other kind; the first save is accepted at any time.
23. **Days without pay are the last dates of an application**, and they count
    in the units charged.

## Open items

Inputs from HR:

- **HR's printed CSC tables** for leave credits earned with days without pay
  and for days of service, to check the formula's values (L1).
- **The balances copy** HR enters from, before go-live. The start is not
  1 January, so it includes each employee's VL and forced-leave days already
  taken this year.
