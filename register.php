<?php
require_once 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$field_errors = ['username' => '', 'email' => '', 'password' => '', 'confirm_password' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = clean($_POST['username'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($username)) $field_errors['username'] = 'Username wajib diisi.';
    elseif (strlen($username) < 3) $field_errors['username'] = 'Username minimal 3 karakter.';
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $field_errors['username'] = 'Hanya huruf, angka, dan underscore (_).';
    if (empty($email)) $field_errors['email'] = 'Email wajib diisi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $field_errors['email'] = 'Format email tidak valid. Contoh: kamu@email.com.';
    if (empty($password)) $field_errors['password'] = 'Kata sandi wajib diisi.';
    elseif (strlen($password) < 6) $field_errors['password'] = 'Minimal 6 karakter. Kombinasikan huruf + angka.';
    if (empty($confirm)) $field_errors['confirm_password'] = 'Ulangi kata sandi untuk konfirmasi.';
    elseif ($password !== $confirm) $field_errors['confirm_password'] = 'Konfirmasi tidak cocok. Cek lagi.';
    $has_field_error = (bool)array_filter($field_errors);
    if ($has_field_error) $error = 'Periksa kembali formulir di bawah.';
    if (!$has_field_error) {
        try {
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 2");
            if (!$stmt) {
                throw new Exception("Gagal mempersiapkan verifikasi akun: " . $conn->error);
            }
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $user_taken = false; $email_taken = false;
            foreach ($rows as $r) {
                if (strcasecmp((string)$r['username'], $username) === 0) $user_taken = true;
                if (strcasecmp((string)$r['email'], $email) === 0) $email_taken = true;
            }
            if ($user_taken || $email_taken) {
                if ($user_taken) $field_errors['username'] = 'Username ini sudah dipakai. Coba variasi lain atau masuk.';
                if ($email_taken) $field_errors['email'] = 'Email ini sudah terdaftar. Masuk atau gunakan email lain.';
                $error = 'Username atau email sudah digunakan. Cek detail di bawah.';
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
                    set_flash('success', "Akun berhasil dibuat. Selamat datang, {$username}! Atur start-mu dulu.");
                    session_regenerate_id(true);
                    redirect('onboarding.php');
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

<main class="auth-wrapper" id="main">
    <div class="auth-box" style="max-width: 480px;">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3" style="width: 40px; height: 40px; font-size: 0.9rem;" aria-hidden="true">LT</div>
            <h1 class="h3 fw-bold mb-1">Buat akun baru</h1>
            <p class="text-secondary small mb-0">Daftar gratis, atur target 1 menit, langsung dapat quest minggu pertama.</p>
        </div>
        <ul class="auth-benefits">
            <li><i class="fas fa-map" aria-hidden="true"></i>Roadmap 12 minggu RPL · TKJ · DKV · DevOps</li>
            <li><i class="fas fa-fire" aria-hidden="true"></i>Streak + misi harian biar konsisten</li>
            <li><i class="fas fa-file-export" aria-hidden="true"></i>Portofolio siap export CV PKL</li>
        </ul>

        <?php $first_err = array_filter($field_errors) ? array_search(current(array_filter($field_errors)), $field_errors) : ''; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 small mb-4" role="alert" tabindex="-1" id="registerError">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="reg-username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" id="reg-username" class="form-control <?= $field_errors['username'] ? 'is-invalid' : '' ?>" placeholder="Contoh: devops_ranger" required autocomplete="username" minlength="3" pattern="[a-zA-Z0-9_]+" aria-describedby="reg-username-help reg-username-err" aria-invalid="<?= $field_errors['username'] ? 'true' : 'false' ?>" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-text" id="reg-username-help">Hanya huruf, angka, dan underscore (_). Min 3 karakter.</div>
                <?php if ($field_errors['username']): ?><div class="invalid-feedback d-block" id="reg-username-err"><?= htmlspecialchars($field_errors['username']) ?></div><?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="reg-email" class="form-label">Alamat email</label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" id="reg-email" class="form-control <?= $field_errors['email'] ? 'is-invalid' : '' ?>" placeholder="kamu@email.com" required autocomplete="email" aria-describedby="reg-email-err" aria-invalid="<?= $field_errors['email'] ? 'true' : 'false' ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <?php if ($field_errors['email']): ?><div class="invalid-feedback d-block" id="reg-email-err"><?= htmlspecialchars($field_errors['email']) ?></div><?php endif; ?>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="reg-password" class="form-label">Kata sandi</label>
                    <div class="input-group">
                        <input type="password" name="password" id="reg-password" class="form-control <?= $field_errors['password'] ? 'is-invalid' : '' ?>" placeholder="Min 6 karakter" required minlength="6" autocomplete="new-password" aria-describedby="reg-password-err" aria-invalid="<?= $field_errors['password'] ? 'true' : 'false' ?>">
                        <button type="button" class="btn btn-cyber-outline" data-toggle-password="reg-password" aria-label="Tampilkan kata sandi" aria-pressed="false"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <?php if ($field_errors['password']): ?><div class="invalid-feedback d-block" id="reg-password-err"><?= htmlspecialchars($field_errors['password']) ?></div><?php endif; ?>
                </div>
                <div class="col-sm-6">
                    <label for="reg-confirm" class="form-label">Konfirmasi kata sandi</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="reg-confirm" class="form-control <?= $field_errors['confirm_password'] ? 'is-invalid' : '' ?>" placeholder="Ulangi sandi" required autocomplete="new-password" aria-describedby="reg-confirm-err" aria-invalid="<?= $field_errors['confirm_password'] ? 'true' : 'false' ?>">
                        <button type="button" class="btn btn-cyber-outline" data-toggle-password="reg-confirm" aria-label="Tampilkan konfirmasi kata sandi" aria-pressed="false"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <?php if ($field_errors['confirm_password']): ?><div class="invalid-feedback d-block" id="reg-confirm-err"><?= htmlspecialchars($field_errors['confirm_password']) ?></div><?php endif; ?>
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
