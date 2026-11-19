<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Models\DtrUpload;
use App\Services\Leave\DtrDeduction;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * HR reopens a final DTR, with the reason, to take in a change such as leave
 * filed late: the VL it deducted is given back, and it can be marked final again.
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
            ->color('gray')
            ->authorize('reopen')
            ->modalHeading('Reopen this DTR')
            ->modalDescription('The VL it deducted is given back, and the DTR can be changed again.')
            ->schema([
                Textarea::make('reopen_reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->modalSubmitActionLabel('Reopen')
            ->action(function (DtrUpload $record, array $data, DtrDeduction $deduction): void {
                try {
                    $deduction->reopen($record, auth()->user(), $data['reopen_reason']);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                    $this->halt();
                }

                $record->forgetComputed();

                Notification::make()->success()->title('DTR reopened')->send();
            });
    }
}
