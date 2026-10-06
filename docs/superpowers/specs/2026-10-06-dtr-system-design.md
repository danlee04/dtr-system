# DTR System — Design

Status: draft for review · 2026-10-06
Repository: `C:\laragon\www\dtr-system` → `http://dtr-system.test`

## Context

HR at DTRC computes every employee's tardiness and undertime by hand. Someone
reads the biometric logs, compares each one with the person's schedule, adds up
the minutes, and types CS Form 48. It takes days every month, and the
arithmetic is easy to get wrong.

This system replaces that work. It is a standalone Laravel application,
separate from the HRIS by HR's own choice: the HRIS had planned a "Phase 2b:
DTR", and HR decided to build it as its own system instead. If the two are
ever linked, `employee_number` is the shared key.

It runs on a local server inside the hospital network, and only HR uses it.
The design was agreed section by section in chat on 5–6 October 2026. This
document collects it, together with the decisions made while writing it down,
which are listed under **Decisions to review**.

## Goal

For any month, HR opens the system and finds correct late, undertime and
absence figures for every employee, without computing anything by hand, and
prints CS Form 48 from them.

"Correct" is not taken on trust. Before the system replaces the manual
process, one past month that HR already computed by hand is run through it,
and every difference is explained (see **Testing**).

## Scope

**In v1**

- Employees and offices, by CSV import or one at a time.
- Schedules: fixed templates assigned to employees, and a monthly duty roster
  for staff who rotate.
- Holidays and work suspensions.
- Exceptions: leave, official business (OB) and travel order (TO), for a whole
  day or half a day.
- Pulling logs from the biometric device(s) on the LAN, every 15 minutes and
  on demand.
- Computing late, undertime and absences for every employee and day.
- Corrections that never edit a raw log: an added manual punch, or an ignored
  punch, each with a reason.
- Reports: CS Form 48 (PDF), a monthly summary (screen and Excel), CSC
  day-equivalents, habitual tardiness.
- Closing each employee's month: finalize and reopen.
- Two roles: `admin` and `hr`.

**Not in v1**

- Leave applications and leave credits. They are the next phase; until then
  the CSC conversion is a report and deducts nothing.
- Logins for employees or department heads.
- Importing USB `.dat` files. The device interface leaves room for it.
- Habitual undertime, overtime, night differential.
- The device pushing logs to the server (ADMS).

## What HR told us

| Question | Answer |
|---|---|
| Biometric device | On the LAN with an IP address; the system pulls the logs |
| Schedules | Mixed. Fixed templates such as Regular (Mon–Fri, 8–12 and 1–5), 4-Day Week (Mon–Thu, 8–12 and 1–7) and Driver (Mon–Fri, 7–12 and 1–4), plus a rotating duty roster (AM, PM, Night) for nurses |
| Punches per day | Four: AM in, lunch out, lunch in, PM out |
| Tardiness rule | No grace period and no flexi-time. 8:01 against an 8:00 schedule is one minute late |
| Outputs | CS Form 48, monthly summary, CSC conversion of minutes to days, habitual tardiness flag |
| Exceptions | Holidays and suspensions, OB/TO, leave, forgotten punches |
| Users | HR only |
| Employee data | CSV import plus manual entry |

Assumptions stated during the design and not corrected:

- `employee_number` is the stable key for an employee.
- Time is counted in whole minutes. Seconds are dropped: 8:00:59 is 8:00.
- A shift that crosses midnight belongs to the date it starts on.
- The CSC conversion is a report only.
- Job Order and Contract of Service personnel have no leave credits, so their
  figures stay in minutes.

## Approach

**Raw punches are immutable, computed days can be rebuilt, and a finalized
month is frozen.**

Every log read from a device is stored exactly as read and is never edited or
deleted. A computed table, `attendance_days`, holds one row per employee per
date. It is rebuilt from the raw punches whenever something that affects it
changes: a sync, a schedule, a roster entry, an exception, a holiday, a
correction. When HR finalizes an employee's month, that month stops changing.

Two alternatives were rejected:

- **Computing live on every report.** The schema is simpler, but a schedule
  change next month would quietly rewrite a DTR that has already been signed,
  and 130+ employees × 31 days would be recomputed for every report.
- **An editable DTR grid**, the way much commercial software works. It is fast
  for HR, but the original log is lost and nothing records why a time changed.
  That is not acceptable for a government DTR.

Logs are **pulled** from the device rather than pushed to the server (ADMS), so
no unauthenticated endpoint has to be opened for the device to call.

## Architecture

Laravel 13, PHP 8.4, Livewire 4, Filament 5, Tailwind v4, MySQL 8, PHPUnit.
The application timezone is `Asia/Manila`. The device records local time with
no zone, so an application running in UTC would put every late eight hours
off.

One Filament panel serves the whole application at `/`, with Filament's own
login. Roles are a `role` column on `users`; two roles do not justify
`spatie/laravel-permission`.

All attendance logic lives in `app/Services/Attendance/`, and Filament pages
and actions only call it. The services are where the tests concentrate, and
the leave module will later reuse `ScheduleResolver` to count working days
without going through a screen.

| Service | Responsibility |
|---|---|
| `DeviceSync` | Reads one device through a `PunchSource`, stores new punches, records the run |
| `ScheduleResolver` | Answers "what is this employee's schedule on this date?" as concrete datetimes |
| `PunchMatcher` | Picks the punches that belong to one employee-day and assigns them to slots |
| `DayCalculator` | Turns expected blocks and matched punches into late, undertime, absence, status and issues |
| `AttendanceRecomputer` | Recomputes the employee-days an input change affects; never writes a finalized month |
| `MonthClosing` | The finalize and reopen rules |
| `CscConversion` | Minutes to day-equivalents, using the CSC table |
| `HabitualTardiness` | Applies the CSC habitual tardiness rule to finalized months |
| `MonthlySummary` | The month's figures per employee, for the screen and the Excel file |
| `Form48Exporter` | Builds the CS Form 48 PDF |

Outside the attendance namespace: `app/Services/EmployeeImporter.php` for the
CSV import, and `app/Services/Backup/DatabaseDump.php` for `dtr:backup`,
modelled on the HRIS.

Device access sits behind an interface in
`app/Services/Attendance/PunchSources/`:

```php
interface PunchSource
{
    /** @return iterable<RawPunch> biometric ID, punched_at, raw state */
    public function fetchPunches(Device $device): iterable;

    public function deviceTime(Device $device): CarbonImmutable;

    public function setDeviceTime(Device $device, CarbonImmutable $time): void;

    /** @return iterable<DeviceUser> biometric ID and the name stored on the device */
    public function deviceUsers(Device $device): iterable;
}
```

`ZktecoPunchSource` is the first implementation. Tests use a `FakePunchSource`
that serves punches held in memory.

**New packages** (approving this spec approves them): `filament/filament` 5,
`barryvdh/laravel-dompdf`, `spatie/laravel-activitylog` (the HRIS uses it too),
and one ZKTeco library chosen by the probe (see **Device sync**). Excel files
are written with OpenSpout, which Filament already requires.

### Screens

| Screen | Contents | Roles |
|---|---|---|
| Dashboard | Last successful sync per device (red after 2 hours), **Sync now**, counts of incomplete days, unmatched logs and months ready to finalize | admin, hr |
| Employees | List, add/edit, CSV import, biometric ID, office, status, hire and separation dates; schedule assignments as a tab; a bulk action that assigns a schedule to the selected employees | admin, hr |
| Offices | Name, and the head who signs Form 48 as "In Charge" | admin, hr |
| Schedules | Templates: code, name, work days, one or two blocks | admin, hr |
| Duty roster | Monthly grid of an office's employees × dates; each cell holds a schedule code, OFF, or nothing | admin, hr |
| Holidays & suspensions | Dated list | admin, hr |
| Exceptions | Leave, OB and TO per employee and date range | admin, hr |
| Attendance | One employee-month: each day's schedule, punches, late, undertime, status and issues; add a manual punch, ignore a punch | admin, hr |
| Unmatched logs | Logs whose biometric ID belongs to no employee, grouped by ID | admin, hr |
| Month closing | One office and month: each employee's state, finalize, finalize all with no issues, reopen | admin, hr |
| Reports | Form 48, monthly summary, habitual tardiness | admin, hr |
| Devices | IP, port, comm key, sync history, **Sync now**, **Set device time** | admin |
| Users | Accounts, roles, active flag | admin |

## Data model

```
users                 Laravel's default columns, plus role (admin|hr) and is_active

offices               id, name (unique), head_name (nullable), is_active, timestamps

employees             id, employee_number (unique), last_name, first_name,
                      middle_name (nullable), suffix (nullable), office_id,
                      employment_status, biometric_id (unique, nullable),
                      date_hired (nullable), date_separated (nullable), is_active,
                      timestamps, deleted_at

devices               id, name, ip, port (default 4370), comm_key (encrypted, nullable),
                      is_active, last_synced_at, last_sync_status, last_sync_message,
                      clock_drift_seconds, timestamps
device_sync_logs      id, device_id, trigger (schedule|user), user_id (nullable),
                      started_at, finished_at, status (ok|failed), punches_read,
                      punches_new, message (nullable)
device_users          id, device_id, biometric_id, name, refreshed_at
                      unique (device_id, biometric_id)

schedules             id, code (unique), name, work_days (json), is_active, timestamps
schedule_blocks       id, schedule_id, sequence (1|2), time_in, time_out
                      unique (schedule_id, sequence)
schedule_assignments  id, employee_id, schedule_id, effective_from,
                      effective_to (nullable), timestamps
roster_entries        id, employee_id, date, schedule_id (nullable = day off), timestamps
                      unique (employee_id, date)
holidays              id, date (unique), name, type (regular|special|suspension),
                      suspension_starts_at (time, nullable), timestamps

punches               id, device_id (nullable), biometric_id (nullable),
                      employee_id (nullable), punched_at, source (device|manual),
                      raw_state (nullable), is_ignored, note (nullable),
                      created_by_user_id (nullable), timestamps
                      unique (device_id, biometric_id, punched_at)
                      index (employee_id, punched_at)
day_exceptions        id, employee_id, date_from, date_to,
                      type (leave|official_business|travel_order),
                      coverage (whole_day|first_half|second_half), leave_type (nullable),
                      reference_no (nullable), remarks (nullable),
                      created_by_user_id, timestamps

attendance_days       id, employee_id, date, status, schedule_id (nullable),
                      schedule_source (fixed|roster, nullable), holiday_id (nullable),
                      exception_type (nullable), exception_coverage (nullable),
                      block1_in, block1_out, block2_in, block2_out (datetime, nullable),
                      late_minutes, undertime_minutes, late_count, undertime_count,
                      absent_days (0 | 0.5 | 1), issues (json), computed_at
                      unique (employee_id, date)
attendance_months     id, employee_id, period (YYYY-MM), finalized_at (nullable),
                      finalized_by_user_id (nullable), reopen_reason (nullable),
                      changes_after_finalize (json, nullable), timestamps
                      unique (employee_id, period)
```

Plus the `activity_log` table from `spatie/laravel-activitylog`.

- `employment_status` uses the HRIS's four values: `permanent`, `coterminous`,
  `job_order`, `contract_of_service`. Permanent and co-terminous employees earn
  leave credits; job order and contract of service do not.
- `work_days` holds ISO weekday numbers: `[1,2,3,4]` is Monday to Thursday. It
  only matters when a schedule is used through an assignment; roster entries
  name their own dates.
- A schedule has one or two blocks, because that is what CS Form 48 holds.
  Each time in a schedule is later than the one before it. A time that is not
  later falls on the next day, so `22:00 → 02:00` ends the next morning and HR
  never ticks a "next day" box. A schedule spans less than 24 hours.
- The roster uses the same templates as assignments: AM, PM and Night are
  templates with codes. There is no separate notion of a shift.
- An employee's assignments may not overlap.
- Device punches are never edited. A correction is an added `manual` punch
  with a `note`. A wrong log is marked `is_ignored`, with a `note`, and stays
  where it is.
- A device log whose biometric ID belongs to no employee is still stored, with
  `employee_id` null, and appears under **Unmatched logs**. No log is lost.
  Manual punches carry `employee_id` directly and have no biometric ID.
- Half-day exceptions cover one date only. An employee's exceptions may not
  overlap, except a first half and a second half on the same date.
- `leave_type` comes from a list in `config/dtr.php` (VL, SL, SPL, FL and the
  rest). It is a label until the leave phase.
- Months are finalized per employee, not per office: one employee with an
  incomplete day does not hold up everyone else.
- Every change to employees, offices, schedules, assignments, roster entries,
  holidays, exceptions, manual and ignored punches, devices and users, and
  every finalize and reopen, is written to the activity log with who and when.

### Day statuses

| Status | Meaning | Counts toward |
|---|---|---|
| `present` | Every required block has both punches | late and undertime |
| `absent_half` | One of two blocks has no punches | ½ day absent, plus the other block's late and undertime |
| `absent` | No punches in any required block | 1 day absent |
| `incomplete` | A block has one punch where it needs two | an issue; blocks finalizing |
| `no_schedule` | No roster entry and no assignment | an issue; blocks finalizing |
| `rest_day` | Not a work day, or a roster day off | nothing |
| `holiday` | Regular or special non-working holiday (fixed schedules only) | nothing |
| `suspended` | Whole-day work suspension (fixed schedules only) | nothing |
| `leave`, `official_business`, `travel_order` | Whole-day exception | nothing |
| `not_employed` | Before `date_hired` or after `date_separated` | nothing |
| `pending` | Not computed yet (see **Computing a day**) | nothing yet; blocks finalizing |

### Employee import

The CSV columns are employee number, surname, first name, middle name, suffix,
office, employment status, biometric ID and date hired. The importer checks
every row before it saves any, and imports nothing while a row has an error;
the errors name the row and the column.

- An employee number that already exists updates that employee instead of
  creating a second one.
- Offices are matched by name, ignoring case. An unknown office is an error,
  so a typo cannot create a second office.
- Employment status is matched the way the HRIS matches it: "Co-terminous",
  "coterminous" and "CO TERMINOUS" are all accepted.
- It runs in the request. Filament's built-in importer runs through the
  queue, and the server runs no queue worker.

## Schedule resolution

`ScheduleResolver` answers "what is X's schedule on date D?" in a fixed order.
The first rule that applies wins:

| # | When | Result |
|---|---|---|
| 1 | D is before X's `date_hired` or after `date_separated` | `not_employed` |
| 2 | X has a roster entry on D | That schedule. A day-off entry means `rest_day`. |
| 3 | An assignment is in effect on D | If D's weekday is not in the schedule's `work_days`, `rest_day`. Otherwise that schedule, after the holiday rules below. |
| 4 | Neither | `no_schedule`, an issue for HR to fix. The system does not guess. |

Holidays and suspensions apply to fixed schedules (rule 3) only:

- **Regular or special holiday:** `holiday`. No late, undertime or absence.
  Only non-working days are entered; a special working day is an ordinary day.
- **Suspension with a time** (e.g. 3:00 PM): the schedule is cut at that time.
  A 1:00–5:00 block becomes 1:00–3:00, so leaving at 3:00 is not undertime. A
  block that starts at or after 3:00 is dropped.
- **Whole-day suspension** (no time): `suspended`, like a holiday.
- **Holidays and suspensions do not apply to rostered staff.** A nurse
  rostered on a holiday is expected at work, which is how a hospital runs. To
  give that nurse the day off, HR changes the roster. One rule, no exceptions
  to remember.

The resolver returns concrete datetimes, not just times. Nurse A's Night shift
on 5 October:

```
Block 1:  2026-10-05 22:00  →  2026-10-06 02:00
Block 2:  2026-10-06 03:00  →  2026-10-06 07:00
```

The shift belongs to 5 October and appears on that row of Form 48.

Because assignments carry `effective_from` and `effective_to`, moving X to the
4-Day Week from 1 November leaves October as it was. A change that reaches
into the past recomputes only months that are not finalized.

| Employee | Schedule | Friday, 9 October |
|---|---|---|
| Clerk | Regular (Mon–Fri, 8–12 / 1–5) | Work day |
| Engineer | 4-Day Week (Mon–Thu, 8–12 / 1–7) | Rest day |
| Driver | Driver (Mon–Fri, 7–12 / 1–4) | Work day; late after 7:00 |
| Nurse | Roster: Night | The Night schedule from the roster |

## Computing a day

### 1. Collect the day's punches

The window for day D runs from 4 hours before the first expected time in to 6
hours after the last expected time out. When the day before or the day after
also has working blocks and the two windows would overlap, the boundary moves
to the midpoint between the earlier day's last expected out and the later
day's first expected in. Every punch therefore belongs to at most one day, and
a 6:58 AM punch after a night shift lands on the night shift's date.

Ignored punches are left out. A punch less than 2 minutes after the last punch
kept is a double tap and is dropped, so 8:00:10 and 8:01:30 count as one punch
at 8:00.

A day stays `pending` until its window has closed **and** every active device
has been synced since. A morning in progress is never shown as absent, and a
day is never judged on logs still sitting in the device. The scheduled run
every 15 minutes, and every successful sync, computes the pending days that
now qualify. With no active device, a day is computed once its window closes.

### 2. Assign punches to slots

Each block has two slots, in and out.

- **The count matches** (four punches for four slots, two for two): the
  punches are assigned in order. This is the usual day and the most reliable
  case.
- **Too few or too many:** the window is split at the midpoints between
  consecutive expected times (on a regular day, 10:00, 12:30 and 15:00). Each
  in-slot takes the earliest punch in its part, and each out-slot takes the
  latest.
- **A slot still empty:** if the other slot of its block has a punch, the day
  is `incomplete`, with an issue such as "Missing lunch in". If both slots of a
  block are empty, that block is absent. The system never invents a punch.

### 3. Compute

Seconds are dropped first. For each block:

- **Late** = actual in − expected in, when positive. Coming back late from
  lunch is late.
- **Undertime** = expected out − actual out, when positive. Leaving early for
  lunch is undertime.
- **No offset.** Arriving at 7:30 does not make up for leaving at 4:30. There
  is no flexi-time.

`late_count` and `undertime_count` count the blocks with any late or any
undertime, so a day can be late twice.

| Punches | Late | Undertime | Status |
|---|---|---|---|
| 7:52, 12:03, 12:58, 5:05 | 0 | 0 | `present` |
| 8:14, 12:01, 1:09, 5:00 | 14 + 9 = **23** (twice) | 0 | `present` |
| 7:58, 11:45, 1:00, 4:50 | 0 | 15 + 10 = **25** (twice) | `present` |
| 7:55, 12:00, 5:01 | 0 | — | `incomplete` (missing lunch in) |
| none | — | — | `absent`, 1 day |
| none in the morning; 12:58, 5:00 | — | — | `absent_half`, ½ day |

### 4. Exceptions

- **Whole day** (leave, OB, TO) on a work day: the day takes the exception's
  status, with no late, undertime or absence. On a rest day or a holiday it
  changes nothing.
- **First half** skips block 1, and **second half** skips block 2. The other
  block is computed as usual. On a one-block day, the block is split at its
  midpoint and the covered half is skipped.

## Device sync

**The interface comes before the library.** The first development task is a
probe, `php artisan device:probe {ip} {--port=4370} {--key=0}`, which connects
to the real device and prints its clock, its user count and its last ten logs.
The ZKTeco library (`rats/zkteco`, or another the probe proves) is chosen only
after it works against this device, not on the strength of its README. It must
read logs, users and the clock, set the clock, and never clear the device's
logs. Most such libraries need PHP's `sockets` extension, which is not yet
enabled in Laragon.

If no library can read the device, stop and revisit this section before
building on it.

A sync of one device:

1. Take a lock (`device-sync:{id}`) so the scheduled run and the button never
   overlap. If it is held, the scheduled run skips and the button reports that
   a sync is already running.
2. Read the device clock and store its drift from the server. Past **2
   minutes**, the dashboard and the Devices page show a warning, and **Set
   device time** (admin) corrects it. A wrong device clock makes everyone late,
   or no one.
3. Read the logs. Logs are never deleted from the device, so reading them
   again is safe.
4. Insert with `insertOrIgnore` on `(device_id, biometric_id, punched_at)`, in
   chunks. However many times a sync runs, nothing is stored twice. Each new
   punch takes `employee_id` from its biometric ID, or null when no employee
   has it.
5. Refresh `device_users`, the names stored on the device.
6. Recompute the affected employee-days and the pending days that now qualify
   (see **Recomputation**).
7. Write `device_sync_logs` (read, new, status, error message) and update the
   device. `last_synced_at` is the time a successful sync **started**: every
   punch made before it has been read.

A failed run is logged with its message, and `last_synced_at` keeps its old
value.

**When it runs**

- Every **15 minutes**, for every active device, followed by the pending days
  that now qualify. Windows has no cron, so **Windows Task Scheduler** runs
  `php artisan schedule:run` every minute (setup under **Deployment**).
- **Sync now**, on the dashboard for both roles and on the Devices page. It
  runs in the request, without a queue: it takes seconds, and there is then no
  queue worker on the server to keep alive.

**When the scheduler stops silently**, which is the real risk: the dashboard
shows each device's last successful sync and turns it red after **2 hours**.
Days stay `pending` while a device is behind, and any report for a month that
still has pending days says so.

**Unmatched logs** groups the logs no employee owns by biometric ID: how many,
the first and last date, and the name stored on the device. That makes the
first mapping of 130+ employees practical. Setting an employee's biometric ID,
from this page or from the employee form, attaches that ID's logs and
recomputes the affected days.

On a device punch, `employee_id` is a copy of the biometric ID → employee
mapping. When an employee's biometric ID changes, logs with the old ID are
detached (back to Unmatched, unless another employee holds that ID), logs with
the new ID are attached, and the affected days are recomputed. Manual punches
are not touched. A separated employee's biometric ID has to be cleared before
another employee can use it.

## Recomputation

`AttendanceRecomputer` takes a set of employee-dates and recomputes them,
always including one day on each side, because a neighbouring day's schedule
moves the window boundary.

| Change | Employee-days recomputed |
|---|---|
| New punches from a sync | Each punch's employee, on the punch's date and the day before |
| The 15-minute scheduled run, and each successful sync | Every pending day that now qualifies (see **Computing a day**) |
| Manual punch added; punch ignored or restored | That employee and date |
| Biometric ID set or changed | Every date with a re-attached or detached log |
| Hire or separation date changed | That employee, between the old and the new date |
| Assignment created, changed or deleted | That employee, over the assignment's dates |
| Roster entry changed | That employee and date |
| Schedule template edited | Everyone using it, by assignment or roster |
| Holiday or suspension added, changed or deleted | Everyone on a fixed schedule that day |
| Exception created, changed or deleted | That employee, over its dates |

Computation starts at `DTR_START_DATE`, the first day the system is used (set
earlier for the parallel run), and stops at today. A finalized month is never
written; see **Month closing**.

## Month closing

An employee's month can be finalized when:

1. **The month has ended.**
2. **No day in it is `pending`, `incomplete` or `no_schedule`.** HR fixes
   those first: a manual punch with a note, an ignored punch, an exception, an
   assignment, a roster entry. Because a day stays pending until every active
   device has synced after its window closed, a night shift on the 31st holds
   the month until a sync after 13:00 on the 1st. An inactive device is left
   out, so one broken device does not hold everyone up.

**Finalize all with no issues** works on one office and month. It finalizes
every employee who qualifies and lists the rest with the reason.

**After finalizing**, nothing in the month changes. When an input change would
alter a finalized day (a log arriving late from a sync, a holiday added
afterwards), the recomputer works the day out in memory. If the result differs
from what is stored, it adds an entry to `changes_after_finalize`: the date,
each field that would change with its old and new value, and the cause. The
month is then marked **Changed after finalizing** and lists the entries. HR
decides whether to reopen. No figure changes silently, and no correction is
silently left behind.

**Reopen** (hr or admin) requires a reason. It clears the finalization, keeps
the reason, recomputes the month (which applies the recorded changes) and
empties `changes_after_finalize`. The month then has to be finalized and
printed again. Form 48 prints the date of finalization in its footer, so an
outdated printout is easy to spot.

## Reports

Every report reads `attendance_days` and nothing else. No report computes
anything again, so the screen, Form 48 and the summary always agree.

### CS Form 48

- One employee, or every employee of an office for a month in one PDF, one
  employee per page, ordered by surname. Built with `barryvdh/laravel-dompdf`,
  which is pure PHP: it works offline and needs no Chrome or Node on the
  server.
- **The layout follows the form HR prints today.** A photo or scan is an open
  item. Until it arrives: two copies side by side on one landscape 8.5 × 13 in
  page, with the standard text: "Civil Service Form No. 48", "DAILY TIME
  RECORD", the name, "For the month of", "Official hours for arrival and
  departure" with Regular days and Saturdays, the table (Day; A.M. Arrival and
  Departure; P.M. Arrival and Departure; Undertime Hours and Minutes), the
  total, the certification, the employee's signature, "VERIFIED as to the
  prescribed office hours", and "In Charge".
- Block 1 fills the A.M. columns and block 2 the P.M. columns, even when a
  night shift's times fall in the evening. A one-block schedule prints its in
  under A.M. Arrival and its out under P.M. Departure. Times print as `7:58`;
  on a shift that crosses midnight they carry AM or PM.
- **The Undertime columns hold late and undertime together**, in hours and
  minutes, because the form has one column for time not rendered. The summary
  keeps the two apart.
- A day without times carries a label across its columns: SATURDAY or SUNDAY
  for a weekend rest day, OFF for any other rest day, HOLIDAY, SUSPENDED,
  LEAVE, OB, TO. A half-day exception labels its own half only. An absent day
  is left blank, as it would be by hand.
- "Official hours" comes from the schedule (e.g. "8:00–12:00, 1:00–5:00"). If
  the schedule changed during the month, each one is listed with its dates. An
  employee with roster entries in the month reads "As per duty roster".
- "In Charge" prints the office's `head_name`, or a blank line.
- **Only a finalized month prints an official copy.** Before that, every page
  carries a DRAFT watermark, and a draft can be given to the employee to check.
  The order is finalize, print, sign, so the signed copy always matches locked
  data.
- The footer shows when the month was finalized, and when and by whom the PDF
  was generated.

### Monthly summary

One row per employee, on screen and as an Excel file, filtered by month,
office, employment status and month state:

| Employee No. | Name | Office | Status | Late (times / min) | Undertime (times / min) | Total min | Day-equivalent | Absent (days) | 10+ late | Month |
|---|---|---|---|---|---|---|---|---|---|---|
| 2019-0042 | Dela Cruz, Juan | Admin | Permanent | 12 / 47 | 2 / 25 | 72 | 0.150 | 1 | yes | Finalized |

- The header shows how many employees are finalized ("118 of 132").
- The Excel file is written with OpenSpout and downloaded directly. Filament's
  built-in export is not used, because it runs through the queue. Text cells
  are written as text, so a name or remark beginning with `=` is never read as
  a formula.

### CSC conversion

- The month's total late and undertime minutes are converted with the CSC
  table for an eight-hour day: each whole hour is 0.125 day, and the remaining
  minutes come from the minutes table. 72 minutes = 1 hour (0.125) + 12
  minutes (0.025) = **0.150**. The monthly total is converted, not each day.
- The table is stored in code as a lookup, not computed as minutes ÷ 480,
  because the official table rounds some values differently: 6 minutes is
  0.012, not 0.013. The tests check every value.
- It deducts nothing from leave credits. Job order and contract of service
  rows show minutes only, with no day-equivalent. Absences are reported
  separately, in days.

| Min | Day | Min | Day | Min | Day | Min | Day | Min | Day | Min | Day |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | .002 | 11 | .023 | 21 | .044 | 31 | .065 | 41 | .085 | 51 | .106 |
| 2 | .004 | 12 | .025 | 22 | .046 | 32 | .067 | 42 | .087 | 52 | .108 |
| 3 | .006 | 13 | .027 | 23 | .048 | 33 | .069 | 43 | .090 | 53 | .110 |
| 4 | .008 | 14 | .029 | 24 | .050 | 34 | .071 | 44 | .092 | 54 | .112 |
| 5 | .010 | 15 | .031 | 25 | .052 | 35 | .073 | 45 | .094 | 55 | .115 |
| 6 | .012 | 16 | .033 | 26 | .054 | 36 | .075 | 46 | .096 | 56 | .117 |
| 7 | .015 | 17 | .035 | 27 | .056 | 37 | .077 | 47 | .098 | 57 | .119 |
| 8 | .017 | 18 | .037 | 28 | .058 | 38 | .079 | 48 | .100 | 58 | .121 |
| 9 | .019 | 19 | .040 | 29 | .060 | 39 | .081 | 49 | .102 | 59 | .123 |
| 10 | .021 | 20 | .042 | 30 | .062 | 40 | .083 | 50 | .104 | 60 | .125 |

Hours: 1 = .125, 2 = .250, 3 = .375, 4 = .500, 5 = .625, 6 = .750, 7 = .875,
8 = 1.000. Beyond eight hours the hours keep adding 0.125 each.

This table has to be checked against HR's printed copy before it goes into
code (see **Open items**).

### Habitual tardiness

- **The CSC rule:** late ten or more times in a month, in at least two months
  of the same semester (January–June or July–December), or in two consecutive
  months of the same year.
- **Every late counts once.** Late in the morning and late back from lunch on
  the same day is two.
- Only finalized months count, so the basis of a memo cannot shift under it.
- The summary marks each month with ten or more lates. The **Habitual
  tardiness** page takes a year and a semester and lists every employee with at
  least one such month, their count for each month, and a **Habitual** mark for
  those who meet the rule. It is the basis for HR's memo; the system does not
  write the memo.
- Consecutive months are read within one calendar year, so December and the
  following January do not pair. June and July do pair, so an employee with
  ten or more lates in both is marked **Habitual** in either semester's view.

## Security

The **Security Guidelines** in the project's `CLAUDE.md` apply to all code.
For this system in particular:

- Attendance records are personal information under the Data Privacy Act
  (RA 10173). Only HR has accounts.
- No public registration. Admin creates accounts, and the first admin is
  created with `php artisan dtr:user`. A deactivated user cannot log in
  (`canAccessPanel()` checks `is_active`). Filament's login is rate-limited.
- Every resource has a Policy; hiding a menu is not authorization. Only admin
  reaches Users and Devices, including **Set device time**.
- HTTPS even on the LAN, with a self-signed certificate, switched by
  `APP_FORCE_HTTPS` as in the HRIS. It stays off in development, where Laragon
  serves `http`.
- `APP_ENV=production`, `APP_DEBUG=false` and `php artisan config:cache` on the
  server. With debug on, an error page would show the database password and the
  device's comm key.
- Security headers as in the HRIS, without a `script-src` policy (see
  `CLAUDE.md`).
- `composer audit` before each release.

## Backup

`php artisan dtr:backup` follows `hris:backup`: `mysqldump` into one dated zip,
`dtr-YYYY-MM-DD-HHmm.zip`. Archives older than the retention period (30 days)
are removed only after a successful run, so a backup that has been failing for
weeks cannot delete the last good one.

- It runs **daily at 12:00**, because an office PC may be off at night.
- `BACKUP_PATH` should point off the machine, to a network share or another
  PC; a dump on the same disk is not a backup. `BACKUP_MYSQLDUMP` holds the
  path to `mysqldump`, which Laragon keeps off the PATH.
- `storage/backups`, the default location, is gitignored.
- This matters more than usual: once the device's memory fills or is cleared,
  the database is the only copy of the logs.

## Deployment

- A Windows PC on the hospital LAN running Laragon, reachable from HR's PCs,
  and able to reach the device's IP.
- Laragon set to start with Windows and to start all services, so a restart
  does not silently stop the web server, MySQL or the sync.
- PHP `sockets` enabled (Laragon menu → PHP → Extensions).
- `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`,
  `APP_FORCE_HTTPS=true`, `APP_URL`, the database credentials, `BACKUP_PATH`,
  `BACKUP_MYSQLDUMP` (today
  `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe`), and
  `DTR_START_DATE`.
- Deploy: `git pull` → `composer install --no-dev -o` →
  `php artisan migrate --force` → `npm run build` → `php artisan optimize`.

**Task Scheduler**, one task:

1. **Create Task.** Name it "DTR System scheduler" and choose "Run whether user
   is logged on or not".
2. **Trigger:** Daily, starting today at 12:00 AM. Tick "Repeat task every",
   type `1 minute` (the list starts at 5 minutes), for a duration of
   "Indefinitely".
3. **Action:** Program
   `C:\laragon\bin\php\php-8.4.24-Win32-vs17-x64\php.exe`, arguments
   `artisan schedule:run`, start in `C:\laragon\www\dtr-system`. When Laragon's
   PHP is upgraded, the program path changes with it.
4. **Settings:** "If the task is already running: Do not start a new
   instance".

The dashboard's sync indicator is the check that the task is running.

## Testing

PHPUnit, as in the HRIS, on SQLite in memory (the project default). The
computation runs in PHP rather than in SQL date functions, so SQLite tests
prove it.

- **The services carry most of the tests**, built from the cases in this spec:
  regular, 4-day week, driver, a night shift across midnight, a missing lunch
  punch, a double tap, too many punches, a half-day exception on a two-block
  and on a one-block day, a timed suspension, a rostered nurse on a holiday,
  a hire and a separation mid-month, a night shift followed by a day shift, a
  schedule change mid-month, and pending days while a device is behind.
- `CscConversion`: all 60 minute values and the hour values against the table,
  and totals over eight hours.
- `HabitualTardiness`: two months in a semester, two consecutive months across
  June and July, December and January not pairing, unfinalized months ignored.
- `DeviceSync` against a `FakePunchSource`: a second run stores nothing,
  unmatched logs are kept, drift is recorded, a failure is logged without
  moving `last_synced_at`, the lock is respected. The real device is exercised
  by `device:probe`.
- `MonthClosing`: finalizing is refused while a day is pending, incomplete or
  without a schedule; a finalized month never changes; a later change is
  recorded in `changes_after_finalize`; reopening needs a reason and applies
  the changes.
- Access: `hr` cannot open Users or Devices; an inactive user cannot log in.
- The importer: a file with one bad row imports nothing and names the row; a
  re-imported employee number updates instead of duplicating.
- Form 48 and the summary: tests assert on the data each is built from (rows,
  labels, totals, DRAFT), not on PDF bytes.

**The parallel run** is the acceptance test. HR picks a past month it has
already computed by hand and whose logs are still on the device. The system
computes the same month, with `DTR_START_DATE` set to its first day, and the
results are compared employee by employee. Each difference is either a bug in
the system or a slip in the manual computation, and every one is explained
before the system replaces the manual process.

## Definition of done

1. Logs arrive from the device every 15 minutes and on demand, and a second
   sync stores nothing new.
2. Every log is either attached to an employee or listed under Unmatched logs.
3. For every schedule kind (Regular, 4-Day Week, Driver, and roster AM, PM and
   Night), late, undertime and absence match the hand-worked cases in the
   tests.
4. HR corrects a day with a manual punch or an ignored punch, the reason is
   kept, and the original log is untouched.
5. A month with issues cannot be finalized; a finalized month does not change;
   later changes are flagged; reopening requires a reason.
6. Form 48 prints for one employee and for a whole office, marked DRAFT before
   finalizing.
7. The monthly summary shows on screen and downloads as Excel, with CSC
   day-equivalents.
8. The habitual tardiness page lists the employees who meet the rule.
9. The parallel run is complete and every difference is explained.
10. `dtr:backup` runs daily and its archive reaches a location off the machine.
11. `php artisan test` passes in full and `npm run build` succeeds.

## Implementation shape

This spec is larger than one plan. It breaks into five, each of which leaves
working software:

| Plan | Delivers |
|---|---|
| **1. Probe and foundation** | `device:probe` against the real device, and the library chosen. Filament, users and roles, offices, employees with CSV import, the activity log, the security baseline. HR can load the employee list. |
| **2. Schedules and sync** | Schedules, assignments, the roster grid, holidays. Devices, `DeviceSync`, the scheduler, Unmatched logs, the dashboard. HR can map biometric IDs and watch logs arrive. |
| **3. Computation** | Resolver, matcher, calculator, recomputer, exceptions, corrections, the Attendance screen. HR sees late and undertime for every day. |
| **4. Closing and reports** | Finalize and reopen, Form 48, the monthly summary and its Excel file, CSC conversion, habitual tardiness. Form 48's layout waits on the sample. |
| **5. Backup, deployment, parallel run** | `dtr:backup`, HTTPS and Task Scheduler on the server, then the parallel run. |

The order follows the risk. If the device cannot be read, everything after
Plan 1 changes, so the probe comes first. Mapping 130+ biometric IDs takes HR
the longest, so sync and Unmatched logs come before the computation.

## Decisions to review

These were settled while writing this spec and were not discussed in chat:

1. **Hire and separation dates** (`date_hired`, `date_separated`) and the
   `not_employed` status. Without them, someone hired on the 15th has fourteen
   `no_schedule` days that block finalizing.
2. **`pending` days.** A day is not computed until its window has closed and
   every active device has synced since, so a morning in progress never shows
   as absent. This also replaces a separate "month end synced" check when
   finalizing. Computation starts at `DTR_START_DATE` rather than at the
   beginning of time.
3. **Adjacent windows meet at the midpoint** between one day's last out and the
   next day's first in, so a punch never belongs to two days.
4. **Schedule codes** (REG, CWW, DRV, AM, PM, N and so on), so a roster grid of
   31 columns stays readable.
5. **`device_users`**, which stores the names from the device for the Unmatched
   logs page.
6. **Sync now on the dashboard** for HR as well, while the Devices page stays
   admin-only. HR needs fresh logs before finalizing.
7. **Form 48 details:** absent days left blank; SATURDAY, SUNDAY and OFF
   labels; a one-block schedule fills A.M. Arrival and P.M. Departure. All of
   them to be checked against the sample.
8. **Consecutive months** for habitual tardiness are read within one calendar
   year.
9. **Backups at noon**, with `BACKUP_PATH` pointed off the machine.
10. **Employment statuses** are the HRIS's four; permanent and co-terminous
    earn leave credits.
11. **`punches.source`** is `device` or `manual` in v1; `usb` arrives with the
    USB importer.

## Open items

Inputs from HR, each needed before the plan that uses it:

- **A photo or scan of the Form 48 HR prints today:** paper size, copies per
  page, labels, name format, where a one-block schedule goes (Plan 4).
- **The device's IP, port and comm key**, and `sockets` enabled in Laragon
  (Plan 1).
- **HR's printed CSC conversion table**, to check against the table above
  (Plan 4).
- **A past month computed by hand**, whose logs are still on the device
  (Plan 5).
- **The employee list** as CSV: employee number, surname, first name, middle
  name, suffix, office, employment status, biometric ID if known, date hired
  (Plan 1).
- **The schedule templates in use, and who is on the roster.** If the nursing
  roster is already kept in Excel, importing it may beat typing a grid; decide
  when Plan 2 starts.
- **This year's holidays** (Plan 2).
