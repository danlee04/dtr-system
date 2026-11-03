<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;
use Exception;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads the DTRs the IT Unit prints to PDF: CS Form 48, three copies side by
 * side on a page, one employee per page. It reads the leftmost copy only; the
 * other two repeat it.
 *
 * Text is found by where it sits, not by the order it was written in: the
 * name is the line just above "(Employee Name)", and a time belongs to the
 * Arrival or Departure column it lines up with. A blank cell leaves no text
 * behind, so position is the only way to tell a missing lunch in from a
 * missing time out.
 *
 * The form carries no employee number or biometric ID, so the name is all
 * there is to tell whose DTR it is. Matching it to an employee, and computing
 * the day, come after.
 */
class DtrPdfReader
{
    /** The form's time columns, left to right. */
    public const COLUMNS = ['am_arrival', 'am_departure', 'pm_arrival', 'pm_departure', 'ot_arrival', 'ot_departure'];

    /** "07:51 am", as the IT Unit's software prints a time. */
    private const TIME = '/^(\d{1,2}):(\d{2})\s*([ap])\.?m\.?$/i';

    /**
     * @return list<PrintedDtr> one per page
     *
     * @throws UnreadableDtr
     */
    public function read(string $path): array
    {
        // Only the library runs in here, and any way it fails means the same to HR.
        try {
            $pages = array_map(
                fn ($page): array => $page->getDataTm(),
                (new Parser)->parseFile($path)->getPages(),
            );
        } catch (Throwable) {
            throw new UnreadableDtr('It is not a PDF the app can open.');
        }

        if ($pages === []) {
            throw new UnreadableDtr('It has no pages.');
        }

        $dtrs = [];

        foreach ($pages as $index => $texts) {
            try {
                $dtrs[] = $this->page($this->items($texts));
            } catch (UnreadableDtr $e) {
                throw count($pages) === 1 ? $e : new UnreadableDtr('Page '.($index + 1).': '.$e->getMessage());
            }
        }

        return $dtrs;
    }

    /**
     * @param  list<array{0: list<string>, 1: string}>  $texts  each text shown on the page, with its text matrix
     * @return list<array{x: float, y: float, text: string}>
     */
    private function items(array $texts): array
    {
        $items = [];

        foreach ($texts as [$matrix, $text]) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));

            if ($text !== '') {
                $items[] = ['x' => (float) $matrix[4], 'y' => (float) $matrix[5], 'text' => $text];
            }
        }

        return $items;
    }

    /** @param  list<array{x: float, y: float, text: string}>  $items */
    private function page(array $items): PrintedDtr
    {
        $nameLabel = $this->leftmost($items, '(Employee Name)')
            ?? throw new UnreadableDtr('It has no "(Employee Name)" line, so it is not a DTR printed by the IT Unit.');
        $name = $this->nearest(
            $items,
            fn (array $item): bool => $item['y'] > $nameLabel['y'] && $item['y'] - $nameLabel['y'] < 20 && abs($item['x'] - $nameLabel['x']) < 60,
            fn (array $item): float => $item['y'] - $nameLabel['y'],
        ) ?? throw new UnreadableDtr('The name above "(Employee Name)" is blank.');

        $monthLabel = $this->leftmost($items, 'For the month of')
            ?? throw new UnreadableDtr('It has no "For the month of" line.');
        $month = $this->nearest(
            $items,
            fn (array $item): bool => $item['x'] > $monthLabel['x'] && abs($item['y'] - $monthLabel['y']) < 2,
            fn (array $item): float => $item['x'] - $monthLabel['x'],
        ) ?? throw new UnreadableDtr('Its "For the month of" line is blank.');
        [$start, $end] = $this->period($month['text']);

        // The second copy's "Day" heading is where the first copy ends.
        $dayHeadings = $this->sortedByX(array_filter($items, fn (array $item): bool => $item['text'] === 'Day'));
        $dayHeading = $dayHeadings[0] ?? throw new UnreadableDtr('Its table has no "Day" column.');
        $copyEnd = $dayHeadings[1]['x'] ?? INF;

        $headings = $this->sortedByX(array_filter(
            $items,
            fn (array $item): bool => in_array($item['text'], ['Arrival', 'Departure'], true) && $item['x'] < $copyEnd,
        ));

        if (count($headings) !== count(self::COLUMNS)) {
            throw new UnreadableDtr('Its table does not have the six Arrival and Departure columns.');
        }

        $columnX = array_combine(self::COLUMNS, array_column($headings, 'x'));
        $days = [];

        foreach ($items as $dayNumber) {
            $day = ctype_digit($dayNumber['text']) ? (int) $dayNumber['text'] : 0;

            // The form prints rows 1 to 31 whatever the month; only the period's days are read.
            if ($day < $start->day || $day > $end->day || abs($dayNumber['x'] - $dayHeading['x']) > 8 || $dayNumber['y'] >= $dayHeading['y']) {
                continue;
            }

            $row = array_filter(
                $items,
                fn (array $item): bool => $item['x'] > $dayHeading['x'] + 8 && $item['x'] < $copyEnd && abs($item['y'] - $dayNumber['y']) < 4,
            );
            $entries = $this->entries($day, $this->sortedByX($row), $columnX);

            if ($entries !== []) {
                $days[$start->setDay($day)->toDateString()] = $entries;
            }
        }

        ksort($days);

        return new PrintedDtr($name['text'], $start, $end, $days);
    }

    /**
     * A row's times by the column they print under; any other text is the
     * remarks.
     *
     * @param  list<array{x: float, y: float, text: string}>  $cells
     * @param  array<string, float>  $columnX
     * @return array<string, string>
     */
    private function entries(int $day, array $cells, array $columnX): array
    {
        $entries = [];

        foreach ($cells as $cell) {
            if (preg_match(self::TIME, $cell['text'], $time)) {
                $distances = array_map(fn (float $x): float => abs($x - $cell['x']), $columnX);
                $column = array_search(min($distances), $distances, true);

                if (isset($entries[$column])) {
                    throw new UnreadableDtr("Day {$day} has two times under one column.");
                }

                $entries[$column] = $this->time($day, $cell['text'], $time);
            } elseif ($cell['text'] !== '-') {
                // shortcut: the IT Unit's software prints an invisible "-" in some rows; its meaning is unknown, so it is dropped until IT explains it.
                $entries['remarks'] = trim(($entries['remarks'] ?? '').' '.$cell['text']);
            }
        }

        return $entries;
    }

    /** @param  array<int, string>  $time  the TIME match: hour, minute, a or p */
    private function time(int $day, string $text, array $time): string
    {
        [, $hour, $minute, $meridiem] = $time;

        if ((int) $hour < 1 || (int) $hour > 12 || (int) $minute > 59) {
            throw new UnreadableDtr("Day {$day} has a time the app cannot read: \"{$text}\".");
        }

        return sprintf('%02d:%02d', (int) $hour % 12 + (strtolower($meridiem) === 'p' ? 12 : 0), (int) $minute);
    }

    /**
     * "OCTOBER 1 - 31, 2026": the first and last dates the DTR covers.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(string $text): array
    {
        $unreadable = new UnreadableDtr("Its month reads \"{$text}\", not a month such as \"OCTOBER 1 - 31, 2026\".");

        if (! preg_match('/^([A-Za-z]+)\s+(\d{1,2})\s*-\s*(\d{1,2}),\s*(\d{4})$/', $text, $match)) {
            throw $unreadable;
        }

        try {
            $month = CarbonImmutable::createFromFormat('!F Y', ucfirst(strtolower($match[1])).' '.$match[4]);
        } catch (Exception) {
            throw $unreadable;
        }

        [$first, $last] = [(int) $match[2], (int) $match[3]];

        if ($first < 1 || $first > $last || $last > $month->daysInMonth) {
            throw $unreadable;
        }

        return [$month->setDay($first), $month->setDay($last)];
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $items
     * @return array{x: float, y: float, text: string}|null
     */
    private function leftmost(array $items, string $text): ?array
    {
        return $this->sortedByX(array_filter($items, fn (array $item): bool => $item['text'] === $text))[0] ?? null;
    }

    /**
     * The item that passes the filter with the smallest distance.
     *
     * @param  list<array{x: float, y: float, text: string}>  $items
     * @param  callable(array{x: float, y: float, text: string}): bool  $filter
     * @param  callable(array{x: float, y: float, text: string}): float  $distance
     * @return array{x: float, y: float, text: string}|null
     */
    private function nearest(array $items, callable $filter, callable $distance): ?array
    {
        $candidates = array_values(array_filter($items, $filter));
        usort($candidates, fn (array $a, array $b): int => $distance($a) <=> $distance($b));

        return $candidates[0] ?? null;
    }

    /**
     * @param  array<array{x: float, y: float, text: string}>  $items
     * @return list<array{x: float, y: float, text: string}>
     */
    private function sortedByX(array $items): array
    {
        usort($items, fn (array $a, array $b): int => $a['x'] <=> $b['x']);

        return $items;
    }
}
