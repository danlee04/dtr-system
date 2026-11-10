<?php

namespace App\Filament\Resources\DtrUploads\Schemas;

use App\Enums\AttendanceStatus;
use App\Enums\DayKind;
use App\Enums\DtrSlot;
use App\Filament\Resources\DtrUploads\Actions\FillDayAction;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\ManualTap;
use App\Services\Attendance\ComputedDay;
use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\ResolvedDay;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * One uploaded DTR: whose it is, what the month comes to, then a row for
 * every date laid out like the paper form, blank where the DTR printed
 * nothing, so HR can lay it beside the printout and check it line by line.
 * A blank the computation needs can be filled from the signed paper.
 */
class DtrUploadInfolist
{
    /** Dates that are not work dates, greyed as on a calendar. */
    private const OFF = [
        AttendanceStatus::RestDay,
        AttendanceStatus::Holiday,
        AttendanceStatus::Suspended,
        AttendanceStatus::NotEmployed,
        AttendanceStatus::Upcoming,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.dtr-uploads.dtr')
                    ->key('dtr')
                    ->registerActions([FillDayAction::make()])
                    ->viewData(fn (DtrUpload $record): array => self::viewData($record))
                    ->columnSpanFull(),
            ]);
    }

    /** "Dela Cruz, Juan S. · October 2026", or the name as printed while no employee is chosen. */
    public static function heading(DtrUpload $record): string
    {
        $wholeMonth = $record->period_start->isSameDay($record->period_start->copy()->startOfMonth())
            && $record->period_end->isSameDay($record->period_start->copy()->endOfMonth());

        return ($record->employee->full_name ?? $record->employee_name).' · '.($wholeMonth ? $record->period_start->format('F Y') : $record->period());
    }

    /** "17:13" or "17:13:00", as the database hands it back, to "5:13 PM". */
    public static function clock(?string $time): ?string
    {
        return $time === null ? null : CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
    }

    /** @return array<string, mixed> */
    private static function viewData(DtrUpload $record): array
    {
        $computed = $record->isComputed() ? $record->computed() : null;
        $uploaded = 'Uploaded '.$record->created_at->format('M j, Y').($record->uploadedBy ? " by {$record->uploadedBy->name}" : '');

        return [
            'upload' => $record,
            'printed' => "Printed as {$record->employee_name} · {$uploaded}",
            'schedule' => $computed !== null ? self::schedule($record) : null,
            'cards' => $computed !== null ? self::cards($computed) : [],
            'notComputed' => match (true) {
                $computed !== null => null,
                $record->employee_id === null => 'Choose the employee, and the app computes this DTR against their schedule.',
                default => 'Job order and contract of service staff earn no leave credits, so their DTR is shown as printed and not computed.',
            },
            'rows' => self::rows($record, $computed),
        ];
    }

    /** What late and undertime were measured against: the month's schedule, or the codes of several. */
    private static function schedule(DtrUpload $record): string
    {
        $schedules = collect(app(ScheduleResolver::class)->days($record->employee, $record->period_start->toImmutable(), $record->period_end->toImmutable()))
            ->filter(fn (ResolvedDay $day): bool => $day->kind === DayKind::WorkDay && $day->schedule !== null)
            ->map(fn (ResolvedDay $day) => $day->schedule)
            ->unique('id');

        return match ($schedules->count()) {
            0 => 'No schedule for this month',
            1 => "{$schedules->first()->name} · {$schedules->first()->hours}",
            default => 'Schedules '.$schedules->pluck('code')->implode(', '),
        };
    }

    /** @return list<array{label: string, value: string, detail: string, icon: Heroicon, tone: ?string}> */
    private static function cards(ComputedDtr $dtr): array
    {
        $blanks = array_sum(array_map(fn (ComputedDay $day): int => count($day->missing), $dtr->days));

        $cards = [
            ['label' => 'Late', 'value' => "{$dtr->late} min", 'detail' => self::times($dtr->lateCount), 'icon' => Heroicon::OutlinedClock, 'tone' => null],
            ['label' => 'Undertime', 'value' => "{$dtr->undertime} min", 'detail' => self::times($dtr->undertimeCount), 'icon' => Heroicon::OutlinedArrowRightStartOnRectangle, 'tone' => null],
            ['label' => 'Late + undertime', 'value' => ($dtr->late + $dtr->undertime).' min', 'detail' => "= {$dtr->dayEquivalent} day (CSC)", 'icon' => Heroicon::OutlinedCalculator, 'tone' => null],
            ['label' => 'Absent', 'value' => self::count($dtr->absent, 'day'), 'detail' => 'work days with no tap', 'icon' => Heroicon::OutlinedUserMinus, 'tone' => null],
            ['label' => 'Blanks to fill', 'value' => (string) $blanks, 'detail' => $blanks > 0 ? 'on '.self::count($dtr->daysMissingTaps, 'date') : 'None', 'icon' => Heroicon::OutlinedPencilSquare, 'tone' => $blanks > 0 ? 'is-warning' : null],
        ];

        if ($dtr->daysWithoutSchedule > 0) {
            $cards[] = ['label' => 'Without a schedule', 'value' => self::count($dtr->daysWithoutSchedule, 'day'), 'detail' => 'not judged until one is set', 'icon' => Heroicon::OutlinedCalendarDays, 'tone' => 'is-warning'];
        }

        return $cards;
    }

    /** @return list<array<string, mixed>> */
    private static function rows(DtrUpload $record, ?ComputedDtr $computed): array
    {
        $printed = $record->days->keyBy(fn (DtrDay $day): string => $day->date->toDateString());
        $filled = $record->filledTimes();
        $rows = [];

        foreach (CarbonPeriod::create($record->period_start, $record->period_end) as $date) {
            $key = $date->toDateString();
            $day = $printed->get($key);
            $result = $computed?->days[$key] ?? null;
            $slots = [];
            $taps = [];

            foreach (DtrSlot::cases() as $slot) {
                // A printed time wins; HR's time for the slot is then kept but not shown.
                $tap = $day?->{$slot->value} === null ? $filled->get("{$key} {$slot->value}") : null;
                $taps = $tap !== null ? [...$taps, $tap] : $taps;
                $slots[$slot->value] = [
                    'printed' => self::clock($day?->{$slot->value}),
                    'filled' => self::clock($tap?->time),
                    'fillable' => in_array($slot, $result->fillable ?? [], true),
                ];
            }

            $rows[] = [
                'date' => $key,
                'label' => $date->format('D, M j'),
                'off' => in_array($result?->status, self::OFF, true),
                'absent' => $result?->status === AttendanceStatus::Absent,
                'slots' => $slots,
                'ot' => implode('–', array_filter([self::clock($day?->ot_arrival), self::clock($day?->ot_departure)])) ?: null,
                'late' => $result?->late ?: null,
                'undertime' => $result?->undertime ?: null,
                'status' => $result?->status,
                // Each note with whether it needs a look.
                'notes' => array_values(array_filter([
                    $result?->note !== null ? [$result->note, false] : null,
                    ($result->missing ?? []) !== [] ? ['No '.implode(', ', $result->missing), true] : null,
                    $taps !== [] ? [self::filledNote($taps), false] : null,
                    $day?->remarks !== null ? [$day->remarks, false] : null,
                ])),
            ];
        }

        return $rows;
    }

    /**
     * "HR: forgot to tap", with the note HR left.
     *
     * @param  list<ManualTap>  $taps
     */
    private static function filledNote(array $taps): string
    {
        $reasons = collect($taps)->map(fn (ManualTap $tap): string => Str::lower($tap->reason->getLabel()))->unique()->implode(', ');
        $remarks = collect($taps)->pluck('remarks')->filter()->unique()->implode('; ');

        return "HR: {$reasons}".($remarks !== '' ? " ({$remarks})" : '');
    }

    /** "2 times", or "None". */
    private static function times(int $times): string
    {
        return $times === 0 ? 'None' : self::count($times, 'time');
    }

    private static function count(int|float $number, string $unit): string
    {
        return $number.' '.Str::plural($unit, (float) $number === 1.0 ? 1 : 2);
    }
}
