<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Filament\Resources\DtrUploads\Schemas\DtrUploadInfolist;
use App\Models\DtrUpload;
use App\Services\Attendance\ComputedDay;
use App\Services\Leave\DtrDeduction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * HR marks a DTR final once it is checked against the signed paper: its late
 * and undertime come off VL, its absent days off SL, then VL, and it is
 * locked until reopened.
 */
class FinalizeDtrAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'finalize';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Mark final')
            ->icon(Heroicon::OutlinedLockClosed)
            ->authorize('finalize')
            ->requiresConfirmation()
            ->modalHeading('Mark this DTR final')
            ->modalDescription(fn (DtrUpload $record, DtrDeduction $deduction): string => self::description($record, $deduction))
            ->modalSubmitActionLabel('Mark final')
            ->action(function (DtrUpload $record, DtrDeduction $deduction): void {
                try {
                    $deduction->finalize($record, auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                    $this->halt();
                }

                $record->forgetComputed();

                Notification::make()->success()->title('DTR marked final')
                    ->body($deduction->whyNothing($record) ?? DtrUploadInfolist::deductions($deduction->deductedByType($record)))
                    ->send();
            });
    }

    /** What Final will deduct, or why nothing, and the blanks HR has not filled. */
    private static function description(DtrUpload $record, DtrDeduction $deduction): string
    {
        if (! $record->isComputed()) {
            return '';
        }

        $computed = $record->computed();
        $blanks = array_sum(array_map(fn (ComputedDay $day): int => count($day->missing), $computed->days));
        $parts = array_filter([
            bccomp($computed->dayEquivalent, '0', 3) > 0 ? "{$computed->dayEquivalent} days from VL for late and undertime" : null,
            bccomp($computed->absentCharge, '0', 3) > 0
                ? "{$computed->absentCharge} days for {$computed->absent} absent ".Str::plural('day', $computed->absent === 1.0 ? 1 : 2).' from SL, then VL'
                : null,
        ]);

        return ($deduction->whyNothing($record) ?? 'Deduct '.implode(', and ', $parts).'.')
            .($blanks > 0 ? " {$blanks} ".Str::plural('blank', $blanks).' still empty.' : '');
    }
}
