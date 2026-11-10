# DTR System design guide

How every screen of the DTR System looks and behaves, so a new page matches the
pages already built. It records what the app does today, read from its code; where
a page still differs from the rule, **Known deviations** says so. Change this guide
in the same commit as any change to the look it describes.

The app is one Filament 5 panel. Filament draws most of it; our own styles are a
few Blade partials in `resources/views/filament/` (see **Where the styles live**).

## 1. Concept

- **A quiet frame, the data first.** Surfaces are neutral greys and white. Amber
  (the primary colour) marks the one main action of a view and things that need a
  look; every other colour on screen is a status or a data series.
- **Filament first.** Use Filament's components (sections, tables, buttons,
  badges, modals, empty states, notifications) as they are. Write our own markup
  only for the data-entry grids, the dashboard and the frame.
- **Dense where HR types, roomy where HR reads.** The grids (Duty Roster, Opening
  Balances, Monthly Summary, Habitual Tardiness) pack rows tightly for long sessions; the dashboard
  gives each card room. Both use the same tokens.
- **No titles on screen.** The sidebar marks the page that is open and the
  breadcrumbs and sections say the rest; each page's `h1` stays for screen
  readers. An employee's page is the exception: its heading (initials and name)
  says whose page it is.
- **Light and dark.** Every colour is defined for both; check both.

## 2. Tokens

All values are Filament's CSS variables, which follow Tailwind's scales. Use the
variable, never the raw value (see **Rules**).

### 2.1 Colour

The panel's palette: **primary = amber**, **gray = zinc**, and Filament's status
colours (success = green, warning = amber, danger = red, info = blue).

| Role | Light | Dark | Used for |
|---|---|---|---|
| Primary | `--primary-500` fill, `--primary-700` text | `--primary-400` text | The main button of a view; links; the count on the topbar bell; the initials avatars (`--primary-100` / `--primary-800`); on an employee's page, the open tab, the lead card and the Needs a look card |
| Secondary | Filament `gray` buttons (white, thin ring) | same, dark | Every action that is not the main one |
| Accent | none | none | There is no separate accent hue: colour beyond amber is status or data only |
| Page background | `--gray-50` | `--gray-950` | Behind cards; the topbar on desktop |
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
| `danger` | Wrong or destructive | Validation errors, Absent, Habitual, Delete |
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
- **Icons:** Heroicons, outlined. 20px in navigation and actions, 16px inline
  (the lock), 24px in empty states.
- **Control height:** 40px for topbar cards and main controls; quick-action tiles 44px.

### 2.5 Motion

Filament's own transitions (75–150ms). Tooltips fade in 120ms. Nothing moves for
decoration.

## 3. Layout

- **Frame (desktop, 1024px and up).**
  - **Sidebar:** 16rem wide, a white card set 8px in from the screen's edges. It
    holds the brand card (seal, "DTR System", "DTRC Caraga"), the groups, and the
    signed-in user at its foot.
  - **Topbar:** it blends into the page. Search sits on its left (Ctrl+K). On its
    right, staff see the "Waiting for you" bell and **Upload DTRs**.
  - **Phones** keep Filament's topbar and slide-out sidebar.
- **Content** runs the full width of the screen (`maxContentWidth(Width::Full)`),
  except the Profile: a few fields, at `FourExtraLarge`, so they read as one column.
- **Breakpoints** (Filament's): 640px, 768px, 1024px (the desktop frame), 1280px
  (the dashboard's four columns).
- **A page, top to bottom:**
  1. A hidden `h1`.
  2. Breadcrumbs, with header actions on the right.
  3. One section or table per job. A section's filters sit in its header
     (`afterHeader`), each with a visible label.
- **Wide tables** scroll sideways inside their frame, with the name column fixed.
- **Long lists** come a page at a time: 25 rows by default (10, 25, 50, 100) on
  our grids. Filament tables keep Filament's pagination.

## 4. Components

### Buttons

| Variant | Use for | Example |
|---|---|---|
| Primary (amber, filled) | The one main action of a view | Save roster, New employee, Download Excel |
| Gray | Secondary actions | Upload DTRs in the topbar, Import CSV |
| Outlined | A secondary action beside the primary | Delete and Restore on an employee's page |
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
  picked, come before the search.
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
| `.dtr-grid-ob` | "+ OB/TO" beside a day's status badge: the dashed box of `.dtr-grid-add`, in the info blue of the OB and travel order badges |
| `.dtr-grid-hr` / `.dtr-grid-hr-tag` | A time HR copied in: the time as text, then a blue "HR" tag |

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
  and office, the schedule the month was measured against, and the printed name
  with who uploaded it (`.dtr-record-head`, `.dtr-record-line`, `.dtr-record-sub`).
- **Cards:** the employee page's cards (`.dtr-profile-card`), grey, filling the
  row: Late, Undertime, Late + undertime, Absent, and Blanks to fill, which turns
  `is-warning` (amber tint) while any blank is left. A DTR that is not computed
  shows one warning sentence instead (`.dtr-record-warning`).
- **Days:** a grid with A.M. and P.M. as group headings over Arrival and
  Departure, as on the paper form. Grey rows are not work dates, red-tinted rows
  are absent, and Notes say what is missing in amber.
- **Blanks:** a blank the computation needs is a `.dtr-grid-add` link; a time HR
  filled is a link too, showing the time and the HR tag, with a tooltip saying
  who filled it, when and why. Each has an `aria-label` naming the slot and date
  ("Add P.M. departure, Fri, Oct 2").
- **+ OB/TO** beside the status of a day with a part that has no tap (absent, or
  a half with both taps blank), with an `aria-label` naming the date. It opens
  "OB or travel order from Fri, Oct 16": the OB & travel orders form without its
  employee, three across, the date and the untapped part filled in.
- **Legend** under the grid for + Add, HR and the grey rows.
- **Fill-in modal:** "Fill in Fri, Oct 2", the date's four slots in a row
  (printed times as read-only text marked Printed, the blanks and HR's times as
  time inputs, the clicked one focused), the reason as two radio choices, an
  optional note; Save, Cancel, and an outlined danger "Remove HR times" when the
  date has any.
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

- **Stat:** a grey label, the figure, a link.
- **Progress bar:** green on the track.
- **Segmented bar:** with a legend giving each part's share.
- **Gauge:** an SVG half-circle.
- **List rows:** an initials avatar, two lines of text, and the status on the right.
- **Quick-action tiles:** a 44px icon box over its label. As many fit across as
  allow 64px each; the rest wrap to the next row. The view's one main action, if
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

- **Heading:** 16px under the breadcrumbs, a 56px round photo (their initials
  without one) centred on the name, and the name 28px from 640px. Under the name,
  lined up with it: the position and office in grey, then chips for the status,
  the employment status and the number.
- **Photo:** Filament's round uploader with its cropper, in the employee form for
  HR and on the Profile page for the employee.
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
| Locked | The value as text, a lock icon, and one line saying why | Opening balances that can no longer change |
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
| `--gray-950` on `--primary-500` | ≈ 9:1 |

  Don't use `--gray-400` text on white (about 2.5:1). Amber fills take dark text,
  never white.
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

- Light and dark.
- 1024px, 1400px and a phone.
- A keyboard-only pass.
- Contrast of any new colour pair.

## 9. Known deviations

None open: on 2026-11-04 every screen was brought in line with this guide. List a
new deviation here, with the page, when one is found and not fixed at once.

Intentional exceptions:

- **Paper documents** (the HRM-07 leave form) use raw hex and Arial: they are
  printed forms, not screens.
- **The initials avatar** on an employee's page is an SVG with amber hex values:
  an image cannot read CSS variables.
