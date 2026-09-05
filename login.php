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
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Harap isi username dan password!';
    } else {
        try {
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
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
                    
                    set_flash('success', "Selamat datang kembali, {$user['username']}!");
                    session_regenerate_id(true);
                    redirect('index.php');
                } else {
                    $error = 'Kata sandi salah. Silakan coba lagi!';
                }
            } else {
                $error = 'Akun dengan username/email tersebut tidak ditemukan!';
            }
            $stmt->close();
            $conn->close();
        } catch (Throwable $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$page_title = 'Login - Masuk ke Akun Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="auth-wrapper" role="main">
    <div class="auth-box">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3" style="width: 40px; height: 40px; font-size: 0.9rem;" aria-hidden="true">LT</div>
            <h1 class="h3 fw-bold mb-1">Masuk ke Learn Tracker</h1>
            <p class="text-secondary small mb-0">Lanjutkan quest dan streak belajarmu</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small mb-4" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="login-username" class="form-label">Username atau Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="login-username" class="form-control" placeholder="Username atau email" required autofocus autocomplete="username" maxlength="255" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="login-password" class="form-label mb-0">Kata Sandi</label>
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary text-decoration-none small" id="togglePasswordBtn" onclick="togglePasswordVisibility('login-password')">
                        <i class="far fa-eye me-1"></i>Lihat
                    </button>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="login-password" class="form-control" placeholder="Kata sandi" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn btn-cyber w-100 py-2 mt-2">
                <i class="fas fa-sign-in-alt me-2"></i> Masuk Sekarang
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

<script>
function togglePasswordVisibility(id) {
    const input = document.getElementById(id);
    const btn = document.getElementById('togglePasswordBtn');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="far fa-eye-slash me-1"></i>Sembunyikan';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="far fa-eye me-1"></i>Lihat';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
