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
    $_SESSION['login_otp_full_name'] = $user['full_name'];

    $sent = sendLoginOtpEmail($user['email'], $user['full_name'], $code);
    if (!$sent) {
        clearLoginOtpSession();
        throw new RuntimeException('Unable to send the OTP email. Check Gmail SMTP settings in config/mail.php.');
    }
    return true;
}

if (isset($_GET['cancel_otp'])) {
    clearLoginOtpSession();
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend_otp'])) {
        try {
            if (!$otp_step) {
                throw new RuntimeException('No pending OTP request.');
            }
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([(int) $_SESSION['login_otp_user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new RuntimeException('This account is no longer active.');
            }
            issueLoginOtp($user);
            $otp_step = true;
            $masked_email = $_SESSION['login_otp_email_masked'] ?? '';
            $info = 'A new verification code was sent to ' . $masked_email . '.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $otp_step = isset($_SESSION['login_otp_hash'], $_SESSION['login_otp_user_id']);
        }
    } elseif (isset($_POST['verify_otp'])) {
        try {
            $otp = trim($_POST['otp'] ?? '');
            if (!$otp_step || time() > (int) ($_SESSION['login_otp_expires'] ?? 0)) {
                clearLoginOtpSession();
                throw new RuntimeException('Your OTP has expired. Please sign in again.');
            }
            if ((int) ($_SESSION['login_otp_attempts'] ?? 0) >= 5) {
                clearLoginOtpSession();
                throw new RuntimeException('Too many invalid OTP attempts. Please sign in again.');
            }
            $_SESSION['login_otp_attempts'] = (int) ($_SESSION['login_otp_attempts'] ?? 0) + 1;
            if (!preg_match('/^\d{6}$/', $otp) || !password_verify($otp, $_SESSION['login_otp_hash'])) {
                throw new RuntimeException('Invalid OTP. Please check the code and try again.');
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([(int) $_SESSION['login_otp_user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new RuntimeException('This account is no longer active.');
            }
            completeLoginSession($user, 'email OTP');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $otp_step = isset($_SESSION['login_otp_hash'], $_SESSION['login_otp_user_id']);
            $masked_email = $_SESSION['login_otp_email_masked'] ?? '';
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
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

                // Local/dev: skip OTP until Gmail SMTP credentials are set.
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
            background:
                radial-gradient(circle at top right, rgba(245,158,11,0.18), transparent 28%),
                linear-gradient(145deg, #0f172a 0%, #1e2a3a 55%, #16213e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--navy-dark);
        }
        .login-shell {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            background: rgba(255,255,255,0.96);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
        }
        .login-brand {
            background: linear-gradient(160deg, #1e2a3a 0%, #0f172a 100%);
            color: #fff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 560px;
        }
        .brand-mark {
            display: block;
            max-width: 420px;
        }
        .brand-mark img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
            filter: drop-shadow(0 8px 22px rgba(0,0,0,0.28));
        }
        .brand-copy h1 {
            font-size: 2rem;
            line-height: 1.2;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .brand-copy p {
            color: #94a3b8;
            line-height: 1.6;
            max-width: 34ch;
        }
        .brand-points {
            display: grid;
            gap: 10px;
            margin-top: 28px;
        }
        .brand-points div {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #cbd5e1;
            font-size: 0.92rem;
        }
        .brand-points i { color: var(--amber); width: 18px; }
        .login-panel {
            padding: 48px 42px;
            background: #fff;
        }
        .login-panel h2 {
            font-size: 1.55rem;
            margin-bottom: 6px;
        }
        .login-panel .subtitle {
            color: var(--muted);
            margin-bottom: 28px;
            font-size: 0.95rem;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 0.85rem;
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
        .notice {
            background: var(--off-white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 18px;
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
            font-size: 0.8rem;
            color: #94a3b8;
        }
        @media (max-width: 860px) {
            .login-shell { grid-template-columns: 1fr; }
            .login-brand { min-height: auto; padding: 32px 28px; }
            .brand-copy h1 { font-size: 1.6rem; }
            .login-panel { padding: 32px 24px; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <aside class="login-brand">
            <div>
                <div class="brand-mark">
                    <img src="<?php echo APP_URL; ?>assets/images/logo.png" alt="RUNEHA INC. logo">
                </div>
                <div class="brand-copy" style="margin-top:42px;">
                    <h1>Secure access to your ERP workspace</h1>
                    <p>Sign in with your department account. When Gmail SMTP is configured, a one-time email code protects every login.</p>
                </div>
                <div class="brand-points">
                    <div><i class="fas fa-shield-alt"></i> Email OTP verification</div>
                    <div><i class="fas fa-building"></i> Department-based access</div>
                    <div><i class="fas fa-chart-line"></i> Projects, procurement, finance & warehouse</div>
                </div>
            </div>
            <div style="color:#64748b;font-size:0.8rem;">Enterprise Resource Planning</div>
        </aside>

        <section class="login-panel">
            <?php if (!$otp_step): ?>
                <h2>Welcome back</h2>
                <p class="subtitle">Enter your credentials to continue</p>
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

            <?php if (!isSmtpConfigured() && !$otp_step): ?>
                <div class="notice">
                    Gmail SMTP is not configured yet. Login works with username/password only.
                    Set credentials in <strong>config/mail.php</strong> to enable OTP.
                </div>
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
        </section>
    </div>
</body>
</html>
