<?php
/**
 * SMTP mail configuration (Gmail-ready).
 *
 * How to enable Gmail OTP login:
 * 1. Use a Gmail account with 2-Step Verification ON
 * 2. Create an App Password: Google Account > Security > App passwords
 * 3. Put the Gmail address in SMTP_USER / SMTP_FROM
 * 4. Put the 16-character App Password in SMTP_PASS (no spaces)
 *
 * Leave SMTP_USER / SMTP_PASS empty for local development
 * (login will skip OTP until mail is configured).
 */
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', 'joramvillanueva02@gmail.com');
    define('SMTP_PASS', 'pmfcbkidnvewxjzj');
    define('SMTP_FROM', 'joramvillanueva02@gmail.com');
    define('SMTP_FROM_NAME', 'RUNEHA INC. ERP');
    define('SMTP_SECURE', 'tls');
    define('MAIL_DEBUG', false);
}

if (!function_exists('isSmtpConfigured')) {
    function isSmtpConfigured() {
        return defined('SMTP_USER')
            && defined('SMTP_PASS')
            && trim((string) SMTP_USER) !== ''
            && trim((string) SMTP_PASS) !== '';
    }
}

if (!function_exists('maskEmail')) {
    function maskEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'your email';
        }
        [$local, $domain] = explode('@', $email, 2);
        $keep = max(1, min(2, strlen($local)));
        return substr($local, 0, $keep) . str_repeat('*', max(3, strlen($local) - $keep)) . '@' . $domain;
    }
}
