@php
    use Filament\Support\Icons\Heroicon;

    $user = filament()->auth()->user();
@endphp
{{--
    The signed-in user at the sidebar's foot, with no menu to open: their photo and
    name lead to their profile, and the button beside them signs out.
--}}
@if ($user)
    <div class="dtr-sidebar-user">
        <a href="{{ filament()->getProfileUrl() }}" class="dtr-sidebar-user-link">
            <x-filament-panels::avatar.user :user="$user" size="md" />
            <span class="dtr-sidebar-user-name">{{ filament()->getUserName($user) }}</span>
        </a>

        <form method="post" action="{{ filament()->getLogoutUrl() }}">
            @csrf
            <x-filament::icon-button
                type="submit"
                :icon="Heroicon::OutlinedArrowRightStartOnRectangle"
                color="gray"
                label="Sign out"
                tooltip="Sign out"
            />
        </form>
    </div>
@endif
