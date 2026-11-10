<?php

namespace App\Filament\Employee\Pages;

use App\Models\DtrUpload;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The signed-in employee's own DTRs, read-only, so they can check the late,
 * undertime and absences against their copy before HR acts on them. A
 * mistake goes to HR with the signed paper; nothing here can be changed.
 */
class MyDtr extends Page
{
    protected string $view = 'filament.employee.pages.my-dtr';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'My DTR';

    protected static ?string $title = 'My DTR';

    protected static ?string $slug = 'my-dtr';

    // Right after the Dashboard.
    protected static ?int $navigationSort = -1;

    /** The month on screen, as "2026-10". It only picks among the employee's own uploads. */
    #[Url]
    public ?string $month = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasCurrentEmployee() ?? false;
    }

    /**
     * The employee's own uploads, latest first, by month.
     *
     * shortcut: one DTR a month, as IT prints them; key by period start if IT ever prints half months.
     *
     * @return Collection<string, DtrUpload>
     */
    #[Computed]
    public function uploads(): Collection
    {
        return DtrUpload::query()
            ->where('employee_id', auth()->user()->employee_id)
            ->with(['employee', 'uploadedBy', 'days'])
            ->orderByDesc('period_start')
            ->get()
            ->keyBy(fn (DtrUpload $upload): string => $upload->period_start->format('Y-m'));
    }

    public function upload(): ?DtrUpload
    {
        return $this->uploads->get((string) $this->month) ?? $this->uploads->first();
    }
}
