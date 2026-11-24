{{--
    An employee's notifications (EmployeeNotices): a bell at the right of each page's title, as staff
    have "Waiting for you" in their topbar (2026-11-24); in the sidebar it looked out of place.
    Filament's own component and slide-over; sidebar.blade.php styles its button as the staff bell.
    Staff have no database notifications, so they get nothing here.
--}}
@if (filament()->hasDatabaseNotifications())
    <div class="dtr-notifications-bell">
        @livewire(filament()->getDatabaseNotificationsLivewireComponent(), ['lazy' => false])
    </div>
@endif
