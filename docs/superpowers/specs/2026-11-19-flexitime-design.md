# Flexitime: design

2026-11-19. Decided with HR in conversation on this date. Replaces "no
flexitime yet" (2026-11-18).

## Why

Some employees on the 8:00 AM – 5:00 PM schedule work flexitime: they may
come in between 7:00 and 9:00 and work their 8 hours from then. Today the
app judges them by the fixed 8–5, so an 8:30 arrival is 30 minutes late,
which HR does not count.

## What HR decided

- **Who:** an employee HR checks as flexitime, on a day whose schedule is
  8:00–12:00 and 1:00–5:00. Not checked, or any other schedule: the usual
  rules. Only HR identifies them, so it is a checkbox on the employee, with
  no dates.
- **Time in** counts from 7:00 at the earliest: an earlier tap counts as
  7:00.
- **Late** only after 9:00: 9:01 is 1 minute late.
- **Time out due** is the time in, from 7:00 to 9:00, plus 9 hours (8 of
  work and the break): 4:00 at the earliest, 6:00 at the latest. Leaving
  after it earns nothing.

| In | Out | Late | Undertime |
|---|---|---|---|
| 6:30 | 3:30 | 0 | 30 (due 4:00) |
| 7:15 | 4:15 | 0 | 0 |
| 8:30 | 5:30 | 0 | 0 |
| 9:10 | 6:10 | 10 | 0 (due 6:00) |

- **The break stays 12:00–1:00** whatever the time in: lunch out before
  12:00 is undertime and lunch in after 1:00 is late, as on 8–5.
- **No time in** (blank, or the morning on an OB or leave): the time in is
  taken as 8:00, so the time out due is 5:00.
- **A half with no tap** is still 4 hours undertime, and **an absent day**
  still 8 hours, as on 8–5.
- The DTR's schedule line says "Flexi", so HR sees why the figures differ.

## Approaches

- **Checkbox on the employee (HR's choice).** One field; the calculator
  adjusts the day when it is checked and the day's schedule is 8–5. Cost: no
  dates, so checking or unchecking it also changes earlier months that are
  still open; a final DTR keeps what it deducted and shows "Changed since
  Final" if its figures move. HR accepted this.
- **A separate "8–5 Flexi" schedule assigned from a date.** Keeps past
  months as they were, through the schedule assignments' dates. HR chose
  the checkbox instead.

## Design

- `employees.flexitime`: boolean, default false; in `Employee`'s
  `#[Fillable]`, cast boolean. A checkbox on the employee form (staff only,
  as the form is), with helper text stating the rule.
- `DtrCalculator::day()`: when the employee is flexitime and the day's
  schedule is 8:00/12:00/1:00/5:00, the morning's late cutoff becomes 9:00
  and the afternoon's time out due becomes clamp(time in, 7:00, 9:00) + 9
  hours, never after a suspension's start. The halves' own times, which give
  the untapped-half and absent hours and the OB/TO times, stay 8–12 and 1–5.
- `DtrUploadInfolist::schedule()`: " · Flexi" after the hours when it
  applies.

## Tests

The four rows of the table; lunch in at 1:10 is 10 minutes late; no time in
is due at 5:00; a flexitime employee on another schedule and an unchecked
one on 8–5 keep the usual rules; the untapped afternoon is 4 hours; the
form saves the checkbox; the DTR's schedule line says Flexi.

## Left to the builder

The checkbox's label and helper copy, and where it sits in the form.
