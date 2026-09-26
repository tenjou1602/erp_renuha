<?php
require_once 'config/database.php';
require_once 'includes/mailer.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$info = '';
$otp_step = isset($_SESSION['login_otp_hash'], $_SESSION['login_otp_user_id']);
$masked_email = $_SESSION['login_otp_email_masked'] ?? '';

function clearLoginOtpSession() {
    unset(
        $_SESSION['login_otp_hash'],
        $_SESSION['login_otp_user_id'],
        $_SESSION['login_otp_expires'],
        $_SESSION['login_otp_attempts'],
        $_SESSION['login_otp_email_masked'],
        $_SESSION['login_otp_full_name']
    );
}

function completeLoginSession(array $user, $via = 'password') {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['role'] = $user['role'];
    clearLoginOtpSession();
    logActivity($user['id'], 'login', 'auth', 'User logged in via ' . $via);
    $_SESSION['success'] = 'Login successful. Welcome back, ' . $user['full_name'] . '!';
    header('Location: index.php');
    exit();
}

function maskEmail(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return 'your email';
    }

    $local = $parts[0];
    $domain = $parts[1];

    if (strlen($local) <= 2) {
        $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1));
    } else {
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2));
    }

    return $maskedLocal . '@' . $domain;
}

function issueLoginOtp(array $user) {
    if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('No valid email address is registered for this account.');
    }

    $code = (string) random_int(100000, 999999);
    $_SESSION['login_otp_hash'] = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['login_otp_user_id'] = (int) $user['id'];
    $_SESSION['login_otp_expires'] = time() + 300;
    $_SESSION['login_otp_attempts'] = 0;
    $_SESSION['login_otp_email_masked'] = maskEmail($user['email']);

    $subject = 'Your Runeha ERP login code';
    $body = "Your Runeha ERP login code is: <strong>{$code}</strong><br><br>This code expires in 5 minutes.";
    sendEmail($user['email'], $subject, $body);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_otp'])) {
        clearLoginOtpSession();
        $otp_step = false;
    } elseif (isset($_POST['resend_otp'])) {
        $userId = $_SESSION['login_otp_user_id'] ?? null;
        if (!$userId) {
            $error = 'Your OTP session has expired. Please log in again.';
            $otp_step = false;
        } else {
            try {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if (!$user) {
                    throw new RuntimeException('The selected user could not be found.');
                }

                issueLoginOtp($user);
                $otp_step = true;
                $masked_email = $_SESSION['login_otp_email_masked'] ?? '';
                $info = 'We sent a new 6-digit code to ' . $masked_email . '.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
                clearLoginOtpSession();
                $otp_step = false;
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        $otp = trim((string) ($_POST['otp'] ?? ''));

        if (!$otp || !isset($_SESSION['login_otp_hash'], $_SESSION['login_otp_user_id'], $_SESSION['login_otp_expires'])) {
            $error = 'Your OTP session is invalid or expired. Please try again.';
            clearLoginOtpSession();
            $otp_step = false;
        } elseif (time() > $_SESSION['login_otp_expires']) {
            $error = 'Your OTP code has expired. Please log in again.';
            clearLoginOtpSession();
            $otp_step = false;
        } elseif (!password_verify($otp, $_SESSION['login_otp_hash'])) {
            $_SESSION['login_otp_attempts'] = (int) ($_SESSION['login_otp_attempts'] ?? 0) + 1;
            $error = 'Invalid OTP code. Please try again.';
            $otp_step = true;
            $masked_email = $_SESSION['login_otp_email_masked'] ?? '';
        } else {
            $userId = (int) $_SESSION['login_otp_user_id'];
            try {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND status = ? LIMIT 1');
                $stmt->execute([$userId, 'active']);
                $user = $stmt->fetch();
                if (!$user) {
                    throw new RuntimeException('Account not found or no longer active.');
                }

                completeLoginSession($user, 'OTP');
            } catch (Throwable $e) {
                $error = $e->getMessage();
                clearLoginOtpSession();
                $otp_step = false;
            }
        }
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please enter username and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password'])) {
                    throw new RuntimeException('Invalid username or password.');
                }

                if (!isSmtpConfigured()) {
                    completeLoginSession($user, 'password (OTP skipped: SMTP not configured)');
                }

                issueLoginOtp($user);
                $otp_step = true;
                $masked_email = $_SESSION['login_otp_email_masked'] ?? '';
                $info = 'We sent a 6-digit code to ' . $masked_email . '.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e2a3a;
            --navy-dark: #0f172a;
            --amber: #f59e0b;
            --off-white: #f8fafc;
            --muted: #64748b;
            --border: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--navy-dark);
            background: #0f172a;
            overflow: hidden;
            position: relative;
        }

        .bg-slideshow {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: 0;
        }

        .bg-slideshow::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.72) 0%, rgba(15, 52, 96, 0.7) 50%, rgba(15, 23, 42, 0.82) 100%);
            z-index: 1;
        }

        .bg-slide {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 1s ease;
            filter: saturate(1.08) contrast(1.08);
        }

        .bg-slide.active { opacity: 1; }

        .login-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 460px;
            background: rgba(255,255,255,0.96);
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(2, 6, 23, 0.4);
            padding: 30px 28px 20px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 18px;
        }

        .login-logo {
            display: block;
            width: 100%;
            max-width: 230px;
            height: auto;
            margin: 0 auto 10px;
        }

        .enterprise-tag {
            font-size: 0.7rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }

        .login-panel {
            width: 100%;
            background: transparent;
        }

        .login-panel h2 {
            font-size: 2rem;
            line-height: 1.2;
            margin-bottom: 6px;
            text-align: center;
            color: var(--navy-dark);
            font-weight: 700;
        }

        .login-panel .subtitle {
            text-align: center;
            color: var(--muted);
            margin-bottom: 24px;
            font-size: 0.96rem;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 0.82rem;
        }
        .form-group input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            background: var(--off-white);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .form-group input:focus {
            outline: none;
            background: #fff;
            border-color: var(--amber);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.16);
        }

        .otp-input {
            letter-spacing: 0.45em;
            text-align: center;
            font-size: 1.35rem !important;
            font-weight: 700;
        }

        .btn {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            font-size: 0.98rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, var(--navy), var(--navy-dark));
            color: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15,23,42,0.22);
        }
        .btn-secondary {
            background: #fff;
            color: var(--navy-dark);
            border: 1px solid var(--border);
            box-shadow: none;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background: var(--off-white);
            box-shadow: none;
        }
        .btn-link {
            display: inline-block;
            margin-top: 14px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.88rem;
        }
        .btn-link:hover { color: var(--navy-dark); }

        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            border-left: 4px solid transparent;
        }
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-left-color: #10b981;
        }
        .alert-info {
            background: #fffbeb;
            color: #92400e;
            border-left-color: var(--amber);
        }

        .otp-meta {
            background: var(--off-white);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            color: #475569;
            font-size: 0.9rem;
        }
        .otp-meta strong { color: var(--navy-dark); }

        .login-footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 0.78rem;
            color: #94a3b8;
        }

        @media (max-width: 520px) {
            body { padding: 16px; }
            .login-shell {
                max-width: 100%;
                padding: 24px 20px 18px;
            }
            .login-panel h2 { font-size: 1.7rem; }
            .enterprise-tag { letter-spacing: 0.12em; }
        }
    </style>
</head>
<body>
    <div class="bg-slideshow" aria-hidden="true">
        <img class="bg-slide active" src="<?php echo APP_URL; ?>assets/images/picture1.webp" alt="" onerror="this.style.display='none';">
        <img class="bg-slide" src="<?php echo APP_URL; ?>assets/images/picture2.webp" alt="" onerror="this.style.display='none';">
        <img class="bg-slide" src="<?php echo APP_URL; ?>assets/images/picture3.webp" alt="" onerror="this.style.display='none';">
    </div>

    <div class="login-shell">
        <div class="login-panel">
            <div class="login-brand">
                <img src="<?php echo APP_URL; ?>assets/images/logo.png" alt="RUNEHA INC." class="login-logo">
                <div class="enterprise-tag">ENTERPRISE RESOURCE PLANNING</div>
            </div>

            <?php if (!$otp_step): ?>
                <h2>Welcome back</h2>
                <p class="subtitle">Sign in to your account to continue</p>
            <?php else: ?>
                <h2>Verify your identity</h2>
                <p class="subtitle">Enter the 6-digit code sent to your email</p>
            <?php endif; ?>

            <?php if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> You have been logged out successfully.</div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert alert-info"><i class="fas fa-envelope-open-text"></i> <?php echo htmlspecialchars($info); ?></div>
            <?php endif; ?>

            <?php if (!$otp_step): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" placeholder="Enter your username" required autofocus autocomplete="username">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
            <?php else: ?>
            <div class="otp-meta">
                Code sent to <strong><?php echo htmlspecialchars($masked_email ?: 'your registered email'); ?></strong>.
                Expires in 5 minutes.
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Email OTP</label>
                    <input class="otp-input" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" required autofocus autocomplete="one-time-code">
                </div>
                <button type="submit" name="verify_otp" value="1" class="btn">Verify and Sign In</button>
            </form>
            <form method="POST" action="">
                <button type="submit" name="resend_otp" value="1" class="btn btn-secondary">Resend code</button>
            </form>
            <a class="btn-link" href="login.php?cancel_otp=1"><i class="fas fa-arrow-left"></i> Back to password login</a>
            <?php endif; ?>

            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const slides = document.querySelectorAll('.bg-slide');
            if (!slides.length) return;

            let currentIndex = 0;
            setInterval(function () {
                currentIndex = (currentIndex + 1) % slides.length;
                slides.forEach((slide, index) => slide.classList.toggle('active', index === currentIndex));
            }, 4500);
        });
    </script>
</body>
</html>
