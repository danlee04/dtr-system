{{--
    The employee page's heading, after HR's reference (2026-11-23): a large round photo, or
    their initials without one, with a pencil on it that opens Edit for those who may edit,
    then their name.
--}}
<span class="dtr-person">
    <span class="dtr-person-photo">
        <x-filament::avatar :src="$avatar" alt="" size="lg" class="dtr-person-avatar" />
        @can('update', $employee)
            <button type="button" class="dtr-person-edit" wire:click="mountAction('edit')" aria-label="Edit {{ $employee->full_name }}">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::Pencil" class="dtr-person-edit-icon" />
            </button>
        @endcan
    </span>
    <span class="dtr-person-name">{{ $employee->full_name }}</span>
</span>
