<?php

namespace Tests\Feature\Attendance;

use App\Models\DtrDay;
use App\Models\DtrUpload;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\DtrImporter;
use App\Services\Attendance\PrintedDtr;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DtrImporterTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, array<string, string>>  $days */
    private function printed(string $name, string $start = '2026-10-01', string $end = '2026-10-31', array $days = []): PrintedDtr
    {
        return new PrintedDtr($name, CarbonImmutable::parse($start), CarbonImmutable::parse($end), $days);
    }

    private function dave(): Employee
    {
        return Employee::factory()->create(['last_name' => 'LUCAR', 'first_name' => 'DAVE', 'middle_name' => 'AVILA']);
    }

    public function test_links_the_dtr_to_the_one_employee_with_that_name_and_keeps_its_days(): void
    {
        $user = User::factory()->create();
        $dave = $this->dave();

        $upload = (new DtrImporter)->import($this->printed('LUCAR, DAVE A.', days: [
            '2026-10-01' => ['am_arrival' => '07:51', 'am_departure' => '12:25', 'pm_arrival' => '12:44', 'pm_departure' => '17:13'],
            '2026-10-26' => ['remarks' => 'OB'],
        ]), 'LUCAR, DAVE A..pdf', $user);

        $this->assertSame($dave->id, $upload->employee_id);
        $this->assertSame(['LUCAR, DAVE A.', '2026-10-01', '2026-10-31', 'LUCAR, DAVE A..pdf', $user->id], [
            $upload->employee_name,
            $upload->period_start->toDateString(),
            $upload->period_end->toDateString(),
            $upload->file_name,
            $upload->uploaded_by_user_id,
        ]);
        // MySQL hands a time back with seconds and SQLite as written, so only hours and minutes are compared.
        $time = fn (?string $time): ?string => $time === null ? null : substr($time, 0, 5);
        $this->assertSame([
            ['2026-10-01', '07:51', '12:25', '12:44', '17:13', null],
            ['2026-10-26', null, null, null, null, 'OB'],
        ], $upload->days()->orderBy('date')->get()->map(fn (DtrDay $day): array => [
            $day->date->toDateString(), $time($day->am_arrival), $time($day->am_departure), $time($day->pm_arrival), $time($day->pm_departure), $day->remarks,
        ])->all());
    }

    /** @return array<string, array{string}> */
    public static function spellings(): array
    {
        return [
            'as HR spells it' => ['Peña, Juan S. Jr.'],
            'in capitals' => ['PEÑA, JUAN S. JR.'],
            'without the ñ' => ['PENA, JUAN S. JR.'],
            'with the suffix after the surname' => ['PEÑA JR., JUAN S.'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_matches_a_name_whatever_its_case_accents_or_suffix_place(string $printed): void
    {
        $juan = Employee::factory()->create(['last_name' => 'Peña', 'first_name' => 'Juan', 'middle_name' => 'Santos', 'suffix' => 'Jr.']);

        $this->assertSame($juan->id, (new DtrImporter)->import($this->printed($printed), 'dtr.pdf', null)->employee_id);
    }

    public function test_leaves_a_name_that_matches_nobody_for_hr_to_choose(): void
    {
        Employee::factory()->create(['last_name' => 'LUCAR', 'first_name' => 'MAY-EN', 'middle_name' => 'DEL CASTILLO']);

        $upload = (new DtrImporter)->import($this->printed('LUCAR, DAVE A.', days: ['2026-10-01' => ['am_arrival' => '07:51']]), 'dtr.pdf', null);

        $this->assertNull($upload->employee_id);
        $this->assertSame(1, $upload->days()->count());
    }

    public function test_leaves_a_name_two_employees_share_for_hr_to_choose(): void
    {
        $this->dave();
        Employee::factory()->create(['last_name' => 'LUCAR', 'first_name' => 'DAVE', 'middle_name' => 'ANTONIO']);

        $this->assertNull((new DtrImporter)->import($this->printed('LUCAR, DAVE A.'), 'dtr.pdf', null)->employee_id);
    }

    public function test_does_not_match_a_removed_employee(): void
    {
        $this->dave()->delete();

        $this->assertNull((new DtrImporter)->import($this->printed('LUCAR, DAVE A.'), 'dtr.pdf', null)->employee_id);
    }

    public function test_a_newer_upload_replaces_the_older_one_for_the_same_dates(): void
    {
        $dave = $this->dave();
        $older = DtrUpload::factory()->for($dave)->has(DtrDay::factory(), 'days')->create();

        $newer = (new DtrImporter)->import($this->printed('LUCAR, DAVE A.'), 'dtr.pdf', null);

        $this->assertModelMissing($older);
        $this->assertSame([$newer->id], DtrUpload::query()->pluck('id')->all());
        $this->assertSame(0, DtrDay::query()->where('dtr_upload_id', $older->id)->count());
    }

    public function test_keeps_uploads_for_other_months_and_other_employees(): void
    {
        $dave = $this->dave();
        $september = DtrUpload::factory()->for($dave)->create(['period_start' => '2026-09-01', 'period_end' => '2026-09-30']);
        $someoneElse = DtrUpload::factory()->create();

        (new DtrImporter)->import($this->printed('LUCAR, DAVE A.'), 'dtr.pdf', null);

        $this->assertModelExists($september);
        $this->assertModelExists($someoneElse);
    }

    public function test_choosing_the_employee_links_the_dtr_and_replaces_the_older_one(): void
    {
        $dave = $this->dave();
        $older = DtrUpload::factory()->for($dave)->create();
        $unmatched = DtrUpload::factory()->unmatched()->create(['employee_name' => 'LUCAR, DAVID A.']);

        (new DtrImporter)->assign($unmatched, $dave);

        $this->assertSame($dave->id, $unmatched->fresh()->employee_id);
        $this->assertModelMissing($older);
    }

    public function test_the_upload_and_the_one_it_replaced_are_in_the_activity_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $older = DtrUpload::factory()->for($this->dave())->create();

        $newer = (new DtrImporter)->import($this->printed('LUCAR, DAVE A.'), 'dtr.pdf', $user);

        $logged = ['subject_type' => $newer->getMorphClass(), 'causer_id' => $user->id];
        $this->assertDatabaseHas(Activity::class, [...$logged, 'subject_id' => $newer->id, 'event' => 'created']);
        $this->assertDatabaseHas(Activity::class, [...$logged, 'subject_id' => $older->id, 'event' => 'deleted']);
    }
}
