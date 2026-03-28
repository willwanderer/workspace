<?php
/**
 * Login Page
 * WorkSpace Pro
 */

// Handle login form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Mohon isi semua kolom';
    } else {
        $result = loginUser($email, $password);
        
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
                <p>Dashboard Manajemen Tugas & Tempat Kerja</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error mb-4">
                <span>⚠️</span>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- <div class="demo-credentials">
                <strong>Akun Demo:</strong>
                <p>Email: <code>admin@workspace.local</code></p>
                <p>Kata Sandi: <code>admin123</code></p>
            </div> -->
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Masukkan email Anda" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kata Sandi</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi Anda" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    Masuk
                </button>
            </form>
            
            <div class="auth-footer">
                Belum punya akun? 
                <a href="index.php?page=register">Daftar</a>
            </div>
        </div>
    </div>
</div>
