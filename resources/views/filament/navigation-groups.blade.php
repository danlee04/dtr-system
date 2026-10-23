{{--
    The sidebar's groups: a coloured heading over each group's items, not a dropdown.
    AdminPanelProvider gives every group's <li> a fi-color-nav-* class, which Filament
    fills with --color-50 … --color-950 for that group, so these rules serve every
    group at once. Filament's own styles sit in a cascade layer; these do not, so
    they win without !important.
    Shades: 700 for text and 600 for icons on light (4.5:1 and 3:1 against white),
    400 and 300 on dark.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    /* A heading, not a link: smaller than the links (12px against 14px), in capitals. */
    .dtr-nav-group .fi-sidebar-group-label {
        color: var(--color-700);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .dtr-nav-group .fi-sidebar-item-icon {
        color: var(--color-600);
    }

    .dtr-nav-group .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--color-50);
    }

    .dtr-nav-group .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .dtr-nav-group .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--color-700);
    }

    .dark .dtr-nav-group .fi-sidebar-group-label,
    .dark .dtr-nav-group .fi-sidebar-item-icon {
        color: var(--color-400);
    }

    .dark .dtr-nav-group .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: color-mix(in oklab, var(--color-400) 12%, transparent);
    }

    .dark .dtr-nav-group .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .dark .dtr-nav-group .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--color-300);
    }
</style>

<script>
    // The groups no longer collapse, but the browser remembers any group collapsed while
    // they still could, and Filament would keep it shut with no way to open it. Forget those.
    document.addEventListener('alpine:initialized', () => {
        const sidebar = window.Alpine.store('sidebar');
        const groups = @js(array_map(fn (\App\Enums\NavigationGroup $group): string => $group->getLabel(), \App\Enums\NavigationGroup::cases()));

        sidebar.collapsedGroups = (sidebar.collapsedGroups ?? []).filter((group) => ! groups.includes(group));
    });
</script>
