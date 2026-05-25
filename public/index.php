<?php
session_start();

if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_name'] = 'Rahiel';
        $_SESSION['user_initials'] = 'RH';
        $_SESSION['user_email'] = $email;

        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Mohon isi email dan password';
    }
}

function svgIcon(string $name, string $class = 'icon'): string
{
    $path = __DIR__ . '/../assets/icons/' . $name . '.svg';

    if (!file_exists($path)) {
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24"></svg>';
    }

    $svg = file_get_contents($path);

    if (preg_match('/\bclass="/', $svg)) {
        return preg_replace('/\bclass="/', 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' ', $svg, 1);
    }

    return preg_replace('/<svg\b/', '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"', $svg, 1);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Resurvey Alda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="page-background">
        <div class="login-container">
            <div class="brand-header">
                <div class="brand-icon">
                    <?php echo svgIcon('home-icon', 'icon-xl'); ?>
                </div>
                <h1 class="brand-title">Resurvey Alda</h1>
            </div>

            <div class="login-card">
                <div class="login-header">
                    <h2>Masuk ke Akun Anda</h2>
                    <p>Silakan masuk untuk melanjutkan</p>
                </div>

                <?php if (isset($error)): ?>
                    <div
                        style="padding: 12px; background-color: #FEE2E2; color: var(--error); border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <!-- GANTI MENJADI MENGGUNAKAN NIK -->
                        <label class="form-label" for="email">NIK</label>
                        <div class="input-wrapper">
                            <svg class="icon input-icon" viewBox="0 0 24 24" style="color: var(--text-muted);">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                </path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="Masukkan email Anda" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Kata Sandi</label>
                        <div class="input-wrapper">
                            <svg class="icon input-icon" viewBox="0 0 24 24" style="color: var(--text-muted);">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Masukkan kata sandi" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg id="toggleIcon" class="icon" viewBox="0 0 24 24">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <a href="#" class="forgot-password">Lupa kata sandi?</a>

                    <button type="submit" class="btn-login" id="submitBtn">
                        Masuk
                    </button>
                </form>
            </div>

            <div class="login-footer">
                &copy; 2026 Suzuki Finance Indonesia.
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
            } else {
                input.type = 'password';
                icon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.form-input').forEach(function (input) {
                input.addEventListener('focus', function () {
                    const icon = this.closest('.input-wrapper').querySelector('.input-icon');
                    if (icon) icon.style.color = 'var(--primary-light)';
                });

                input.addEventListener('blur', function () {
                    const icon = this.closest('.input-wrapper').querySelector('.input-icon');
                    if (icon) icon.style.color = 'var(--text-muted)';
                });
            });

            document.getElementById('loginForm').addEventListener('submit', function () {
                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.textContent = 'Memuat...';
            });
        });
    </script>
</body>

</html>