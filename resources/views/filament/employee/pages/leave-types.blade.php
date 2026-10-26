<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr)); gap: 1rem;">
        @foreach ($types as $type)
            <x-filament::section :heading="$type->getLabel()" :description="$type->value">
                <p>{{ $type->description() }}</p>

                @if ($type->isTracked())
                    <p style="margin-top: 0.75rem; font-weight: 600;">You have {{ $balances[$type->value] }} days.</p>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
