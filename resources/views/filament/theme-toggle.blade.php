@php
    use Filament\Support\Icons\Heroicon;
@endphp
{{--
    Light or dark, beside the brand: one button that switches to the other. It sends
    Filament's own theme-changed event, so the choice is kept as Filament keeps it.
--}}
@if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    <div class="dtr-theme-toggle" x-data>
        <x-filament::icon-button
            :icon="Heroicon::OutlinedMoon"
            color="gray"
            label="Switch to dark mode"
            tooltip="Dark mode"
            x-show="$store.theme !== 'dark'"
            x-on:click="$dispatch('theme-changed', 'dark')"
        />
        <x-filament::icon-button
            :icon="Heroicon::OutlinedSun"
            color="gray"
            label="Switch to light mode"
            tooltip="Light mode"
            x-cloak
            x-show="$store.theme === 'dark'"
            x-on:click="$dispatch('theme-changed', 'light')"
        />
    </div>
@endif
