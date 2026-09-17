<?php
/**
 * PHPMailer wrapper for RUNEHA ERP.
 * Failed sends are logged and never thrown to the caller.
 */

if (!function_exists('sendMail')) {
    require_once __DIR__ . '/../config/mail.php';

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    function mailTemplate($title, $contentHtml) {
        $brand = htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'RUNEHA INC. ERP');
        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f8fafc;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,0.08);">'
            . '<tr><td style="background:linear-gradient(135deg,#1e2a3a,#0f172a);padding:20px 24px;color:#fff;">'
            . '<div style="font-size:18px;font-weight:800;letter-spacing:0.4px;">RUNEHA <span style="color:#f59e0b;">INC.</span></div>'
            . '<div style="font-size:12px;color:#94a3b8;margin-top:4px;">' . $brand . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 24px;">'
            . '<h1 style="margin:0 0 12px;font-size:20px;color:#0f172a;">' . htmlspecialchars($title) . '</h1>'
            . $contentHtml
            . '</td></tr>'
            . '<tr><td style="padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:12px;">This is an automated message. Please do not reply.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    function sendMail($to, $subject, $body) {
        try {
            if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                logActivity($_SESSION['user_id'] ?? null, 'Email skipped', 'Mail', 'Invalid recipient: ' . (string) $to);
                return false;
            }

            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', 'PHPMailer is not installed. Run composer install.');
                return false;
            }

            if (!isSmtpConfigured()) {
                logActivity($_SESSION['user_id'] ?? null, 'Email skipped', 'Mail', 'SMTP credentials are not configured. To: ' . $to . ' | ' . $subject);
                return false;
            }

            $from = trim((string) SMTP_FROM);
            if ($from === '') {
                $from = trim((string) SMTP_USER);
            }

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = trim((string) SMTP_USER);
            $mail->Password = trim((string) SMTP_PASS);
            $mail->SMTPSecure = SMTP_SECURE ?: PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;
            if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
                $mail->SMTPDebug = 2;
            }

            $mail->setFrom($from, SMTP_FROM_NAME);
            $mail->addReplyTo($from, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n"], $body))));
            $mail->send();
            return true;
        } catch (Throwable $e) {
            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage() . ' | To: ' . $to . ' | ' . $subject);
            return false;
        }
    }

    function sendLoginOtpEmail($to, $full_name, $code) {
        $title = 'Your login verification code';
        $content = '<p style="margin:0 0 12px;color:#475569;">Hello ' . htmlspecialchars($full_name) . ',</p>'
            . '<p style="margin:0 0 16px;color:#475569;">Use this one-time code to finish signing in to RUNEHA ERP:</p>'
            . '<div style="display:inline-block;background:#0f172a;color:#f59e0b;font-size:28px;font-weight:800;letter-spacing:6px;padding:14px 22px;border-radius:12px;">'
            . htmlspecialchars($code) . '</div>'
            . '<p style="margin:18px 0 0;color:#64748b;font-size:13px;">This code expires in 5 minutes. If you did not request it, you can ignore this email.</p>';
        return sendMail($to, 'RUNEHA ERP login code', mailTemplate($title, $content));
    }

    function notifyDepartment($department, $subject, $body, $role = null) {
        global $pdo;
        try {
            $sql = "SELECT email, full_name FROM users WHERE department = ? AND status = 'active'";
            $params = [$department];
            if ($role) {
                $sql .= " AND role = ?";
                $params[] = $role;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sent = 0;
            foreach ($stmt->fetchAll() as $user) {
                if (!empty($user['email'])) {
                    try {
                        if (sendMail($user['email'], $subject, mailTemplate($subject, $body))) {
                            $sent++;
                        }
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                }
            }
            return $sent;
        } catch (Throwable $e) {
            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
            return 0;
        }
    }

    function notifyPurchaseRequestSubmitted($pr_number, $purpose, $requestor_name) {
        $subject = 'New purchase request: ' . $pr_number;
        $body = '<p>A new purchase request has been submitted.</p>'
            . '<p><strong>PR:</strong> ' . htmlspecialchars($pr_number) . '<br>'
            . '<strong>Requestor:</strong> ' . htmlspecialchars($requestor_name) . '<br>'
            . '<strong>Purpose:</strong> ' . nl2br(htmlspecialchars($purpose)) . '</p>';
        notifyDepartment('procurement', $subject, $body, 'manager');
    }

    function notifyPurchaseOrderApproved($po_number, $supplier, $supplier_email = null) {
        $subject = 'Purchase order approved: ' . $po_number;
        $body = '<p>Purchase order <strong>' . htmlspecialchars($po_number) . '</strong> has been approved/confirmed.</p>'
            . '<p><strong>Supplier:</strong> ' . htmlspecialchars($supplier) . '</p>';
        notifyDepartment('procurement', $subject, $body);
        if (!empty($supplier_email)) {
            try {
                sendMail($supplier_email, $subject, mailTemplate($subject, $body));
            } catch (Throwable $e) {
                logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
            }
        }
    }

    function notifyInvoiceGenerated($invoice_number, $client, $amount) {
        $subject = 'Invoice generated: ' . $invoice_number;
        $body = '<p>A new invoice has been generated.</p>'
            . '<p><strong>Invoice:</strong> ' . htmlspecialchars($invoice_number) . '<br>'
            . '<strong>Client:</strong> ' . htmlspecialchars($client) . '<br>'
            . '<strong>Amount:</strong> ₱' . number_format((float) $amount, 2) . '</p>';
        notifyDepartment('accounting', $subject, $body);
    }

    function notifyPayslipReady($employee_name, $payroll_number, $net_pay, $period_start, $period_end) {
        global $pdo;
        $subject = 'Your payslip is ready: ' . $payroll_number;
        $body = '<p>Hello ' . htmlspecialchars($employee_name) . ',</p>'
            . '<p>Your payslip is ready.</p>'
            . '<p><strong>Payroll #:</strong> ' . htmlspecialchars($payroll_number) . '<br>'
            . '<strong>Period:</strong> ' . htmlspecialchars($period_start) . ' to ' . htmlspecialchars($period_end) . '<br>'
            . '<strong>Net pay:</strong> ₱' . number_format((float) $net_pay, 2) . '</p>';
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE full_name = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$employee_name]);
            $user = $stmt->fetch();
            if (!empty($user['email'])) {
                sendMail($user['email'], $subject, mailTemplate($subject, $body));
            } else {
                logActivity($_SESSION['user_id'] ?? null, 'Email skipped', 'Mail', 'No matching user email for employee: ' . $employee_name);
            }
        } catch (Throwable $e) {
            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
        }
    }

    function notifyLowStock($material_name, $current_stock, $min_stock, $unit = 'pcs') {
        $subject = 'Low stock alert: ' . $material_name;
        $body = '<p>A material has reached or fallen below its minimum stock level.</p>'
            . '<p><strong>Material:</strong> ' . htmlspecialchars($material_name) . '<br>'
            . '<strong>Current stock:</strong> ' . htmlspecialchars((string) $current_stock) . ' ' . htmlspecialchars((string) $unit) . '<br>'
            . '<strong>Minimum stock:</strong> ' . htmlspecialchars((string) $min_stock) . ' ' . htmlspecialchars((string) $unit) . '</p>'
            . '<p>Please review warehouse inventory and replenish as needed.</p>';
        notifyDepartment('warehouse', $subject, $body);
    }

    function lookupSupplierEmail($supplier_name) {
        global $pdo;
        if (empty($supplier_name)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT email FROM suppliers WHERE name = ? AND email IS NOT NULL AND email != '' LIMIT 1");
            $stmt->execute([$supplier_name]);
            $row = $stmt->fetch();
            return $row['email'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
