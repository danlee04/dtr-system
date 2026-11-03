{{--
    The topbar's right side, after the search, for staff only (AdminPanelProvider):
    a bell for what is waiting for HR, counted on each page load, and the month's main
    job. The bell's count is said in its label too, not by the badge alone.
--}}
@php
    use App\Enums\LeaveApplicationStatus;
    use App\Filament\Resources\DtrUploads\DtrUploadResource;
    use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
    use Filament\Support\Icons\Heroicon;

    $waiting = $pendingLeave + $unmatchedDtrs;
@endphp

<div class="dtr-topbar-actions">
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button type="button" class="dtr-topbar-btn" aria-label="Waiting for you: {{ $waiting }}">
                <x-filament::icon :icon="Heroicon::OutlinedBell" class="dtr-topbar-btn-icon" />
                @if ($waiting > 0)
                    <span class="dtr-topbar-count" aria-hidden="true">{{ $waiting }}</span>
                @endif
            </button>
        </x-slot>

        <x-filament::dropdown.header>Waiting for you</x-filament::dropdown.header>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                tag="a"
                :href="LeaveApplicationResource::getUrl('index', ['filters' => ['status' => ['value' => LeaveApplicationStatus::Pending->value]]])"
                :icon="Heroicon::OutlinedDocumentText"
                :badge="$pendingLeave"
            >
                Leave applications to approve
            </x-filament::dropdown.list.item>

            <x-filament::dropdown.list.item
                tag="a"
                :href="DtrUploadResource::getUrl('index', ['filters' => ['needs_employee' => ['isActive' => true]]])"
                :icon="Heroicon::OutlinedDocumentArrowUp"
                :badge="$unmatchedDtrs"
            >
                DTRs that need an employee
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>

    {{-- Opens the upload on the DTR Uploads page. --}}
    <x-filament::button
        tag="a"
        :href="DtrUploadResource::getUrl('index', ['action' => 'uploadDtrs'])"
        color="gray"
        :icon="Heroicon::OutlinedArrowUpTray"
        labeled-from="sm"
    >
        Upload DTRs
    </x-filament::button>
</div>
