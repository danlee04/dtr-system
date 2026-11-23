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
        grid-template-areas: "top" "k1" "k2" "k3" "k4" "a" "c" "d" "e" "g" "h" "o" "f" "i";
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
            grid-template-areas: "top top" "k1 k2" "k3 k4" "a a" "c c" "d e" "g h" "o f" "i i";
        }
    }

    /*
     * HR's reference (2026-11-20): the heading and month, four figures, the chart wide beside
     * the quick actions, the month's progress along the next row; today's leave at the foot.
     */
    @media (min-width: 80rem) {
        .dtr-dash {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            grid-template-areas: "top top top top" "k1 k2 k3 k4" "a a a c" "d e g h" "o f i i";
        }
    }

    .dtr-dash-top { grid-area: top; }
    .dtr-dash-k1 { grid-area: k1; }
    .dtr-dash-k2 { grid-area: k2; }
    .dtr-dash-k3 { grid-area: k3; }
    .dtr-dash-k4 { grid-area: k4; }
    .dtr-dash-o { grid-area: o; }
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

    /* The heading, the month picker at the right. */
    .dtr-dash-top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: calc(var(--spacing) * 4);
    }

    .dtr-dash-heading {
        font-size: 1.5rem;
        line-height: 1.2;
        font-weight: var(--font-weight-semibold);
        color: var(--dash-text);
    }

    .dtr-dash-month {
        inline-size: 12rem;
    }

    /* K: a figure beside a round, tinted icon. */
    .dtr-dash-kpi {
        flex-direction: row;
        align-items: center;
        gap: calc(var(--spacing) * 4);
    }

    .dtr-dash-kpi-icon {
        display: grid;
        flex: none;
        place-items: center;
        width: calc(var(--spacing) * 12);
        height: calc(var(--spacing) * 12);
        border-radius: 9999px;
        background-color: color-mix(in oklab, var(--tone) 14%, var(--dash-card));
        color: var(--tone);
    }

    .dtr-dash-kpi-icon > svg {
        width: calc(var(--spacing) * 6);
        height: calc(var(--spacing) * 6);
    }

    .dtr-dash-kpi-icon.is-info { --tone: var(--info-500); }
    .dtr-dash-kpi-icon.is-success { --tone: var(--success-500); }
    .dtr-dash-kpi-icon.is-warning { --tone: var(--warning-500); }
    .dtr-dash-kpi-icon.is-primary { --tone: var(--primary-500); }

    .dtr-dash-kpi-text {
        display: grid;
        gap: calc(var(--spacing) * 1);
        min-width: 0;
    }

    /* A card title with a small icon before it. */
    .dtr-dash-titled {
        display: flex;
        align-items: flex-start;
        gap: calc(var(--spacing) * 2.5);
    }

    .dtr-dash-title-icon {
        display: grid;
        flex: none;
        place-items: center;
        width: calc(var(--spacing) * 6);
        height: calc(var(--spacing) * 6);
        color: var(--dash-muted);
    }

    .dtr-dash-title-icon > svg {
        width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
    }

    /* A: the scale at the left of the bars, level with their tracks. */
    .dtr-dash-plot {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: calc(var(--spacing) * 3);
        margin-top: calc(var(--spacing) * 6);
    }

    .dtr-dash-plot .dtr-dash-chart {
        margin-top: 0;
    }

    .dtr-dash-axis {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 12rem;
        padding-bottom: 1.5rem;
        font-size: var(--text-xs);
        text-align: end;
        color: var(--dash-muted);
        font-variant-numeric: tabular-nums;
    }

    /*
     * The month's row and the foot row hold cards of the same height: what ends a card
     * (a link, the "more" line) sits at its foot, the gauge in the middle, and an empty
     * card shows its icon and words in the middle rather than a blank.
     */
    .dtr-dash-foot,
    .dtr-dash-e > .dtr-dash-link {
        margin-top: auto;
    }

    .dtr-dash-g .dtr-dash-gauge {
        margin-block: auto;
    }

    /* The quick actions fill their card, as tall as the chart beside it. */
    .dtr-dash-c .dtr-dash-actions.is-tiles {
        flex: 1;
        grid-auto-rows: minmax(5.5rem, 1fr);
    }

    .dtr-dash-meters {
        display: grid;
        gap: calc(var(--spacing) * 5);
        margin-block: auto;
    }

    .dtr-dash-count {
        font-weight: var(--font-weight-semibold);
        font-variant-numeric: tabular-nums;
    }

    .dtr-dash-empty {
        display: flex;
        flex: 1;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: calc(var(--spacing) * 2);
        padding-block: calc(var(--spacing) * 4);
        font-size: var(--text-sm);
        text-align: center;
        color: var(--dash-muted);
    }

    .dtr-dash-empty > svg {
        width: calc(var(--spacing) * 8);
        height: calc(var(--spacing) * 8);
        opacity: 0.6;
    }

    /* H: an office's name over its figures. */
    .dtr-dash-office {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
    }

    /* E: the note in a tinted box, the bulb beside it. */
    .dtr-dash-note {
        display: flex;
        align-items: flex-start;
        gap: calc(var(--spacing) * 3);
        padding: calc(var(--spacing) * 3);
        border-radius: var(--radius-lg);
        background-color: color-mix(in oklab, var(--warning-400) 10%, var(--dash-card));
        font-size: var(--text-sm);
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

    /* The staff dashboard's quick actions: framed tiles, three across, the icon over the label. */
    .dtr-dash-actions.is-tiles {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .dtr-dash-actions.is-tiles .dtr-dash-action {
        justify-content: center;
        min-height: 5.5rem;
        padding: calc(var(--spacing) * 3) calc(var(--spacing) * 2);
        border-radius: var(--radius-lg);
        box-shadow: 0 0 0 1px var(--dash-line);
    }

    .dtr-dash-actions.is-tiles .dtr-dash-action-icon {
        width: auto;
        height: auto;
        box-shadow: none;
        color: var(--primary-600);
    }

    .dark .dtr-dash-actions.is-tiles .dtr-dash-action-icon {
        color: var(--primary-400);
    }

    .dtr-dash-actions.is-tiles .dtr-dash-action:hover,
    .dtr-dash-actions.is-tiles .dtr-dash-action:focus-visible {
        background-color: var(--dash-track);
    }

    .dtr-dash-actions.is-tiles .dtr-dash-action:hover .dtr-dash-action-icon,
    .dtr-dash-actions.is-tiles .dtr-dash-action:focus-visible .dtr-dash-action-icon {
        background-color: transparent;
    }

    .dtr-dash-actions.is-tiles .dtr-dash-action:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .dtr-dash-actions.is-tiles .dtr-dash-action:focus-visible .dtr-dash-action-icon {
        outline: none;
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

    /* E: the next step's bulb. */
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

    /*
     * The staff dashboard after HR's second reference (2026-11-23): a soft blue page with
     * pale waves at its top, white cards with rounder corners and a soft blue shadow,
     * figures in solid tinted circles with a faint wave each, icons on the card titles,
     * links with an arrow, and the next step as a wide blue button. An employee's own
     * dashboard (.is-mine) keeps the plainer look.
     */
    body:has(.dtr-dash.is-staff) .fi-main-ctn {
        background:
            radial-gradient(130% 70% at 100% -12%, transparent 46%, color-mix(in oklab, var(--primary-100) 75%, transparent) 47%, transparent 58%),
            radial-gradient(110% 60% at 85% -18%, transparent 50%, color-mix(in oklab, var(--primary-200) 45%, transparent) 51%, transparent 60%),
            radial-gradient(60% 40% at 90% 0%, color-mix(in oklab, var(--primary-200) 55%, transparent), transparent 70%),
            linear-gradient(180deg, color-mix(in oklab, var(--primary-50) 80%, var(--color-white)) 0%, color-mix(in oklab, var(--primary-50) 35%, var(--gray-50)) 45%, var(--gray-50) 100%);
    }

    /* The topbar lets the page's blue through, frosted, so cards scrolling under it stay soft. */
    @media (min-width: 64rem) {
        body:has(.dtr-dash.is-staff) .fi-topbar {
            background-color: color-mix(in oklab, var(--primary-50) 55%, transparent);
            backdrop-filter: blur(10px);
        }
    }

    .dtr-dash.is-staff .dtr-dash-card {
        border-radius: 1rem;
        box-shadow:
            0 0 0 1px color-mix(in oklab, var(--gray-950) 4%, transparent),
            0 12px 32px -20px color-mix(in oklab, var(--primary-800) 45%, transparent);
    }

    .dtr-dash.is-staff .dtr-dash-heading {
        font-size: 1.75rem;
        font-weight: var(--font-weight-bold);
        letter-spacing: -0.01em;
    }

    .dtr-dash.is-staff .dtr-dash-month {
        inline-size: 13rem;
    }

    .dtr-dash.is-staff .dtr-dash-month .fi-input-wrp {
        border-radius: 0.75rem;
        box-shadow:
            0 0 0 1px color-mix(in oklab, var(--gray-950) 6%, transparent),
            0 6px 16px -10px color-mix(in oklab, var(--primary-800) 40%, transparent);
    }

    /* K: the figure over a faint wave in the card's colour; the icon solid in a tinted circle. */
    .dtr-dash.is-staff .dtr-dash-kpi {
        position: relative;
        overflow: hidden;
    }

    .dtr-dash.is-staff .dtr-dash-kpi.is-primary { --tone: var(--primary-500); }
    .dtr-dash.is-staff .dtr-dash-kpi.is-success { --tone: var(--success-500); }
    .dtr-dash.is-staff .dtr-dash-kpi.is-warning { --tone: var(--warning-500); }
    .dtr-dash.is-staff .dtr-dash-kpi.is-violet { --tone: var(--violet-500); }

    .dtr-dash.is-staff .dtr-dash-kpi-icon {
        position: relative;
        z-index: 1;
        width: calc(var(--spacing) * 14);
        height: calc(var(--spacing) * 14);
        background-color: color-mix(in oklab, var(--tone) 16%, var(--color-white));
        color: var(--tone);
    }

    .dtr-dash.is-staff .dtr-dash-kpi-icon > svg {
        width: calc(var(--spacing) * 7);
        height: calc(var(--spacing) * 7);
    }

    .dtr-dash.is-staff .dtr-dash-kpi-text {
        position: relative;
        z-index: 1;
    }

    .dtr-dash.is-staff .dtr-dash-num {
        font-weight: var(--font-weight-bold);
    }

    .dtr-dash-wave {
        position: absolute;
        inset-inline-end: 0;
        bottom: 0;
        width: 55%;
        height: 70%;
        fill: color-mix(in oklab, var(--tone) 24%, transparent);
        -webkit-mask-image: linear-gradient(to bottom, black 25%, transparent);
        mask-image: linear-gradient(to bottom, black 25%, transparent);
        pointer-events: none;
    }

    /* D: View all stays at the title's right, as in the reference. */
    .dtr-dash.is-staff .dtr-dash-d .dtr-dash-head {
        flex-wrap: nowrap;
    }

    .dtr-dash.is-staff .dtr-dash-head > .dtr-dash-link {
        flex: none;
        white-space: nowrap;
    }

    /* Links end in an arrow. */
    .dtr-dash.is-staff .dtr-dash-link {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 1);
        font-weight: var(--font-weight-semibold);
    }

    .dtr-dash-link-arrow {
        width: 0.875rem;
        height: 0.875rem;
    }

    /* Card titles: a navy icon before each, the quick actions' bolt in blue. */
    .dtr-dash.is-staff .dtr-dash-title-icon {
        color: var(--primary-800);
    }

    .dtr-dash.is-staff .dtr-dash-title-icon.is-accent {
        color: var(--primary-600);
    }

    .dtr-dash.is-staff .dtr-dash-key {
        border-radius: 9999px;
    }

    .dtr-dash.is-staff .dtr-dash-track {
        border-radius: 0.375rem;
        background-color: var(--gray-100);
    }

    /* C: four tiles, two by two, the icon blue and large over a bold label. */
    .dtr-dash.is-staff .dtr-dash-actions.is-tiles {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: calc(var(--spacing) * 3);
    }

    .dtr-dash.is-staff .dtr-dash-actions.is-tiles .dtr-dash-action {
        gap: calc(var(--spacing) * 2.5);
        border-radius: 0.875rem;
        background-color: var(--color-white);
        box-shadow:
            0 0 0 1px color-mix(in oklab, var(--gray-950) 6%, transparent),
            0 8px 20px -16px color-mix(in oklab, var(--primary-800) 45%, transparent);
        font-size: var(--text-sm);
        font-weight: var(--font-weight-semibold);
        color: var(--gray-800);
    }

    .dtr-dash.is-staff .dtr-dash-actions.is-tiles .dtr-dash-action:hover,
    .dtr-dash.is-staff .dtr-dash-actions.is-tiles .dtr-dash-action:focus-visible {
        background-color: var(--primary-50);
    }

    .dtr-dash.is-staff .dtr-dash-actions.is-tiles .dtr-dash-action:focus-visible {
        outline-color: var(--primary-600);
    }

    .dtr-dash.is-staff .dtr-dash-actions.is-tiles .dtr-dash-action-icon > svg {
        width: calc(var(--spacing) * 7);
        height: calc(var(--spacing) * 7);
    }

    /* D and H: the bars a little thicker. */
    .dtr-dash.is-staff .dtr-dash-progress {
        height: 0.625rem;
    }

    .dtr-dash.is-staff .dtr-dash-progress.is-thin {
        height: 0.375rem;
    }

    /* E: the note in an amber box with a warning icon, its count in amber, then the wide button. */
    .dtr-dash.is-staff .dtr-dash-note {
        align-items: flex-start;
        background-color: color-mix(in oklab, var(--warning-400) 14%, var(--color-white));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--warning-400) 35%, transparent);
        color: var(--gray-800);
    }

    .dtr-dash.is-staff .dtr-dash-note strong {
        font-weight: var(--font-weight-semibold);
        color: var(--warning-700);
    }

    .dtr-dash.is-staff .dtr-dash-note.is-done {
        background-color: color-mix(in oklab, var(--success-400) 12%, var(--color-white));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--success-400) 35%, transparent);
    }

    .dtr-dash-warn {
        display: grid;
        flex: none;
        place-items: center;
        color: var(--warning-600);
    }

    .dtr-dash-note.is-done .dtr-dash-warn {
        color: var(--success-600);
    }

    .dtr-dash-warn > svg {
        width: calc(var(--spacing) * 6);
        height: calc(var(--spacing) * 6);
    }

    .dtr-dash.is-staff .dtr-dash-step {
        width: 100%;
        min-height: calc(var(--spacing) * 12);
        margin-top: auto;
        border-radius: 0.75rem;
    }

    /* G: a thicker gauge. */
    .dtr-dash.is-staff .dtr-dash-gauge path {
        stroke-width: 18;
    }
</style>
