@php
    use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;

    $upload = $this->upload();
@endphp
<x-filament-panels::page>
    @if ($upload === null)
        <x-filament::section>
            <x-filament::empty-state
                :contained="false"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentCheck"
                heading="No DTR uploaded for you yet"
                description="HR uploads each month's DTR once the IT Unit prints it. It shows here then."
            />
        </x-filament::section>
    @else
        @php($data = DtrUploadInfolist::viewData($upload, mine: true))

        <x-filament::section :heading="DtrUploadInfolist::month($upload)"
            :description="implode(' · ', array_filter([$data['schedule'], 'Printed as '.$upload->employee_name]))">
            <x-slot name="afterHeader">
                <label class="dtr-grid-picker">
                    Month
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="month">
                            @foreach ($this->uploads as $key => $each)
                                <option value="{{ $key }}" @selected($each->is($upload))>{{ DtrUploadInfolist::month($each) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </x-slot>

            <p class="dtr-record-callout">Something wrong? Bring your signed DTR to HR. Only HR can add a time or fix your schedule.</p>

            @include('filament.dtr-uploads.dtr', [...$data, 'getAction' => fn (): null => null])
        </x-filament::section>
    @endif
</x-filament-panels::page>
