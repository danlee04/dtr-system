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

    /* An employee's page: their initials beside their name, then chips of who they are. */
    .dtr-person {
        display: inline-flex;
        align-items: center;
        gap: calc(var(--spacing) * 3);
    }

    .dtr-person-chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: calc(var(--spacing) * 2);
        margin-top: calc(var(--spacing) * 1);
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
