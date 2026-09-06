<?php
require_once 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = clean($_POST['username'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Semua field wajib diisi!';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh huruf, angka, dan underscore!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi kata sandi tidak cocok!';
    } else {
        try {
            $conn = db_connect();
            // Check uniqueness
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            if (!$stmt) {
                throw new Exception("Gagal mempersiapkan verifikasi akun: " . $conn->error);
            }
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'Username atau email sudah digunakan oleh akun lain!';
                $stmt->close();
            } else {
                $stmt->close();
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, xp, streak, last_active_date) VALUES (?, ?, ?, 0, 1, CURDATE())");
                if (!$stmt) {
                    throw new Exception("Gagal mempersiapkan query pendaftaran: " . $conn->error);
                }
                $stmt->bind_param("sss", $username, $email, $hashed);

                if ($stmt->execute()) {
                    $new_id = (int)$stmt->insert_id;
                    $_SESSION['user_id'] = $new_id;
                    $_SESSION['username'] = $username;
                    $stmt->close();
                    seed_quiz_bank($conn, $new_id);
                    $conn->close();
                    set_flash('success', "Akun berhasil dibuat. Selamat datang, {$username}! 36 kartu kuis menantimu.");
                    session_regenerate_id(true);
                    redirect('index.php');
                } else {
                    $error = 'Terjadi kesalahan sistem saat mendaftar. Silakan coba lagi.';
                    $stmt->close();
                }
            }
            $conn->close();
        } catch (Throwable $e) {
            error_log("Register error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$page_title = 'Daftar Akun Baru - Learn Tracker';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="auth-wrapper" role="main">
    <div class="auth-box" style="max-width: 480px;">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3" style="width: 40px; height: 40px; font-size: 0.9rem;" aria-hidden="true">LT</div>
            <h1 class="h3 fw-bold mb-1">Buat akun baru</h1>
            <p class="text-secondary small mb-0">Daftar dan mulai roadmap DevOps 12 minggu</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small mb-4" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate id="registerForm">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="reg-username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="reg-username" class="form-control" placeholder="Contoh: devops_ranger" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-text text-secondary" style="font-size: 0.75rem;">Hanya huruf, angka, dan underscore (_). Min 3 karakter.</div>
            </div>

            <div class="mb-3">
                <label for="reg-email" class="form-label">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="reg-email" class="form-control" placeholder="kamu@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="reg-password" class="form-label">Kata sandi</label>
                    <div class="input-group">
                        <input type="password" name="password" id="reg-password" class="form-control" placeholder="Min 6 karakter" required minlength="6" autocomplete="new-password">
                        <button type="button" class="btn btn-cyber-outline" onclick="togglePasswordVisibility('reg-password', this)" aria-label="Tampilkan kata sandi"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="reg-confirm" class="form-label">Konfirmasi kata sandi</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="reg-confirm" class="form-control" placeholder="Ulangi sandi" required autocomplete="new-password">
                        <button type="button" class="btn btn-cyber-outline" onclick="togglePasswordVisibility('reg-confirm', this)" aria-label="Tampilkan konfirmasi kata sandi"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-cyber w-100 py-2 mt-2">
                <i class="fas fa-rocket me-2" aria-hidden="true"></i> Buat akun
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <p class="text-secondary small mb-2">Sudah punya akun sebelumnya?</p>
            <a href="login.php" class="btn btn-cyber-outline btn-sm w-100">
                <i class="fas fa-sign-in-alt me-1"></i> Masuk ke Akun
            </a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
