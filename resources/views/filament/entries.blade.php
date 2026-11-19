{{--
    The read views. Every one of them (the employee's Details tab, the leave application
    modal and the rest) shows its labels in grey and its values in near-black, so the eye
    lands on the values, as on the balance cards. Filament draws both near-black.
    Grey 500 on white and grey 400 on grey 900 pass 4.5:1, so a placeholder such as
    "Not set" stays readable. Filament's own grey 400 placeholder does not.
    Also here: an employee's page heading and chips, and the employee's Leave types cards.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    .fi-in-entry-label,
    .fi-in-placeholder {
        color: var(--gray-500);
    }

    .dark .fi-in-entry-label,
    .dark .fi-in-placeholder {
        color: var(--gray-400);
    }

    /*
     * An employee's page, after HR's reference: a large photo, or their initials,
     * beside their name, then their position, office and chips.
     */
    .dtr-person {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-person-avatar {
        width: calc(var(--spacing) * 14);
        height: calc(var(--spacing) * 14);
        object-fit: cover;
    }

    .dtr-person-chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: calc(var(--spacing) * 2);
    }

    .dtr-person-role {
        font-size: var(--text-sm);
        color: var(--gray-600);
    }

    .dark .dtr-person-role {
        color: var(--gray-400);
    }

    /* Room under the breadcrumbs before the photo and name: twice Filament's 8px. */
    .fi-resource-employees.fi-resource-view-record-page .fi-breadcrumbs {
        margin-bottom: calc(var(--spacing) * 4);
    }

    @media (min-width: 40rem) {
        .fi-resource-employees .dtr-person-name {
            font-size: 1.75rem;
            line-height: 1.3;
        }

        /* The photo sits centred on the name (HR, 2026-11-10); the chips line up under the name. */
        .fi-resource-employees .fi-header-subheading {
            margin-top: calc(var(--spacing) * 1);
            margin-inline-start: calc(var(--spacing) * 17);
        }
    }

    /* Its tabs as pills on a grey track, from the start, the open one amber. Amber takes dark text. */
    .fi-resource-employees.fi-resource-view-record-page .fi-tabs:not(.fi-contained) {
        width: fit-content;
        margin-inline: 0;
        padding: calc(var(--spacing) * 1);
        border-radius: 9999px;
        background-color: var(--gray-100);
        box-shadow: none;
    }

    .dark .fi-resource-employees.fi-resource-view-record-page .fi-tabs:not(.fi-contained) {
        background-color: color-mix(in oklab, var(--color-white) 5%, transparent);
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item {
        padding-inline: calc(var(--spacing) * 4);
        border-radius: 9999px;
    }

    /* Filament marks a focused tab by its fill alone, which the amber one hides. */
    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item.fi-active {
        background-color: var(--primary-400);
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item.fi-active .fi-tabs-item-label,
    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item.fi-active > .fi-icon {
        color: var(--gray-950);
    }

    /*
     * The Overview's cards: last month in solid amber, then each balance tinted in
     * its colour on the dashboard's leave chart, then the schedule in grey.
     * As many across as fit at 9rem; six on a wide screen.
     */
    .dtr-profile-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 9rem), 1fr));
        gap: calc(var(--spacing) * 3);
    }

    @media (min-width: 80rem) {
        .dtr-profile-cards {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    .dtr-profile-card {
        --tone: var(--gray-400);
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 0.5);
        padding: calc(var(--spacing) * 4);
        border-radius: var(--radius-xl);
        background-color: color-mix(in oklab, var(--tone) 14%, var(--color-white));
        color: var(--gray-950);
    }

    .dark .dtr-profile-card {
        background-color: color-mix(in oklab, var(--tone) 16%, var(--gray-900));
        color: var(--color-white);
    }

    .dtr-profile-card.is-vacation {
        --tone: var(--success-500);
    }

    .dtr-profile-card.is-sick {
        --tone: var(--info-500);
    }

    .dtr-profile-card.is-special {
        --tone: var(--warning-400);
    }

    .dtr-profile-card.is-wellness {
        --tone: var(--danger-400);
    }

    .dtr-profile-card.is-main,
    .dark .dtr-profile-card.is-main {
        --tone: var(--gray-950);
        background-color: var(--primary-400);
        color: var(--gray-950);
    }

    .dtr-profile-card-icon {
        display: grid;
        place-items: center;
        width: calc(var(--spacing) * 8);
        height: calc(var(--spacing) * 8);
        margin-bottom: calc(var(--spacing) * 3);
        border-radius: var(--radius-lg);
        background-color: color-mix(in oklab, var(--color-white) 75%, transparent);
        color: color-mix(in oklab, var(--tone) 50%, var(--gray-950));
    }

    .dark .dtr-profile-card-icon {
        background-color: color-mix(in oklab, var(--gray-950) 40%, transparent);
        color: color-mix(in oklab, var(--tone) 60%, var(--color-white));
    }

    /* The label in a dark shade of the card's colour: 5:1 or better on its tint. Short, so the values line up. */
    .dtr-profile-card-label {
        font-size: var(--text-xs);
        font-weight: var(--font-weight-medium);
        color: color-mix(in oklab, var(--tone) 50%, var(--gray-950));
    }

    .dark .dtr-profile-card-label {
        color: color-mix(in oklab, var(--tone) 60%, var(--color-white));
    }

    /* The dashboard's figure size. */
    /* A size smaller below 1280px, so "12 hrs 27 min" stays on one line in a card on a laptop or a tablet. */
    .dtr-profile-card-value {
        font-size: 1.375rem;
        line-height: 1.15;
        font-weight: var(--font-weight-semibold);
        font-variant-numeric: tabular-nums;
    }

    @media (min-width: 80rem) {
        .dtr-profile-card-value {
            font-size: 1.75rem;
        }
    }

    .dtr-profile-card-detail {
        font-size: var(--text-xs);
        color: var(--gray-600);
    }

    .dark .dtr-profile-card-detail {
        color: var(--gray-300);
    }

    .dtr-profile-card.is-main .dtr-profile-card-label,
    .dark .dtr-profile-card.is-main .dtr-profile-card-label,
    .dark .dtr-profile-card.is-main .dtr-profile-card-icon {
        color: var(--gray-950);
    }

    .dtr-profile-card.is-main .dtr-profile-card-detail,
    .dark .dtr-profile-card.is-main .dtr-profile-card-detail {
        color: var(--gray-800);
    }

    .dark .dtr-profile-card.is-main .dtr-profile-card-icon {
        background-color: color-mix(in oklab, var(--color-white) 75%, transparent);
    }

    /*
     * "(optional)" beside a form field's label, read with its name, instead of
     * at the far end of the row where Filament puts what follows a label.
     */
    .fi-fo-field-label-ctn:has(.dtr-field-optional) {
        column-gap: calc(var(--spacing) * 1);
    }

    .fi-fo-field-label-ctn > .fi-sc:has(.dtr-field-optional) {
        flex-grow: 0;
    }

    /*
     * A chosen value stays on one line, so a long office name leaves the select
     * as tall as the inputs beside it; the open list still shows every name whole.
     */
    .fi-select-input .fi-select-input-value-ctn > span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* The employee form: each section's heading marked in amber, a rule between sections. */
    .dtr-form-section .fi-section-header-heading {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 2.5);
    }

    .dtr-form-section .fi-section-header-heading::before {
        content: '';
        inline-size: calc(var(--spacing) * 1);
        block-size: 1.125rem;
        border-radius: 9999px;
        background-color: var(--primary-500);
    }

    .dtr-form-section-divided {
        border-block-start: 1px solid var(--gray-200);
        padding-block-start: calc(var(--spacing) * 6);
    }

    .dark .dtr-form-section-divided {
        border-block-start-color: color-mix(in oklab, var(--color-white) 10%, transparent);
    }

    /* The photo: a wide rounded box with a camera, the full width of its column, a rule between it and the name fields. */
    .dtr-photo-drop.fi-fo-file-upload-avatar .fi-fo-file-upload-input-ctn {
        inline-size: 100%;
    }

    .dtr-photo-drop .filepond--drop-label {
        min-block-size: 7.5rem;
    }

    .dtr-photo-drop .filepond--panel-root {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-xl);
        background-color: var(--gray-50);
    }

    .dark .dtr-photo-drop .filepond--panel-root {
        border-color: color-mix(in oklab, var(--color-white) 10%, transparent);
        background-color: color-mix(in oklab, var(--color-white) 5%, transparent);
    }

    .dtr-photo-drop .filepond--drop-label svg {
        display: block;
        inline-size: 1.5rem;
        block-size: 1.5rem;
        margin: 0 auto calc(var(--spacing) * 1.5);
        color: var(--gray-500);
    }

    @media (min-width: 48rem) {
        .dtr-form-names {
            border-inline-start: 1px solid var(--gray-200);
            padding-inline-start: calc(var(--spacing) * 6);
        }

        .dark .dtr-form-names {
            border-inline-start-color: color-mix(in oklab, var(--color-white) 10%, transparent);
        }
    }

    /* The switches stacked in Attendance's last column, level with the inputs, not their labels. */
    @media (min-width: 40rem) {
        .dtr-form-switches {
            padding-block-start: calc(var(--spacing) * 7);
        }
    }

    /* Beside the photo on someone's own profile: their name, their post, and how to change the photo. */
    .dtr-profile-identity {
        display: grid;
        gap: calc(var(--spacing) * 1);
    }

    .dtr-profile-identity-name {
        font-size: var(--text-xl);
        line-height: 1.3;
        font-weight: var(--font-weight-semibold);
        color: var(--gray-950);
    }

    .dark .dtr-profile-identity-name {
        color: var(--color-white);
    }

    .dtr-profile-identity-hint {
        font-size: var(--text-xs);
        color: var(--gray-500);
    }

    .dark .dtr-profile-identity-hint {
        color: var(--gray-400);
    }

    /* A figure that needs a look, as a DTR's blanks to fill. */
    .dtr-profile-card.is-warning {
        --tone: var(--warning-400);
    }

    /*
     * A DTR's view: whose it is beside their photo or initials, the month in
     * cards that fill the row, then its days (grid classes in entry-grid).
     */
    .dtr-record-head {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 3);
        margin-bottom: calc(var(--spacing) * 4);
    }

    .dtr-record-line {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
    }

    .dtr-record-sub,
    .dtr-record-desc,
    .dtr-record-notes {
        font-size: var(--text-xs);
        color: var(--gray-500);
    }

    .dark .dtr-record-sub,
    .dark .dtr-record-desc,
    .dark .dtr-record-notes {
        color: var(--gray-400);
    }

    .dtr-record-warning,
    .dtr-record-notes .is-warning {
        color: var(--warning-700);
    }

    .dark .dtr-record-warning,
    .dark .dtr-record-notes .is-warning {
        color: var(--warning-400);
    }

    .dtr-record-warning {
        font-size: var(--text-sm);
    }

    .dtr-record-notes .is-warning {
        font-weight: var(--font-weight-medium);
    }

    /* At least 11rem a card: three to a row on a tablet rather than four squeezed. */
    .dtr-record .dtr-profile-cards,
    .dtr-dash .dtr-profile-cards {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 11rem), 1fr));
    }

    /* A line that says what to do, above what it is about: "Something wrong? Bring your signed DTR to HR." */
    .dtr-record-callout {
        margin-bottom: calc(var(--spacing) * 4);
        padding: calc(var(--spacing) * 2.5) calc(var(--spacing) * 3.5);
        border-radius: var(--radius-lg);
        background-color: color-mix(in oklab, var(--primary-400) 12%, var(--color-white));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 25%, transparent);
        font-size: var(--text-sm);
        color: var(--gray-950);
    }

    .dark .dtr-record-callout {
        background-color: color-mix(in oklab, var(--primary-400) 10%, var(--gray-900));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-400) 25%, transparent);
        color: var(--color-white);
    }

    .dtr-record-heading {
        margin-top: calc(var(--spacing) * 6);
        font-size: var(--text-base);
        font-weight: var(--font-weight-semibold);
    }

    .dtr-record-desc {
        margin-block: calc(var(--spacing) * 1) calc(var(--spacing) * 3);
    }

    .dtr-record-desc strong {
        font-weight: var(--font-weight-medium);
    }

    .dtr-record-days th,
    .dtr-record-days td {
        white-space: nowrap;
    }

    .dtr-record-days tbody th {
        font-weight: var(--font-weight-medium);
    }

    .dtr-record-time {
        font-variant-numeric: tabular-nums;
    }

    /* Room between a day's status badge and its "+ OB/TO" button. */
    .dtr-record-days .dtr-grid-badges {
        gap: calc(var(--spacing) * 2);
    }

    /* A time HR may click reads at the grid's size, not the link's own, so its column is no wider than a printed one. */
    .dtr-record-days .fi-link {
        font-size: inherit;
    }

    /* In a narrow column the "HR" tag drops under its time; the time itself never breaks. */
    .dtr-record-days .dtr-grid-hr {
        white-space: normal;
    }

    .dtr-record-days .dtr-grid-time {
        white-space: nowrap;
    }

    /* Below 1280px the dashed "+ Add" and "+ OB/Leave" boxes hug their text, so the columns stay narrow. */
    .dtr-record-days .dtr-grid-add,
    .dtr-record-days .dtr-grid-ob {
        gap: calc(var(--spacing) * 0.5);
        padding-inline: 0.25rem;
    }

    @media (min-width: 80rem) {
        .dtr-record-days .dtr-grid-add,
        .dtr-record-days .dtr-grid-ob {
            gap: calc(var(--spacing) * 1);
            padding-inline: 0.5rem;
        }
    }

    /* A narrow column wraps the badges onto lines; it never cuts "Official business" short. */
    .dtr-record-days .dtr-grid-badges > * {
        flex: none;
    }

    /* Where the grid still scrolls sideways, as in the DTR modal on a portrait tablet, the dates stay in view. */
    .dtr-record-days tr > :first-child {
        position: sticky;
        inset-inline-start: 0;
        z-index: 1;
        box-shadow: inset -1px 0 0 var(--grid-line);
    }

    /* So the grid fits a laptop or a tablet: headings, notes, and the hours after a day's minutes may take a second line. */
    .dtr-record-days thead th,
    .dtr-record-days td.dtr-grid-value {
        white-space: normal;
    }

    /* Below 1280px the cells tighten, the text is a size smaller, and a date may take two lines, "Thu," over "Oct 1". */
    .dtr-grid-table.dtr-record-days {
        font-size: 0.8125rem;
    }

    .dtr-record-days th,
    .dtr-record-days td {
        padding-inline: 0.375rem;
    }

    .dtr-record-days tbody th.dtr-record-date {
        min-width: 4rem;
        white-space: normal;
    }

    .dtr-record-days td.dtr-record-notes {
        min-width: 5rem;
        white-space: normal;
    }

    @media (min-width: 80rem) {
        .dtr-grid-table.dtr-record-days {
            font-size: 0.875rem;
        }

        .dtr-record-days th,
        .dtr-record-days td {
            padding-inline: 0.75rem;
        }

        .dtr-record-days tbody th.dtr-record-date {
            white-space: nowrap;
        }

        .dtr-record-days td.dtr-record-notes {
            min-width: 10rem;
        }
    }

    .dtr-record-legend {
        margin-top: calc(var(--spacing) * 3);
        margin-bottom: 0;
        font-size: var(--text-xs);
    }

    .dtr-record-legend > span {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 1.5);
    }

    .dtr-record-swatch {
        width: 0.875rem;
        height: 0.875rem;
        border-radius: 0.25rem;
        background-color: var(--grid-weekend);
        box-shadow: inset 0 0 0 1px var(--grid-line);
    }

    /* What needs a look, after the reference's "due a review" card: an amber tint, an icon, a sentence, one button. */
    .dtr-callout {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: calc(var(--spacing) * 1);
        padding: calc(var(--spacing) * 6);
        border-radius: var(--radius-xl);
        background-color: color-mix(in oklab, var(--primary-400) 16%, var(--color-white));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-500) 25%, transparent);
    }

    .dark .dtr-callout {
        background-color: color-mix(in oklab, var(--primary-400) 12%, var(--gray-900));
        box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--primary-400) 25%, transparent);
    }

    .dtr-callout-icon {
        display: grid;
        place-items: center;
        width: calc(var(--spacing) * 10);
        height: calc(var(--spacing) * 10);
        margin-bottom: calc(var(--spacing) * 3);
        border-radius: var(--radius-lg);
        background-color: var(--primary-400);
        color: var(--gray-950);
    }

    .dtr-callout-eyebrow {
        font-size: var(--text-xs);
        font-weight: var(--font-weight-medium);
        color: var(--primary-800);
    }

    .dark .dtr-callout-eyebrow {
        color: var(--primary-300);
    }

    .dtr-callout-heading {
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-semibold);
        color: var(--gray-950);
    }

    .dark .dtr-callout-heading {
        color: var(--color-white);
    }

    .dtr-callout-text {
        margin-bottom: calc(var(--spacing) * 3);
        font-size: var(--text-sm);
        color: var(--gray-700);
        text-wrap: pretty;
    }

    .dark .dtr-callout-text {
        color: var(--gray-300);
    }

    /* The Leave types guide: a card per type, as many across as fit at 20rem. */
    .dtr-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr));
        gap: calc(var(--spacing) * 4);
    }

    .dtr-card-figure {
        margin-top: calc(var(--spacing) * 3);
        font-weight: var(--font-weight-semibold);
    }
</style>
