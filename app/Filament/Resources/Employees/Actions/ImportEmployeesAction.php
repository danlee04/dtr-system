<?php

namespace App\Filament\Resources\Employees\Actions;

use App\Models\Employee;
use App\Services\EmployeeImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Uploads HR's CSV and hands it to EmployeeImporter. The upload lands on the
 * private local disk and is deleted as soon as it has been read.
 */
class ImportEmployeesAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'importEmployees';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Import CSV')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize('create', Employee::class)
            ->modalHeading('Import employees')
            ->modalDescription('Columns: '.implode(', ', EmployeeImporter::COLUMNS).'. In Excel, format employee_number and biometric_id as Text so leading zeros survive. An employee number already on file updates that employee; a blank cell keeps the current value.')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('CSV file')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(2048)
                    // The state is in the browser: without this, a path typed
                    // into it would be read, then deleted, like an upload.
                    ->preventFilePathTampering()
                    ->required(),
            ])
            ->action(function (array $data, EmployeeImporter $importer): void {
                $disk = Storage::disk('local');

                try {
                    $result = $importer->import($disk->path($data['file']));
                } finally {
                    $disk->delete($data['file']);
                }

                if (! $result->isSuccessful()) {
                    Notification::make()
                        ->danger()
                        ->title('Nothing was imported')
                        ->body($this->listOf($result->errors))
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Imported: {$result->created} new, {$result->updated} updated, {$result->unchanged} unchanged")
                    ->send();
            });
    }

    /**
     * The first ten problems, escaped: the messages quote the file's own text.
     *
     * @param  list<string>  $errors
     */
    private function listOf(array $errors): HtmlString
    {
        $lines = array_map(e(...), array_slice($errors, 0, 10));

        if (count($errors) > 10) {
            $lines[] = '…and '.(count($errors) - 10).' more.';
        }

        return new HtmlString(implode('<br>', $lines));
    }
}
