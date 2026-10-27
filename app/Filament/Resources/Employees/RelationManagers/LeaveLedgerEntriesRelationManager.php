<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Enums\LeaveType;
use App\Filament\Resources\LeaveApplications\Actions\FileLeaveAction;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePosting;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee's leave: every credit and deduction, newest first. The
 * balances are the cards above the page's tabs. Rows are never edited; a
 * correction is an adjustment, through LeaveLedger.
 */
class LeaveLedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaveLedgerEntries';

    protected static ?string $title = 'Leave';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    /** It sits on the employee's view page, which Filament would make read-only. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('posted_on')
                    ->label('Date')
                    ->date('M j, Y'),
                TextColumn::make('leave_type')
                    ->label('Type')
                    ->formatStateUsing(fn (LeaveType $state): string => $state->value),
                TextColumn::make('kind')
                    ->label('Entry'),
                TextColumn::make('days')
                    ->label('Days')
                    ->alignEnd()
                    ->formatStateUsing(fn (string $state): string => bccomp($state, '0', 3) > 0 ? "+{$state}" : $state),
                TextColumn::make('remarks')
                    ->label('Remarks')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('createdBy.name')
                    ->label('By')
                    ->placeholder('System'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('posted_on')->orderByDesc('id'))
            ->filters([
                SelectFilter::make('leave_type')
                    ->label('Type')
                    ->options(self::trackedOptions()),
            ])
            ->headerActions([
                FileLeaveAction::make()
                    ->employee(fn (): Employee => $this->employee()),
                // Gray, so File leave is the one that stands out.
                Action::make('adjust')
                    ->label('Add adjustment')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('gray')
                    ->authorize('create', LeaveLedgerEntry::class)
                    ->modalHeading('Add Adjustment')
                    ->modalDescription('Dated today. Use a minus sign to deduct, e.g. -0.500.')
                    // Type and days on one row, the reason under them.
                    ->schema(fn (): array => [Grid::make(2)->schema([
                        Select::make('leave_type')
                            ->label('Type')
                            ->options(self::trackedOptions())
                            ->required(),
                        TextInput::make('days')
                            ->label('Days')
                            ->required()
                            ->regex(LeaveLedger::SIGNED_DAYS)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    // The regex rule has already failed it, and bcmath throws on anything else.
                                    if (preg_match(LeaveLedger::SIGNED_DAYS, (string) $value) !== 1) {
                                        return;
                                    }

                                    $type = LeaveType::tryFrom((string) $get('leave_type'));

                                    if (bccomp((string) $value, '0', 3) === 0) {
                                        $fail('Enter a number of days other than zero.');
                                    } elseif ($type !== null) {
                                        app(LeavePosting::class)->catchUp();
                                        $result = bcadd(app(LeaveLedger::class)->balance($this->employee(), $type), (string) $value, 3);

                                        if (bccomp($result, '0', 3) < 0) {
                                            $fail("That would leave {$type->value} at {$result}.");
                                        }
                                    }
                                },
                            ]),
                        Textarea::make('remarks')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])])
                    ->action(function (array $data, LeaveLedger $ledger): void {
                        $ledger->adjust($this->employee(), LeaveType::from($data['leave_type']), (string) $data['days'], $data['remarks']);

                        Notification::make()->success()->title('Adjustment added')->send();
                    }),
            ]);
    }

    /** "VL 10.000 · SL 0.000 · SPL 0.000 · Wellness 0.000" */

    /** @return array<string, string> code → label */
    private static function trackedOptions(): array
    {
        return collect(LeaveType::tracked())
            ->mapWithKeys(fn (LeaveType $type): array => [$type->value => $type->getLabel()])
            ->all();
    }

    private function employee(): Employee
    {
        /** @var Employee */
        return $this->getOwnerRecord();
    }
}
