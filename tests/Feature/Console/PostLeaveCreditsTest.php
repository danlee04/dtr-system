<?php

namespace Tests\Feature\Console;

use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostLeaveCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Employee::factory()->create();
    }

    public function test_the_command_posts_what_is_due(): void
    {
        config(['leave.start_date' => '2026-11-01']);
        $this->travelTo('2026-12-01 08:00');

        $this->artisan('leave:post')->expectsOutputToContain('Posted 2 rows.')->assertSuccessful();
    }

    public function test_the_command_refuses_without_a_start_date(): void
    {
        config(['leave.start_date' => null]);

        $this->artisan('leave:post')->expectsOutputToContain('Set LEAVE_START_DATE')->assertFailed();
        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_the_command_refuses_a_start_date_that_is_not_the_first(): void
    {
        config(['leave.start_date' => '2026-11-15']);
        $this->travelTo('2026-12-01 08:00');

        $this->artisan('leave:post')->expectsOutputToContain('1st of a month')->assertFailed();
        $this->assertSame(0, LeaveLedgerEntry::count());
    }
}
