<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Models\DtrUpload;
use App\Services\Leave\DtrDeduction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * HR reopens a final DTR in one click, with no reason asked (HR, 2026-11-20),
 * to take in a change such as leave filed late: the VL it deducted is given
 * back, and it can be marked final again.
 */
class ReopenDtrAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reopen';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reopen')
            ->icon(Heroicon::OutlinedLockOpen)
            ->authorize('reopen')
            ->action(function (DtrUpload $record, DtrDeduction $deduction): void {
                try {
                    $deduction->reopen($record, auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                    $this->halt();
                }

                $record->forgetComputed();

                Notification::make()->success()->title('DTR reopened')->send();
            });
    }
}
