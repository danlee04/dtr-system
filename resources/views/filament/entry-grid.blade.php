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
        --grid-muted: var(--gray-500);
    }

    .dark .dtr-grid {
        --grid-bg: var(--gray-900);
        --grid-head: color-mix(in oklab, var(--color-white) 5%, var(--gray-900));
        --grid-line: color-mix(in oklab, var(--color-white) 10%, transparent);
        --grid-hover: color-mix(in oklab, var(--color-white) 4%, var(--gray-900));
        --grid-weekend: color-mix(in oklab, var(--color-white) 6%, var(--gray-900));
        --grid-current: color-mix(in oklab, var(--primary-400) 12%, var(--gray-900));
        --grid-muted: var(--gray-400);
    }

    /* A focused cell near the bottom must not hide behind the save bar. */
    html {
        scroll-padding-bottom: 6rem;
    }

    .dtr-grid-picker {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
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

    .dtr-grid-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.875rem;
    }

    .dtr-grid-table th,
    .dtr-grid-table td {
        padding: 0.375rem 0.75rem;
        background-color: var(--grid-bg);
    }

    /* Many narrow columns, as a month of days. */
    .dtr-grid-table.is-dense th,
    .dtr-grid-table.is-dense td {
        padding: 0.375rem 0.125rem;
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
        min-width: 14rem;
        padding-inline: 0.75rem;
        text-align: start;
        white-space: nowrap;
        box-shadow: inset -1px 0 0 var(--grid-line);
    }

    /* Small grey text under a name or a heading: an employee number, a limit, a weekday. */
    .dtr-grid-sub {
        display: block;
        font-size: 0.75rem;
        font-weight: 400;
        color: var(--grid-muted);
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

    .dtr-grid-input {
        width: 5.5rem;
        text-align: end;
        font-variant-numeric: tabular-nums;
    }

    .dtr-grid-input.is-code {
        width: 2.75rem;
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

    .dark .dtr-grid-legend strong {
        color: var(--color-white);
    }
</style>
