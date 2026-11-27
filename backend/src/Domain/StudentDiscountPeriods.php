<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

final class StudentDiscountPeriods
{
    private static function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid date');
        }
        return $date;
    }

    /**
     * Split an existing tariff/assignment period at discount boundaries.
     * All ends are exclusive. Supply existing financial engine bases per
     * resulting segment; this class does not invent a billing-day policy.
     */
    public static function split(string $from, string $to, array $discounts): array
    {
        self::date($from);
        self::date($to);
        if ($from >= $to) {
            throw new \InvalidArgumentException('Invalid period');
        }
        $points = [$from, $to];
        foreach ($discounts as $discount) {
            $start = $discount['effective_from'];
            $end = $discount['effective_to'] ?? null;
            self::date($start);
            if ($end !== null) {
                self::date($end);
                if ($end <= $start) {
                    throw new \InvalidArgumentException('Invalid discount period');
                }
            }
            StudentDiscountMath::calculate(0, 0, (int)$discount['rate_milli_percent'],
                (bool)$discount['apply_to_student'], (bool)$discount['apply_to_driver']);
            if ($start > $from && $start < $to) $points[] = $start;
            if ($end !== null && $end > $from && $end < $to) $points[] = $end;
        }
        $points = array_values(array_unique($points));
        sort($points, SORT_STRING);
        $result = [];
        for ($i = 0; $i < count($points)-1; $i++) {
            $active = array_values(array_filter($discounts, static fn(array $d): bool =>
                $d['effective_from'] <= $points[$i] &&
                (($d['effective_to'] ?? null) === null || $d['effective_to'] > $points[$i])));
            if (count($active) > 1) {
                throw new \DomainException('Overlapping student discounts');
            }
            $result[] = ['from'=>$points[$i], 'to'=>$points[$i+1], 'discount'=>$active[0] ?? null];
        }
        return $result;
    }

    public static function calculateSegment(int $studentBase, int $driverBase, ?array $discount): array
    {
        if ($studentBase < 0 || $driverBase < 0) {
            throw new \InvalidArgumentException('Invalid bases');
        }
        if ($discount === null) {
            return ['studentDiscount'=>0,'driverDiscount'=>0,'studentNet'=>$studentBase,
                'driverNet'=>$driverBase,'companyShare'=>$studentBase-$driverBase];
        }
        return StudentDiscountMath::calculate($studentBase, $driverBase,
            (int)$discount['rate_milli_percent'], (bool)$discount['apply_to_student'],
            (bool)$discount['apply_to_driver']);
    }
}
