<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveCoverage;
use App\Enums\LeaveDetail;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Services\Leave\LeaveApplications;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The fields of CS Form No. 6 that the system cannot work out itself. The
 * preview line under the dates shows what the leave will cost before it is
 * filed, so HR can change the type or the dates first.
 */
class LeaveApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->options(fn (): array => self::employeeOptions())
                    ->searchable()
                    ->required()
                    ->default(fn (): ?int => request()->integer('employee') ?: null)
                    ->live(),
                ...self::fields(fn (Get $get): ?Employee => filled($get('employee_id')) ? Employee::find($get('employee_id')) : null),
            ]);
    }

    /**
     * Everything but the employee, so an employee's own form can use the same
     * fields with the employee fixed.
     *
     * @param  Closure(Get): ?Employee  $employee
     * @return array<int, Component>
     */
    public static function fields(Closure $employee): array
    {
        return [
            Select::make('leave_type')
                ->label('Leave type')
                ->options(LeaveType::class)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('detail', null)),
            DatePicker::make('date_from')
                ->label('From')
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => blank($get('date_to')) ? $set('date_to', $state) : null),
            DatePicker::make('date_to')
                ->label('To')
                ->required()
                ->afterOrEqual('date_from')
                ->live(),
            Select::make('coverage')
                ->label('Part of the day')
                ->options(LeaveCoverage::class)
                ->default(LeaveCoverage::WholeDay->value)
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => self::oneDate($get))
                ->live(),
            Text::make(fn (Get $get): string => self::preview($get, $employee($get)))
                ->columnSpanFull(),
            Select::make('detail')
                ->label('Details')
                ->options(fn (Get $get): array => collect(LeaveDetail::forType(self::type($get) ?? LeaveType::Maternity))
                    ->mapWithKeys(fn (LeaveDetail $detail): array => [$detail->value => $detail->getLabel()])
                    ->all())
                ->visible(fn (Get $get): bool => self::type($get) !== null && LeaveDetail::forType(self::type($get)) !== [])
                ->live(),
            TextInput::make('detail_text')
                ->label(fn (Get $get): string => $get('detail') === LeaveDetail::Abroad->value ? 'Country' : 'Illness')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => self::needsText($get))
                ->required(fn (Get $get): bool => self::needsText($get)),
            Toggle::make('commutation_requested')
                ->label('Commutation requested'),
            TextInput::make('division_head')
                ->label('Division Head (signs 7.B)')
                ->maxLength(255),
        ];
    }

    /** @return array<int, string> "Dela Cruz, Juan S. — 2020-0001", for everyone who earns credits */
    private static function employeeOptions(): array
    {
        $earning = array_filter(EmploymentStatus::cases(), fn (EmploymentStatus $status): bool => $status->earnsLeaveCredits());

        return Employee::query()
            ->where('is_active', true)
            ->whereIn('employment_status', $earning)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => "{$employee->full_name} — {$employee->employee_number}"])
            ->all();
    }

    private static function preview(Get $get, ?Employee $employee): string
    {
        $type = self::type($get);

        if ($employee === null || $type === null || blank($get('date_from')) || blank($get('date_to'))) {
            return 'Choose the employee, the leave type and the dates to see the days it will take.';
        }

        // A hidden coverage field is not saved, so a range is always whole days.
        $state = $get('coverage');
        $coverage = self::oneDate($get)
            ? ($state instanceof LeaveCoverage ? $state : LeaveCoverage::tryFrom((string) $state)) ?? LeaveCoverage::WholeDay
            : LeaveCoverage::WholeDay;

        try {
            return app(LeaveApplications::class)->preview(
                $employee, $type, CarbonImmutable::parse($get('date_from')), CarbonImmutable::parse($get('date_to')), $coverage,
            )->describe();
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first();
        } catch (Throwable) {
            return 'Enter valid dates.';
        }
    }

    private static function type(Get $get): ?LeaveType
    {
        $state = $get('leave_type');

        return $state instanceof LeaveType ? $state : LeaveType::tryFrom((string) $state);
    }

    private static function oneDate(Get $get): bool
    {
        return filled($get('date_from')) && $get('date_from') === $get('date_to');
    }

    private static function needsText(Get $get): bool
    {
        return LeaveDetail::tryFrom((string) $get('detail'))?->needsText() === true
            || self::type($get) === LeaveType::SpecialLeaveBenefitsForWomen;
    }
}
