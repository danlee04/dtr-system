{{-- The employee page's heading: their photo, or their initials without one, then their name. --}}
<span class="dtr-person">
    <x-filament::avatar :src="$avatar" alt="" size="lg" class="dtr-person-avatar" />
    <span class="dtr-person-name">{{ $employee->full_name }}</span>
</span>
