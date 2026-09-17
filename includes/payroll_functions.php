<?php
/**
 * Payroll calculations use basic salary less deductions.
 * net_pay = basic_salary - deductions
 */

if (!function_exists('computePayroll')) {
    function computePayroll($basic_salary, $deductions = 0) {
        $basic = round((float) $basic_salary, 2);
        $deduct = round((float) $deductions, 2);
        $net = round($basic - $deduct, 2);
        return [
            'basic_salary' => $basic,
            'deductions' => $deduct,
            'net_pay' => $net,
        ];
    }

    function savePayroll(array $data, $id = null) {
        global $pdo;
        $computed = computePayroll($data['basic_salary'] ?? 0, $data['deductions'] ?? 0);
        $payload = [
            'user_id' => (int)($data['user_id'] ?? 0),
            'payroll_number' => $data['payroll_number'] ?? '',
            'employee_name' => $data['employee_name'] ?? '',
            'employee_position' => $data['employee_position'] ?? '',
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'basic_salary' => $computed['basic_salary'],
            'deductions' => $computed['deductions'],
            'net_pay' => $computed['net_pay'],
            'status' => $data['status'] ?? 'draft',
        ];

        if ($id) {
            $stmt = $pdo->prepare("UPDATE payroll SET user_id = ?, employee_name = ?, employee_position = ?, period_start = ?, period_end = ?, basic_salary = ?, deductions = ?, net_pay = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $payload['user_id'] ?: null,
                $payload['employee_name'],
                $payload['employee_position'],
                $payload['period_start'],
                $payload['period_end'],
                $payload['basic_salary'],
                $payload['deductions'],
                $payload['net_pay'],
                $payload['status'],
                $id,
            ]);
            return (int) $id;
        }

        $stmt = $pdo->prepare("INSERT INTO payroll (user_id, payroll_number, employee_name, employee_position, period_start, period_end, basic_salary, deductions, net_pay, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $payload['user_id'] ?: null,
            $payload['payroll_number'],
            $payload['employee_name'],
            $payload['employee_position'],
            $payload['period_start'],
            $payload['period_end'],
            $payload['basic_salary'],
            $payload['deductions'],
            $payload['net_pay'],
            $payload['status'],
            $data['created_by'] ?? ($_SESSION['user_id'] ?? null),
        ]);
        return (int) $pdo->lastInsertId();
    }
}
