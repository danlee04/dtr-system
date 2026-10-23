<?php

namespace Tests\Unit\Leave;

use App\Enums\LeaveType;
use App\Services\Leave\LeaveCharger;
use PHPUnit\Framework\TestCase;

class LeaveChargerTest extends TestCase
{
    public function test_vl_draws_vl_then_goes_without_pay(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000', '1.000', '1.000'), $this->balances(['VL' => '2.000']));

        $this->assertSame(['VL' => '2.000'], $charge->draws);
        $this->assertSame(['3.000', '2.000', '1.000'], [$charge->days, $charge->withPay, $charge->withoutPay]);
        $this->assertSame(['0.000', '0.000', '1.000'], array_column($charge->breakdown, 'without_pay'));
    }

    public function test_sl_draws_sl_then_vl_then_goes_without_pay(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Sick, $this->days('1.000', '1.000', '1.000'), $this->balances(['SL' => '1.000', 'VL' => '1.500']));

        $this->assertSame(['SL' => '1.000', 'VL' => '1.500'], $charge->draws);
        $this->assertSame(['0.000', '0.000', '0.500'], array_column($charge->breakdown, 'without_pay'));
    }

    public function test_vl_never_draws_sl(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000'), $this->balances(['SL' => '10.000']));

        $this->assertSame([], $charge->draws);
        $this->assertSame('1.000', $charge->withoutPay);
    }

    public function test_forced_leave_draws_vl(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Forced, $this->days('1.000'), $this->balances(['VL' => '5.000']));

        $this->assertSame(['VL' => '1.000'], $charge->draws);
    }

    public function test_spl_and_wellness_go_without_pay_when_short(): void
    {
        $spl = (new LeaveCharger)->charge(LeaveType::SpecialPrivilege, $this->days('1.000', '1.000'), $this->balances(['SPL' => '1.000']));
        $wellness = (new LeaveCharger)->charge(LeaveType::Wellness, $this->days('1.000'), $this->balances([]));

        $this->assertSame([['SPL' => '1.000'], '1.000'], [$spl->draws, $spl->withoutPay]);
        $this->assertSame([[], '1.000'], [$wellness->draws, $wellness->withoutPay]);
    }

    public function test_untracked_types_draw_nothing_and_are_with_pay(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Maternity, $this->days('1.000', '1.000'), $this->balances([]));

        $this->assertSame([[], '2.000', '0.000'], [$charge->draws, $charge->withPay, $charge->withoutPay]);
    }

    public function test_a_negative_balance_draws_nothing(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000'), $this->balances(['VL' => '-0.500']));

        $this->assertSame([[], '1.000'], [$charge->draws, $charge->withoutPay]);
    }

    public function test_zero_days_stay_in_the_breakdown(): void
    {
        $charge = (new LeaveCharger)->charge(LeaveType::Vacation, $this->days('1.000', '0.000'), $this->balances(['VL' => '5.000']));

        $this->assertSame(['days' => '0.000', 'without_pay' => '0.000'], $charge->breakdown['2026-11-04']);
    }

    public function test_the_summary_reads_the_way_the_form_shows_it(): void
    {
        $charger = new LeaveCharger;

        $this->assertSame('3.000 days: 3.000 with pay (VL 3.000), 0.000 without pay', $charger->charge(LeaveType::Vacation, $this->days('1.000', '1.000', '1.000'), $this->balances(['VL' => '5.000']))->describe());
        $this->assertSame('9.000 days: 7.000 with pay (SL 2.000, VL 5.000), 2.000 without pay', $charger->charge(LeaveType::Sick, $this->days(...array_fill(0, 9, '1.000')), $this->balances(['SL' => '2.000', 'VL' => '5.000']))->describe());
        $this->assertSame('2.000 days: 0.000 with pay, 2.000 without pay', $charger->charge(LeaveType::Vacation, $this->days('1.000', '1.000'), $this->balances([]))->describe());
        $this->assertSame('2.000 days with pay; no balance is deducted', $charger->charge(LeaveType::Paternity, $this->days('1.000', '1.000'), $this->balances([]))->describe());
    }

    /** @return array<string, string> one value per date from Nov 3, 2026 */
    private function days(string ...$values): array
    {
        $days = [];

        foreach (array_values($values) as $offset => $value) {
            $days[date('Y-m-d', strtotime("2026-11-03 +{$offset} days"))] = $value;
        }

        return $days;
    }

    /**
     * @param  array<string, string>  $given
     * @return array<string, string>
     */
    private function balances(array $given): array
    {
        return $given + ['VL' => '0.000', 'SL' => '0.000', 'SPL' => '0.000', 'WL' => '0.000'];
    }
}
