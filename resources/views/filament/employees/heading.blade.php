{{-- The employee page's heading: their initials, then their name. --}}
<span style="display: inline-flex; align-items: center; gap: 0.75rem;">
    <x-filament::avatar :src="$avatar" alt="" size="lg" />
    <span>{{ $employee->full_name }}</span>
</span>
