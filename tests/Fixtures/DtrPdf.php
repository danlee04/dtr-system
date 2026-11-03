<?php

namespace Tests\Fixtures;

/**
 * Writes a DTR laid out like the ones the IT Unit prints to PDF: CS Form 48,
 * three copies side by side on an A4 landscape page. The coordinates are the
 * IT Unit's sample's own, so the reader meets the layout it will meet in real
 * files, without a real employee's times in the repository.
 */
final class DtrPdf
{
    /** How far right each copy sits from the one before it. */
    private const COPY_OFFSET = 270.7;

    /** Where each column's times print, measured on the sample. The O.T. ones are spaced like the rest. */
    private const ENTRY_X = [
        'am_arrival' => 27.4,
        'am_departure' => 61.1,
        'pm_arrival' => 94.8,
        'pm_departure' => 128.5,
        'ot_arrival' => 162.2,
        'ot_departure' => 195.9,
        'remarks' => 227.2,
    ];

    /** @var list<array{float, float, string}> one copy's text, as x, y and the text */
    private array $items;

    public function __construct(string $name = 'DELA CRUZ, JUAN S.', string $month = 'OCTOBER 1 - 31, 2026')
    {
        $this->items = [
            [8.9, 580.6, 'Civil Service Form No. 48'],
            [96.5, 571.0, 'DAILY TIME RECORD'],
            [110.0, 543.0, $name],
            [113.6, 532.6, '(Employee Name)'],
            [8.9, 523.0, 'For the month of'],
            [126.2, 523.0, $month],
            [8.9, 511.7, 'Official hours of arrival and departure'],
            [10.1, 500.7, 'Day'],
            [49.2, 499.5, 'A.M.'],
            [117.4, 499.5, 'P.M.'],
            [185.8, 499.5, 'O.T.'],
            [229.7, 500.7, 'Remarks'],
            [31.9, 486.5, 'Arrival'],
            [60.1, 486.5, 'Departure'],
            [99.3, 486.5, 'Arrival'],
            [127.6, 486.5, 'Departure'],
            [166.9, 486.5, 'Arrival'],
            [195.1, 486.5, 'Departure'],
            // The sample carries an invisible "-" beside the Remarks heading.
            [242.2, 486.9, '-'],
            [10.4, 104.7, 'I CERTIFY on my honor that the above is true and correct record of'],
        ];

        // The form prints all 31 rows, whatever the month.
        for ($day = 1; $day <= 31; $day++) {
            $this->items[] = [$day < 10 ? 14.2 : 12.2, self::rowY($day), (string) $day];
        }
    }

    /**
     * What a day's row prints, by column: ['am_arrival' => '07:51 am', 'remarks' => 'OB'].
     *
     * @param  array<string, string>  $entries
     */
    public function day(int $day, array $entries): self
    {
        foreach ($entries as $column => $text) {
            $this->items[] = [self::ENTRY_X[$column], self::rowY($day), $text];
        }

        return $this;
    }

    public function contents(): string
    {
        return self::pages($this);
    }

    /** Several DTRs in one file, a page each. */
    public static function pages(self ...$dtrs): string
    {
        return self::pdf(array_map(fn (self $dtr): string => $dtr->stream(), $dtrs));
    }

    /** A PDF that is not a DTR: one line of text per item. */
    public static function document(string ...$lines): string
    {
        $stream = [];

        foreach (array_values($lines) as $i => $line) {
            $stream[] = self::text(50, 550 - 14 * $i, $line);
        }

        return self::pdf([implode("\n", $stream)]);
    }

    private static function rowY(int $day): float
    {
        return 474.9 - 12 * ($day - 1);
    }

    private function stream(): string
    {
        $operators = [];

        foreach ([0, 1, 2] as $copy) {
            foreach ($this->items as [$x, $y, $text]) {
                $operators[] = self::text($x + $copy * self::COPY_OFFSET, $y, $text);
            }
        }

        return implode("\n", $operators);
    }

    private static function text(float $x, float $y, string $text): string
    {
        return sprintf('BT /F1 7 Tf 1 0 0 1 %.1F %.1F Tm (%s) Tj ET', $x, $y, addcslashes($text, '\\()'));
    }

    /**
     * The smallest PDF a reader accepts: a catalog, the pages, one standard
     * font, and an uncompressed content stream per page.
     *
     * @param  list<string>  $streams
     */
    private static function pdf(array $streams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        $number = 4;

        foreach ($streams as $stream) {
            $objects[$number] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$number + 1] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents {$number} 0 R >>";
            $kids[] = ($number + 1).' 0 R';
            $number += 2;
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
