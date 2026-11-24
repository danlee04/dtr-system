{{--
    The read views. Every one of them (the employee's Details tab, the leave application
    modal and the rest) shows its labels in grey and its values in near-black, so the eye
    lands on the values, as on the balance cards. Filament draws both near-black.
    Grey 500 on white passes 4.5:1, so a placeholder such as
    "Not set" stays readable. Filament's own grey 400 placeholder does not.
    Also here: an employee's page heading and chips, and the employee's Leave types cards.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    .fi-in-entry-label,
    .fi-in-placeholder {
        color: var(--gray-500);
    }

    /*
     * An employee's page, after HR's reference (2026-11-23): one white panel under the
     * breadcrumbs with a soft blue wash at its top right; a large round photo (or the
     * initials) with a pencil for Edit, the name, chips, and the tabs beside it; then
     * the cards and the sections. Below 768px the photo shrinks back beside the name.
     */
    .fi-resource-employees.fi-resource-view-record-page {
        --dtr-emp-pad: calc(var(--spacing) * 4);
        --dtr-emp-photo: calc(var(--spacing) * 14);
        --dtr-emp-indent: 0px;
    }

    @media (min-width: 48rem) {
        .fi-resource-employees.fi-resource-view-record-page {
            --dtr-emp-pad: calc(var(--spacing) * 6);
            --dtr-emp-photo: 8rem;
            --dtr-emp-indent: calc(8rem + var(--spacing) * 7);
        }
    }

    /* 16px above the breadcrumbs, as on every page; the breadcrumbs at the page's edge, not the panel's. */
    .fi-resource-employees.fi-resource-view-record-page > .fi-page-header-main-ctn {
        position: relative;
        isolation: isolate;
        padding: calc(var(--spacing) * 4) var(--dtr-emp-pad) var(--dtr-emp-pad);
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-breadcrumbs {
        margin-inline-start: calc(var(--dtr-emp-pad) * -1);
    }

    /* The panel starts under the breadcrumbs: their line, then 16px. */
    .fi-resource-employees.fi-resource-view-record-page > .fi-page-header-main-ctn::before {
        content: "";
        position: absolute;
        z-index: -1;
        inset: calc(var(--spacing) * 4 + 1.25rem + var(--spacing) * 4) 0 0;
        border-radius: 1rem;
        background:
            radial-gradient(45% 60% at 100% 0%, color-mix(in oklab, var(--primary-200) 75%, transparent), transparent 75%),
            radial-gradient(35% 45% at 72% 0%, color-mix(in oklab, var(--primary-100) 80%, transparent), transparent 75%),
            var(--color-white);
        box-shadow: var(--shadow-sm), 0 0 0 1px color-mix(in oklab, var(--gray-950) 5%, transparent);
    }

    /* A phone shows no breadcrumbs: the panel starts at the top, 16px above the name. */
    @media (max-width: 39.99rem) {
        .fi-resource-employees.fi-resource-view-record-page > .fi-page-header-main-ctn {
            padding-top: var(--dtr-emp-pad);
        }

        .fi-resource-employees.fi-resource-view-record-page > .fi-page-header-main-ctn::before {
            inset: 0;
        }
    }

    /* The breadcrumbs above the panel, then the panel's 24px before the name. */
    .fi-resource-employees.fi-resource-view-record-page .fi-breadcrumbs {
        margin-bottom: calc(var(--spacing) * 4 + var(--dtr-emp-pad));
    }

    .dtr-person {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-person-photo {
        position: relative;
        flex: none;
    }

    .dtr-person-avatar {
        width: var(--dtr-emp-photo, calc(var(--spacing) * 14));
        height: var(--dtr-emp-photo, calc(var(--spacing) * 14));
        object-fit: cover;
        box-shadow: 0 0 0 4px var(--color-white), 0 8px 24px color-mix(in oklab, var(--primary-600) 22%, transparent);
    }

    /* The pencil on the photo: a blue round button, 32px, with a white ring. */
    .dtr-person-edit {
        position: absolute;
        inset-inline-end: 0;
        bottom: 0;
        display: grid;
        place-items: center;
        width: calc(var(--spacing) * 8);
        height: calc(var(--spacing) * 8);
        border-radius: 9999px;
        background-color: var(--primary-600);
        box-shadow: 0 0 0 3px var(--color-white);
        color: var(--color-white);
    }

    .dtr-person-edit:hover {
        background-color: var(--primary-700);
    }

    .dtr-person-edit:focus-visible {
        outline: 2px solid var(--primary-600);
        outline-offset: 3px;
    }

    .dtr-person-edit-icon {
        width: calc(var(--spacing) * 4);
        height: calc(var(--spacing) * 4);
    }

    .fi-resource-employees.fi-resource-view-record-page .dtr-person-name {
        font-size: var(--text-2xl);
        line-height: 1.2;
        font-weight: var(--font-weight-bold);
        color: var(--gray-950);
    }

    /*
     * From 768px the photo stands at the panel's top left, as tall as the name, the chips
     * and the tabs together, and they line up to its right.
     */
    @media (min-width: 48rem) {
        .fi-resource-employees.fi-resource-view-record-page .dtr-person-name {
            font-size: 2rem;
        }

        .fi-resource-employees.fi-resource-view-record-page .dtr-person-photo {
            position: absolute;
            top: calc(var(--spacing) * 4 + 1.25rem + var(--spacing) * 4 + var(--dtr-emp-pad));
            inset-inline-start: var(--dtr-emp-pad);
        }

        .fi-resource-employees.fi-resource-view-record-page .fi-header-heading,
        .fi-resource-employees.fi-resource-view-record-page .fi-header-subheading,
        .fi-resource-employees.fi-resource-view-record-page .fi-page-content .fi-sc-tabs > .fi-tabs {
            margin-inline-start: var(--dtr-emp-indent);
        }

        .fi-resource-employees.fi-resource-view-record-page .fi-header-subheading {
            margin-top: calc(var(--spacing) * 2);
        }

        /* Edit and Delete stay at the panel's top right, level with the name, never under the photo. */
        .fi-resource-employees.fi-resource-view-record-page .fi-header {
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
            gap: calc(var(--spacing) * 4);
        }

        .fi-resource-employees.fi-resource-view-record-page .fi-header > div:first-child {
            flex: 1;
            min-width: 0;
        }

        .fi-resource-employees.fi-resource-view-record-page .fi-header-actions-ctn {
            flex: none;
            align-self: flex-start;
            margin-top: calc(1.25rem + var(--spacing) * 4 + var(--dtr-emp-pad));
        }
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

    /* A chip under the name: a soft fill, an icon or a dot, the words on one line. */
    .dtr-chip {
        --chip: var(--gray-500);
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 1.5);
        min-height: calc(var(--spacing) * 8);
        padding-inline: calc(var(--spacing) * 3);
        border-radius: 9999px;
        background-color: color-mix(in oklab, var(--chip) 12%, var(--color-white));
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        color: color-mix(in oklab, var(--chip) 55%, var(--gray-950));
        white-space: nowrap;
    }

    .dtr-chip.is-office { --chip: var(--primary-600); }
    .dtr-chip.is-success { --chip: var(--success-600); }
    .dtr-chip.is-danger { --chip: var(--danger-600); }
    .dtr-chip.is-gray { --chip: var(--gray-500); }

    .dtr-chip-icon {
        width: calc(var(--spacing) * 4);
        height: calc(var(--spacing) * 4);
    }

    .dtr-chip-dot {
        width: calc(var(--spacing) * 2);
        height: calc(var(--spacing) * 2);
        border-radius: 9999px;
        background-color: var(--chip);
    }

    /* The tabs as a white pill track, the open one navy with white words. */
    .fi-resource-employees.fi-resource-view-record-page .fi-tabs:not(.fi-contained) {
        width: fit-content;
        padding: calc(var(--spacing) * 1);
        border-radius: 9999px;
        background-color: var(--color-white);
        box-shadow: var(--shadow-sm), 0 0 0 1px color-mix(in oklab, var(--gray-950) 6%, transparent);
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item {
        padding-inline: calc(var(--spacing) * 4);
        border-radius: 9999px;
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item:focus-visible {
        outline: 2px solid var(--primary-600);
        outline-offset: 2px;
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item.fi-active {
        background-color: var(--primary-800);
    }

    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item.fi-active .fi-tabs-item-label,
    .fi-resource-employees.fi-resource-view-record-page .fi-tabs-item.fi-active > .fi-icon {
        color: var(--color-white);
    }

    /* Job Details' rows tighter, as in HR's reference: 10px above and below each fact. */
    .fi-resource-employees.fi-resource-view-record-page .fi-section.fi-divided > .fi-section-content-ctn > .fi-section-content > .fi-grid-col {
        padding-block: calc(var(--spacing) * 2.5);
    }

    /*
     * The Overview's five cards: a soft gradient in each one's colour, a small icon, the
     * label, the figure, and a large faint icon in the top right corner. Five across from
     * 1280px; below that, as many as fit at 10rem.
     */
    .dtr-emp-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 10rem), 1fr));
        gap: calc(var(--spacing) * 4);
    }

    @media (min-width: 80rem) {
        .dtr-emp-cards {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
    }

    .dtr-emp-card {
        --tone: var(--primary-500);
        position: relative;
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 0.5);
        overflow: hidden;
        padding: calc(var(--spacing) * 5);
        border-radius: var(--radius-xl);
        background: linear-gradient(135deg, color-mix(in oklab, var(--tone) 18%, var(--color-white)), color-mix(in oklab, var(--tone) 6%, var(--color-white)));
        box-shadow: var(--shadow-sm), 0 0 0 1px color-mix(in oklab, var(--tone) 18%, transparent);
    }

    .dtr-emp-card.is-tardiness { --tone: var(--primary-500); }
    .dtr-emp-card.is-vacation { --tone: var(--success-500); }
    .dtr-emp-card.is-sick { --tone: var(--violet-500); }
    .dtr-emp-card.is-special { --tone: var(--warning-400); }
    .dtr-emp-card.is-wellness { --tone: var(--danger-400); }

    .dtr-emp-card-icon {
        width: calc(var(--spacing) * 6);
        height: calc(var(--spacing) * 6);
        margin-bottom: calc(var(--spacing) * 3);
        color: color-mix(in oklab, var(--tone) 70%, var(--gray-950));
    }

    .dtr-emp-card-art {
        position: absolute;
        top: calc(var(--spacing) * 4);
        inset-inline-end: calc(var(--spacing) * 4);
        width: 3.5rem;
        height: 3.5rem;
        color: var(--tone);
        opacity: 0.3;
    }

    .dtr-emp-card-label {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        color: var(--gray-700);
    }

    .dtr-emp-card-value {
        font-size: 1.375rem;
        line-height: 1.15;
        font-weight: var(--font-weight-bold);
        font-variant-numeric: tabular-nums;
        color: var(--gray-950);
    }

    @media (min-width: 80rem) {
        .dtr-emp-card-value {
            font-size: 1.75rem;
        }
    }

    .dtr-emp-card-detail {
        font-size: var(--text-xs);
        color: var(--gray-600);
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

    .dtr-profile-card.is-main {
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

    /* The label in a dark shade of the card's colour: 5:1 or better on its tint. Short, so the values line up. */
    .dtr-profile-card-label {
        font-size: var(--text-xs);
        font-weight: var(--font-weight-medium);
        color: color-mix(in oklab, var(--tone) 50%, var(--gray-950));
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

    .dtr-profile-card.is-main .dtr-profile-card-label {
        color: var(--gray-950);
    }

    .dtr-profile-card.is-main .dtr-profile-card-detail {
        color: var(--gray-800);
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

    .dtr-profile-identity-hint {
        font-size: var(--text-xs);
        color: var(--gray-500);
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

    .dtr-record-warning,
    .dtr-record-notes .is-warning {
        color: var(--warning-700);
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

    /* Room between a day's status badge and its "+ OB/Leave" or "Edit" button; on a portrait tablet they may wrap. */
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

    /* "+ Add", "+ OB/Leave" and "Edit" read a size under the grid's text, as tall as a badge (HR, 2026-11-23). */
    .dtr-record-days .dtr-grid-add,
    .dtr-record-days .dtr-grid-ob,
    .dtr-record-legend .dtr-grid-add,
    .dtr-record-legend .dtr-grid-ob {
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
    }

    .dtr-record-days .dtr-grid-add > .fi-icon,
    .dtr-record-days .dtr-grid-ob > .fi-icon,
    .dtr-record-legend .dtr-grid-ob > .fi-icon {
        width: 0.875rem;
        height: 0.875rem;
    }

    /* "Edit" on a day an entry covers: the box of "+ OB/Leave" with less room around the word. */
    .dtr-grid-edit,
    .dtr-record-days .dtr-grid-edit {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 0.5);
        padding: 0.125rem 0.375rem;
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

    /*
     * One line a day from a laptop's width (HR, 2026-11-23): a time keeps its OB, TO or HR tag
     * beside it, a date and the hours do not break, and a long note ends in "…", its whole
     * text in the cell's title.
     */
    @media (min-width: 64rem) {
        .dtr-record-days .dtr-grid-hr,
        .dtr-record-days td.dtr-grid-value,
        .dtr-record-days tbody th.dtr-record-date {
            white-space: nowrap;
        }

        /* The badge and its button as words on one line, not flex items: a squeezed flex row let "Edit" spill over the note. */
        .dtr-record-days .dtr-grid-badges {
            display: block;
        }

        .dtr-record-days .dtr-grid-badges > * {
            display: inline-flex;
            vertical-align: middle;
        }

        .dtr-record-days .dtr-grid-badges > * + * {
            margin-inline-start: calc(var(--spacing) * 1);
        }

        /* Filament truncates a badge, so a squeezed column would cut it to "Official b…"; here it is shown whole. */
        .dtr-record-days .fi-badge,
        .dtr-record-days .fi-badge .fi-badge-label {
            overflow: visible;
            text-overflow: clip;
        }

        .dtr-record-days td.dtr-record-notes {
            width: 100%;
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
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

    /* An employee's deductions this year (HR, 2026-11-23): a line each, what on the left, the leave and days on the right in red, a total under them. */
    .dtr-deductions {
        display: flex;
        flex-direction: column;
    }

    .dtr-deduction,
    .dtr-deductions-total {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: calc(var(--spacing) * 3);
        padding-block: calc(var(--spacing) * 2.5);
        font-size: var(--text-sm);
    }

    .dtr-deduction + .dtr-deduction {
        border-top: 1px solid color-mix(in oklab, var(--gray-950) 5%, transparent);
    }

    .dtr-deduction-what {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .dtr-deduction-title {
        color: var(--gray-950);
    }

    .dtr-deduction-detail {
        font-size: var(--text-xs);
        color: var(--gray-500);
    }

    .dtr-deduction-days {
        flex: none;
        font-size: var(--text-base);
        font-weight: var(--font-weight-bold);
        font-variant-numeric: tabular-nums;
        color: var(--danger-600);
    }

    /* What the list is, in a soft blue box with an info icon (HR's reference, 2026-11-23). */
    .dtr-deductions-note {
        display: flex;
        align-items: flex-start;
        gap: calc(var(--spacing) * 2);
        margin-bottom: calc(var(--spacing) * 2);
        padding: calc(var(--spacing) * 2.5) calc(var(--spacing) * 3);
        border-radius: var(--radius-lg);
        background-color: var(--primary-50);
        font-size: var(--text-xs);
        color: var(--gray-700);
    }

    .dtr-deductions-note-icon {
        flex: none;
        width: calc(var(--spacing) * 4);
        height: calc(var(--spacing) * 4);
        color: var(--primary-600);
    }

    .dtr-deductions-total {
        margin-top: calc(var(--spacing) * 1);
        border-top: 1px solid color-mix(in oklab, var(--gray-950) 10%, transparent);
        font-weight: var(--font-weight-semibold);
        color: var(--gray-950);
    }

    .dtr-deductions-empty {
        font-size: var(--text-sm);
        color: var(--gray-500);
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

    .dtr-callout-heading {
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-semibold);
        color: var(--gray-950);
    }

    .dtr-callout-text {
        margin-bottom: calc(var(--spacing) * 3);
        font-size: var(--text-sm);
        color: var(--gray-700);
        text-wrap: pretty;
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
