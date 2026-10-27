{{--
    Every read view (the employee's Details tab, the leave application modal and the rest)
    shows its labels in grey and its values in near-black, so the eye lands on the values,
    as on the balance cards. Filament draws both near-black.
    Grey 500 on white and grey 400 on grey 900 pass 4.5:1, so a placeholder such as
    "Not set" stays readable. Filament's own grey 400 placeholder does not.
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
</style>
