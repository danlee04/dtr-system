<x-filament-panels::page>
    <div class="dtr-cards">
        @foreach ($types as $type)
            <x-filament::section :heading="$type->getLabel()" :description="$type->value">
                <p>{{ $type->description() }}</p>

                @if ($type->isTracked())
                    <p class="dtr-card-figure">You have {{ $balances[$type->value] }} days.</p>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
