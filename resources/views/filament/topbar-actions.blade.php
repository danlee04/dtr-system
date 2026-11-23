{{--
    The topbar's right side, after the search, for staff only (AdminPanelProvider):
    a bell for what is waiting for HR (WaitingForHr, counted on each page load), and New for
    what HR files most, the month's upload first (it had a button of its own until
    2026-11-23). The bell's count is said in its label too, not by the badge alone. Each
    New item opens its form on its own page.
--}}
@php
    use App\Enums\LeaveApplicationStatus;
    use App\Filament\Pages\LeaveOpeningBalances;
    use App\Filament\Resources\DayExceptions\DayExceptionResource;
    use App\Filament\Resources\DtrUploads\DtrUploadResource;
    use App\Filament\Resources\Employees\EmployeeResource;
    use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
    use App\Models\DayException;
    use App\Models\DtrUpload;
    use App\Models\Employee;
    use App\Models\LeaveApplication;
    use Filament\Support\Icons\Heroicon;

    $waiting = $pendingLeave + $unmatchedDtrs + $dtrsNotFinal + $withoutSchedule + $withoutOpenings;
    $user = auth()->user();
    // What HR files most, each offered only to those who may (HR, 2026-11-23).
    $new = array_filter([
        $user->can('create', DtrUpload::class) ? ['Upload DTRs', Heroicon::OutlinedArrowUpTray, DtrUploadResource::getUrl('index', ['action' => 'uploadDtrs'])] : null,
        $user->can('create', LeaveApplication::class) ? ['File leave', Heroicon::OutlinedDocumentPlus, LeaveApplicationResource::getUrl('index', ['action' => 'fileLeave'])] : null,
        $user->can('create', DayException::class) ? ['OB, TO or gate pass', Heroicon::OutlinedBriefcase, DayExceptionResource::getUrl('index', ['action' => 'create'])] : null,
        $user->can('create', Employee::class) ? ['Employee', Heroicon::OutlinedUserPlus, EmployeeResource::getUrl('index', ['action' => 'create'])] : null,
    ]);
@endphp

<div class="dtr-topbar-actions">
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button type="button" class="dtr-topbar-btn" aria-label="Waiting for you: {{ $waiting }}">
                <x-filament::icon :icon="Heroicon::OutlinedBell" class="dtr-topbar-btn-icon" />
                @if ($waiting > 0)
                    <span class="dtr-topbar-count" aria-hidden="true">{{ $waiting > 99 ? '99+' : $waiting }}</span>
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

            <x-filament::dropdown.list.item
                tag="a"
                :href="DtrUploadResource::getUrl('index', ['filters' => ['not_final' => ['isActive' => true]]])"
                :icon="Heroicon::OutlinedLockOpen"
                :badge="$dtrsNotFinal"
            >
                DTRs not final yet
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                tag="a"
                :href="EmployeeResource::getUrl('index', ['filters' => ['no_schedule' => ['isActive' => true]]])"
                :icon="Heroicon::OutlinedClock"
                :badge="$withoutSchedule"
            >
                Staff with no schedule
            </x-filament::dropdown.list.item>

            <x-filament::dropdown.list.item
                tag="a"
                :href="LeaveOpeningBalances::getUrl()"
                :icon="Heroicon::OutlinedScale"
                :badge="$withoutOpenings"
            >
                Staff with no opening balance
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>

    @if ($new !== [])
        <x-filament::dropdown placement="bottom-end" teleport>
            <x-slot name="trigger">
                <x-filament::button color="gray" :icon="Heroicon::OutlinedPlus" labeled-from="sm" aria-label="New">
                    New
                </x-filament::button>
            </x-slot>

            <x-filament::dropdown.header>New</x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($new as [$label, $icon, $url])
                    <x-filament::dropdown.list.item tag="a" :href="$url" :icon="$icon">{{ $label }}</x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
</div>
