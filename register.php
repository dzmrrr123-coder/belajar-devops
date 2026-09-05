<?php
require_once 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$conn = db_connect();
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
        // Check uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Username atau email sudah digunakan oleh akun lain!';
        } else {
            $stmt->close();
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, xp, streak, last_active_date) VALUES (?, ?, ?, 0, 1, CURDATE())");
            $stmt->bind_param("sss", $username, $email, $hashed);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                set_flash('success', "Akun berhasil dibuat! Selamat datang di petualangan DevOps, {$username}! 🚀");
                redirect('index.php');
            } else {
                $error = 'Terjadi kesalahan sistem saat mendaftar. Silakan coba lagi.';
            }
        }
        $stmt->close();
    }
}
$conn->close();

$page_title = 'Daftar Akun Baru - Learn Tracker';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="auth-wrapper" role="main">
    <div class="auth-box" style="max-width: 480px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);">
                <i class="fas fa-user-astronaut text-emerald" style="font-size: 2.2rem;"></i>
            </div>
            <h1 class="h3 fw-bold mb-1">Mulai Petualangan <span class="text-gradient">DevOps</span></h1>
            <p class="text-secondary small mb-0">Daftar sekarang dan selesaikan 14 quest roadmap 3 bulan</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small border-0 mb-4" role="alert" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5; border-radius: var(--radius-sm);">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate id="registerForm">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="reg-username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text border-0" style="background: rgba(15, 23, 42, 0.9); color: var(--text-muted);"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="reg-username" class="form-control" placeholder="Contoh: devops_ranger" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-text text-secondary" style="font-size: 0.75rem;">Hanya huruf, angka, dan underscore (_). Min 3 karakter.</div>
            </div>

            <div class="mb-3">
                <label for="reg-email" class="form-label">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text border-0" style="background: rgba(15, 23, 42, 0.9); color: var(--text-muted);"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="reg-email" class="form-control" placeholder="kamu@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="reg-password" class="form-label">Kata Sandi</label>
                    <div class="input-group">
                        <input type="password" name="password" id="reg-password" class="form-control" placeholder="Min 6 karakter" required minlength="6">
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="reg-confirm" class="form-label">Konfirmasi Sandi</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="reg-confirm" class="form-control" placeholder="Ulangi sandi" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-cyber w-100 py-2 mt-2">
                <i class="fas fa-rocket me-2"></i> Buat Akun & Mulai Belajar
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center" style="border-color: var(--border-subtle) !important;">
            <p class="text-secondary small mb-2">Sudah punya akun sebelumnya?</p>
            <a href="login.php" class="btn btn-cyber-outline btn-sm w-100">
                <i class="fas fa-sign-in-alt me-1"></i> Masuk ke Akun
            </a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
