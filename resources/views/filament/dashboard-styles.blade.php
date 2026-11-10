{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    .dtr-dash {
        --dash-card: var(--color-white);
        --dash-line: color-mix(in oklab, var(--gray-950) 6%, transparent);
        --dash-track: var(--gray-100);
        --dash-text: var(--gray-950);
        --dash-muted: var(--gray-500);

        display: grid;
        gap: calc(var(--spacing) * 4);
        grid-template-columns: minmax(0, 1fr);
        grid-template-areas: "a" "b" "c" "d" "e" "f" "g" "h" "i";
    }

    .dark .dtr-dash {
        --dash-card: var(--gray-900);
        --dash-line: color-mix(in oklab, var(--color-white) 10%, transparent);
        --dash-track: color-mix(in oklab, var(--color-white) 6%, transparent);
        --dash-text: var(--color-white);
        --dash-muted: var(--gray-400);
    }

    @media (min-width: 48rem) {
        .dtr-dash {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-template-areas: "a a" "b c" "d e" "f g" "h i";
        }
    }

    /* The reference's layout: the chart wide on top, the quick actions down the right, four cards along the foot. */
    @media (min-width: 80rem) {
        .dtr-dash {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            grid-template-areas: "a a b c" "d e e c" "f g h i";
        }
    }

    .dtr-dash-a { grid-area: a; }
    .dtr-dash-b { grid-area: b; }
    .dtr-dash-c { grid-area: c; }
    .dtr-dash-d { grid-area: d; }
    .dtr-dash-e { grid-area: e; }
    .dtr-dash-f { grid-area: f; }
    .dtr-dash-g { grid-area: g; }
    .dtr-dash-h { grid-area: h; }
    .dtr-dash-i { grid-area: i; }

    .dtr-dash-card {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 3);
        min-width: 0;
        padding: calc(var(--spacing) * 5);
        border-radius: var(--radius-xl);
        background-color: var(--dash-card);
        box-shadow: 0 0 0 1px var(--dash-line);
        color: var(--dash-text);
    }

    .dtr-dash-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash-title {
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-semibold);
    }

    .dtr-dash-sub,
    .dtr-dash-muted {
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        color: var(--dash-muted);
    }

    .dtr-dash-big,
    .dtr-dash-num {
        font-weight: var(--font-weight-semibold);
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .dtr-dash-big {
        font-size: 2.25rem;
        line-height: 1.1;
    }

    .dtr-dash-num {
        font-size: 1.75rem;
        line-height: 1.15;
    }

    .dtr-dash-big small,
    .dtr-dash-num small {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        letter-spacing: normal;
        color: var(--dash-muted);
    }

    .dtr-dash-link {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        color: var(--primary-700);
    }

    .dark .dtr-dash-link {
        color: var(--primary-400);
    }

    .dtr-dash-link:hover,
    .dtr-dash-link:focus-visible {
        text-decoration: underline;
    }

    .dtr-dash-key {
        display: inline-block;
        flex: none;
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 0.1875rem;
        background-color: var(--key);
    }

    .dtr-dash-legend {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 3);
        font-size: var(--text-xs);
        color: var(--dash-muted);
    }

    .dtr-dash-legend li {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 1.5);
    }

    /* A: a column per weekday, the day's late and undertime minutes stacked on a grey track. */
    .dtr-dash-chart {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: calc(var(--spacing) * 3);
        height: 12rem;
        margin-top: calc(var(--spacing) * 6);
    }

    .dtr-dash-col {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 2);
        border-radius: 0.5rem;
    }

    .dtr-dash-col:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .dtr-dash-track {
        display: flex;
        flex: 1;
        flex-direction: column;
        justify-content: flex-end;
        overflow: hidden;
        border-radius: 0.5rem;
        background-color: var(--dash-track);
    }

    .dtr-dash-track > span {
        display: block;
        flex: none;
        background-color: var(--key);
    }

    /* The two series, in the legend and in the bars. */
    .dtr-dash .is-late {
        --key: var(--warning-400);
    }

    .dtr-dash .is-undertime {
        --key: var(--info-400);
    }

    /* A few lines one under the other, 8px apart. */
    .dtr-dash-stack {
        display: grid;
        gap: calc(var(--spacing) * 2);
    }

    .dtr-dash-day {
        font-size: var(--text-xs);
        text-align: center;
        color: var(--dash-muted);
    }

    /* The day's figures over its column, on hover or keyboard focus. */
    .dtr-dash-tip {
        position: absolute;
        bottom: calc(100% + 0.375rem);
        left: 50%;
        z-index: 1;
        padding: calc(var(--spacing) * 1.5) calc(var(--spacing) * 2);
        border-radius: 0.5rem;
        background-color: var(--gray-950);
        color: var(--color-white);
        font-size: var(--text-xs);
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transform: translateX(-50%);
        transition: opacity 120ms;
    }

    .dtr-dash-col:hover .dtr-dash-tip,
    .dtr-dash-col:focus-visible .dtr-dash-tip {
        opacity: 1;
    }

    /* B: three figures, one under the other. */
    .dtr-dash-stats {
        display: grid;
    }

    .dtr-dash-stat {
        display: grid;
        gap: calc(var(--spacing) * 1);
        padding-block: calc(var(--spacing) * 3);
    }

    .dtr-dash-stat + .dtr-dash-stat {
        border-top: 1px solid var(--dash-line);
    }

    .dtr-dash-stat:first-child {
        padding-top: 0;
    }

    .dtr-dash-stat:last-child {
        padding-bottom: 0;
    }

    /* C: the quick actions as tiles, then who is out today. */
    /* As many tiles across as fit at 64px each; the rest wrap to a second row rather than crowd. */
    .dtr-dash-actions {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(4rem, 1fr));
        gap: calc(var(--spacing) * 2);
    }

    .dtr-dash-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: calc(var(--spacing) * 1.5);
        font-size: var(--text-xs);
        line-height: 1.25;
        text-align: center;
        color: var(--gray-700);
    }

    .dark .dtr-dash-action {
        color: var(--gray-300);
    }

    .dtr-dash-action-icon {
        display: grid;
        place-items: center;
        width: calc(var(--spacing) * 11);
        height: calc(var(--spacing) * 11);
        border-radius: 0.75rem;
        box-shadow: 0 0 0 1px var(--dash-line);
    }

    .dtr-dash-action:hover .dtr-dash-action-icon,
    .dtr-dash-action:focus-visible .dtr-dash-action-icon {
        background-color: var(--dash-track);
    }

    .dtr-dash-action:focus-visible {
        outline: none;
    }

    .dtr-dash-action:focus-visible .dtr-dash-action-icon {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .dtr-dash-action-icon > svg {
        width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
    }

    .dtr-dash-divider {
        border-top: 1px solid var(--dash-line);
    }

    .dtr-dash-people {
        display: flex;
        flex-wrap: wrap;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash-person {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: calc(var(--spacing) * 1);
        width: calc(var(--spacing) * 14);
        font-size: var(--text-xs);
        text-align: center;
    }

    .dtr-dash-person > span:last-child {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dtr-dash-avatar {
        display: grid;
        flex: none;
        place-items: center;
        width: calc(var(--spacing) * 10);
        height: calc(var(--spacing) * 10);
        border-radius: 9999px;
        background-color: var(--primary-100);
        color: var(--primary-800);
        font-size: var(--text-xs);
        font-weight: var(--font-weight-semibold);
    }

    .dark .dtr-dash-avatar {
        background-color: color-mix(in oklab, var(--primary-400) 20%, transparent);
        color: var(--primary-300);
    }

    /* D and H: progress bars. */
    .dtr-dash-progress {
        height: 0.75rem;
        overflow: hidden;
        border-radius: 9999px;
        background-color: var(--dash-track);
    }

    .dtr-dash-progress.is-thin {
        height: 0.5rem;
    }

    .dtr-dash-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background-color: var(--success-500);
    }

    .dtr-dash-between {
        display: flex;
        justify-content: space-between;
        gap: calc(var(--spacing) * 2);
        font-size: var(--text-sm);
    }

    /* E: the next step, with a bulb beside it. */
    .dtr-dash-e {
        flex-direction: row;
        align-items: flex-start;
    }

    .dtr-dash-bulb {
        display: grid;
        flex: none;
        place-items: center;
        width: calc(var(--spacing) * 11);
        height: calc(var(--spacing) * 11);
        border-radius: 9999px;
        background-color: color-mix(in oklab, var(--warning-400) 20%, transparent);
        color: var(--warning-700);
    }

    .dark .dtr-dash-bulb {
        color: var(--warning-300);
    }

    .dtr-dash-bulb > svg {
        width: calc(var(--spacing) * 6);
        height: calc(var(--spacing) * 6);
    }

    /* F: one bar cut into the types, then the types with their shares. */
    .dtr-dash-segments {
        display: flex;
        gap: 0.1875rem;
        height: 0.75rem;
    }

    .dtr-dash-segments > span {
        border-radius: 0.25rem;
        background-color: var(--key);
    }

    .dtr-dash-breakdown {
        display: grid;
        gap: calc(var(--spacing) * 1.5);
        font-size: var(--text-sm);
    }

    .dtr-dash-breakdown li {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 2);
    }

    .dtr-dash-breakdown li > span:last-child {
        margin-inline-start: auto;
        color: var(--dash-muted);
        font-variant-numeric: tabular-nums;
    }

    /* G: a half-circle gauge, the share written under it. */
    .dtr-dash-gauge {
        position: relative;
        width: 100%;
        max-width: 15rem;
        margin-inline: auto;
    }

    .dtr-dash-gauge svg {
        display: block;
        width: 100%;
    }

    .dtr-dash-gauge path {
        fill: none;
        stroke-width: 16;
        stroke-linecap: round;
    }

    .dtr-dash-gauge .is-track {
        stroke: var(--dash-track);
    }

    .dtr-dash-gauge .is-value {
        stroke: var(--success-500);
    }

    .dtr-dash-gauge p {
        position: absolute;
        inset-inline: 0;
        bottom: 0;
        text-align: center;
    }

    /* I: the newest leave applications. */
    .dtr-dash-rows {
        display: grid;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash-row {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 3);
        min-width: 0;
    }

    .dtr-dash-row-text {
        display: grid;
        flex: 1;
        min-width: 0;
        font-size: var(--text-sm);
    }

    .dtr-dash-row-text > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: var(--font-weight-medium);
    }

    .dtr-dash-row-end {
        display: grid;
        justify-items: end;
        gap: calc(var(--spacing) * 1);
        font-size: var(--text-sm);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /*
     * An employee's own dashboard: their balances wide beside the quick actions,
     * then their latest DTR beside the leave waiting for approval.
     */
    .dtr-dash.is-mine {
        grid-template-areas: "a" "b" "c" "d";
    }

    @media (min-width: 48rem) {
        .dtr-dash.is-mine {
            grid-template-areas: "a a" "c c" "b d";
        }
    }

    @media (min-width: 80rem) {
        .dtr-dash.is-mine {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            grid-template-areas: "a a b" "c c d";
        }
    }

    /* The view's one main action: amber, with dark text on it. */
    .dtr-dash-action.is-main .dtr-dash-action-icon {
        background-color: var(--primary-400);
        box-shadow: none;
        color: var(--gray-950);
    }

    .dtr-dash-action.is-main:hover .dtr-dash-action-icon,
    .dtr-dash-action.is-main:focus-visible .dtr-dash-action-icon {
        background-color: var(--primary-500);
    }
</style>
