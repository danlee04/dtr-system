<?php

namespace App\Filament\Resources\DtrUploads\Actions;

use App\Models\DtrUpload;
use App\Services\Attendance\DtrImporter;
use App\Services\Attendance\DtrPdfReader;
use App\Services\Attendance\UnreadableDtr;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Uploads the IT Unit's DTR PDFs and saves each for the employee it names.
 * The files land on the private local disk and are deleted once read. A file
 * that cannot be read is named in the result, and the rest are saved anyway:
 * each file is its own DTR, so one bad file says nothing about the others.
 */
class UploadDtrsAction extends Action
{
    /** A file takes about a second and a half to read, so a full batch stays well inside one request. */
    private const MAX_FILES = 30;

    public static function getDefaultName(): ?string
    {
        return 'uploadDtrs';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Upload DTRs')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize('create', DtrUpload::class)
            ->modalHeading('Upload DTRs')
            ->modalDescription('The PDFs the IT Unit prints, up to '.self::MAX_FILES.' files at a time. Each DTR goes to the employee whose name it prints, and replaces an older upload of theirs for the same dates.')
            ->modalSubmitActionLabel('Upload')
            ->schema([
                FileUpload::make('files')
                    ->label('DTR files (PDF)')
                    ->multiple()
                    ->maxFiles(self::MAX_FILES)
                    ->disk('local')
                    ->directory('dtr-uploads')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(5120)
                    ->storeFileNamesIn('file_names')
                    // The state is in the browser: without this, a path typed
                    // into it would be read, then deleted, like an upload.
                    ->preventFilePathTampering()
                    ->required(),
            ])
            ->action(function (array $data, DtrPdfReader $reader, DtrImporter $importer): void {
                $disk = Storage::disk('local');
                $read = 0;
                $unmatched = 0;
                $errors = [];

                try {
                    foreach ($data['files'] as $path) {
                        $fileName = $data['file_names'][$path] ?? basename($path);

                        try {
                            foreach ($reader->read($disk->path($path)) as $dtr) {
                                $read++;

                                if ($importer->import($dtr, $fileName, auth()->user())->employee_id === null) {
                                    $unmatched++;
                                }
                            }
                        } catch (UnreadableDtr $e) {
                            $errors[] = "{$fileName}: {$e->getMessage()}";
                        }
                    }
                } finally {
                    $disk->delete($data['files']);
                }

                $this->report($read, $unmatched, $errors);
            });
    }

    /** @param  list<string>  $errors */
    private function report(int $read, int $unmatched, array $errors): void
    {
        $lines = [];

        if ($unmatched > 0) {
            $lines[] = "{$unmatched} ".($unmatched === 1 ? 'needs' : 'need').' an employee: no one on the list, or more than one, has the name printed. Choose one on the list.';
        }

        if ($errors !== []) {
            $lines[] = 'Not read:';
            array_push($lines, ...$errors);
        }

        $notification = Notification::make()
            ->status(match (true) {
                $read === 0 => 'danger',
                $errors !== [] || $unmatched > 0 => 'warning',
                default => 'success',
            })
            // Not Str::plural(): it keeps the capitals and writes "DTRS".
            ->title($read === 0 ? 'Nothing was read' : "Read {$read} ".($read === 1 ? 'DTR' : 'DTRs'))
            ->body($lines === [] ? null : $this->listOf($lines));

        // A list of files to fix stays until HR closes it.
        if ($errors !== []) {
            $notification->persistent();
        }

        $notification->send();
    }

    /**
     * The first ten lines, escaped: they quote file names and the files' own text.
     *
     * @param  list<string>  $lines
     */
    private function listOf(array $lines): HtmlString
    {
        $shown = array_map(e(...), array_slice($lines, 0, 10));

        if (count($lines) > 10) {
            $shown[] = '…and '.(count($lines) - 10).' more.';
        }

        return new HtmlString(implode('<br>', $shown));
    }
}
