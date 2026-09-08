<?php
require_once 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$field_errors = ['username' => '', 'password' => ''];
$retry_after = 0;
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => time()];
if (time() - $_SESSION['login_attempts']['first'] > 300) $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
if ($_SESSION['login_attempts']['count'] >= 5) {
    $retry_after = max(0, 300 - (time() - $_SESSION['login_attempts']['first']));
    $mins = (int)ceil($retry_after / 60);
    $error = "Terlalu banyak percobaan. Coba lagi dalam ±{$mins} menit.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => time()];
    if (time() - $_SESSION['login_attempts']['first'] > 300) $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
    if ($_SESSION['login_attempts']['count'] >= 5) {
        $retry_after = max(0, 300 - (time() - $_SESSION['login_attempts']['first']));
        $mins = (int)ceil($retry_after / 60);
        $error = "Terlalu banyak percobaan. Coba lagi dalam ±{$mins} menit.";
    } else {
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) $field_errors['username'] = 'Isi username atau email kamu.';
    if (empty($password)) $field_errors['password'] = 'Isi kata sandi kamu.';
    if (array_filter($field_errors)) {
        $error = 'Periksa kembali formulir di bawah.';
    } else {
        try {
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT id, username, password, onboarded FROM users WHERE username = ? OR email = ?");
            if (!$stmt) {
                throw new Exception("Gagal mempersiapkan query login: " . $conn->error);
            }
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    
                    // Update streak on login
                    update_user_streak($conn, $user['id']);
                    touch_login_time($conn, $user['id']);

                    if (isset($_POST['remember'])) {
                        create_remember_token($conn, $user['id']);
                    }

                    set_flash('success', "Selamat datang kembali, {$user['username']}!");
                    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
                    session_regenerate_id(true);
                    redirect(empty($user['onboarded']) ? 'onboarding.php' : 'index.php');
                } else {
                    $_SESSION['login_attempts']['count']++;
                    $field_errors['password'] = 'Kata sandi salah. Coba lagi.';
                    $error = 'Kata sandi salah. Coba lagi.';
                }
            } else {
                $_SESSION['login_attempts']['count']++;
                $field_errors['username'] = 'Akun tidak ditemukan. Cek ejaan atau daftar baru.';
                $error = 'Akun tidak ditemukan. Cek ejaan atau daftar baru.';
            }
            $stmt->close();
            $conn->close();
        } catch (Throwable $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
    }
}

$page_title = 'Login - Masuk ke Akun Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="auth-wrapper" id="main">
    <div class="auth-box">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3" style="width: 40px; height: 40px; font-size: 0.9rem;" aria-hidden="true">LT</div>
            <h1 class="h3 fw-bold mb-1">Masuk lagi</h1>
            <p class="text-secondary small mb-0">Lanjutkan quest dan streak belajarmu.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small mb-4" role="alert" tabindex="-1" id="loginError"<?php if ($retry_after > 0): ?> data-retry-after="<?= (int)$retry_after ?>"<?php endif; ?>>
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?><?php if ($retry_after > 0): ?> <span id="retryClock"></span><?php endif; ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="login-username" class="form-label">Username atau email</label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="login-username" class="form-control <?= $field_errors['username'] ? 'is-invalid' : '' ?>" placeholder="Username atau email" required autocomplete="username" maxlength="255" aria-invalid="<?= $field_errors['username'] ? 'true' : 'false' ?>" aria-describedby="login-username-err" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <?php if ($field_errors['username']): ?><div class="invalid-feedback d-block" id="login-username-err"><?= htmlspecialchars($field_errors['username']) ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="login-password" class="form-label mb-0">Kata sandi</label>
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary text-decoration-none small" data-toggle-password="login-password" aria-label="Tampilkan kata sandi" aria-pressed="false">
                        <i class="far fa-eye me-1" aria-hidden="true"></i>Lihat
                    </button>
                </div>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="login-password" class="form-control <?= $field_errors['password'] ? 'is-invalid' : '' ?>" placeholder="Kata sandi" required autocomplete="current-password" aria-invalid="<?= $field_errors['password'] ? 'true' : 'false' ?>" aria-describedby="login-password-err">
                </div>
                <?php if ($field_errors['password']): ?><div class="invalid-feedback d-block" id="login-password-err"><?= htmlspecialchars($field_errors['password']) ?></div><?php endif; ?>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="remember" id="remember-me">
                <label class="form-check-label small text-secondary" for="remember-me">Ingat saya di perangkat pribadi ini (30 hari). Jangan centang di komputer sekolah/warnet.</label>
            </div>
            <p class="small text-secondary mb-3">Lupa kata sandi? <a href="feedback.php">Minta bantuan</a>.</p>

            <button type="submit" class="btn btn-cyber w-100 py-2 mt-1">
                <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i> Masuk
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <p class="text-secondary small mb-2">Belum memiliki akun?</p>
            <a href="register.php" class="btn btn-cyber-outline btn-sm w-100">
                <i class="fas fa-user-plus me-1"></i> Buat Akun Baru (Gratis)
            </a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
