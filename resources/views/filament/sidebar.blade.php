{{--
    The panel's frame: the sidebar, the topbar and every page's heading.
    The sidebar and topbar follow HR's references. The sidebar: on desktop a white card
    standing a little in from the screen's edges, with the brand at its top, the menu
    with a light grey fill for the page that is open, small grey headings in sentence
    case, and the signed-in user at its foot. The topbar: on desktop it blends into the
    page, with the search on the left and, on the right, white cards for what is
    waiting for HR and the upload (filament.topbar-actions). Phones keep Filament's
    topbar and slide-out sidebar. The width is set in AdminPanelProvider (sidebarWidth).
    Filament's own styles sit in a cascade layer; these do not, so they win without
    !important.
    On white: grey 700 labels and grey 500 headings pass 4.5:1, grey 500 icons 3:1.
    On grey 900: grey 200 labels, grey 400 headings and icons.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    :root {
        --dtr-sidebar: var(--color-white);
        --dtr-line: color-mix(in oklab, var(--gray-950) 8%, transparent);
        --dtr-hover: var(--gray-50);
        --dtr-current: var(--gray-100);
    }

    .dark {
        --dtr-sidebar: var(--gray-900);
        --dtr-line: color-mix(in oklab, var(--color-white) 10%, transparent);
        --dtr-hover: color-mix(in oklab, var(--color-white) 5%, transparent);
        --dtr-current: color-mix(in oklab, var(--color-white) 10%, transparent);
    }

    /*
        No page title on screen: the sidebar marks the page that is open, and the breadcrumbs
        and the page's own sections say the rest. The h1 stays for screen readers. An
        employee's page keeps its heading, their initials and name, since it says whose page it is.
    */
    .fi-page:not(.fi-resource-view-record-page) .fi-header-heading {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
        border: 0;
    }

    /* A header left with nothing to show, as the dashboard's, gives back the gap after it. */
    .fi-page:not(.fi-resource-view-record-page) .fi-header:not(.fi-header-has-breadcrumbs, .fi-header-has-subheading):not(:has(.fi-header-actions-ctn)) {
        margin-bottom: calc(var(--spacing) * -8);
    }

    /* The headings still shown a step smaller and lighter than Filament's 30px bold. */
    .fi-header-heading {
        font-size: var(--text-xl);
        line-height: var(--text-xl--line-height);
        font-weight: var(--font-weight-semibold);
    }

    @media (min-width: 40rem) {
        .fi-header-heading {
            font-size: var(--text-2xl);
            line-height: var(--text-2xl--line-height);
        }
    }

    /* The brand (filament.brand): the seal, the app's name and, under it, whose it is. */
    .dtr-brand {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 2.5);
        height: 100%;
    }

    .dtr-brand-mark {
        flex: none;
        width: calc(var(--spacing) * 8);
        height: calc(var(--spacing) * 8);
        border-radius: 9999px;
    }

    /* Filament sets the logo bold and tight for a one-line name; the two lines set their own. */
    .dtr-brand-text {
        display: grid;
        text-align: start;
        letter-spacing: normal;
    }

    .dtr-brand-name {
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-semibold);
        color: var(--gray-950);
    }

    .dtr-brand-sub {
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: 400;
        color: var(--gray-500);
    }

    .dark .dtr-brand-name {
        color: var(--color-white);
    }

    .dark .dtr-brand-sub {
        color: var(--gray-400);
    }

    .fi-sidebar {
        background-color: var(--dtr-sidebar);
    }

    .fi-sidebar-header {
        background-color: transparent;
        box-shadow: none;
    }

    @media (min-width: 64rem) {
        /* The topbar starts where the sidebar card ends, as the content does; the brand stays in the sidebar. */
        .fi-body-has-topbar .fi-topbar-ctn {
            margin-inline-start: calc(var(--sidebar-width) + var(--spacing) * 2);
        }

        .fi-body-has-topbar .fi-topbar-start {
            display: none;
        }

        /* No white strip: the page's grey, with the search and the cards on the right standing on it. */
        .fi-topbar {
            padding-inline: calc(var(--spacing) * 8);
            background-color: var(--gray-50);
            box-shadow: none;
        }

        .dark .fi-topbar {
            background-color: var(--gray-950);
        }

        /* The search on the left and everything after it pushed to the right. */
        .fi-topbar-end {
            flex: 1;
            margin-inline-start: 0;
        }

        .fi-topbar-end > .fi-global-search-ctn {
            margin-inline-end: auto;
        }

        .fi-topbar .fi-global-search-field {
            width: min(28rem, 40vw);
        }

        /* A card 0.5rem in from the edges. Nothing is clipped: the user menu opens inside it. */
        .fi-body-has-topbar .fi-main-sidebar {
            position: fixed;
            top: calc(var(--spacing) * 2);
            inset-inline-start: calc(var(--spacing) * 2);
            height: calc(100dvh - var(--spacing) * 4);
            border: 1px solid var(--dtr-line);
            border-radius: 0.75rem;
        }

        .fi-body-has-topbar .fi-main-ctn {
            margin-inline-start: calc(var(--sidebar-width) + var(--spacing) * 2);
        }

        .fi-body-has-topbar .fi-sidebar-header {
            display: flex;
        }
    }

    /* The topbar's search: a white field, rounder, its shortcut in a small key cap. */
    .fi-topbar .fi-global-search-field .fi-input-wrp {
        border-radius: 0.75rem;
    }

    .fi-topbar .fi-global-search-field .fi-input-wrp-suffix .fi-input-wrp-label {
        padding-inline: calc(var(--spacing) * 1.5);
        border-radius: 0.375rem;
        background-color: var(--dtr-hover);
        box-shadow: inset 0 0 0 1px var(--dtr-line);
        font-size: var(--text-xs);
        color: var(--gray-500);
    }

    /* The topbar's right side (filament.topbar-actions): white cards 2.5rem high. */
    .dtr-topbar-actions {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 2);
    }

    .dtr-topbar-btn {
        position: relative;
        display: grid;
        place-items: center;
        width: calc(var(--spacing) * 10);
        height: calc(var(--spacing) * 10);
        border-radius: 0.75rem;
        background-color: var(--color-white);
        box-shadow: 0 0 0 1px var(--dtr-line);
        color: var(--gray-600);
    }

    .dtr-topbar-btn:hover {
        background-color: var(--gray-50);
    }

    .dtr-topbar-btn:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: 2px;
    }

    .dtr-topbar-btn-icon {
        width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
    }

    /* How many things wait, on the bell's corner; the button's label says it for screen readers. */
    .dtr-topbar-count {
        position: absolute;
        top: calc(var(--spacing) * -1.5);
        inset-inline-end: calc(var(--spacing) * -1.5);
        min-width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
        padding-inline: calc(var(--spacing) * 1);
        border-radius: 9999px;
        background-color: var(--primary-500);
        color: var(--gray-950);
        font-size: var(--text-xs);
        line-height: calc(var(--spacing) * 5);
        font-weight: var(--font-weight-semibold);
        text-align: center;
    }

    .dtr-topbar-actions .fi-btn {
        min-height: calc(var(--spacing) * 10);
        border-radius: 0.75rem;
    }

    .dark .dtr-topbar-btn {
        background-color: var(--gray-900);
        color: var(--gray-300);
    }

    .dark .dtr-topbar-btn:hover {
        background-color: var(--gray-800);
    }

    .dark .dtr-topbar-btn:focus-visible {
        outline-color: var(--gray-400);
    }

    /* The brand as a card that leads home, lined up with the menu below it. */
    .fi-sidebar-header {
        height: auto;
        padding: calc(var(--spacing) * 3) calc(var(--spacing) * 3) 0;
    }

    .fi-sidebar-header-logo-ctn {
        flex: 1;
    }

    .fi-sidebar-header-logo-ctn > a {
        display: flex;
        padding: calc(var(--spacing) * 2);
        border: 1px solid var(--dtr-line);
        border-radius: 0.625rem;
    }

    .fi-sidebar-header-logo-ctn > a:hover {
        background-color: var(--dtr-hover);
    }

    .fi-sidebar-header .fi-logo {
        margin-inline-start: 0;
    }

    /*
        Filament's own insets (a negative margin round the menu, a margin round the footer,
        room kept for a scrollbar) put each part at a different edge. Without them the
        brand, the menu fills and the user line up as in the reference.
    */
    .fi-sidebar-nav {
        padding: calc(var(--spacing) * 3);
        scrollbar-gutter: auto;
        scrollbar-width: thin;
    }

    .fi-sidebar-nav-groups {
        row-gap: calc(var(--spacing) * 5);
        margin-inline: 0;
    }

    /* The items in no group (the Dashboard) ruled off from the groups, as at the top of the reference. */
    .fi-sidebar-group[data-group-label=""] {
        padding-bottom: calc(var(--spacing) * 5);
        border-bottom: 1px solid var(--dtr-line);
    }

    .fi-sidebar-group-items {
        row-gap: calc(var(--spacing) * 0.5);
    }

    .fi-sidebar-item-btn {
        column-gap: calc(var(--spacing) * 2.5);
        padding: calc(var(--spacing) * 2) calc(var(--spacing) * 2.5);
        border-radius: 0.5rem;
    }

    /* 20px icons beside 14px text. */
    .fi-sidebar .fi-sidebar-item-icon {
        width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
        color: var(--gray-500);
    }

    .fi-sidebar .fi-sidebar-item-label {
        color: var(--gray-700);
    }

    /* A heading, not a link: small and grey, in sentence case. */
    .fi-sidebar-group-btn {
        padding: 0 calc(var(--spacing) * 2.5) calc(var(--spacing) * 1);
    }

    .fi-sidebar .fi-sidebar-group-label {
        color: var(--gray-500);
        font-size: var(--text-xs);
        line-height: var(--text-xs--line-height);
        font-weight: var(--font-weight-medium);
    }

    .fi-sidebar .fi-sidebar-item-btn:hover,
    .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        background-color: var(--dtr-hover);
    }

    /* Filament marks keyboard focus with a fill alone, the same as hover here; a ring tells them apart. */
    .fi-sidebar .fi-sidebar-item-btn:focus-visible,
    .fi-sidebar-header-logo-ctn > a:focus-visible,
    .fi-sidebar .fi-user-menu-trigger:focus-visible {
        outline: 2px solid var(--gray-500);
        outline-offset: -2px;
    }

    /* The page that is open: a light grey fill and dark text, no colour. */
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--dtr-current);
    }

    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label {
        color: var(--gray-950);
        font-weight: var(--font-weight-medium);
    }

    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--gray-950);
    }

    /* The signed-in user at the foot, under a rule; the menu opens upwards from it. */
    .fi-sidebar-footer {
        margin: 0;
        padding: calc(var(--spacing) * 3);
        border-top: 1px solid var(--dtr-line);
    }

    .fi-sidebar-footer .fi-user-menu,
    .fi-sidebar-footer .fi-user-menu .fi-dropdown-trigger {
        width: 100%;
        min-width: 0;
    }

    /* Filament keeps this button from shrinking, which pushed a long name out of the sidebar. */
    .fi-sidebar .fi-user-menu-trigger {
        flex-shrink: 1;
        min-width: 0;
        width: 100%;
        padding: calc(var(--spacing) * 2);
        border-radius: 0.5rem;
    }

    .fi-sidebar .fi-user-menu-trigger:hover {
        background-color: var(--dtr-hover);
    }

    /* One line: a long name ends in "…" rather than pushing the row taller. */
    .fi-sidebar .fi-user-menu-trigger-text {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: start;
        font-weight: var(--font-weight-medium);
        color: var(--gray-950);
    }

    .dark .fi-sidebar .fi-sidebar-group-label,
    .dark .fi-sidebar .fi-sidebar-item-icon,
    .dark .fi-topbar .fi-global-search-field .fi-input-wrp-suffix .fi-input-wrp-label {
        color: var(--gray-400);
    }

    .dark .fi-sidebar .fi-sidebar-item-label {
        color: var(--gray-200);
    }

    .dark .fi-sidebar .fi-sidebar-item-btn:focus-visible,
    .dark .fi-sidebar-header-logo-ctn > a:focus-visible,
    .dark .fi-sidebar .fi-user-menu-trigger:focus-visible {
        outline-color: var(--gray-400);
    }

    .dark .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .dark .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
    .dark .fi-sidebar .fi-user-menu-trigger-text {
        color: var(--color-white);
    }
</style>

<script>
    // The groups no longer collapse, but the browser remembers any group collapsed while
    // they still could, and Filament would keep it shut with no way to open it. Forget those.
    document.addEventListener('alpine:initialized', () => {
        const sidebar = window.Alpine.store('sidebar');
        const groups = @js(array_map(fn(\App\Enums\NavigationGroup $group): string => $group->getLabel(), \App\Enums\NavigationGroup::cases()));

        sidebar.collapsedGroups = (sidebar.collapsedGroups ?? []).filter((group) => !groups.includes(group));
    });
</script>
