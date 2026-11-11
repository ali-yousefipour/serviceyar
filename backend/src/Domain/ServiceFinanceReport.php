<?php
declare(strict_types=1);
namespace ServiceYar\Domain;

use ServiceYar\Auth\AuthException;
use ServiceYar\Auth\Authorization;
use ServiceYar\Database\Connection;

final class ServiceFinanceReport
{
    public static function report(int $uid, int $aid, array $filter, ?int $studentId = null): array
    {
        Authorization::requireAgencyAccess($uid, $aid);
        Authorization::requirePermission($uid, 'finance.view');
        Authorization::requirePermission($uid, 'students.view');

        $year = $filter['academicYear'] ?? '';
        $from = $filter['from'] ?? '';
        $to = $filter['to'] ?? '';
        $months = filter_var($filter['months'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if (!is_string($year) || !preg_match('/\A(1[34]\d{2})[-\/](1[34]\d{2})\z/', $year, $match)
            || (int)$match[1] + 1 !== (int)$match[2] || !is_string($from) || !is_string($to)
            || !OperationsService::validDate($from) || !OperationsService::validDate($to)
            || $from > $to || (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days > 365
            || $months === false) {
            throw new AuthException(422, 'سال تحصیلی، بازه حداکثر یک‌ساله و تعداد ماه خدمت را بررسی کنید.', 'VALIDATION_ERROR');
        }
        $year = str_replace('/', '-', $year);
        $where = $studentId === null ? ' AND st.is_active=1 AND a.service_id IS NOT NULL' : ' AND st.id=:student';
        $params = [
            'assignment_agency' => $aid, 'invoice_agency' => $aid, 'student_agency' => $aid,
            'from_date' => $from, 'to_date' => $to, 'paid_through' => $to,
            'academic_year' => $year, 'months' => $months,
        ];
        if ($studentId !== null) $params['student'] = $studentId;

        $sql = "
            WITH assignments AS (
                SELECT ss.student_id, MIN(s.id) service_id, COUNT(*) assignment_count
                FROM service_students ss JOIN services s ON s.id=ss.service_id
                WHERE s.agency_id=:assignment_agency AND s.is_active=1 AND s.status='active'
                GROUP BY ss.student_id
            ), invoice_rows AS (
                SELECT i.party_id student_id, i.amount,
                    COALESCE((SELECT SUM(p.amount) FROM payments p
                        WHERE p.agency_id=i.agency_id AND p.invoice_id=i.id
                          AND p.payment_date<=:paid_through),0) received
                FROM invoices i
                WHERE i.agency_id=:invoice_agency AND i.party_type='student'
                    AND i.status IN ('open','paid','overdue')
                    AND i.issue_date BETWEEN :from_date AND :to_date
            ), financial AS (
                SELECT student_id, SUM(amount) invoiced, SUM(received) received
                FROM invoice_rows GROUP BY student_id
            ), student_rows AS (
                SELECT st.id student_id, CONCAT_WS(' ',st.first_name,st.last_name) student,
                    sc.name school, s.title service, CONCAT_WS(' ',d.first_name,d.last_name) driver,
                    COALESCE(a.assignment_count,0) assignment_count,
                    NULLIF(st.monthly_fee,0) monthly_fee,
                    NULLIF(st.monthly_fee,0)*:months estimated_fee,
                    COALESCE(f.invoiced,0) invoiced, COALESCE(f.received,0) received,
                    COALESCE(f.invoiced,0)-COALESCE(f.received,0) balance
                FROM students st
                LEFT JOIN assignments a ON a.student_id=st.id
                LEFT JOIN services s ON s.id=a.service_id AND s.agency_id=st.agency_id AND a.assignment_count=1
                LEFT JOIN schools sc ON sc.id=st.school_id AND sc.agency_id=st.agency_id
                LEFT JOIN drivers d ON d.id=s.driver_id AND d.agency_id=st.agency_id
                LEFT JOIN financial f ON f.student_id=st.id
                WHERE st.agency_id=:student_agency AND REPLACE(st.academic_year,'/','-')=:academic_year".$where."
            )
            SELECT student_rows.*, COUNT(*) OVER () total_students,
                COUNT(*) OVER () - COUNT(monthly_fee) OVER () missing_fees,
                SUM(estimated_fee) OVER () total_estimated, SUM(invoiced) OVER () total_invoiced,
                SUM(received) OVER () total_received, SUM(balance) OVER () total_balance
            FROM student_rows ORDER BY school,service,student,student_id";
        $query = Connection::get()->prepare($sql);
        $query->execute($params);
        $rows = $query->fetchAll();
        if ($studentId !== null && !$rows) throw new AuthException(404, 'دانش‌آموز در این سال تحصیلی پیدا نشد.', 'NOT_FOUND');

        $first = $rows[0] ?? [];
        $totals = [
            'student_count' => (int)($first['total_students'] ?? 0),
            'missing_fees' => (int)($first['missing_fees'] ?? 0),
            'estimated_fee' => $first['total_estimated'] ?? null,
            'invoiced' => $first['total_invoiced'] ?? '0.00',
            'received' => $first['total_received'] ?? '0.00',
            'balance' => $first['total_balance'] ?? '0.00',
        ];
        foreach ($rows as &$row) {
            unset($row['total_students'], $row['missing_fees'], $row['total_estimated'],
                $row['total_invoiced'], $row['total_received'], $row['total_balance']);
        }
        unset($row);
        return ['academicYear'=>$year, 'from'=>$from, 'to'=>$to, 'months'=>$months,
            'currency'=>'IRR', 'totals'=>$totals, 'items'=>$rows];
    }
}
