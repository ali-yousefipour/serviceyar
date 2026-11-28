<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

final class StudentDiscountMath
{
    // Rate is thousandths of one percent: 20% = 20000.
    // Bases must be undiscounted by this specific discount, after ordinary tariff rules.
    public static function calculate(int $studentBase, int $driverBase, int $rate, bool $student, bool $driver): array
    {
        if ($studentBase < 0 || $driverBase < 0 || $rate <= 0 || $rate > 100000 || (!$student && !$driver)) {
            throw new \InvalidArgumentException('Invalid discount bases, rate or scope');
        }
        $s = $student ? self::amount($studentBase, $rate) : 0;
        $d = $driver ? self::amount($driverBase, $rate) : 0;
        return ['studentDiscount'=>$s,'driverDiscount'=>$d,'studentNet'=>$studentBase-$s,'driverNet'=>$driverBase-$d,'companyShare'=>($studentBase-$s)-($driverBase-$d)];
    }

    private static function amount(int $base, int $rate): int
    {
        // Split before multiplying to avoid overflow and round half up to the base currency unit.
        return intdiv($base,100000)*$rate + intdiv(($base%100000)*$rate+50000,100000);
    }
}
