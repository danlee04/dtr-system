{{--
    In the head of every page (AdminPanelProvider, STYLES_AFTER): the app's own stylesheet,
    public/css/dtr.css, with the file's time as its version, so the browser keeps it until it
    changes (2026-11-24; its rules sat inline here and in three other partials before, sent
    again with every page); and the sidebar's one script.
--}}
<link rel="stylesheet" href="{{ asset('css/dtr.css') }}?v={{ filemtime(public_path('css/dtr.css')) }}">

<script>
    // The groups no longer collapse, but the browser remembers any group collapsed while
    // they still could, and Filament would keep it shut with no way to open it. Forget those.
    document.addEventListener('alpine:initialized', () => {
        const sidebar = window.Alpine.store('sidebar');
        const groups = @js(array_map(fn(\App\Enums\NavigationGroup $group): string => $group->getLabel(), \App\Enums\NavigationGroup::cases()));

        sidebar.collapsedGroups = (sidebar.collapsedGroups ?? []).filter((group) => !groups.includes(group));
    });
</script>
