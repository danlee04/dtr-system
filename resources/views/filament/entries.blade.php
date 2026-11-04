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

    @media (min-width: 40rem) {
        .fi-resource-employees .dtr-person-name {
            font-size: 1.75rem;
            line-height: 1.3;
        }

        /* The avatar runs down beside the chips, as the reference's photo does, and the chips line up under the name. */
        .fi-resource-employees .dtr-person {
            align-items: flex-start;
        }

        .fi-resource-employees .dtr-person-avatar {
            margin-bottom: calc(var(--spacing) * -6);
        }

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
    .dtr-profile-card-value {
        font-size: 1.75rem;
        line-height: 1.15;
        font-weight: var(--font-weight-semibold);
        font-variant-numeric: tabular-nums;
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
