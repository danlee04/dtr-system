{{-- The employee page's heading: their initials, then their name. --}}
<span class="dtr-person">
    <x-filament::avatar :src="$avatar" alt="" size="lg" />
    <span>{{ $employee->full_name }}</span>
</span>
