{{--
    The look of the grids HR types into for an hour at a time, one office at a time
    (opening balances, the duty roster). They are built for keeping one's place: the
    row being typed in is lit, the employee's name stays on screen while the table
    scrolls sideways, a changed cell is tinted until it is saved, and the save bar
    follows the page down. Put .dtr-grid on the section that holds the grid.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    .dtr-grid {
        --grid-bg: var(--color-white);
        --grid-head: var(--gray-50);
        --grid-line: color-mix(in oklab, var(--gray-950) 10%, transparent);
        --grid-hover: color-mix(in oklab, var(--gray-950) 3%, var(--color-white));
        --grid-weekend: color-mix(in oklab, var(--gray-950) 5%, var(--color-white));
        --grid-current: color-mix(in oklab, var(--primary-500) 9%, var(--color-white));
        --grid-absent: color-mix(in oklab, var(--danger-500) 7%, var(--color-white));
        --grid-muted: var(--gray-500);
    }

    /* A focused cell near the bottom must not hide behind the save bar. */
    html {
        scroll-padding-bottom: 6rem;
    }

    /* A section header's filters side by side, each with its label. */
    .dtr-grid-pickers {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-grid-picker {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* What is wrong with what was typed, in words, above the grid. */
    .dtr-grid-errors {
        display: grid;
        gap: calc(var(--spacing) * 1);
        margin-bottom: calc(var(--spacing) * 4);
        color: var(--danger-600);
    }

    /* What follows the grid: its pagination, a closing hint. */
    .dtr-grid-after {
        margin-top: calc(var(--spacing) * 3);
    }

    /* Badges side by side in a cell, and a button beside them on the same line. */
    .dtr-grid-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: calc(var(--spacing) * 1);
    }

    /* A typed cell held to the end of its column, under its right-aligned heading. */
    .dtr-grid-cell-end {
        width: fit-content;
        margin-inline-start: auto;
    }

    .dtr-grid-count {
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        color: var(--grid-muted);
    }

    /* As wide as the cells need, so each one sits close to its name. */
    .dtr-grid-wrap {
        width: fit-content;
        max-width: 100%;
        overflow-x: auto;
        border: 1px solid var(--grid-line);
        border-radius: 0.75rem;
    }

    /* As wide as the page, so a grid uses the room it has. */
    .dtr-grid-wrap.is-full {
        width: auto;
    }

    .dtr-grid-wrap.is-full > .dtr-grid-table {
        width: 100%;
    }

    .dtr-grid-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.875rem;
    }

    /* Small screens first: tighter cells so a grid fits a laptop or a tablet (HR, 2026-11-18); roomier from 1280px. */
    .dtr-grid-table th,
    .dtr-grid-table td {
        padding: 0.375rem 0.5rem;
        background-color: var(--grid-bg);
    }

    @media (min-width: 80rem) {
        .dtr-grid-table th,
        .dtr-grid-table td {
            padding-inline: 0.75rem;
        }
    }

    /* Many narrow columns, as a month of days, with 8px between neighbouring inputs so a click lands on the right day. */
    .dtr-grid-table.is-dense th,
    .dtr-grid-table.is-dense td {
        padding: 0.5rem 0.25rem;
    }

    .dtr-grid-table thead th {
        background-color: var(--grid-head);
        font-weight: 600;
        text-align: end;
        vertical-align: bottom;
    }

    .dtr-grid-table.is-centered thead th:not(.dtr-grid-name) {
        text-align: center;
    }

    /* A heading over text, read from the start. */
    .dtr-grid-table thead th.dtr-grid-start {
        text-align: start;
    }

    .dtr-grid-table .dtr-grid-group th {
        padding-bottom: 0;
        text-align: center;
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--grid-muted);
    }

    .dtr-grid-table .dtr-grid-weekend {
        background-color: var(--grid-weekend);
    }

    .dtr-grid-table tbody tr > * {
        border-top: 1px solid var(--grid-line);
    }

    /* A row with nothing to count, as an employee with no DTR in a report. */
    .dtr-grid-table tbody tr.is-muted {
        color: var(--grid-muted);
    }

    .dtr-grid-table tbody tr:hover > * {
        background-color: var(--grid-hover);
    }

    .dtr-grid-table tbody tr:focus-within > * {
        background-color: var(--grid-current);
    }

    /* The name stays put while the cells scroll sideways. */
    .dtr-grid-table .dtr-grid-name {
        position: sticky;
        left: 0;
        z-index: 1;
        min-width: 9rem;
        padding-inline: 0.75rem;
        text-align: start;
        box-shadow: inset -1px 0 0 var(--grid-line);
    }

    /* A long name may take two lines on a small screen; from 1280px it keeps to one. */
    @media (min-width: 80rem) {
        .dtr-grid-table .dtr-grid-name {
            min-width: 12rem;
            white-space: nowrap;
        }
    }

    /* A long office under the name wraps, so it does not widen the grid past a laptop's screen. */
    .dtr-grid-table .dtr-grid-name .dtr-grid-sub {
        white-space: normal;
    }

    /* Small grey text under a name or a heading: an employee number, a limit, a weekday. */
    .dtr-grid-sub {
        display: block;
        font-size: 0.75rem;
        font-weight: 400;
        color: var(--grid-muted);
    }

    /* Under a balance, in red: what the month took from it (Balances). */
    .dtr-grid-taken {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--danger-600);
    }

    /* On Balances, a sum where the month took something: the balance before it, small and grey; the red; then the month's end under a rule. */
    .dtr-grid-sum {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .dtr-grid-before {
        font-size: 0.75rem;
        color: var(--grid-muted);
    }

    .dtr-grid-total {
        align-self: stretch;
        margin-top: 0.125rem;
        padding-top: 0.125rem;
        border-top: 1px solid var(--grid-line);
        text-align: end;
    }

    .dtr-grid-lock {
        display: flex;
        align-items: flex-start;
        gap: 0.25rem;
        max-width: 15rem;
        font-size: 0.75rem;
        font-weight: 400;
        color: var(--grid-muted);
        white-space: normal;
    }

    /* A flex item shrinks to make room for the text; the icon must not. */
    .dtr-grid-lock > svg {
        flex: none;
        width: 0.875rem;
        height: 0.875rem;
        margin-top: 0.0625rem;
    }

    /* A rule that sets a column apart from the ones before it. */
    .dtr-grid-table .dtr-grid-divider {
        border-inline-start: 1px solid var(--grid-line);
    }

    /* Room for "123.456"; a little more from 1280px. */
    .dtr-grid-input {
        width: 4.5rem;
        text-align: end;
        font-variant-numeric: tabular-nums;
    }

    @media (min-width: 80rem) {
        .dtr-grid-input {
            width: 5.5rem;
        }
    }

    /* Fills its day's column, so a wide screen gives bigger boxes; never narrower than a code. */
    .dtr-grid-input.is-code {
        width: 100%;
        min-width: 2.75rem;
        padding-inline: 0.25rem;
        text-align: center;
        text-transform: uppercase;
    }

    .dtr-grid-value {
        text-align: end;
        font-variant-numeric: tabular-nums;
    }

    .dtr-grid-changed {
        background-color: color-mix(in oklab, var(--warning-400) 25%, transparent);
    }

    .dtr-grid-bar {
        position: sticky;
        bottom: 0;
        z-index: 2;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        margin-top: 1rem;
        padding-block: 0.75rem;
        border-top: 1px solid var(--grid-line);
        background-color: var(--grid-bg);
    }

    .dtr-grid-hint {
        font-size: 0.875rem;
        color: var(--grid-muted);
    }

    .dtr-grid-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem 1rem;
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        color: var(--grid-muted);
    }

    .dtr-grid-legend strong {
        margin-inline-end: 0.25rem;
        font-weight: 600;
        color: var(--gray-950);
    }

    /* A DTR's dates: grey when not a work date; tinted red when absent, which its badge also says. */
    .dtr-grid-table tbody tr.is-off > * {
        background-color: var(--grid-weekend);
        color: var(--grid-muted);
    }

    .dtr-grid-table tbody tr.is-absent > * {
        background-color: var(--grid-absent);
    }

    /* A blank HR may fill from the signed DTR: a dashed amber box, with dark text on its tint (about 7:1). */
    .dtr-grid-add {
        padding: 0.125rem 0.5rem;
        border: 1px dashed var(--warning-600);
        border-radius: var(--radius-md);
        background-color: color-mix(in oklab, var(--warning-400) 14%, transparent);
        color: var(--warning-800);
    }

    .dtr-grid-add > .fi-icon {
        color: inherit;
    }

    /*
     * "+ OB/TO" beside a day's badge: the same dashed box as "+ Add", in the blue of
     * the OB and travel order badges, with dark text on its tint (about 6:1).
     */
    .dtr-grid-ob {
        padding: 0.125rem 0.5rem;
        border: 1px dashed var(--info-600);
        border-radius: var(--radius-md);
        background-color: color-mix(in oklab, var(--info-500) 10%, transparent);
        color: var(--info-700);
        white-space: nowrap;
    }

    .dtr-grid-ob > .fi-icon {
        color: inherit;
    }

    /* A time HR copied in reads like a printed one, then a blue "HR" tag: the word, not only the colour. */
    .dtr-grid-hr {
        padding-block: 0.125rem;
        color: inherit;
    }

    /* A printed time HR may correct reads as printed; a dotted underline on hover or focus says it opens. */
    .dtr-grid-printed {
        padding-block: 0.125rem;
        color: inherit;
        font-weight: inherit;
    }

    .dtr-grid-printed:hover,
    .dtr-grid-printed:focus-visible {
        text-decoration: underline dotted;
        text-underline-offset: 3px;
    }

    .dtr-grid-hr-tag {
        display: inline-block;
        padding-inline: 0.375rem;
        border-radius: var(--radius-md);
        background-color: color-mix(in oklab, var(--info-500) 14%, transparent);
        color: var(--info-700);
        font-size: var(--text-xs);
        font-weight: 600;
        line-height: 1.25rem;
    }
</style>
