<?php
/**
 * Register Page
 * WorkSpace Pro
 */

// Handle registration form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = sanitize($_POST['full_name'] ?? '');
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Mohon isi semua kolom yang wajib';
    } elseif ($password !== $confirmPassword) {
        $error = 'Kata sandi tidak cocok';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal 6 karakter';
    } else {
        $result = registerUser($username, $email, $password, $fullName);
        
        if ($result['success']) {
            login($result['user_id']);
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!-- Auth Page Wrapper -->
<div class="auth-page-wrapper">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon">📊</div>
                <h1>WorkSpace Pro</h1>
                <p>Buat akun Anda</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error mb-4">
                <span>⚠️</span>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="Pilih username" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Nama lengkap Anda">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" placeholder="Masukkan email Anda" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Konfirmasi Kata Sandi *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Masukkan kata sandi lagi" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    Daftar
                </button>
            </form>
            
            <div class="auth-footer">
                Sudah punya akun? 
                <a href="index.php?page=login">Masuk</a>
            </div>
        </div>
    </div>
</div>
