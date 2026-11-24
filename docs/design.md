# DTR System design guide

How every screen of the DTR System looks and behaves, so a new page matches the
pages already built. It records what the app does today, read from its code; where
a page still differs from the rule, **Known deviations** says so. Change this guide
in the same commit as any change to the look it describes.

The app is one Filament 5 panel. Filament draws most of it; our own styles are a
few Blade partials in `resources/views/filament/` (see **Where the styles live**).

## 1. Concept

- **A quiet frame, the data first.** Surfaces are neutral greys and white. Blue
  (the primary colour since HR's reference of 2026-11-23; amber before) marks the
  one main action of a view and things that need a look, and a navy gradient runs
  down the sidebar; every other colour on screen is a status or a data series.
  Where this guide still says amber for something drawn in the primary colour (the
  employee form's section bar, the quick-action icons, the Needs a look card, the
  current grid row), it is now blue. Amber stays for warnings.
- **Filament first.** Use Filament's components (sections, tables, buttons,
  badges, modals, empty states, notifications) as they are. Write our own markup
  only for the data-entry grids, the dashboard and the frame.
- **Dense where HR types, roomy where HR reads.** The grids (Duty Roster, Opening
  Balances, Monthly Summary, Habitual Tardiness) pack rows tightly for long sessions; the dashboard
  gives each card room. Both use the same tokens.
- **Every page after the dashboard** (HR, 2026-11-23): its title on screen (28px
  bold, `.fi-header-heading`; hidden until then), the soft blue page with pale arcs
  at its top (`.fi-main-ctn`, behind a frosted topbar), and every section and table
  a white card with 16px corners and a soft blue shadow, 20px inside, 16px between
  (`filament.sidebar`). The dashboard draws its own heading beside the month picker,
  so its `h1` stays for screen readers; an employee's page heading is their photo
  and name.
- **Light only** (HR, 2026-11-23): dark mode is off (`darkMode(false)`) and its
  switch is gone. The `.dark` rules left in the partials no longer apply.

## 2. Tokens

All values are Filament's CSS variables, which follow Tailwind's scales. Use the
variable, never the raw value (see **Rules**).

### 2.1 Colour

The panel's palette: **primary = blue** (2026-11-23), **gray = zinc**, Filament's status
colours (success = green, warning = amber, danger = red, info = blue), and **violet**,
registered for the Sick card on an employee's page.

| Role | Light | Dark | Used for |
|---|---|---|---|
| Primary | `--primary-500` fill, `--primary-700` text | `--primary-400` text | The main button of a view; links; the count on the topbar bell; the initials avatars (`--primary-100` / `--primary-800`); on an employee's page, the open tab, the lead card and the Needs a look card |
| Secondary | none on pages: every button and link is blue (HR, 2026-11-24) | — | Filament's own Close and Cancel in modals stay grey |
| Accent | none | none | There is no separate accent hue: colour beyond blue is status or data only |
| Sidebar | navy gradient, `--primary-900` to `--primary-950` | `--primary-950` into `--gray-950` | The sidebar, with white words and icons, headings in white at 65%, and a `--primary-500` at 55% pill for the open page |
| Page background | `--primary-50` into `--gray-50`, with pale `--primary-100`/`--primary-200` arcs at the top | — | Behind cards; the topbar shows it frosted |
| Card, sidebar | `--color-white` | `--gray-900` | Sections, tables, grids, dashboard cards, the sidebar |
| Text | `--gray-950` | `--color-white` | Values, names, titles |
| Body text in lists | `--gray-700` | `--gray-200` | Sidebar items, tile labels |
| Secondary text | `--gray-500` | `--gray-400` | Labels, captions, hints, legends, placeholders, group headings |
| Lines | `--gray-950` at 5–10% | white at 10% | Borders, rules, card rings |
| Hover fill | `--gray-50` | white at 5% | Nav items, rows, tiles |
| Current fill | `--gray-100` | white at 10% | The open page in the sidebar |
| Current grid row | `--primary-500` at 9% | `--primary-400` at 12% | The row being typed in |

**Status colours** (with a word, always):

| Colour | Means | Examples |
|---|---|---|
| `success` | Done, fine | Approved, Active, progress bars, the on-time gauge |
| `warning` | Waiting or needs a look | Pending, unsaved changes, report notes ("10+ lates") |
| `danger` | Wrong or destructive | Validation errors, Absent, Habitual, Delete, Disapproved |
| `info` | Away on purpose | Leave, OB, travel order |
| `gray` | Inactive or not counted | Cancelled, Inactive, rest day |

**Data series** (charts and bars): late `--warning-400`; undertime `--info-400`;
leave by type: Vacation `--success-500`, Sick `--info-500`, Special privilege
`--warning-400`, Wellness `--danger-400`, Others `--gray-400`.

### 2.2 Typography

- **Family:** Inter Variable, Filament's default, everywhere on screen. Paper
  documents (the HRM-07 leave form) use Arial / Arial Narrow, as the printed forms do.
- **Scale:**

| Token | Size | Use |
|---|---|---|
| `--text-xs` | 12px | Labels, captions, hints, legends, badges, sub-lines under names |
| `--text-sm` | 14px | Body text, table cells, buttons, inputs |
| `--text-base` | 16px | Card and section titles |
| `--text-xl` | 20px | A shown page heading, on phones |
| `--text-2xl` | 24px | A shown page heading, from 640px |
| 1.75rem | 28px | Dashboard figures; the figures on an employee's cards; an employee's name on their page, from 640px |
| 2.25rem | 36px | The dashboard's lead figure |

- **Weights:** 400 for body text; 500 for labels, links and the current nav item;
  600 for titles, page headings and figures. Do not add 700 (bold) on screen.
- **Numbers:** `font-variant-numeric: tabular-nums`, right-aligned in columns.
- **Case:** sentence case for headings, labels and buttons ("Save roster",
  "Download Excel"). Capitals only on paper forms.
- Nothing smaller than 12px.

### 2.3 Spacing

One unit, `--spacing`, is 4px. Use its multiples: 4, 8, 12, 16, 20, 24, 32.

| Where | Value |
|---|---|
| Page side padding (Filament) | 16 / 24 / 32px on phone / tablet / desktop |
| Above the page, and between its header and content | 16px |
| Between cards | 16px |
| Card padding | Filament section header 24 × 16px; dashboard cards 20px |
| Between elements in a card | 12px; between groups 16px |
| Grid cell | 6 × 12px; dense grids (a month of days) 8 × 4px |
| Sidebar | 12px around; items 8 × 10px; 2px between items; 20px between groups |
| Topbar | 8px between its cards |
| Neighbouring clickable targets | at least 8px apart |

### 2.4 Shape and size

| Token | Value | Used for |
|---|---|---|
| `--radius-md` | 6px | Badges |
| `--radius-lg` | 8px | Buttons, inputs, nav items, chart bars |
| `--radius-xl` | 12px | Cards, sections, tables, grid frames, the sidebar card, topbar cards |
| full | pill | Avatars, progress bars, counts, an employee's tabs |

- **Lines:** 1px, at the line colour above. No heavy shadows: Filament's own card
  shadow at most.
- **Icons:** Heroicons, outlined, except the sidebar's, which are solid (HR's
  reference, 2026-11-23). 20px in navigation and actions, 16px inline
  (the lock), 24px in empty states.
- **Control height:** 40px for topbar cards and main controls; quick-action tiles 44px.

### 2.5 Motion

Filament's own transitions (75–150ms). Tooltips fade in 120ms. Nothing moves for
decoration.

## 3. Layout

- **Frame (desktop, 1024px and up).**
  - **Sidebar:** 16rem wide, down the screen's whole left edge in a navy gradient
    (HR's reference, 2026-11-23; a white card set 8px in before), with white words
    and solid icons, headings in 14px, and a bright blue gradient pill
    (`--primary-600` to `--primary-500`) for the open page. It holds the brand, centred: a
    48px seal on a white disc, "DTR System" bold and white, "DTRC Caraga" under it;
    20px under it, the groups, **Overview** first (the Dashboard, and
    an employee's My DTR), so no item stands outside a group; and at its foot,
    in a pale pill, the signed-in user with no menu to open: their photo and name
    lead to the Profile, and a Sign out icon button sits beside them
    (`filament.sidebar-user`).
  - **Topbar** (staff only; an employee's pages have none since 2026-11-23, and on a
    phone Filament's own button opens their sidebar): it blends into the page. Search sits on its left (Ctrl+K). On its
    right, staff see the "Waiting for you" bell and **New** (2026-11-23; Upload
    DTRs had a button of its own until then and is New's first item now). The bell (`WaitingForHr`, counted each page load, "99+" past 99)
    lists leave to approve, DTRs that need an employee, DTRs not final yet, then
    staff with no schedule (the Employees list's "No schedule" filter) and staff
    with no opening balance (Opening Balances). New is a menu of what HR files
    most: Upload DTRs, File leave, OB, TO or gate pass, and Employee, each shown only to those
    who may, each opening its form on its own page (`?action=`).
  - **Phones** keep Filament's topbar and slide-out sidebar.
- **Content** runs the full width of the screen (`maxContentWidth(Width::Full)`),
  except the Profile: a few fields, at `FourExtraLarge`, so they read as one column.
- **Breakpoints** (Filament's): 640px, 768px, 1024px (the desktop frame), 1280px
  (the dashboard's four columns).
- **A page, top to bottom:**
  1. Breadcrumbs.
  2. The title, 28px bold, with header actions on the right.
  3. One section or table per job. A section's filters sit in its header
     (`afterHeader`), each with a visible label.
- **Every screen fits a laptop or a tablet** (HR, 2026-11-18): no sideways
  scroll at 1536, 1366, 1280, 1024 or 768px. The content is narrowest at
  1024px, about 700px, since the sidebar is still there.
  - Table text and headings wrap (`configureTables()` in `AppServiceProvider`);
    names and dates keep to one line (`->wrap(false)`). On Leave Applications a
    long name may take two lines below 1280px (`->wrap()` with
    `dtr-nowrap-from-xl`), so View and Edit stay on a 1024px screen.
  - A table still too wide hides its least-used columns with `->visibleFrom('xl')`
    or `'2xl'`; the search still covers a hidden column.
  - Our grids are tighter below 1280px (smaller padding, inputs and type; a long
    name may take two lines) and roomier from 1280px up (`min-width: 80rem`).
  - A DTR's day grid shows O.T. only when the month has some and keeps its Day
    column fixed. From 1024px every day is one line (HR, 2026-11-23): a time
    keeps its OB, TO or HR tag beside it, dates and hours do not break, the
    status badge and its button sit on one line (as inline boxes, and the badge
    is not truncated, so the column is never squeezed under them), and the
    Notes column takes what is left, a long note ending in "…" with its whole
    text in the cell's `title`. At 1024px that leaves Notes about 90px, so most
    warnings are cut there. On a portrait tablet (768px) a date may break after
    its weekday, a tag may drop under its time and a button under its badge:
    one line there would scroll the grid sideways.
- **Wide tables** that still cannot fit, Duty Roster's 31 days, scroll sideways
  inside their frame, with the name column fixed.
- **Long lists** come a page at a time: 25 rows by default (10, 25, 50, 100) on
  our grids. Filament tables keep Filament's pagination.

## 4. Components

### Buttons

| Variant | Use for | Example |
|---|---|---|
| Primary (blue, filled, white text) | The one main action of a view | Save roster, New employee, Download Excel |
| Blue, filled or as a link | Every other action too (HR, 2026-11-24: no grey buttons) | New, Opening balances, Print, Edit, Reopen, Add adjustment, a row's View |
| Outlined | A secondary action beside the primary | Edit, Delete and Restore on an employee's page |
| Danger | Confirming a destructive action | Filament's delete confirmation |

- Use `x-filament::button` or Filament actions; never a hand-made button.
- Labels start with a verb and say what happens ("Save opening balances", not "Submit").
- An icon goes with a label. An icon-only button needs an `aria-label`, as the bell has.
- **Loading:** `x-filament::button` with `wire:click`, and every Filament action,
  shows its own spinner and disables itself while working. Don't build another.
- **Links:** `--primary-700` (dark: `--primary-400`), weight 500, underlined on
  hover and focus.

### Cards and sections

- Page content goes in `x-filament::section`:
  - a short heading (the month, or "Balances as of Oct 31, 2026");
  - at most a one-sentence description;
  - filters in the `afterHeader` slot.
- Dashboard cards (`.dtr-dash-card`): a 16px semibold title with a 12px grey
  caption under it, then the figure or the list.

### Tables and grids

- **Resource lists** (Employees, Leave Applications, DTR Uploads…) are Filament
  tables, with their search, filters, sorting, pagination and row actions. The
  search sits at the left of a table's toolbar, lined up with its first column;
  the filter and column buttons stay at the right; bulk actions, once rows are
  picked, come before the search. A list with a few filters may show them in its
  toolbar right of the search, applied as they change, in place of Filament's
  filter dropdown (DTR uploads: `filament.dtr-uploads.toolbar-filters`, with
  Month, Needs an employee and Not final yet). The DTR list's Status column is a
  badge, "Final" (green, a closed lock) or "Not final" (grey, an open lock), "—"
  for a DTR not computed; it took the place of Month (HR, 2026-11-20), which
  the Month filter already shows.
  Columns: Employee (the printed name under "Needs an employee"; the search
  covers it, with no column of its own), the figures right-aligned (Late +
  undertime, Absent, Days with entries), Uploaded, then Status last, beside
  the actions (HR, 2026-11-20). Days with
  entries was dropped with Month and brought back the same day by HR's request;
  it shows from 1536 px (`visibleFrom('2xl')`), as before: at 1024 the row
  scrolled sideways and cut Delete off, at 1280 it broke Uploaded's time in two. A click on a row opens the DTR. The
  actions show as links with icon and label (View, Choose employee, Delete),
  lined up from the left (`recordActionsAlignment(Alignment::Start->value)`), so View
  stays in one place on a final DTR, which has no Delete. A "⋯" menu that hid
  them was tried and dropped (HR, 2026-11-20): View must be seen.
  The leave lists (HR's and the employee's) follow the same pattern: a click on
  a row opens the application, and the row shows View and, while pending,
  Edit, lined up from the left. Print, Edit, Approve, an outlined red
  Disapprove (HR only) and an outlined Cancel wait in the application's footer
  (2026-11-23): with Print, Approve and Cancel on the row too, it ran past a
  1024px screen with a long name. Edit opens the filing form filled in
  ("Change Leave", "Print the form again after the change."); Disapprove asks
  "Disapproved due to", which prints in 7.D, and leaves 7.C blank.
  The application's view (5xl modal) shows the form itself, HRM-07 as printed
  with the names (HR, 2026-11-23), under one row of what the paper does not
  say: Status, Charged to, Filed, and Approved, Cancelled or Disapproved,
  spread across the modal (`Flex`, `Alignment::Between`, each entry its own
  width so a name and its time stay on one line; a reason may wrap)
  (`filament.leave-applications.form`). It is the `leave.print` view with
  `embedded` (no toolbar, 16px around the sheet, the sheet zoomed to 0.9,
  0.82 or 0.74 when the frame is under 860, 780 or 715px), in an iframe with
  `srcdoc` and `sandbox`: the form's page styles stay in it, no script runs,
  and the app's `frame-ancestors 'none'` needs no exception. The day-by-day
  breakdown left the view; the form's 6.C and 7.C carry the totals.
- **Data entry and reports** use the grid partial, `entry-grid.blade.php`:

| Class | Use |
|---|---|
| `.dtr-grid-wrap` | The frame; add `is-full` to run the page's width |
| `.dtr-grid-table` | The table; `is-dense` for many narrow columns, `is-centered` to centre their headings |
| `.dtr-grid-group` | A row of column-group headings |
| `.dtr-grid-name` | The name column, fixed while the grid scrolls sideways |
| `.dtr-grid-sub` | The grey line under a name or heading |
| `.dtr-grid-value` | A number shown, not typed |
| `.dtr-grid-input` | A typed cell; `is-code` for a short code that fills its column |
| `.dtr-grid-changed` | A cell typed but not saved |
| `.dtr-grid-weekend` | A weekend column |
| `.dtr-grid-legend` | The key to the codes or colours |
| `.dtr-grid-bar` | The save bar, fixed to the bottom of the screen |
| `.dtr-grid-pickers` | The filters in a section's header, side by side |
| `.dtr-grid-errors` | The list of problems above a grid (and the page-level alert) |
| `.dtr-grid-after` | What follows a grid: its pagination, a closing hint |
| `.dtr-grid-start` | A heading over a text column, read from the start |
| `.dtr-grid-badges` | Badges side by side in a cell |
| `.dtr-grid-cell-end` | A typed cell held to the end of its column |
| `tr.is-muted` | A row with nothing to count, such as an employee with no DTR |
| `tr.is-off` | A date that is not a work date: grey fill and grey text |
| `tr.is-absent` | An absent date: a faint red tint, always with its Absent badge |
| `.dtr-grid-add` | A blank HR may fill: a dashed amber box ("+ Add") on a Filament link action |
| `.dtr-grid-ob` | "+ OB/Leave" beside a day's status badge: the dashed box of `.dtr-grid-add`, in the info blue of the OB and travel order badges |
| `.dtr-grid-edit` | With `.dtr-grid-ob`: the "Edit" of an entry already recorded, with less room around the word |

In the DTR modal and its legend, "+ Add", "+ OB/Leave" and "Edit" use xs text
and a 14px icon, as tall as the badges beside them (HR, 2026-11-23: the
grid's own size made them loud).
| `.dtr-grid-hr` / `.dtr-grid-hr-tag` | A time HR copied in: the time as text, then a blue "HR" tag |
| `.dtr-grid-printed` | A printed time HR may correct: plain text on a Filament link action, a dotted underline on hover or focus |

- **Names:** surname first ("Dela Cruz, Juan S."). The employee number goes under
  the name in grey, with the office when every office is listed.
- **Values:** numbers are right-aligned. A report shows "—" where there is no value.

### Inputs and forms

- **Resources:** Filament form fields, with their labels, helper text and rules.
- **Required and optional:** most fields are required, so no field shows an
  asterisk. A field that may be left blank shows a grey "(optional)" right after
  its label (`.dtr-field-optional`). Both are set once, for every form, in
  `AppServiceProvider::configureFields()`; a field gets the mark from its rules
  (not `required()`, not disabled), so nothing is typed per field. The mark is not
  part of the label, so validation messages keep the plain name. Switches,
  checkboxes, fields without a visible label and table filters get no mark. A field
  that is optional alone but one of a set that needs one value opts out with
  `->afterLabel(null)`, as the fill-in modal's times do; a field that always has a
  value (a select without a blank choice) is `required()`.
- **Our pages:** `x-filament::input.wrapper` with `input` or `select`, inside a
  visible `<label class="dtr-grid-picker">`.
- **Grid cells:** `wire:model` (deferred), with an `aria-label` naming the row and
  the column ("Dela Cruz, Juan S., Nov 15").
- **Errors:** Filament marks the field. A grid marks the cell (`fi-invalid`) and
  lists every problem in words, in a `role="alert"` list (`.dtr-grid-errors`) at
  the top of its section.

### Modals

- Filament action modals for create, edit and view, and Filament's confirmations
  for destructive actions. No modals of our own.
- Widths: `SixExtraLarge` for the employee form; `SevenExtraLarge` for a DTR's view;
  `TwoExtraLarge` for the fill-in modal.
- A modal may open one more over it, as Filament nests actions: a DTR's view opens
  the fill-in modal from a blank. Closing the inner one returns to the outer.

### A DTR's view

The modal over the DTR uploads list, headed with the employee's name and the
month. Its pieces, top to bottom (`filament/dtr-uploads/dtr.blade.php`):

- **Who:** the photo or initials (`x-filament::avatar`), then the employee number
  and office, the schedule the month was measured against (ending "· Flexi"
  for an employee on flexitime), and the printed name
  with who uploaded it (`.dtr-record-head`, `.dtr-record-line`, `.dtr-record-sub`).
- **Cards:** the employee page's cards (`.dtr-profile-card`), grey, filling the
  row: Late and Undertime in hours and minutes ("1 hr 13 min"; under an hour the minutes alone, "48 min", never "0 hrs"; as the Monthly Summary shows them); Late + undertime leads with the CSC days ("2.158 days", "equivalent to leave credits"), its hours in the label,
  Absent (the days, with "2.000 days from SL, then VL" under them), and Blanks to fill, which turns
  `is-warning` (amber tint) while any blank is left. A DTR that is not computed
  shows one warning sentence instead (`.dtr-record-warning`).
- **Hours, not minutes:** since 2026-11-20 every late and undertime figure is in that
  hours format: each day's Late and Undertime cells, the DTR list, the dashboard's
  chart and the employee's cards. The Monthly Summary's Excel holds them as
  `[h]:mm` durations, which still add up.
- **Final:** once HR marks it final, a `.dtr-record-sub` line under the cards,
  "Final · Nov 3, 2026 by Ana Reyes · SL −1.250 · VL −0.100" (real minus signs,
  SL before VL), or "nothing deducted". When the figures move after Final, HR sees a `.dtr-record-warning`
  under it: "Changed since Final: deducted 0.100, now 0.752. Reopen and mark it
  final again." The modal footer holds **Mark final** (a confirmation saying
  what it deducts, or why nothing, and how many blanks are still empty) or
  **Reopen** (one click, no reason and no confirmation, HR 2026-11-20; it can
  be marked final again). While final, + Add, the printed-time links,
  + OB/Leave, Edit and Delete are gone.
- **Days:** a grid with A.M. and P.M. as group headings over Arrival and
  Departure, as on the paper form. Grey rows are not work dates, red-tinted rows
  are absent, and Notes say what is missing in amber. An absent day shows its
  hours in the Undertime column ("480 (8 hrs)") and nothing more in Notes.
- **Blanks:** a blank the computation needs is a `.dtr-grid-add` link; a time HR
  filled is a link too, showing the time and the HR tag, with a tooltip saying
  who filled it, when and why. Each has an `aria-label` naming the slot and date
  ("Add P.M. departure, Fri, Oct 2"). A printed time is a `.dtr-grid-printed`
  link ("Change A.M. arrival, Wed, Oct 14") for a time the device got wrong;
  once corrected it shows HR's time and tag, and the tooltip names the printed
  time.
- **+ OB/Leave** beside the status of a day with a part that has no tap (absent,
  or a half with both taps blank), with an `aria-label` naming the date. It opens
  "OB, TO, leave or gate pass from Fri, Oct 16": the OB, Leave & Gate Pass form
  without its employee, three across, the date and the untapped part filled in.
- **Edit** (pencil icon and the word only, `.dtr-grid-ob.dtr-grid-edit`: the
  same box a size smaller, as tall as the badge) beside the status of each day
  an entry covers, one link per entry, on the badge's line from 1024 px; a
  tooltip and `aria-label` name the entry and its dates, "Change or remove the
  official business, Oct 5–6, 2026" (HR, 2026-11-23: a mistake made from the
  DTR must be fixable there; "Edit OB" was too big and broke the line). It opens
  "Official business: Oct 5–6, 2026", the same form filled in, with **Save**
  and an outlined red **Remove**. Leave filed as an application is not listed:
  it is changed on the leave screens.
- **On an OB, travel order or gate pass** a blank of the covered day or half
  shows the schedule's time with an "OB", "TO" or "GP" tag, as the paper form
  does; it is shown, not stored, so removing the entry removes the times. Leave
  shows none. (A gate pass showed none until HR asked, 2026-11-23.)
- **Legend** under the grid for + Add, + OB/Leave, Edit, HR, OB, TO and GP,
  and the grey rows.
- **Fill-in modal:** "Fill in Fri, Oct 2", the date's four slots in a row
  (printed times as read-only text marked Printed, the blanks and HR's times as
  time inputs, the clicked one focused), the reason as radio choices, an
  optional note; Save, Cancel, and an outlined danger "Remove HR times" when the
  date has any. Picking Out on official business fills each empty input with
  the schedule's time; picking Gate pass fills only the time clicked, if empty.
  The time out is the flexi time out due; printed and typed times stay, and HR
  may change or clear what was filled (HR, 2026-11-23).
- **My DTR** shows the same view to the employee, read-only (`viewData(mine: true)`,
  no fill-in action registered): a section headed with the month, the schedule
  and printed name as its description, a Month picker in `afterHeader`, then a
  `.dtr-record-callout` ("Something wrong? Bring your signed DTR to HR…"). The
  who-is-this heading, the Add text and the + Add legend item drop out, and
  Blanks to fill reads **Missing taps**.

### Badges

- `x-filament::badge`, coloured by the enum's `getColor()`, always with its word.
- Report notes ("No DTR uploaded", "10+ lates") are `warning` badges, and so is
  a month's count of ten or more lates. **Habitual** is `danger`.

### Empty states

`x-filament::empty-state` with `:contained="false"`, an outlined icon, a short
heading ("No one to roster") and one sentence that says why, or what to do.

### Notifications

Filament notifications:
- **Success** after a save, with the count: "Roster saved: 3 changes", or "No
  changes to save".
- **Danger** when something blocks the action.
- **Warning** when an action is refused to protect work: "Save the roster first".

### Dashboard pieces

- **The staff dashboard's look** (HR's second reference, 2026-11-23; `.dtr-dash.is-staff`,
  so an employee's own dashboard keeps the plainer look): a soft blue page with pale
  arcs at its top (on `.fi-main-ctn`, the topbar frosted over it), the heading 28px
  bold, the month select with a calendar icon, white cards with 16px corners and a
  soft blue shadow; the four figures with the icon solid in a tinted circle (blue
  calendar, green people, amber document, violet check) and a faint wave in the same
  colour at the card's right (`.dtr-dash-wave`, SVG); a navy icon before each card
  title, "Attendance Overview" and "Quick Actions" in title case, round legend dots;
  four quick actions two by two (Upload DTRs, File Leave, Add Employee, Duty Roster;
  the Summary tile left, 2026-11-23); links ending in an arrow; Upload the rest's note
  in an amber box with a warning icon and its count in amber, then the step as a wide
  blue button (`.dtr-dash-step`).
- **The staff dashboard's layout** (HR's reference, 2026-11-20): a visible
  "Dashboard" heading (`.dtr-dash-heading`, no amber mark; the page's own h1
  stays for screen readers) with a month select at the right (Livewire `month`: last month and the months with a DTR, as DTR Uploads lists them;
  last month by default); four figures (`.dtr-dash-kpi`: a round icon tinted info,
  success, warning or amber, the figure, its label, a link): late and undertime,
  absences, leave to approve, out today; the Attendance overview chart (three
  columns, a scale in hours at its left, the legend and tooltips in hours) beside the quick actions; DTRs for the
  month (Uploaded and Final, each a label and count over its bar), the next step
  (its note in a tinted box), On time and Offices not done (three offices, each
  name over its figures; "+ N more" and View all at the foot); Out today, Leave in
  the year and Recent leave at the foot. Cards in a row are one height: a card's
  closing link sits at its foot (`.dtr-dash-foot`), the gauge and the bars in the
  middle, an empty card shows an icon and its words in the middle
  (`.dtr-dash-empty`), and the quick-action tiles fill their card. Two columns on a
  tablet, one on a phone. Amber stays the primary colour.
- **Stat:** a grey label, the figure, a link.
- **Progress bar:** green on the track.
- **Segmented bar:** with a legend giving each part's share.
- **Gauge:** an SVG half-circle.
- **List rows:** an initials avatar, two lines of text, and the status on the right.
- **Quick-action tiles:** a 44px icon box over its label. As many fit across as
  allow 64px each; the rest wrap to the next row. On the staff dashboard they are
  framed tiles, three across (`.dtr-dash-actions.is-tiles`), the icon in amber. The view's one main action, if
  it has one, is `.is-main`: an amber box with dark text (File leave, on an
  employee's dashboard).
- **Series colours** come from `.is-late` and `.is-undertime`; a stack of lines
  inside a card is `.dtr-dash-stack`.
- **An employee's dashboard** (`.dtr-dash.is-mine`) uses the same cards: Leave
  balance (the employee page's tinted cards, VL first; SPL and Wellness "until Dec
  31") wide beside Quick actions, then Your DTR for the latest month (grey figure
  cards, Missing taps amber when any, a link to My DTR) beside Waiting for
  approval (list rows, each with Print). Three columns from 1280px; balances and
  the DTR full width from 768px; one column on phones.

### An employee's page

Laid out after a reference HR chose, and the only page with a heading on screen.

- **Panel** (HR's reference, 2026-11-23): under the breadcrumbs, the heading, tabs,
  cards and sections sit in one white panel (16px corners) with a soft blue wash at
  its top right; 24px inside from 768px.
- **Heading:** a 128px round photo (their initials without one) with a white ring at
  the panel's top left, and on it a 32px blue pencil that opens Edit for those who
  may edit; beside it the name, 32px bold; under the name, chips: the office (blue,
  a briefcase), where they stand (a dot, green when active), and the number; then
  the tabs. Edit (outlined blue, a pencil) and Delete (outlined red, a trash can)
  stay at the top right, level with the name. Below 768px the photo is 56px beside
  the name and everything stacks.
- **Tabs:** a white pill track, the open tab navy (`--primary-800`) with white words.
- **Cards** (`.dtr-emp-cards`): five, Tardiness for last month then Vacation, Sick,
  Special privilege and Wellness, each a soft gradient in its colour (blue, green,
  violet, amber, red) with a small icon, the label, the figure in bold, a grey line,
  and a large faint icon in the top right corner. Five across from 1280px. The
  schedule left the cards for the Schedules tab; those who earn no leave have none.
- **Sections:** Job Details (title case, blue icon, tighter rows, the employment
  status as a green badge, icons on the hire date and biometric ID) and Deductions
  (blue coins icon, a soft blue note on what the list is).
- **Photo:** Filament's round uploader with its cropper, in the employee form for
  HR and on the Profile page for the employee.
- **Employee form** (the modal): Name first, the photo (a wide rounded box with a
  camera, `.dtr-photo-drop`; the Profile page keeps the round one) beside the four
  name fields (two by two), a rule between them (`.dtr-form-names`); Employment
  four across; Attendance's three fields, then the Active and Flexitime switches
  stacked in the fourth column, labels beside them (`.dtr-form-switches`). Two
  across on a tablet, one on a phone. Headings, not cards, each marked with a
  short amber bar (`.dtr-form-section`), a rule between sections
  (`.dtr-form-section-divided`).
- **Balances** (Leave; it took Opening Balances' place in the sidebar, which now
  opens from its "Opening balances" button, and a Save there that changed
  something comes back here with its notice): the entry grid with Office and Month
  selects (the leave start to this month, last month by default), a row per
  employee, a column per balance (VL, SL, SPL, Wellness): the balance at the
  month's end. Where the month took from it, the cell is a sum (`.dtr-grid-sum`):
  the balance before in small grey (`.dtr-grid-before`), what the month took in
  red (`.dtr-grid-taken`), then the month's end under a rule (`.dtr-grid-total`).
  A row counts in the month it is for: a leave in the month of its
  first date, a credit or a DTR in its period, anything else when posted.
- **Monthly Summary, Without pay:** one column, the total with "DTR x · leave y"
  under it when there is any; the Excel has the three as columns. "Without pay
  only" is a checkbox above the count, out of the section header, shown even when
  nothing matches.
- **Dashboard, closing the month:** "DTRs for <month>" has a second bar, "N final
  of M uploaded"; "Offices not done" lists an office with a DTR not uploaded or not
  final, "uploaded a of s · final f", its bar the share final.
- **Activity log** (admin only): a Filament table, When, Who ("System" when no one
  was signed in), Record (kind, #id under it), Action (badge: created green,
  deleted red, else grey), Fields (from lg); View opens Field / Before / After in a
  grid (`filament.activity-log.changes`). Labels in sentence case.
- **Selects** keep a chosen value on one line, cut with "…", so a long office
  name leaves the box as tall as the inputs beside it; the open list shows every
  name whole.
- **Tabs:** pills on a grey track (`--gray-100`; dark: white at 5%), started
  from the left. The open one is `--primary-400` with dark text, and a focused one
  gets the 2px outline.
- **Cards** (`.dtr-profile-cards`): an icon box, a 12px label in a dark shade
  of the card's colour, the figure, and a grey line under it. Last month leads in
  solid `--primary-400`. Each balance is tinted (14%; dark: 16%) in its leave
  chart colour, and the schedule in grey. Six across from 1280px; below that, as
  many as fit at 9rem. In a DTR's view and on an employee's dashboard they fill
  their row instead; `.is-warning` tints one amber when it needs a look.
- **Details:** Filament sections, divided, with inline labels: one fact a row.
- **Needs a look** (`.dtr-callout`): an amber tint and ring, a 40px amber icon
  box, "Needs a look", a 16px heading, one sentence and one button. It shows
  only when something needs a look.
- **Deductions** (`.dtr-deductions`, HR, 2026-11-23, in the Name card's place;
  the name stays in the heading and the form): a section, for those who earn
  leave, with one line per deduction this year, newest first: what on the left
  ("October 2026 DTR", and "57 min late and undertime" in grey under it; a
  leave's type and dates), the leave and days on the right in red ("VL
  −0.119"), then a total per leave under a rule. Only what still stands: a
  DTR's rows since it was last reopened, a leave's net charge (none once
  cancelled), and forced leave, lapses and adjustments that took days.

All in `entries.blade.php`.

### The Profile

Everyone's own page, from the user menu (`App\Filament\Pages\Auth\ChangePassword`).

- **Who** (an account linked to an employee): a card with the round photo
  uploader on the left and, beside it, the name (20px semibold), the position
  and office in grey, and "Click the photo to change it." (`.dtr-profile-identity`).
- **Employment:** a compact, divided section with inline labels, one fact a row:
  Employee No., Office, Position, Employment status, Date hired, Schedule. Shown,
  never typed, with "As HR keeps it. Ask HR to correct anything here."
- **Account:** the email, then the new password (optional for an employee, who
  may save only a photo), its confirmation and the current password.
- Staff without an employee see only Account.

## 5. States

| State | Looks like | Where |
|---|---|---|
| Hover | Grey fill (`--gray-50`; white 5% dark) | Nav items, rows, tiles, topbar cards |
| Keyboard focus | 2px outline, `--gray-500` (dark `--gray-400`): inset 2px in lists and the nav, 2px out around tiles and cards. Filament's own ring on inputs and buttons | Everything that can be focused |
| Current | `--gray-100` fill, `--gray-950` text, weight 500 | The open page in the sidebar |
| Current row | Faint amber tint | The grid row being typed in |
| Disabled | Filament's own | Buttons and inputs |
| Loading | Filament's spinners on buttons and tables | Saving, downloading |
| Unsaved | Amber tint on the cell, and a warning badge "N unsaved changes" counted on the server | Grids; changing the month is refused while any are unsaved |
| Invalid | Red ring on the field or cell, and the problem in words | Forms and grids |
| Empty | An empty-state component | Lists and grids with nothing to show |
| Locked | The value as text, a lock icon, and one line saying why | "Taken this year" of an opening already in use |
| In use | Still an input, a lock icon and "In use: a change is saved as an adjustment dated today"; Save asks first, naming each change | Opening balances that credits or leave have posted against |
| To fill | A dashed amber "+ Add" in the blank, and an amber Blanks to fill card | A DTR's blanks that HR can fill from the signed paper |
| Filled by HR | The time, then a blue "HR" tag, and the reason in Notes | A time HR copied into a DTR's blank |
| Stale | A line saying how old the figures are | The dashboard's DTR figures, kept 10 minutes |

## 6. Accessibility

- **Contrast:** text at least 4.5:1, large text and icons at least 3:1. These
  pairs are checked:

| Pair | Ratio |
|---|---|
| `--gray-500` on white | ≈ 4.8:1 |
| `--gray-400` on `--gray-900` | ≈ 7:1 |
| `--gray-700` on white | ≈ 10:1 |
| `--primary-700` link on white | ≈ 5:1 |
| White on `--primary-600` (Filament's blue buttons) | ≈ 5.2:1 |
| White on the navy sidebar (`--primary-900`) | ≈ 13:1 |

  Don't use `--gray-400` text on white (about 2.5:1). Blue fills take white text;
  amber (warning) fills take dark text.
- **Keyboard:**
  - Every action can be reached with Tab and run with Enter or Space.
  - Esc closes modals and menus (Filament).
  - Ctrl+K opens search.
  - The dashboard's chart columns take focus and say their figures.
  - Never remove a focus outline.
- **Structure:** one `h1` per page, hidden on screen but read aloud; an `h2` per
  card or section; the page content in `main`.
- **Labels:** every input has a visible label. In a grid, an `aria-label` names
  the row and the column. Never rely on a placeholder alone.
- **Icons:** a decorative icon is `aria-hidden`; an icon-only button has an `aria-label`.
- **Colour is never the only signal.** Badges carry words, legends carry labels,
  and a count is also in the button's label.
- **Targets:** at least 24px (WCAG 2.2), with 40px for main controls, and at least
  8px between neighbours.

## 7. Writing

- **Language and voice:** English UI copy, in sentence case, short and plain.
- **Formats:**
  - Units: "24 min", "0.125 days", "3 of 97".
  - Dates: "Oct 31, 2026", or "October 2026" for a month.
  - Times: 12-hour, as on the DTR: "8:14 AM"; a schedule's hours "8:00 AM – 12:00 PM,
    1:00 PM – 5:00 PM" (`Schedule::hours`). The 24-hour form is for storage only.
  - Names: "Dela Cruz, Juan S.".
  - Plurals: `str()->plural()`.
- **Notifications** say what happened, with the count.
- **Errors** say what is wrong and with which cell: "\"NN\" for Dela Cruz, Juan S.
  on Nov 2 is not a schedule code."
- **Empty states** say what to do next.

## 8. Rules

### Where the styles live

| File | Holds |
|---|---|
| `resources/views/filament/sidebar.blade.php` | The frame: sidebar, topbar, page header spacing, hidden headings, the tables' search on the left |
| `resources/views/filament/entry-grid.blade.php` | The grids (`.dtr-grid-*`) |
| `resources/views/filament/entries.blade.php` | Read views: grey labels, near-black values; an employee's page: its heading and chips (`.dtr-person`, `.dtr-person-chips`), tabs, cards (`.dtr-profile-*`) and Needs a look card (`.dtr-callout`); the Leave types cards (`.dtr-cards`, `.dtr-card-figure`); a DTR's view (`.dtr-record-*`); the "(optional)" mark beside a form field's label (`.dtr-field-optional`) |
| `resources/views/filament/dashboard-styles.blade.php` | Both dashboards' styles (`.dtr-dash-*`, the employee layout `.dtr-dash.is-mine`) |
| `resources/views/filament/dashboard.blade.php` | The staff dashboard's cards |
| `resources/views/filament/employee/dashboard.blade.php` | An employee's dashboard's cards |
| `resources/views/filament/topbar-actions.blade.php` | The topbar's right side (its styles are in `sidebar.blade.php`) |
| `resources/views/filament/brand.blade.php` | The brand |
| `resources/views/filament/sidebar-user.blade.php` | The signed-in user and Sign out at the sidebar's foot (its styles are in `sidebar.blade.php`) |

### Writing styles

- **Where the CSS goes:** in these partials, loaded by render hooks or
  `@include`, until the panel gets a theme. This is a known shortcut.
- **No `!important`.** Our CSS sits outside Filament's cascade layers, so it wins
  without it.
- **Class names:** our own classes start with `dtr-`. Target Filament's `fi-`
  classes only to restyle Filament.
- **Values:** use Filament's variables (`--gray-*`, `--primary-*`, `--spacing`,
  `--text-*`, `--radius-*`), never raw hex, and define every colour for `.dark` too.
- **Inline `style=""`** only for values that come from data, such as bar widths
  and heights, or a series colour picked by the row's label (`--key`). Layout
  and fixed colours go in classes.
- **Reuse before writing new:** a grid page includes `filament.entry-grid`; a new
  page follows the section-with-filters pattern of the Monthly Summary.

### Before shipping a screen

- Light only.
- 1536, 1366, 1280, 1024 and 768px with no sideways scroll, and a phone.
- A keyboard-only pass.
- Contrast of any new colour pair.

## 9. Known deviations

None open: on 2026-11-04 every screen was brought in line with this guide. List a
new deviation here, with the page, when one is found and not fixed at once.

Intentional exceptions:

- **Duty Roster** scrolls sideways at every width up to 1536px: 31 day boxes
  cannot shrink below a usable size. The names stay fixed. A half-month view
  would fit, if the page stays.

- **Paper documents** (the HRM-07 leave form) use raw hex and Arial: they are
  printed forms, not screens.
- **The initials avatar** on an employee's page is an SVG with blue hex values
  (`#dbeafe` / `#1e40af`, `--primary-100` / `--primary-800`): an image cannot read
  CSS variables.
