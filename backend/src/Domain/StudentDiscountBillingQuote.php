<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

/**
 * Discount quote adapter for issued student contracts. All inputs and results
 * are integer rials. ContractBilling remains the sole source of Jalali billing
 * day weights; discount percentages apply to the undiscounted student/driver
 * basis independently.
 */
final class StudentDiscountBillingQuote
{
    public static function calculate(
        string $academicYear,
        string $contractStart,
        string $contractEnd,
        int $studentMonthlyRials,
        int $driverMonthlyRials,
        array $discounts
    ): array {
        if ($studentMonthlyRials < 0 || $driverMonthlyRials < 0 ||
            !OperationsService::validDate($contractStart) ||
            !OperationsService::validDate($contractEnd) || $contractEnd < $contractStart) {
            throw new \InvalidArgumentException('Invalid contract period or base rate');
        }
        $exclusiveEnd = (new \DateTimeImmutable($contractEnd, new \DateTimeZone('UTC')))
            ->modify('+1 day')->format('Y-m-d');
        $year = ContractBilling::year($academicYear);

        // Cumulative differences preserve the exact original issued total,
        // including integer rounding, when a month contains several segments.
        $baseTo = static function (string $exclusive, int $monthly) use ($year, $contractStart): int {
            if ($exclusive <= $contractStart) return 0;
            $inclusive = (new \DateTimeImmutable($exclusive, new \DateTimeZone('UTC')))
                ->modify('-1 day')->format('Y-m-d');
            return (int)ContractBilling::quote($year, $contractStart, $inclusive, $monthly)['amount'];
        };
        return StudentDiscountPeriods::calculatePeriod(
            $contractStart,
            $exclusiveEnd,
            $discounts,
            static function(string $from, string $to) use ($baseTo, $studentMonthlyRials, $driverMonthlyRials): array {
                return [
                    'studentBase' => $baseTo($to, $studentMonthlyRials) - $baseTo($from, $studentMonthlyRials),
                    'driverBase' => $baseTo($to, $driverMonthlyRials) - $baseTo($from, $driverMonthlyRials),
                ];
            }
        );
    }
}
