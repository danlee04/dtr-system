{{--
    The panel's frame: the sidebar, the topbar and every page's heading.
    The sidebar and topbar follow HR's references. The sidebar (HR's reference,
    2026-11-23): a navy gradient down the screen's left edge, with the brand at its top in
    white, the menu in white with a lighter blue pill for the page that is open, small
    pale headings in sentence case, and the signed-in user at its foot in a pale pill. The topbar: on desktop it blends into the
    page, with the search on the left and, on the right, white cards for what is
    waiting for HR and the upload (filament.topbar-actions). Phones keep Filament's
    topbar and slide-out sidebar. The width is set in AdminPanelProvider (sidebarWidth).
    Filament's own styles sit in a cascade layer; these do not, so they win without
    !important.
    On the navy: white labels and icons, and headings in white at 65%, pass 4.5:1.
--}}
{{-- shortcut: inline styles because the panel has no custom theme; move them to one when a later plan adds a theme. --}}
<style>
    :root {
        --dtr-sidebar: linear-gradient(180deg, var(--primary-900) 0%, color-mix(in oklab, var(--primary-900) 50%, var(--primary-950)) 50%, var(--primary-950) 100%);
        --dtr-line: color-mix(in oklab, var(--gray-950) 8%, transparent);
        --dtr-hover: var(--gray-50);
        --dtr-current: var(--gray-100);
        --dtr-side-line: color-mix(in oklab, var(--color-white) 12%, transparent);
        --dtr-side-hover: color-mix(in oklab, var(--color-white) 8%, transparent);
        --dtr-side-current: linear-gradient(90deg, var(--primary-600), var(--primary-500));
        --dtr-side-text: color-mix(in oklab, var(--color-white) 90%, transparent);
        --dtr-side-muted: color-mix(in oklab, var(--color-white) 65%, transparent);
    }

    /*
        Every page shows its title, as the dashboard does (HR, 2026-11-23; hidden before). The
        dashboard draws its own heading beside the month picker, so its h1 stays for screen
        readers only.
    */
    .fi-page:has(.dtr-dash) .fi-header-heading {
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

    /* Filament's 2rem above the page and under its header, half that with the titles gone. */
    .fi-page-header-main-ctn {
        row-gap: calc(var(--spacing) * 4);
        padding-block: calc(var(--spacing) * 4);
    }

    /* The dashboard's header, left with nothing to show, gives back the gap after it. */
    .fi-page:has(.dtr-dash) .fi-header:not(.fi-header-has-breadcrumbs, .fi-header-has-subheading):not(:has(.fi-header-actions-ctn)) {
        margin-bottom: calc(var(--spacing) * -4);
    }

    /*
        Every table's search at its left (HR, 2026-11-10), where the eye starts; the
        filter and column buttons stay at its right. Filament pushes the whole group right.
    */
    .fi-ta-header-toolbar> :nth-child(2) {
        flex: 1;
        margin-inline-start: 0;
    }

    .fi-ta-header-toolbar .fi-ta-search-field {
        margin-inline-end: auto;
    }

    /* A list's own filters in its toolbar, right of the search (filament.dtr-uploads.toolbar-filters). */
    .dtr-toolbar-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: calc(var(--spacing) * 4);
    }

    .dtr-toolbar-filter {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 2);
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        white-space: nowrap;
    }

    /*
        No toolbar action showing (none, or bulk actions hidden until rows are picked):
        no empty slot, and no gap, before the search, so it lines up with the table.
    */
    .fi-ta-header-toolbar>.fi-ta-actions:not(:has(> :not([style*="display: none"]))) {
        display: none;
    }

    /* Every page's title as the dashboard's: 28px bold from 640px, 24px on a phone. */
    .fi-header-heading {
        font-size: var(--text-2xl);
        line-height: 1.2;
        font-weight: var(--font-weight-bold);
        letter-spacing: -0.01em;
        color: var(--gray-950);
    }

    @media (min-width: 40rem) {
        .fi-header-heading {
            font-size: 1.75rem;
        }
    }

    /*
        Every page after the dashboard (HR, 2026-11-23): a soft blue page with pale arcs at its
        top, white cards with 16px corners and a soft blue shadow, 20px inside them, and 16px
        between them.
    */
    /*
        One background for the whole screen, fixed to it: the topbar paints the same one, so
        it runs on into the main area with no seam (HR, 2026-11-24), and still hides what
        scrolls under it.
    */
    :root {
        --dtr-page-bg:
            radial-gradient(130% 70% at 100% -12%, transparent 46%, color-mix(in oklab, var(--primary-100) 75%, transparent) 47%, transparent 58%),
            radial-gradient(110% 60% at 85% -18%, transparent 50%, color-mix(in oklab, var(--primary-200) 45%, transparent) 51%, transparent 60%),
            radial-gradient(60% 40% at 90% 0%, color-mix(in oklab, var(--primary-200) 55%, transparent), transparent 70%),
            linear-gradient(180deg, color-mix(in oklab, var(--primary-50) 80%, var(--color-white)) 0%, color-mix(in oklab, var(--primary-50) 35%, var(--gray-50)) 45%, var(--gray-50) 100%);
    }

    .fi-body,
    .fi-topbar {
        background: var(--dtr-page-bg);
        background-attachment: fixed;
    }

    .fi-topbar {
        box-shadow: none;
    }

    .fi-page-main .fi-section:not(.fi-section-not-contained),
    .fi-page-main .fi-ta-ctn {
        border-radius: 1rem;
        box-shadow:
            0 0 0 1px color-mix(in oklab, var(--gray-950) 4%, transparent),
            0 12px 32px -20px color-mix(in oklab, var(--primary-800) 45%, transparent);
    }

    .fi-page-main .fi-section:not(.fi-section-not-contained, .fi-compact) > .fi-section-header {
        padding-inline: calc(var(--spacing) * 5);
    }

    .fi-page-main .fi-section:not(.fi-section-not-contained, .fi-compact) > .fi-section-content-ctn > .fi-section-content {
        padding: calc(var(--spacing) * 5);
    }

    .fi-page-content {
        row-gap: calc(var(--spacing) * 4);
    }

    /* An employee's pages have no topbar (2026-11-23): 32px above the title, not 16px, so it does not sit on the screen's edge. */
    .fi-body:not(.fi-body-has-topbar) .fi-page-header-main-ctn {
        padding-top: calc(var(--spacing) * 8);
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

    /* On the navy sidebar (HR's reference, 2026-11-23): a 48px seal on a white disc, the name bold and white (the sign-in page keeps the dark brand). */
    .fi-sidebar .dtr-brand {
        gap: calc(var(--spacing) * 3);
    }

    .fi-sidebar .dtr-brand-mark {
        width: calc(var(--spacing) * 12);
        height: calc(var(--spacing) * 12);
        background-color: var(--color-white);
        box-shadow: 0 0 0 2px color-mix(in oklab, var(--color-white) 35%, transparent);
    }

    .fi-sidebar .dtr-brand-name {
        font-size: var(--text-base);
        line-height: var(--text-base--line-height);
        font-weight: var(--font-weight-bold);
        color: var(--color-white);
    }

    .fi-sidebar .dtr-brand-sub {
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        color: var(--dtr-side-muted);
    }

    .fi-sidebar {
        background: var(--dtr-sidebar);
    }

    .fi-sidebar-header {
        background-color: transparent;
        box-shadow: none;
    }

    @media (min-width: 64rem) {

        /* The topbar starts where the sidebar ends, as the content does; the brand stays in the sidebar. */
        .fi-body-has-topbar .fi-topbar-ctn {
            margin-inline-start: var(--sidebar-width);
        }

        .fi-body-has-topbar .fi-topbar-start {
            display: none;
        }

        .fi-topbar {
            padding-inline: calc(var(--spacing) * 8);
        }

        /* The search on the left and everything after it pushed to the right. */
        .fi-topbar-end {
            flex: 1;
            margin-inline-start: 0;
        }

        .fi-topbar-end>.fi-global-search-ctn {
            margin-inline-end: auto;
        }

        .fi-topbar .fi-global-search-field {
            width: min(28rem, 40vw);
        }

        /* Down the screen's whole left edge (HR's reference, 2026-11-23). */
        .fi-body-has-topbar .fi-main-sidebar {
            position: fixed;
            top: 0;
            inset-inline-start: 0;
            height: 100dvh;
            border: 0;
            border-radius: 0;
        }

        .fi-body-has-topbar .fi-main-ctn {
            margin-inline-start: var(--sidebar-width);
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

    /*
        The brand, lined up with the menu below it, leads home; the header holds the link.
    */
    .fi-sidebar-header {
        height: auto;
        margin: calc(var(--spacing) * 4) calc(var(--spacing) * 3) 0;
        padding: 0;
        border: 0;
        border-radius: 0.625rem;
    }

    .fi-sidebar-header-logo-ctn {
        flex: 1;
        min-width: 0;
    }

    /* The brand centred in the sidebar (HR, 2026-11-23). */
    .fi-sidebar-header-logo-ctn>a {
        display: flex;
        justify-content: center;
        padding: calc(var(--spacing) * 2);
        border-radius: calc(0.625rem - 1px);
    }

    .fi-sidebar-header-logo-ctn>a:hover {
        background-color: var(--dtr-side-hover);
    }

    .fi-sidebar-header .fi-logo {
        margin-inline-start: 0;
    }

    /*
        Filament's own insets (a negative margin round the menu, a margin round the footer,
        room kept for a scrollbar) put each part at a different edge. Without them the
        brand, the menu fills and the user line up as in the reference.
    */
    /* 20px under the brand card before the first group, as between the groups. */
    .fi-sidebar-nav {
        padding: calc(var(--spacing) * 5) calc(var(--spacing) * 3) calc(var(--spacing) * 3);
        scrollbar-gutter: auto;
        scrollbar-width: thin;
    }

    .fi-sidebar-nav-groups {
        row-gap: calc(var(--spacing) * 5);
        margin-inline: 0;
    }

    .fi-sidebar-group-items {
        row-gap: calc(var(--spacing) * 0.5);
    }

    .fi-sidebar-item-btn {
        column-gap: calc(var(--spacing) * 2.5);
        padding: calc(var(--spacing) * 2) calc(var(--spacing) * 2.5);
        border-radius: 0.5rem;
    }

    /* 20px icons beside 14px text, white on the navy. */
    .fi-sidebar .fi-sidebar-item-icon {
        width: calc(var(--spacing) * 5);
        height: calc(var(--spacing) * 5);
        color: var(--dtr-side-text);
    }

    .fi-sidebar .fi-sidebar-item-label {
        color: var(--dtr-side-text);
    }

    /* A heading, not a link: small and grey, in sentence case. */
    .fi-sidebar-group-btn {
        padding: 0 calc(var(--spacing) * 2.5) calc(var(--spacing) * 1);
    }

    .fi-sidebar .fi-sidebar-group-label {
        color: var(--dtr-side-muted);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        font-weight: var(--font-weight-normal);
    }

    .fi-sidebar .fi-sidebar-item-btn:hover,
    .fi-sidebar .fi-sidebar-item-btn:focus-visible {
        background-color: var(--dtr-side-hover);
    }

    /* Filament marks keyboard focus with a fill alone, the same as hover here; a white ring tells them apart. */
    .fi-sidebar .fi-sidebar-item-btn:focus-visible,
    .fi-sidebar-header-logo-ctn>a:focus-visible,
    .dtr-sidebar-user-link:focus-visible {
        outline: 2px solid var(--color-white);
        outline-offset: -2px;
    }

    /* The page that is open: a bright blue pill, white words, a shadow under it. */
    .fi-sidebar .fi-sidebar-item.fi-active>.fi-sidebar-item-btn {
        background: var(--dtr-side-current);
        box-shadow: 0 2px 8px color-mix(in oklab, var(--primary-950) 45%, transparent);
    }

    .fi-sidebar .fi-sidebar-item.fi-active>.fi-sidebar-item-btn>.fi-sidebar-item-label {
        color: var(--color-white);
        font-weight: var(--font-weight-semibold);
    }

    .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--color-white);
    }

    /* Sign out, white on the navy; round, so its hover fits the pill's round end. */
    .fi-sidebar .dtr-sidebar-user .fi-icon-btn {
        margin: 0;
        border-radius: 9999px;
        color: var(--dtr-side-text);
    }

    .fi-sidebar .dtr-sidebar-user .fi-icon-btn:hover {
        background-color: var(--dtr-side-hover);
        color: var(--color-white);
    }

    /*
        The signed-in user at the foot, under a rule, with no menu to open (filament.sidebar-user):
        their photo and name lead to their profile, and sign out sits beside them.
    */
    .dtr-sidebar-user {
        display: flex;
        align-items: center;
        gap: calc(var(--spacing) * 1);
        margin: calc(var(--spacing) * 3);
        padding: calc(var(--spacing) * 1);
        border-radius: 9999px;
        background-color: var(--dtr-side-hover);
    }

    .dtr-sidebar-user-link {
        display: flex;
        flex: 1;
        align-items: center;
        gap: calc(var(--spacing) * 2.5);
        min-width: 0;
        padding: calc(var(--spacing) * 1);
        border-radius: 9999px;
    }

    .dtr-sidebar-user-link:hover {
        background-color: var(--dtr-side-hover);
    }

    /* A table cell that may wrap on a laptop or tablet keeps to one line from 1280px. */
    @media (min-width: 80rem) {
        .fi-ta-text.dtr-nowrap-from-xl {
            white-space: nowrap;
        }
    }

    /* One line: a long name ends in "…" rather than pushing sign out off the sidebar. */
    .dtr-sidebar-user-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
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
