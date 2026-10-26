<x-filament-panels::page>
    <x-filament::tabs label="Leave type">
        @foreach (\App\Enums\LeaveType::tracked() as $tab)
            <x-filament::tabs.item :active="$tab === $this->currentType()" wire:click="$set('type', '{{ $tab->value }}')">
                {{ $tab->getLabel() }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
