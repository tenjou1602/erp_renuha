<?php
/**
 * SMTP mail configuration (Gmail-ready).
 *
 * How to enable Gmail OTP login:
 * 1. Use a Gmail account with 2-Step Verification ON
 * 2. Create an App Password: Google Account > Security > App passwords
 * 3. Set RUNEHA_SMTP_USER to the Gmail address
 * 4. Set RUNEHA_SMTP_PASS to the 16-character App Password
 * 5. Optionally set RUNEHA_SMTP_FROM; it defaults to RUNEHA_SMTP_USER
 *
 * Leave SMTP_USER / SMTP_PASS empty for local development
 * (login will skip OTP until mail is configured).
 */
if (!function_exists('mailConfigValue')) {
    function mailConfigValue($name, $default = '') {
        $value = getenv($name);
        if ($value === false || trim((string) $value) === '') {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? $default;
        }
        return trim((string) $value);
    }
}

if (!defined('SMTP_HOST')) {
    $smtpUser = mailConfigValue('RUNEHA_SMTP_USER');
    $smtpPass = preg_replace('/\s+/', '', mailConfigValue('RUNEHA_SMTP_PASS'));

    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', $smtpUser);
    define('SMTP_PASS', $smtpPass);
    define('SMTP_FROM', mailConfigValue('RUNEHA_SMTP_FROM', $smtpUser));
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
