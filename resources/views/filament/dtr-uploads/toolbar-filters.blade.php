@php
    use App\Filament\Resources\DtrUploads\Tables\DtrUploadsTable;
@endphp
{{--
    The DTR list's filters in its toolbar, at the right of the search, with no dropdown
    to open: each applies as it changes (HR, 2026-11-18). They drive the table's own
    filters, so the query, the default month and the bell's link work as before.
--}}
<div class="dtr-toolbar-filters">
    <label class="dtr-toolbar-filter">
        Month
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="tableFilters.month.value">
                <option value="">All months</option>
                @foreach (DtrUploadsTable::months() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </label>

    <label class="dtr-toolbar-filter">
        <x-filament::input.checkbox wire:model.live="tableFilters.needs_employee.isActive" />
        Needs an employee
    </label>
</div>
