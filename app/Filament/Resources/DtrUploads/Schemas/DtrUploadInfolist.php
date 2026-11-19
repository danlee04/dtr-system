<?php

namespace App\Filament\Resources\DtrUploads\Schemas;

use App\Enums\AttendanceStatus;
use App\Enums\DayKind;
use App\Enums\DtrSlot;
use App\Filament\Resources\DtrUploads\Actions\AddDayExceptionAction;
use App\Filament\Resources\DtrUploads\Actions\FillDayAction;
use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\ManualTap;
use App\Services\Attendance\ComputedDay;
use App\Services\Attendance\ComputedDtr;
use App\Services\Attendance\ResolvedDay;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Leave\DtrDeduction;
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
                    ->registerActions([FillDayAction::make(), AddDayExceptionAction::make()])
                    ->viewData(fn (DtrUpload $record): array => self::viewData($record))
                    ->columnSpanFull(),
            ]);
    }

    /** "Dela Cruz, Juan S. · October 2026", or the name as printed while no employee is chosen. */
    public static function heading(DtrUpload $record): string
    {
        return ($record->employee->full_name ?? $record->employee_name).' · '.self::month($record);
    }

    /** "October 2026" for a whole month, else the period as the form prints it. */
    public static function month(DtrUpload $record): string
    {
        $wholeMonth = $record->period_start->isSameDay($record->period_start->copy()->startOfMonth())
            && $record->period_end->isSameDay($record->period_start->copy()->endOfMonth());

        return $wholeMonth ? $record->period_start->format('F Y') : $record->period();
    }

    /** "17:13" or "17:13:00", as the database hands it back, to "5:13 PM". */
    public static function clock(?string $time): ?string
    {
        return $time === null ? null : CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
    }

    /**
     * What filament.dtr-uploads.dtr shows. $mine is the employee's own read
     * of it on My DTR: no who-is-this heading, and the blanks are theirs to
     * bring to HR, not HR's to fill.
     *
     * @return array<string, mixed>
     */
    public static function viewData(DtrUpload $record, bool $mine = false): array
    {
        $computed = $record->isComputed() ? $record->computed() : null;
        $uploaded = 'Uploaded '.$record->created_at->format('M j, Y').($record->uploadedBy ? " by {$record->uploadedBy->name}" : '');
        $rows = self::rows($record, $computed);

        return [
            'upload' => $record,
            'mine' => $mine,
            'printed' => "Printed as {$record->employee_name} · {$uploaded}",
            'schedule' => $computed !== null ? self::schedule($record) : null,
            'cards' => $computed !== null ? self::cards($computed, $mine) : [],
            'notComputed' => match (true) {
                $computed !== null => null,
                $record->employee_id === null => 'Choose the employee, and the app computes this DTR against their schedule.',
                default => 'Job order and contract of service staff earn no leave credits, so their DTR is shown as printed and not computed.',
            },
            'final' => self::final($record, $mine),
            'rows' => $rows,
            // Most months have no O.T.; its empty column would only push the grid past a laptop's screen.
            'hasOvertime' => array_any($rows, fn (array $row): bool => $row['ot'] !== null),
        ];
    }

    /**
     * When HR marked it final, by whom, and what it took off VL; for HR, also
     * whether the figures moved since, so HR knows to reopen it and mark it
     * final again. Null while it is open.
     *
     * @return array{line: string, changed: ?string}|null
     */
    private static function final(DtrUpload $record, bool $mine): ?array
    {
        if (! $record->isFinal()) {
            return null;
        }

        $deduction = app(DtrDeduction::class);
        $deducted = $deduction->deducted($record);
        $due = $deduction->due($record);

        return [
            'line' => 'Final · '.$record->finalized_at->format('M j, Y')
                .($record->finalizedBy ? " by {$record->finalizedBy->name}" : '')
                .' · '.(self::deductions($deduction->deductedByType($record)) ?? 'nothing deducted'),
            'changed' => ! $mine && bccomp($due, $deducted, 3) !== 0
                ? "Changed since Final: deducted {$deducted}, now {$due}. Reopen and mark it final again."
                : null,
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
    private static function cards(ComputedDtr $dtr, bool $mine): array
    {
        $blanks = array_sum(array_map(fn (ComputedDay $day): int => count($day->missing), $dtr->days));

        $cards = [
            ['label' => 'Late', 'value' => self::hours($dtr->late), 'detail' => self::times($dtr->lateCount), 'icon' => Heroicon::OutlinedClock, 'tone' => null],
            ['label' => 'Undertime', 'value' => self::hours($dtr->undertime), 'detail' => self::times($dtr->undertimeCount), 'icon' => Heroicon::OutlinedArrowRightStartOnRectangle, 'tone' => null],
            // The days of leave first, by the CSC table, then the hours they come from (HR, 2026-11-18).
            ['label' => 'Late + undertime · '.self::hours($dtr->late + $dtr->undertime), 'value' => $dtr->dayEquivalent.' '.Str::plural('day', (float) $dtr->dayEquivalent === 1.0 ? 1 : 2), 'detail' => 'equivalent to leave credits', 'icon' => Heroicon::OutlinedCalculator, 'tone' => null],
            ['label' => 'Absent', 'value' => self::count($dtr->absent, 'day'), 'detail' => bccomp($dtr->absentCharge, '0', 3) > 0 ? self::count((float) $dtr->absentCharge, 'day', $dtr->absentCharge).' from SL, then VL' : 'None', 'icon' => Heroicon::OutlinedUserMinus, 'tone' => null],
            ['label' => $mine ? 'Missing taps' : 'Blanks to fill', 'value' => (string) $blanks, 'detail' => $blanks > 0 ? 'on '.self::count($dtr->daysMissingTaps, 'date') : 'None', 'icon' => Heroicon::OutlinedPencilSquare, 'tone' => $blanks > 0 ? 'is-warning' : null],
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
                // HR's time wins: it fills a blank, or corrects a time the device got wrong.
                $tap = $filled->get("{$key} {$slot->value}");
                $taps = $tap !== null ? [...$taps, $tap] : $taps;
                $away = $result->awayTimes[$slot->value] ?? null;
                $slots[$slot->value] = [
                    'printed' => self::clock($day?->{$slot->value}),
                    'filled' => self::clock($tap?->time),
                    'fillable' => in_array($slot, $result->fillable ?? [], true),
                    // On an OB or travel order, a blank shows the schedule's time, tagged OB or TO.
                    'away' => $away === null ? null : [
                        'time' => self::clock($away['time']),
                        'tag' => $away['status'] === AttendanceStatus::TravelOrder ? 'TO' : 'OB',
                        'label' => $away['status']->getLabel(),
                    ],
                ];
            }

            $rows[] = [
                'date' => $key,
                // "Oct 1" holds together by a no-break space, so a narrow column breaks the date only after the weekday.
                'label' => $date->format('D, M')."\u{00A0}".$date->format('j'),
                'off' => in_array($result?->status, self::OFF, true),
                'absent' => $result?->status === AttendanceStatus::Absent,
                'slots' => $slots,
                'ot' => implode('–', array_filter([self::clock($day?->ot_arrival), self::clock($day?->ot_departure)])) ?: null,
                'late' => self::minutes($result?->late),
                'undertime' => self::minutes($result?->undertime),
                'status' => $result?->status,
                // A part of the day with no tap, which an OB, travel order, leave or gate pass could cover.
                'untapped' => $result?->untappedCoverage() !== null,
                // Each note with whether it needs a look.
                'notes' => array_values(array_filter([
                    $result?->note !== null ? [$result->note, false] : null,
                    ($result->missing ?? []) !== [] ? ['No '.implode(', ', $result->missing), true] : null,
                    $taps !== [] ? [self::filledNote($taps), false] : null,
                    // Not a bare number like "-479": IT has not said what it means, and it confused HR (2026-11-18).
                    $day?->remarks !== null && preg_match('/^-?\d+$/', $day->remarks) !== 1 ? [$day->remarks, false] : null,
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

    /** "0 hrs 48 min", "1 hr 13 min", "4 hrs": the month's minutes as HR reads them, here and on the Monthly Summary (HR, 2026-11-18). */
    public static function hours(int $minutes): string
    {
        $hours = intdiv($minutes, 60);

        return $hours.($hours === 1 ? ' hr' : ' hrs').($minutes % 60 > 0 ? ' '.($minutes % 60).' min' : '');
    }

    /**
     * A day's minutes, with the hours beside them from an hour up: "240 (4 hrs)" (HR, 2026-11-18). None is blank.
     * Each number keeps its unit by a no-break space, so a narrow column breaks "(3 hrs 15 min)" only between the two.
     */
    private static function minutes(?int $minutes): ?string
    {
        return match (true) {
            ! $minutes => null,
            $minutes >= 60 => "{$minutes} (".preg_replace('/(\d) /', "\$1\u{00A0}", self::hours($minutes)).')',
            default => (string) $minutes,
        };
    }

    /** "2 times", or "None". */
    private static function times(int $times): string
    {
        return $times === 0 ? 'None' : self::count($times, 'time');
    }

    /** "2 days"; $shown prints the number another way, as "2.000 days". */
    private static function count(int|float $number, string $unit, ?string $shown = null): string
    {
        return ($shown ?? $number).' '.Str::plural($unit, (float) $number === 1.0 ? 1 : 2);
    }

    /**
     * "SL −1.250 · VL −0.100", with a real minus sign, or null when nothing was taken.
     *
     * @param  array<string, string>  $byType  leave type code → positive days
     */
    public static function deductions(array $byType): ?string
    {
        return $byType === [] ? null : collect($byType)->map(fn (string $days, string $type): string => "{$type} \u{2212}{$days}")->implode(' · ');
    }
}
