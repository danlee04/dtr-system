{{--
    The sidebar runs the full height of the screen with the logo at its top, and the
    topbar covers only the content beside it, both in light grey with neutral grey
    text and icons and tight spacing. --dtr-chrome is their colour: grey 200 on light,
    grey 900 on dark. The width is set in AdminPanelProvider (sidebarWidth).
    Filament's own styles sit in a cascade layer; these do not, so they win without
    !important.
    Against grey 200: grey 700 labels and grey 600 headings pass 4.5:1, grey 500
    icons pass 3:1. On grey 900: grey 200 labels, grey 400 headings and icons.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    :root {
        --dtr-chrome: var(--gray-200);
    }

    .dark {
        --dtr-chrome: var(--gray-900);
    }

    .fi-topbar,
    .fi-sidebar,
    .fi-sidebar-header {
        background-color: var(--dtr-chrome);
    }

    /*
        Filament 5 runs its topbar across the whole width and starts the sidebar under
        it. On desktop the sidebar takes the full height instead, the logo moves from
        the topbar to the sidebar's own header, and the topbar and content start where
        the sidebar ends; a line keeps the two greys apart. Phones keep Filament's layout.
    */
    @media (min-width: 64rem) {
        .fi-body-has-topbar .fi-main-sidebar {
            position: fixed;
            top: 0;
            height: 100dvh;
            border-inline-end: 1px solid color-mix(in oklab, var(--gray-950) 8%, transparent);
        }

        .dark .fi-body-has-topbar .fi-main-sidebar {
            border-inline-end-color: color-mix(in oklab, var(--color-white) 10%, transparent);
        }

        .fi-body-has-topbar .fi-topbar-ctn,
        .fi-body-has-topbar .fi-main-ctn {
            margin-inline-start: var(--sidebar-width);
        }

        .fi-body-has-topbar .fi-topbar-start {
            display: none;
        }

        .fi-body-has-topbar .fi-sidebar-header {
            display: flex;
        }
    }

    /* The logo, on desktop and in the phone's slide-out sidebar alike, lined up with the icons below it. */
    .fi-sidebar-header {
        justify-content: flex-start;
        padding-inline: calc(var(--spacing) * 4);
    }

    .fi-sidebar-header .fi-logo {
        margin-inline-start: 0;
    }

    /* Tight spacing: less padding round the list, between groups and inside each link. */
    .fi-sidebar-nav {
        padding-inline: calc(var(--spacing) * 4);
        padding-block: calc(var(--spacing) * 4);
    }

    .fi-sidebar-nav-groups {
        row-gap: calc(var(--spacing) * 4);
    }

    .fi-sidebar-group-items {
        row-gap: calc(var(--spacing) * 0.5);
    }

    .fi-sidebar-item-btn {
        padding-block: calc(var(--spacing) * 1.5);
    }

    /* 16px icons beside 14px text, smaller than Filament's 24px, which also set each link's height. */
    .fi-sidebar .fi-sidebar-item-icon {
        width: calc(var(--spacing) * 4);
        height: calc(var(--spacing) * 4);
    }

    .fi-sidebar-group-btn {
        padding-block: calc(var(--spacing) * 1);
    }

    /* A heading, not a link: smaller than the links (12px against 14px), in capitals. */
    .fi-sidebar .fi-sidebar-group-label {
        color: var(--gray-600);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-semibold);
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .fi-sidebar .fi-sidebar-item-label {
        color: var(--gray-700);
    }

    .fi-sidebar .fi-sidebar-item-icon {
        color: var(--gray-500);
    }

    .fi-sidebar .fi-sidebar-item-btn:hover,
    .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        background-color: color-mix(in oklab, var(--color-white) 55%, transparent);
    }

    /* Filament marks keyboard focus with a fill alone, the same as hover here; a ring tells them apart. */
    .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: -2px;
    }

    /* The current page: a white tab with dark text, no colour. */
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--color-white);
        box-shadow: 0 1px 2px 0 color-mix(in oklab, var(--gray-950) 8%, transparent);
    }

    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--gray-950);
    }

    .dark .fi-sidebar .fi-sidebar-group-label,
    .dark .fi-sidebar .fi-sidebar-item-icon {
        color: var(--gray-400);
    }

    .dark .fi-sidebar .fi-sidebar-item-label {
        color: var(--gray-200);
    }

    .dark .fi-sidebar .fi-sidebar-item-btn:hover,
    .dark .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        background-color: color-mix(in oklab, var(--color-white) 5%, transparent);
    }

    .dark .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        outline-color: var(--gray-400);
    }

    .dark .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: color-mix(in oklab, var(--color-white) 10%, transparent);
        box-shadow: none;
    }

    .dark .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .dark .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--color-white);
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
