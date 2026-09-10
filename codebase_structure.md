# Codebase Web App - Resurvey Alda

---

## 1. dashboard.php
```php
<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/database.php';
$db = new Database();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['user_nik'])) {
    header('Location: login.php');
    exit();
}

$nik = $_SESSION['user_nik'];
$userName = 'User';
$row_pic = $db->getPicByNik($nik);

if ($row_pic) {
    if ((int) $row_pic['IS_ACTIVE'] === 0) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $userName = $row_pic['NAMA'];
    $_SESSION['user_name'] = $userName;
} else {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$count_baru = 0;
$count_proses = 0;
$count_berjalan = 0;

$row_summary = $db->getPicTasklistSummary($nik);

if ($row_summary) {
    $count_baru = (int) ($row_summary['TUGAS_BARU'] ?? 0);
    $count_proses = (int) ($row_summary['TUGAS_PROSES'] ?? 0);
    $count_berjalan = (int) ($row_summary['TUGAS_BERJALAN'] ?? 0);
}

function svgIcon(string $name, string $class = 'icon'): string
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';
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
    <title>Dashboard - Resurvey Alda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="page-background">
        <nav class="navbar">
            <div class="navbar-left"></div>
            <h1 class="navbar-title">Resurvey Alda</h1>
            <div class="navbar-right">
                <button class="nav-icon-btn"
                    onclick="if(confirm('Yakin ingin keluar dari sistem?')) window.location.href='logout.php'">
                    <?php echo svgIcon('logout-icon', 'icon task-icon'); ?>
                </button>
            </div>
        </nav>

        <div class="dashboard-content">
            <div class="greeting-section">
                <p class="greeting-text">Selamat Datang,</p>
                <h2 class="greeting-name"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>

            <div class="task-buttons">
                <a href="tugas-baru.php" class="task-button">
                    <?php echo svgIcon('tugas-baru-icon', 'icon task-icon'); ?>
                    <span class="task-label">Penugasan Baru</span>
                    <span class="task-badge"><?php echo $count_baru; ?></span>
                    <svg class="icon task-chevron" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
                <a href="tugas-proses.php" class="task-button">
                    <?php echo svgIcon('tugas-proses-icon', 'icon task-icon'); ?>
                    <span class="task-label">Antrian Kunjungan</span>
                    <span class="task-badge"><?php echo $count_proses; ?></span>
                    <svg class="icon task-chevron" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
                <a href="tugas-sedang-berjalan.php" class="task-button">
                    <?php echo svgIcon('tugas-berjalan-icon', 'icon task-icon'); ?>
                    <span class="task-label">Pelaksanaan Kunjungan</span>
                    <span class="task-badge"><?php echo $count_berjalan; ?></span>
                    <svg class="icon task-chevron" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>

            <div class="secondary-buttons">
                <a href="upload.php" class="secondary-button">
                    <?php echo svgIcon('upload-icon', 'icon task-icon'); ?>
                    <span class="secondary-label">Upload</span>
                </a>
                <a href="selesai.php" class="secondary-button">
                    <?php echo svgIcon('selesai-icon', 'icon task-icon'); ?>
                    <span class="secondary-label">Selesai</span>
                </a>
            </div>
        </div>
    </div>
</body>

</html>
```

## 2. database.php
```php
<?php
class Database
{
    private $conn;

    public function __construct()
    {
        $config_serverName = '172.16.1.76';
        $config_db = 'MOBILE_COLLECTION';
        $config_uid = 'sa';
        $config_pwd = 'user.200';

        $connectionInfo = [
            "Database" => $config_db,
            "UID" => $config_uid,
            "PWD" => $config_pwd,
        ];

        $this->conn = sqlsrv_connect($config_serverName, $connectionInfo);
        if ($this->conn === false) {
            die("Koneksi database gagal.");
        }
    }

    public function loginResurveyAlda($nik, $password)
    {
        $tsql = "{CALL SP_LOGIN_RESURVEY_ALDA(?, ?)}";
        $params = [[$nik, SQLSRV_PARAM_IN], [$password, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ? $row : null;
    }

    public function getPicByNik($nik)
    {
        $tsql = 'SELECT TOP 1 [NAMA], [IS_ACTIVE] FROM [dbo].[MASTER_ALDA_PIC] WHERE [NIK] = ?';
        $params = [[$nik, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ? $row : null;
    }

    public function getPicTasklistSummary($nik)
    {
        $tsql = '{CALL SP_ALDA_PIC_TASKLIST_SUMMARY(?)}';
        $params = [[$nik, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ? $row : null;
    }

    public function updateTaskStatus($penugasan_id, $nik, $status, $current_version)
    {
        $tsql = '{CALL SP_ALDA_PIC_UPDATE_STATUS(?, ?, ?, ?)}';
        $params = [
            [$penugasan_id, SQLSRV_PARAM_IN],
            [$nik, SQLSRV_PARAM_IN],
            [$status, SQLSRV_PARAM_IN],
            [$current_version, SQLSRV_PARAM_IN],
        ];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $result ? $result : null;
    }

    public function submitResurveyResult($penugasan_id, $nik, $current_version, $data)
    {
        $tsql = '{CALL SP_ALDA_SUBMIT_RESURVEY_RESULT(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}';
        $params = [
            [$penugasan_id, SQLSRV_PARAM_IN],
            [$nik, SQLSRV_PARAM_IN],
            [$current_version, SQLSRV_PARAM_IN],
            [$data['lokasi_kunjungan'], SQLSRV_PARAM_IN],
            [$data['revisi_alamat_kunjungan'], SQLSRV_PARAM_IN],
            [$data['bertemu_dengan'], SQLSRV_PARAM_IN],
            [$data['pihak_non_debitur'], SQLSRV_PARAM_IN],
            [$data['keberadaan_unit'], SQLSRV_PARAM_IN],
            [$data['pernah_dikunjungi'], SQLSRV_PARAM_IN],
            [$data['penemuan_fraud'], SQLSRV_PARAM_IN],
            [$data['informasi_fraud'], SQLSRV_PARAM_IN],
            [$data['hasil_kunjungan'], SQLSRV_PARAM_IN],
            [$data['foto_path'], SQLSRV_PARAM_IN],
        ];

        $stmt = sqlsrv_query($this->conn, $tsql, $params);

        // Scope 2: Error detail extraction untuk memicu rollback physical file secara deterministik.
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $error_message = (is_array($errors) && isset($errors[0]['message'])) ? $errors[0]['message'] : 'Database transaction failed.';
            return ['success' => false, 'message' => '[DB ERROR] ' . $error_message];
        }

        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $result ? $result : ['success' => false, 'message' => 'Empty response dari Database.'];
    }

    public function getTasks($nik, $status)
    {
        $tsql = '{CALL SP_ALDA_PIC_GET_TASKS(?, ?)}';
        $params = [[$nik, SQLSRV_PARAM_IN], [$status, SQLSRV_PARAM_IN]];
        $stmt = sqlsrv_query($this->conn, $tsql, $params);
        if ($stmt === false)
            return false;

        $tasks = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        return $tasks;
    }

    public function close()
    {
        if ($this->conn) {
            sqlsrv_close($this->conn);
        }
    }
}
```

## 3. login.php
```php
<?php

session_start();

require_once __DIR__ . '/database.php';

$db = new Database();

if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = trim($_POST['nik'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($nik) && !empty($password)) {
        $row = $db->loginResurveyAlda($nik, $password);

        if ($row === false) {
            $error = 'Terjadi kesalahan pada database. Silakan coba lagi.';
        } elseif ($row !== null) {
            if ($row['LoginStatus'] == 1) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_nik'] = $row['NIK'];
                $_SESSION['user_name'] = $row['NAMA'];

                header('Location: dashboard.php');
                exit();
            } else {
                $error = $row['Message'];
            }
        } else {
            $error = 'Gagal memproses respons dari server.';
        }
    } else {
        $error = 'Mohon isi NIK dan kata sandi.';
    }
}

function svgIcon(string $name, string $class = 'icon'): string
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';

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
                    <p>Gunakan NIK Anda untuk melanjutkan</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div
                        style="padding: 12px; background-color: #FEE2E2; color: var(--error); border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center;">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="nik">NIK</label>
                        <div class="input-wrapper">
                            <svg class="icon input-icon" viewBox="0 0 24 24" style="color: var(--text-muted);">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <input type="text" id="nik" name="nik" class="form-input" placeholder="Masukkan NIK Anda"
                                required>
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
                &copy; <?php echo date('Y'); ?> Suzuki Finance Indonesia.
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
```

## 4. selesai.php
```php
<?php
session_start();
require_once __DIR__ . '/database.php';
$db = new Database();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['user_nik'])) {
    header('Location: login.php');
    exit();
}

$nik = $_SESSION['user_nik'];
$tasks = [];
$error_message = '';

$tasks_result = $db->getTasks($nik, 'COMPLETED');
if ($tasks_result === false) {
    $error_message = 'Terjadi kesalahan saat mengambil data histori penugasan.';
} else {
    $tasks = $tasks_result;
}

function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function svgIcon($name, $class = 'icon')
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Selesai - Resurvey ALDA</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
            margin-top: 16px;
        }

        .divider {
            border: none;
            border-top: 1px dashed var(--border-subtle);
            margin: 24px 0;
        }

        .task-card.completed-card {
            border-left: 5px solid var(--success);
        }

        .system-notice {
            font-size: 12px;
            color: var(--error);
            font-style: italic;
        }

        .resurvey-id {
            font-family: 'Inter', monospace;
            font-size: 11px;
            color: var(--text-muted);
            display: block;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="page-background">
        <div class="mobile-wrapper">
            <div class="page-header">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <h1 class="page-title">Riwayat Selesai</h1>
            </div>
            <div class="task-list-container">
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks) && empty($error_message)): ?>
                    <div class="empty-state">
                        <?php echo svgIcon('empty-folder-icon', 'icon-2xl empty-icon'); ?>
                        <h3>Belum Ada Data</h3>
                        <p>Riwayat kunjungan yang telah selesai dilakukan akan tampil di sini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task):
                        $tanggalSelesai = isset($task['UPDATED_AT']) && ($task['UPDATED_AT'] instanceof DateTime) ? $task['UPDATED_AT']->format('d M Y, H:i') : (isset($task['UPDATED_AT']) ? $task['UPDATED_AT'] : '-');
                        $resurveyId = isset($task['RESURVEY_ID']) && $task['RESURVEY_ID'] !== null ? $task['RESURVEY_ID'] : 'NULL (Data Terdahulu)';

                        $payload = json_encode([
                            'resurvey_id' => $resurveyId,
                            'contract_no' => isset($task['CONTRACT_NO']) ? $task['CONTRACT_NO'] : '-',
                            'customer' => isset($task['CUSTOMER_NAME']) ? $task['CUSTOMER_NAME'] : '-',
                            'vehicle' => isset($task['KENDARAAN']) ? $task['KENDARAAN'] : '-',
                            'tanggal' => $tanggalSelesai,
                            'lokasi' => isset($task['LOKASI_KUNJUNGAN']) ? $task['LOKASI_KUNJUNGAN'] : '-',
                            'revisi_alamat' => isset($task['REVISI_ALAMAT_KUNJUNGAN']) ? $task['REVISI_ALAMAT_KUNJUNGAN'] : '',
                            'bertemu' => isset($task['BERTEMU_DENGAN']) ? $task['BERTEMU_DENGAN'] : '-',
                            'pihak_non_debitur' => isset($task['PIHAK_NON_DEBITUR']) ? $task['PIHAK_NON_DEBITUR'] : '-',
                            'unit' => isset($task['KEBERADAAN_UNIT']) ? $task['KEBERADAAN_UNIT'] : '-',
                            'pernah_dikunjungi' => isset($task['PERNAH_DIKUNJUNGI']) ? $task['PERNAH_DIKUNJUNGI'] : '-',
                            'fraud' => isset($task['PENEMUAN_FRAUD']) ? $task['PENEMUAN_FRAUD'] : '-',
                            'info_fraud' => isset($task['INFORMASI_FRAUD']) ? $task['INFORMASI_FRAUD'] : '',
                            'hasil' => isset($task['HASIL_KUNJUNGAN']) ? $task['HASIL_KUNJUNGAN'] : '-',
                            'foto_path' => isset($task['FOTO_PATH']) && !empty($task['FOTO_PATH']) ? $task['FOTO_PATH'] : null,
                        ]);
                        ?>
                        <div class="task-card completed-card">
                            <div class="task-header">
                                <span
                                    class="contract-no"><?php echo htmlspecialchars(isset($task['CONTRACT_NO']) ? $task['CONTRACT_NO'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="task-date">Tgl:
                                    <?php echo htmlspecialchars($tanggalSelesai, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <h3 class="customer-name">
                                <?php echo htmlspecialchars(isset($task['CUSTOMER_NAME']) ? $task['CUSTOMER_NAME'] : '-', ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <?php if (isset($task['KENDARAAN']) && trim($task['KENDARAAN']) !== ''): ?>
                                <div class="customer-vehicle">🚗
                                    <?php echo htmlspecialchars($task['KENDARAAN'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                <span>Status: Selesai Divalidasi</span>
                            </div>
                            <div class="action-group">
                                <button type="button" class="btn btn-outline" style="width: 100%;"
                                    onclick='openModal(<?php echo htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'); ?>)'>
                                    Lihat Hasil
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Detail Kunjungan</h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="info-group">
                    <span class="info-label">Nomor Kontrak</span>
                    <span class="info-value" id="mdlContract">-</span>
                    <span class="resurvey-id" id="mdlResurveyId">ID: -</span>
                </div>
                <div class="info-group"><span class="info-label">Nama Nasabah</span><span class="info-value"
                        id="mdlCustomer">-</span></div>
                <div class="info-group"><span class="info-label">Tipe Unit</span><span class="info-value"
                        id="mdlVehicle">-</span></div>
                <div class="info-group"><span class="info-label">Diselesaikan Pada</span><span class="info-value"
                        id="mdlTanggal">-</span></div>

                <hr class="divider">
                <h4 class="section-title" style="margin-top: 0; margin-bottom: 16px;">Laporan Observasi</h4>

                <!-- Rendering Area Multi Foto -->
                <div class="info-group" id="grpFoto" style="display: none; margin-bottom: 16px;">
                    <span class="info-label">Foto Bukti Kunjungan</span>
                    <div id="fotoContainer"></div>
                </div>

                <div class="info-group"><span class="info-label">Validasi Alamat</span><span class="info-value"
                        id="mdlLokasi">-</span></div>
                <div class="info-group" id="grpRevisiAlamat"
                    style="display: none; background: rgba(245, 158, 11, 0.1); border-color: var(--warning);">
                    <span class="info-label" style="color: var(--warning);">Revisi Alamat (Tidak Sesuai)</span>
                    <span class="info-value" id="mdlRevisiAlamat">-</span>
                </div>
                <div class="info-group"><span class="info-label">Bertemu Dengan</span><span class="info-value"
                        id="mdlBertemu">-</span></div>
                <div class="info-group" id="grpPihakNonDebitur" style="display: none;">
                    <span class="info-label">Pihak Non-Debitur</span>
                    <span class="info-value" id="mdlPihakNonDebitur">-</span>
                </div>
                <div class="info-group"><span class="info-label">Keberadaan Unit</span><span class="info-value"
                        id="mdlUnit">-</span></div>
                <div class="info-group"><span class="info-label">Nasabah Pernah Dikunjungi</span><span
                        class="info-value" id="mdlPernahDikunjungi">-</span></div>
                <div class="info-group"><span class="info-label">Indikasi Fraud</span><span class="info-value"
                        id="mdlFraud">-</span></div>
                <div class="info-group" id="grpInfoFraud"
                    style="display: none; background: rgba(239, 68, 68, 0.1); border-color: var(--error);">
                    <span class="info-label" style="color: var(--error);">Informasi Detail Fraud</span>
                    <span class="info-value" id="mdlInfoFraud">-</span>
                </div>
                <div class="info-group"
                    style="background: rgba(0, 180, 216, 0.08); border-color: var(--primary-light);">
                    <span class="info-label" style="color: var(--primary-mid);">Catatan Hasil Kunjungan</span>
                    <span class="info-value" id="mdlHasil" style="font-weight: 500;">-</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(data) {
            document.getElementById('mdlContract').innerText = data.contract_no;
            var mdlResurveyId = document.getElementById('mdlResurveyId');
            mdlResurveyId.innerText = 'ID Laporan: ' + data.resurvey_id;
            if (data.resurvey_id === 'NULL (Data Terdahulu)') { mdlResurveyId.classList.add('system-notice'); mdlResurveyId.style.marginTop = '4px'; mdlResurveyId.style.display = 'block'; } else { mdlResurveyId.classList.remove('system-notice'); }
            document.getElementById('mdlCustomer').innerText = data.customer; document.getElementById('mdlVehicle').innerText = data.vehicle; document.getElementById('mdlTanggal').innerText = data.tanggal;

            // Integrasi Parsing JSON Array untuk Multi-Foto
            var grpFoto = document.getElementById('grpFoto');
            var fotoContainer = document.getElementById('fotoContainer');
            fotoContainer.innerHTML = '';

            if (data.foto_path) {
                grpFoto.style.display = 'block';
                try {
                    var paths = JSON.parse(data.foto_path);
                    if (Array.isArray(paths)) {
                        paths.forEach(function (pathStr) {
                            var img = document.createElement('img'); img.src = pathStr; img.alt = "Bukti Kunjungan";
                            img.style = "max-width: 100%; border-radius: 8px; margin-top: 8px; display: block; border: 1px solid var(--border-subtle); margin-bottom: 8px;";
                            fotoContainer.appendChild(img);
                        });
                    }
                } catch (e) {
                    // Fallback to plain string rendering for backward-compatibility with previously saved single photos
                    var img = document.createElement('img'); img.src = data.foto_path; img.alt = "Bukti Kunjungan";
                    img.style = "max-width: 100%; border-radius: 8px; margin-top: 8px; display: block; border: 1px solid var(--border-subtle);";
                    fotoContainer.appendChild(img);
                }
            } else {
                grpFoto.style.display = 'none';
            }

            document.getElementById('mdlLokasi').innerText = data.lokasi;
            var grpRevisiAlamat = document.getElementById('grpRevisiAlamat');
            if (data.lokasi === 'Tidak Sesuai') { grpRevisiAlamat.style.display = 'block'; document.getElementById('mdlRevisiAlamat').innerText = data.revisi_alamat || '-'; } else { grpRevisiAlamat.style.display = 'none'; }
            document.getElementById('mdlBertemu').innerText = data.bertemu;
            var grpPihakNonDebitur = document.getElementById('grpPihakNonDebitur');
            if (data.bertemu && data.bertemu.indexOf('Non-Debitur') !== -1) { grpPihakNonDebitur.style.display = 'block'; document.getElementById('mdlPihakNonDebitur').innerText = data.pihak_non_debitur || '-'; } else { grpPihakNonDebitur.style.display = 'none'; }
            document.getElementById('mdlUnit').innerText = data.unit; document.getElementById('mdlPernahDikunjungi').innerText = data.pernah_dikunjungi; document.getElementById('mdlFraud').innerText = data.fraud;
            var grpInfoFraud = document.getElementById('grpInfoFraud');
            if (data.fraud === 'Ada') { grpInfoFraud.style.display = 'block'; document.getElementById('mdlInfoFraud').innerText = data.info_fraud || '-'; } else { grpInfoFraud.style.display = 'none'; }

            document.getElementById('mdlHasil').innerText = data.hasil;
            document.getElementById('detailModal').classList.add('active'); document.body.style.overflow = 'hidden';
        }
        function closeModal() { document.getElementById('detailModal').classList.remove('active'); document.body.style.overflow = 'auto'; }
        document.getElementById('detailModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
    </script>
</body>

</html>
```

## 5. tugas-baru.php
```php
<?php
session_start();
require_once __DIR__ . '/database.php';
$db = new Database();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['user_nik'])) {
    header('Location: login.php');
    exit();
}

$nik = $_SESSION['user_nik'];
$tasks = [];
$error_message = '';

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'proses_tugas') {
    $penugasan_id = isset($_POST['penugasan_id']) ? (int) trim((string) $_POST['penugasan_id']) : 0;
    $assign_version = isset($_POST['assign_version']) ? (int) trim((string) $_POST['assign_version']) : 0;

    if ($penugasan_id <= 0) {
        $error_message = "[ERROR] ID Penugasan tidak valid (Nilai: $penugasan_id).";
    } elseif ($assign_version <= 0) {
        $error_message = "[ERROR] Versi penugasan : $assign_version. Pastikan ASSIGN_VERSION tidak NULL/0.";
    } else {
        $result = $db->updateTaskStatus($penugasan_id, $nik, 'IN_PROGRESS', $assign_version);

        if ($result === false) {
            $error_message = '[ERROR] Terjadi kesalahan server.';
        } else {
            if ($result && (bool) $result['success'] === true) {
                $_SESSION['flash_success'] = '[SUCCESS] Penugasan telah diterima dan masuk ke antrian.';
                header('Location: tugas-proses.php');
                exit();
            } else {
                $error_message = isset($result['message']) ? $result['message'] : '[ERROR] Penugasan gagal diterima.';
            }
        }
    }
}

$tasks_result = $db->getTasks($nik, 'ASSIGNED');
if ($tasks_result === false) {
    $error_message = '[ERROR] Data penugasan gagal diproses.';
} else {
    $tasks = $tasks_result;
}

function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function svgIcon($name, $class = 'icon')
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daftar Penugasan</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="page-background">
        <div class="mobile-wrapper">
            <div class="page-header">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <h1 class="page-title">Penugasan Baru</h1>
            </div>

            <div class="task-list-container">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks) && empty($error_message)): ?>
                    <div class="empty-state">
                        <?php echo svgIcon('empty-folder-icon'); ?>
                        <h3>Tidak Ada Penugasan Baru</h3>
                        <p>Belum ada penugasan yang diberikan.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task):
                        $payload = json_encode([
                            'contract_no' => $task['CONTRACT_NO'],
                            'customer' => $task['CUSTOMER_NAME'],
                            'address' => $task['LEGAL_ADDRESS'],
                            'phone' => $task['CUSTOMER_PHONE'],
                            'vehicle' => $task['KENDARAAN'],
                            'amount' => formatRupiah($task['AMOUNT_TO_BE_PAID']),
                        ]);
                        ?>
                        <div class="task-card">
                            <div class="task-header">
                                <span
                                    class="contract-no"><?php echo htmlspecialchars($task['CONTRACT_NO'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="task-date">
                                    <?php echo ($task['TANGGAL_ASSIGN'] instanceof DateTime) ? $task['TANGGAL_ASSIGN']->format('d M Y H:i') : '-'; ?>
                                </span>
                            </div>
                            <h3 class="customer-name">
                                <?php echo htmlspecialchars($task['CUSTOMER_NAME'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <?php if (trim((string) $task['KENDARAAN']) !== ''): ?>
                                <div class="customer-vehicle">🚗
                                    <?php echo htmlspecialchars($task['KENDARAAN'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span><?php echo htmlspecialchars((string) $task['LEGAL_ADDRESS'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="action-group">
                                <button type="button" class="btn btn-outline"
                                    onclick='openModal(<?php echo htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'); ?>)'>Detail</button>
                                <form method="POST" style="flex: 1;"
                                    onsubmit="return confirm('Konfirmasi terima penugasan? Penugasan akan masuk ke dalam Antrian Kunjungan.');">
                                    <input type="hidden" name="action" value="proses_tugas">
                                    <input type="hidden" name="penugasan_id" value="<?php echo (int) $task['PENUGASAN_ID']; ?>">
                                    <input type="hidden" name="assign_version"
                                        value="<?php echo (int) $task['ASSIGN_VERSION']; ?>">
                                    <button type="submit" class="btn btn-primary" style="width: 100%;">Terima Penugasan</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Detail Nasabah</h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="info-group"><span class="info-label">Nomor Kontrak</span><span class="info-value"
                        id="mdlContract">-</span></div>
                <div class="info-group"><span class="info-label">Nama Nasabah</span><span class="info-value"
                        id="mdlCustomer">-</span></div>
                <div class="info-group"><span class="info-label">Alamat Nasabah</span><span class="info-value"
                        id="mdlAddress">-</span></div>
                <div class="info-group"><span class="info-label">Nomor Telepon</span><span class="info-value"
                        id="mdlPhone">-</span></div>
                <div class="info-group"><span class="info-label">Unit Kendaraan</span><span class="info-value"
                        id="mdlVehicle">-</span></div>
                <div class="info-group"><span class="info-label">Tagihan</span><span class="info-value"
                        style="color: var(--error);" id="mdlAmount">-</span></div>
            </div>
        </div>
    </div>
    <script>
        function openModal(data) {
            document.getElementById('mdlContract').innerText = data.contract_no;
            document.getElementById('mdlCustomer').innerText = data.customer;
            document.getElementById('mdlAddress').innerText = data.address;
            document.getElementById('mdlPhone').innerText = data.phone;
            document.getElementById('mdlVehicle').innerText = data.vehicle ? data.vehicle : '-';
            document.getElementById('mdlAmount').innerText = data.amount;
            document.getElementById('detailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() { document.getElementById('detailModal').classList.remove('active'); document.body.style.overflow = 'auto'; }
        document.getElementById('detailModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
    </script>
</body>

</html>
```

## 6. tugas-proses.php
```php
<?php
session_start();
require_once __DIR__ . '/database.php';
$db = new Database();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['user_nik'])) {
    header('Location: login.php');
    exit();
}

$nik = $_SESSION['user_nik'];
$tasks = [];
$error_message = '';

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'konfirmasi_tugas') {
    $penugasan_id = isset($_POST['penugasan_id']) ? (int) trim((string) $_POST['penugasan_id']) : 0;
    $assign_version = isset($_POST['assign_version']) ? (int) trim((string) $_POST['assign_version']) : 0;

    if ($penugasan_id <= 0) {
        $error_message = "Validasi gagal: ID Penugasan tidak valid (Nilai: $penugasan_id).";
    } elseif ($assign_version <= 0) {
        $error_message = "Validasi gagal: Versi penugasan kosong/korup (Nilai: $assign_version). Pastikan ASSIGN_VERSION di database tidak NULL/0.";
    } else {
        $result = $db->updateTaskStatus($penugasan_id, $nik, 'ON_SITE', $assign_version);

        if ($result === false) {
            $error_message = 'Terjadi kesalahan server saat mengubah status.';
        } else {
            if ($result && (bool) $result['success'] === true) {
                $_SESSION['flash_success'] = 'Kunjungan siap dimulai.';
                header('Location: tugas-sedang-berjalan.php');
                exit();
            } else {
                $error_message = isset($result['message']) ? $result['message'] : 'Gagal memproses tugas.';
            }
        }
    }
}

$tasks_result = $db->getTasks($nik, 'IN_PROGRESS');
if ($tasks_result === false) {
    $error_message = 'Terjadi kesalahan saat mengambil data penugasan.';
} else {
    $tasks = $tasks_result;
}

function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function svgIcon($name, $class = 'icon')
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Antrian Kunjungan</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="page-background">
        <div class="mobile-wrapper">
            <div class="page-header">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <h1 class="page-title">List Antrian Kunjungan</h1>
            </div>
            <div class="task-list-container">
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($tasks) && empty($error_message)): ?>
                    <div class="empty-state">
                        <?php echo svgIcon('tugas-proses-icon'); ?>
                        <h3>Tidak Ada Antrian Kunjungan</h3>
                        <p>Semua penugasan telah diselesaikan atau Anda belum menerima penugasan.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task):
                        $payload = json_encode([
                            'contract_no' => $task['CONTRACT_NO'],
                            'customer' => $task['CUSTOMER_NAME'],
                            'address' => $task['LEGAL_ADDRESS'],
                            'phone' => $task['CUSTOMER_PHONE'],
                            'vehicle' => $task['KENDARAAN'],
                            'amount' => formatRupiah($task['AMOUNT_TO_BE_PAID']),
                        ]);
                        ?>
                        <div class="task-card" style="border-left: 5px solid var(--warning);">
                            <div class="task-header">
                                <span
                                    class="contract-no"><?php echo htmlspecialchars($task['CONTRACT_NO'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="task-date">Tgl. Diterima:
                                    <?php echo ($task['UPDATED_AT'] instanceof DateTime) ? $task['UPDATED_AT']->format('d M Y') : '-'; ?></span>
                            </div>
                            <h3 class="customer-name">
                                <?php echo htmlspecialchars($task['CUSTOMER_NAME'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <?php if (trim((string) $task['KENDARAAN']) !== ''): ?>
                                <div class="customer-vehicle">🚗
                                    <?php echo htmlspecialchars($task['KENDARAAN'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span><?php echo htmlspecialchars((string) $task['LEGAL_ADDRESS'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="action-group">
                                <button type="button" class="btn btn-outline" style="flex: 1;"
                                    onclick='openModal(<?php echo htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'); ?>)'>Detail</button>
                                <form method="POST" style="flex: 1;"
                                    onsubmit="return confirm('Mulai pelaksanaan kunjungan ke nasabah ini?');">
                                    <input type="hidden" name="action" value="konfirmasi_tugas">
                                    <input type="hidden" name="penugasan_id" value="<?php echo (int) $task['PENUGASAN_ID']; ?>">
                                    <input type="hidden" name="assign_version"
                                        value="<?php echo (int) $task['ASSIGN_VERSION']; ?>">
                                    <button type="submit" class="btn btn-primary" style="width: 100%;">Mulai Kunjungan</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="detailModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Detail Nasabah</h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="info-group"><span class="info-label">Nomor Kontrak</span><span class="info-value"
                        id="mdlContract">-</span></div>
                <div class="info-group"><span class="info-label">Nama Nasabah</span><span class="info-value"
                        id="mdlCustomer">-</span></div>
                <div class="info-group"><span class="info-label">Alamat Nasabah</span><span class="info-value"
                        id="mdlAddress">-</span></div>
                <div class="info-group"><span class="info-label">Nomor Telepon</span><span class="info-value"
                        id="mdlPhone">-</span></div>
                <div class="info-group"><span class="info-label">Unit Kendaraan</span><span class="info-value"
                        id="mdlVehicle">-</span></div>
                <div class="info-group"><span class="info-label">Tagihan</span><span class="info-value"
                        style="color: var(--error);" id="mdlAmount">-</span></div>
            </div>
        </div>
    </div>
    <script>
        function openModal(data) {
            document.getElementById('mdlContract').innerText = data.contract_no;
            document.getElementById('mdlCustomer').innerText = data.customer;
            document.getElementById('mdlAddress').innerText = data.address;
            document.getElementById('mdlPhone').innerText = data.phone;
            document.getElementById('mdlVehicle').innerText = data.vehicle ? data.vehicle : '-';
            document.getElementById('mdlAmount').innerText = data.amount;
            document.getElementById('detailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() { document.getElementById('detailModal').classList.remove('active'); document.body.style.overflow = 'auto'; }
        document.getElementById('detailModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
    </script>
</body>

</html>
```

## 7. tugas-sedang-berjalan.php
```php
<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/database.php';
$db = new Database();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['user_nik'])) {
    header('Location: login.php');
    exit();
}

$nik = $_SESSION['user_nik'];
$tasks = [];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_resurvey') {
    $penugasan_id = isset($_POST['penugasan_id']) ? (int) trim((string) $_POST['penugasan_id']) : 0;
    $assign_version = isset($_POST['assign_version']) ? (int) trim((string) $_POST['assign_version']) : 0;

    if ($penugasan_id <= 0 || $assign_version <= 0) {
        $error_message = "Validasi form gagal! (ID: $penugasan_id | Versi: $assign_version). Periksa integrity data.";
    } else {
        $data = [
            'lokasi_kunjungan' => $_POST['lokasi_kunjungan'] ?? '',
            'revisi_alamat_kunjungan' => $_POST['revisi_alamat_kunjungan'] ?? '',
            'bertemu_dengan' => $_POST['bertemu_dengan'] ?? '',
            'pihak_non_debitur' => $_POST['pihak_non_debitur'] ?? '',
            'keberadaan_unit' => $_POST['keberadaan_unit'] ?? '',
            'pernah_dikunjungi' => $_POST['pernah_dikunjungi'] ?? '',
            'penemuan_fraud' => $_POST['penemuan_fraud'] ?? '',
            'informasi_fraud' => $_POST['informasi_fraud'] ?? '',
            'hasil_kunjungan' => $_POST['hasil_kunjungan'] ?? '',
        ];

        if ($data['bertemu_dengan'] === 'Debitur')
            $data['pihak_non_debitur'] = 'Bertemu Debitur';
        if ($data['penemuan_fraud'] === 'Tidak Ada')
            $data['informasi_fraud'] = 'Tidak Ada Fraud';
        if ($data['lokasi_kunjungan'] === 'Sesuai')
            $data['revisi_alamat_kunjungan'] = 'Alamat Kunjungan Valid';

        // === IMPLEMENTASI MULTIPLE FILE UPLOAD SECURE ===
        $foto_paths = [];
        $upload_error = false;
        $upload_dir = __DIR__ . '/uploads/resurvey/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (isset($_FILES['foto_bukti']) && !empty($_FILES['foto_bukti']['name'][0])) {
            $files = $_FILES['foto_bukti'];
            $file_count = count($files['name']);

            if ($file_count > 5) {
                $error_message = 'Maksimal upload adalah 5 foto.';
                $upload_error = true;
            } else {
                for ($i = 0; $i < $file_count; $i++) {
                    $tmp_name = $files['tmp_name'][$i];
                    $error = $files['error'][$i];
                    $name = $files['name'][$i];
                    $size = $files['size'][$i];

                    if ($error === UPLOAD_ERR_NO_FILE)
                        continue;

                    if ($error !== UPLOAD_ERR_OK) {
                        $error_message = 'Gagal upload pada file ke-' . ($i + 1) . ' Error code: ' . $error;
                        $upload_error = true;
                        break;
                    }

                    // --- PERBAIKAN: MIME Type Verification dengan Fallback ---
                    $mime = false;
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmp_name);
                        finfo_close($finfo);
                    } elseif (function_exists('mime_content_type')) {
                        $mime = mime_content_type($tmp_name);
                    } else {
                        // Keterbatasan sistem: ekstensi fileinfo tidak ada, fallback ke $_FILES['type']
                        $mime = $files['type'][$i];
                    }

                    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/jpg'])) {
                        $error_message = 'MIME Type tidak valid: ' . $mime . ' (Harap unggah JPG/PNG nyata)';
                        $upload_error = true;
                        break;
                    }

                    if ($size > 5 * 1024 * 1024) {
                        $error_message = 'Ukuran file ' . htmlspecialchars($name) . ' melebihi 5MB.';
                        $upload_error = true;
                        break;
                    }

                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $error_message = 'Ekstensi file tidak valid.';
                        $upload_error = true;
                        break;
                    }

                    $filename = 'RESURVEY_' . $penugasan_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $target_file = $upload_dir . $filename;

                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $foto_paths[] = 'uploads/resurvey/' . $filename;
                    } else {
                        $error_message = 'Gagal menyimpan file secara sistem.';
                        $upload_error = true;
                        break;
                    }
                }
            }
        } else {
            $error_message = 'Minimal 1 Foto bukti kunjungan wajib dilampirkan.';
            $upload_error = true;
        }

        if (!$upload_error && empty($foto_paths)) {
            $error_message = 'Terjadi kesalahan, tidak ada foto yang terproses.';
            $upload_error = true;
        }

        if (!$upload_error) {
            // Encode sebagai JSON Array (Scope 1 deterministic bind)
            $data['foto_path'] = json_encode($foto_paths);

            // Transaksi Database
            $result = $db->submitResurveyResult($penugasan_id, $nik, $assign_version, $data);

            if ($result && (bool) ($result['success'] ?? false) === true) {
                $_SESSION['flash_success'] = 'Laporan resurvey dan foto bukti berhasil disimpan.';
                header('Location: selesai.php');
                exit();
            } else {
                // Scope 2: Rollback Physical File untuk mencegah Orphan records
                foreach ($foto_paths as $path) {
                    if (file_exists(__DIR__ . '/' . $path))
                        unlink(__DIR__ . '/' . $path);
                }
                $error_message = $result['message'] ?? 'Gagal menyimpan laporan ke database.';
            }
        }
    }
}

$tasks_result = $db->getTasks($nik, 'ON_SITE');
if ($tasks_result === false) {
    $error_message = 'Terjadi kesalahan saat mengambil data penugasan berjalan.';
} else {
    $tasks = $tasks_result;
}

function formatRupiah(mixed $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function svgIcon(string $name, string $class = 'icon'): string
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pelaksanaan On-Site - Resurvey ALDA</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .section-title {
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
            margin-top: 16px;
        }

        .divider {
            border: none;
            border-top: 1px dashed var(--border-subtle);
            margin: 24px 0;
        }

        .form-file-input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            background: #fff;
        }
    </style>
</head>

<body>
    <div class="page-background">
        <div class="mobile-wrapper">
            <div class="page-header">
                <button class="back-btn" onclick="window.location.href='dashboard.php'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                </button>
                <h1 class="page-title">Pelaksanaan Resurvey Nasabah</h1>
            </div>
            <div class="task-list-container">
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks) && empty($error_message)): ?>
                    <div class="empty-state">
                        <?php echo svgIcon('tugas-berjalan-icon'); ?>
                        <h3>Tidak Ada Kunjungan Aktif</h3>
                        <p>Belum ada proses kunjungan yang sedang berlangsung.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task):
                        $payload = json_encode([
                            'id' => $task['PENUGASAN_ID'],
                            'assign_version' => $task['ASSIGN_VERSION'],
                            'contract_no' => $task['CONTRACT_NO'],
                            'customer' => $task['CUSTOMER_NAME'],
                            'vehicle' => $task['KENDARAAN'],
                            'amount' => formatRupiah($task['AMOUNT_TO_BE_PAID']),
                        ]);
                        ?>
                        <div class="task-card">
                            <div class="task-header">
                                <span
                                    class="contract-no"><?php echo htmlspecialchars($task['CONTRACT_NO'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span
                                    class="task-date"><?php echo htmlspecialchars(formatRupiah($task['AMOUNT_TO_BE_PAID']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <h3 class="customer-name">
                                <?php echo htmlspecialchars($task['CUSTOMER_NAME'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if (trim((string) $task['KENDARAAN']) !== ''): ?>
                                <div class="customer-vehicle">🚗
                                    <?php echo htmlspecialchars($task['KENDARAAN'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span><?php echo htmlspecialchars((string) $task['LEGAL_ADDRESS'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="action-group">
                                <button type="button" class="btn btn-primary" style="width: 100%;"
                                    onclick='openModal(<?php echo htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'); ?>)'>
                                    Mulai Resurvey
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Form Kunjungan Resurvey</h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <h4 class="section-title" style="margin-top: 0;">Detail Nasabah</h4>
                <div class="info-group"><span class="info-label">Nomor Kontrak</span><span class="info-value"
                        id="mdlContract">-</span></div>
                <div class="info-group"><span class="info-label">Nama Nasabah</span><span class="info-value"
                        id="mdlCustomer">-</span></div>
                <div class="info-group"><span class="info-label">Tipe Unit</span><span class="info-value"
                        id="mdlVehicle">-</span></div>
                <div class="info-group"><span class="info-label">Tagihan</span><span class="info-value"
                        style="color: var(--error);" id="mdlAmount">-</span></div>

                <hr class="divider">
                <h4 class="section-title">Hasil Kunjungan Resurvey</h4>

                <form id="resurveyForm" method="POST" action="" enctype="multipart/form-data" novalidate
                    onsubmit="validateAndSubmit(event)">
                    <input type="hidden" name="action" value="submit_resurvey">
                    <input type="hidden" name="penugasan_id" id="mdlPenugasanId">
                    <input type="hidden" name="assign_version" id="mdlAssignVersion">

                    <div class="form-group">
                        <label class="form-label">Foto Bukti Kunjungan (Maks 5, Wajib)</label>
                        <input type="file" name="foto_bukti[]" id="fotoBukti" class="form-file-input"
                            accept="image/png, image/jpeg, image/jpg" capture="environment" multiple required>
                        <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">Pilih
                            satu atau lebih file. Format JPG/PNG, 5MB/file.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Validasi Alamat Kunjungan</label>
                        <div class="input-wrapper">
                            <select name="lokasi_kunjungan" id="lokasiKunjungan" class="form-input custom-select-source"
                                required onchange="toggleRevisiAlamat()">
                                <option value="">-- Pilih --</option>
                                <option value="Sesuai">Sesuai</option>
                                <option value="Tidak Sesuai">Tidak Sesuai</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="revisiAlamatGroup" style="display: none;">
                        <label class="form-label">Revisi Alamat Kunjungan</label>
                        <textarea name="revisi_alamat_kunjungan" id="revisiAlamat" class="form-input"
                            style="height: 80px; padding: 12px; resize: none;"
                            placeholder="Masukkan alamat yang benar"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bertemu Dengan</label>
                        <div class="input-wrapper">
                            <select name="bertemu_dengan" id="bertemuDengan" class="form-input custom-select-source"
                                required onchange="togglePihakNonDebitur()">
                                <option value="">-- Pilih --</option>
                                <option value="Debitur">Debitur</option>
                                <option value="Non-Debitur">Non-Debitur</option>
                                <option value="Tidak Bertemu Siapapun">Tidak Bertemu Siapapun</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="pihakNonDebiturGroup" style="display: none;">
                        <label class="form-label">Pihak Non Debitur</label>
                        <div class="input-wrapper">
                            <select name="pihak_non_debitur" id="pihakNonDebitur"
                                class="form-input custom-select-source">
                                <option value="">-- Pilih --</option>
                                <option value="Orang Tua">Orang Tua</option>
                                <option value="Suami/Istri">Suami/Istri</option>
                                <option value="Anak">Anak</option>
                                <option value="Saudara/Kerabat">Saudara/Kerabat</option>
                                <option value="Tetangga">Tetangga</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Keberadaan Unit</label>
                        <div class="input-wrapper">
                            <select name="keberadaan_unit" class="form-input custom-select-source" required>
                                <option value="">-- Pilih --</option>
                                <option value="Ada">Ada</option>
                                <option value="Tidak Ada">Tidak Ada</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nasabah Pernah Dikunjungi</label>
                        <div class="input-wrapper">
                            <select name="pernah_dikunjungi" class="form-input custom-select-source" required>
                                <option value="">-- Pilih --</option>
                                <option value="Sudah">Sudah</option>
                                <option value="Belum">Belum</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Penemuan Fraud</label>
                        <div class="input-wrapper">
                            <select name="penemuan_fraud" id="penemuanFraud" class="form-input custom-select-source"
                                required onchange="toggleFraudInfo()">
                                <option value="">-- Pilih --</option>
                                <option value="Ada">Ada</option>
                                <option value="Tidak Ada">Tidak Ada</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="fraudInfoGroup" style="display: none;">
                        <label class="form-label">Informasi Fraud</label>
                        <textarea name="informasi_fraud" id="informasiFraud" class="form-input"
                            style="height: 80px; padding: 12px; resize: none;"
                            placeholder="Lampirkan detail fraud yang Anda temukan."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasil Kunjungan</label>
                        <textarea name="hasil_kunjungan" class="form-input"
                            style="height: 80px; padding: 12px; resize: none;" required
                            placeholder="Lampirkan hasil kunjungan yang Anda lakukan."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">Simpan
                        Laporan</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => { initCustomSelects(); });
        function initCustomSelects() {
            const selects = document.querySelectorAll('.custom-select-source');
            selects.forEach(select => {
                if (select.nextElementSibling && select.nextElementSibling.classList.contains('custom-select-wrapper')) { select.nextElementSibling.remove(); }
                const wrapper = document.createElement('div'); wrapper.className = 'custom-select-wrapper';
                const trigger = document.createElement('div'); trigger.className = 'custom-select-trigger';
                const textSpan = document.createElement('span'); textSpan.className = 'trigger-text';
                textSpan.textContent = select.options[select.selectedIndex]?.text || '-- Pilih --';
                if (!select.value) trigger.classList.add('placeholder');
                const arrow = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                arrow.setAttribute("class", "arrow"); arrow.setAttribute("viewBox", "0 0 24 24");
                arrow.innerHTML = '<polyline points="6 9 12 15 18 9"></polyline>';
                trigger.appendChild(textSpan); trigger.appendChild(arrow); wrapper.appendChild(trigger);
                const optionsContainer = document.createElement('div'); optionsContainer.className = 'custom-options';
                Array.from(select.options).forEach((option, index) => {
                    if (option.value === "") return;
                    const opt = document.createElement('div'); opt.className = 'custom-option'; opt.textContent = option.text;
                    if (select.selectedIndex === index) opt.classList.add('selected');
                    opt.addEventListener('click', function (e) {
                        e.stopPropagation(); select.selectedIndex = index; textSpan.textContent = option.text; trigger.classList.remove('placeholder');
                        Array.from(optionsContainer.children).forEach(c => c.classList.remove('selected')); this.classList.add('selected'); wrapper.classList.remove('open');
                        select.dispatchEvent(new Event('change', { bubbles: true })); trigger.style.borderColor = 'var(--border-subtle)';
                    });
                    optionsContainer.appendChild(opt);
                });
                wrapper.appendChild(optionsContainer); select.parentNode.insertBefore(wrapper, select.nextSibling);
                trigger.addEventListener('click', function (e) {
                    e.stopPropagation(); const isOpen = wrapper.classList.contains('open'); document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
                    if (!isOpen) wrapper.classList.add('open');
                });
            });
            document.addEventListener('click', function () { document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open')); });
        }
        function resetSingleCustomSelect(selectId) {
            const select = document.getElementById(selectId);
            if (select) {
                select.selectedIndex = 0; const wrapper = select.nextElementSibling;
                if (wrapper && wrapper.classList.contains('custom-select-wrapper')) {
                    const trigger = wrapper.querySelector('.custom-select-trigger'); const textSpan = trigger.querySelector('.trigger-text'); const options = wrapper.querySelectorAll('.custom-option');
                    textSpan.textContent = select.options[0].text; trigger.classList.add('placeholder'); trigger.style.borderColor = 'var(--border-subtle)';
                    options.forEach(opt => opt.classList.remove('selected'));
                }
            }
        }
        function resetCustomSelects() {
            const selects = document.querySelectorAll('.custom-select-source');
            selects.forEach(select => {
                select.selectedIndex = 0; const wrapper = select.nextElementSibling;
                if (wrapper && wrapper.classList.contains('custom-select-wrapper')) {
                    const trigger = wrapper.querySelector('.custom-select-trigger'); const textSpan = trigger.querySelector('.trigger-text'); const options = wrapper.querySelectorAll('.custom-option');
                    textSpan.textContent = select.options[0].text; trigger.classList.add('placeholder'); trigger.style.borderColor = 'var(--border-subtle)';
                    options.forEach(opt => opt.classList.remove('selected'));
                }
            });
            document.querySelectorAll('#resurveyForm .form-input:not(.custom-select-source), #resurveyForm .form-file-input').forEach(input => { input.style.borderColor = 'var(--border-subtle)'; });
        }
        function openModal(data) {
            document.getElementById('mdlPenugasanId').value = data.id; document.getElementById('mdlAssignVersion').value = data.assign_version;
            document.getElementById('mdlContract').innerText = data.contract_no; document.getElementById('mdlCustomer').innerText = data.customer;
            document.getElementById('mdlVehicle').innerText = data.vehicle || '-'; document.getElementById('mdlAmount').innerText = data.amount;
            document.getElementById('resurveyForm').reset(); resetCustomSelects(); toggleFraudInfo(); toggleRevisiAlamat(); togglePihakNonDebitur();
            document.getElementById('detailModal').classList.add('active'); document.body.style.overflow = 'hidden';
        }
        function closeModal() { document.getElementById('detailModal').classList.remove('active'); document.body.style.overflow = 'auto'; }
        function toggleRevisiAlamat() {
            const lokasiSelect = document.getElementById('lokasiKunjungan'); const revisiGroup = document.getElementById('revisiAlamatGroup'); const revisiInput = document.getElementById('revisiAlamat');
            if (lokasiSelect.value === 'Tidak Sesuai') { revisiGroup.style.display = 'block'; revisiInput.setAttribute('required', 'true'); } else { revisiGroup.style.display = 'none'; revisiInput.removeAttribute('required'); revisiInput.value = ''; }
        }
        function togglePihakNonDebitur() {
            const bertemuSelect = document.getElementById('bertemuDengan'); const pihakGroup = document.getElementById('pihakNonDebiturGroup'); const pihakSelect = document.getElementById('pihakNonDebitur');
            if (bertemuSelect.value === 'Non-Debitur') { pihakGroup.style.display = 'block'; pihakSelect.setAttribute('required', 'true'); } else { pihakGroup.style.display = 'none'; pihakSelect.removeAttribute('required'); resetSingleCustomSelect('pihakNonDebitur'); }
        }
        function toggleFraudInfo() {
            const fraudSelect = document.getElementById('penemuanFraud'); const fraudInfoGroup = document.getElementById('fraudInfoGroup'); const fraudInfoInput = document.getElementById('informasiFraud');
            if (fraudSelect.value === 'Ada') { fraudInfoGroup.style.display = 'block'; fraudInfoInput.setAttribute('required', 'true'); } else { fraudInfoGroup.style.display = 'none'; fraudInfoInput.removeAttribute('required'); fraudInfoInput.value = ''; }
        }
        document.getElementById('detailModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
        function validateAndSubmit(e) {
            e.preventDefault(); const form = document.getElementById('resurveyForm'); let isValid = true; let firstError = null;
            const fields = form.querySelectorAll('input[required], select[required], textarea[required]');
            fields.forEach(input => {
                let isEmpty = input.type === 'file' ? input.files.length === 0 : !input.value.trim();
                if (isEmpty) {
                    isValid = false; if (!firstError) firstError = input;
                    if (input.classList.contains('custom-select-source')) {
                        const wrapper = input.nextElementSibling; if (wrapper) wrapper.querySelector('.custom-select-trigger').style.borderColor = 'var(--error)';
                    } else { input.style.borderColor = 'var(--error)'; }
                } else {
                    if (input.classList.contains('custom-select-source')) {
                        const wrapper = input.nextElementSibling; if (wrapper) wrapper.querySelector('.custom-select-trigger').style.borderColor = 'var(--border-subtle)';
                    } else { input.style.borderColor = 'var(--border-subtle)'; }
                }
            });
            if (isValid) {
                if (confirm('Simpan laporan resurvey beserta foto kunjungan? Penugasan akan ditandai sebagai selesai.')) { form.submit(); }
            } else {
                alert('Peringatan: Harap lengkapi semua field yang wajib diisi, termasuk Foto Kunjungan.');
                if (firstError) {
                    if (firstError.classList.contains('custom-select-source')) {
                        const wrapper = firstError.nextElementSibling; if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else { firstError.focus(); firstError.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                }
            }
        }
    </script>
</body>

</html>
```

# Codebase Web Office - (PHP 5.6)

---

## 1. penugasan-alda-resurvey.php
```php
<!-- THIS FILE USE PHP 5.6.32 VERSION -->

<?php
require_once '../config/connection.php';

function isTransientSqlServerError(array $errors)
{
	$transientSqlStates = ['08S01', '08001', 'HYT00', 'HYT01', '07008'];
	$transientPatterns = [
		'semaphore timeout',
		'communication link failure',
		'transport-level error',
		'general network error',
		'connection was forcibly closed',
		'tcp provider',
	];

	foreach ($errors as $error) {
		$sqlState = isset($error['SQLSTATE']) ? $error['SQLSTATE'] : '';
		$message = isset($error['message']) ? strtolower($error['message']) : '';

		if (in_array($sqlState, $transientSqlStates, true)) {
			return true;
		}

		foreach ($transientPatterns as $pattern) {
			if (strpos($message, $pattern) !== false) {
				return true;
			}
		}
	}

	return false;
}

function sqlsrvQueryWithRetry($conn, $sql, array $params = [], $maxAttempts = 3, $initialDelayMs = 300)
{
	$delayMs = $initialDelayMs;

	for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
		$stmt = sqlsrv_query($conn, $sql, $params);

		if ($stmt !== false) {
			return $stmt;
		}

		$errors = sqlsrv_errors();
		$isLastAttempt = $attempt === $maxAttempts;

		if ($isLastAttempt || !isTransientSqlServerError(is_array($errors) ? $errors : [])) {
			return false;
		}

		usleep($delayMs * 1000);
		$delayMs *= 2;
	}

	return false;
}

$usercreate = '';
if (!empty($_SESSION['username_cuser'])) {
	$usercreate = trim($_SESSION['username_cuser']);
} elseif (!empty($_POST['sid'])) {
	$usercreate = trim($_POST['sid']);
} elseif (!empty($_GET['sid'])) {
	$usercreate = trim($_GET['sid']);
}

$branchidcbg = isset($_SESSION['branch_cuser']) ? trim($_SESSION['branch_cuser']) : '';

$sidParam = $usercreate;
$no_kontrak = '';
$contract_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$no_kontrak = isset($_POST['no_kontrak']) ? trim($_POST['no_kontrak']) : '';
	$contract_status = isset($_POST['contract_status']) ? trim($_POST['contract_status']) : '';
} else {
	$no_kontrak = isset($_GET['no_kontrak']) ? trim($_GET['no_kontrak']) : '';
	$contract_status = isset($_GET['contract_status']) ? trim($_GET['contract_status']) : '';
}

$callStatus = 'SELECT DISTINCT CONTRACT_STATUS FROM MASTER_ALDA WHERE BRANCH_ID = ? ORDER BY CONTRACT_STATUS';
$execStatus = sqlsrvQueryWithRetry($conn, $callStatus, [[$branchidcbg, SQLSRV_PARAM_IN]]) or die(print_r(sqlsrv_errors(), true));

$dataStatus = [];
while ($row = sqlsrv_fetch_array($execStatus, SQLSRV_FETCH_ASSOC)) {
	$dataStatus[] = $row['CONTRACT_STATUS'];
}

$callPIC = '{call SP_ALDA_DROPDOWN_PIC(?)}';
$execPIC = sqlsrvQueryWithRetry($conn, $callPIC, [[$branchidcbg, SQLSRV_PARAM_IN]]) or die(print_r(sqlsrv_errors(), true));

$dataPIC = [];
while ($row = sqlsrv_fetch_array($execPIC, SQLSRV_FETCH_ASSOC)) {
	$dataPIC[] = $row;
}

usort($dataPIC, function ($a, $b) {
	$nameA = isset($a['DATA_PIC']) ? $a['DATA_PIC'] : '';
	$nameB = isset($b['DATA_PIC']) ? $b['DATA_PIC'] : '';
	return strcasecmp($nameA, $nameB);
});

// Lookup table (VALUE => DATA_PIC) used to resolve the assigned PIC's display
// name for the result table, since the form only posts back the PIC's VALUE.
$picMap = [];
foreach ($dataPIC as $picRow) {
	$picValueKey = isset($picRow['VALUE']) ? trim($picRow['VALUE']) : '';
	if ($picValueKey !== '') {
		$picMap[$picValueKey] = isset($picRow['DATA_PIC']) ? $picRow['DATA_PIC'] : '';
	}
}

$assignResults = [];
$totalSuccess = 0;
$totalFail = 0;

if (isset($_POST['action']) && $_POST['action'] === 'assign') {
	if ($usercreate === '') {
		$assignResults[] = [
			'kontrak' => '-',
			'success' => false,
			'message' => 'Identitas pengguna tidak ditemukan. Periksa konfigurasi sesi.',
			'submission_id' => null,
			'pic_name' => '-',
		];
		$totalFail++;
	} elseif (isset($_POST['checked']) && is_array($_POST['checked'])) {
		foreach ($_POST['checked'] as $key => $val) {
			if ($val !== '1') {
				continue;
			}

			$pic_nik = isset($_POST['pic'][$key]) ? trim($_POST['pic'][$key]) : '';
			$nomor_kontrak = isset($_POST['nomor_kontrak'][$key]) ? trim($_POST['nomor_kontrak'][$key]) : '';

			if ($pic_nik === '' || $nomor_kontrak === '') {
				continue;
			}

			$picName = isset($picMap[$pic_nik]) ? $picMap[$pic_nik] : $pic_nik;

			$callAssign = '{call SP_ALDA_SUBMIT_ASSIGN(?,?,?,?)}';
			$paramAssign = [
				[$usercreate, SQLSRV_PARAM_IN],
				[$pic_nik, SQLSRV_PARAM_IN],
				[$nomor_kontrak, SQLSRV_PARAM_IN],
				['Assigned via Web', SQLSRV_PARAM_IN],
			];

			$execAssign = sqlsrvQueryWithRetry($conn, $callAssign, $paramAssign);

			if ($execAssign === false) {
				$errs = sqlsrv_errors();
				$errMsg = (is_array($errs) && isset($errs[0]['message'])) ? $errs[0]['message'] : 'Query execution failed.';
				$assignResults[] = [
					'kontrak' => $nomor_kontrak,
					'success' => false,
					'message' => $errMsg,
					'submission_id' => null,
					'pic_name' => $picName,
				];
				$totalFail++;
				continue;
			}

			$spResult = sqlsrv_fetch_array($execAssign, SQLSRV_FETCH_ASSOC);

			if ($spResult === false || $spResult === null) {
				$assignResults[] = [
					'kontrak' => $nomor_kontrak,
					'success' => false,
					'message' => 'Stored procedure gagal dieksekusi.',
					'submission_id' => null,
					'pic_name' => $picName,
				];
				$totalFail++;
				continue;
			}

			$isOk = (int) $spResult['success'] === 1;
			$submissionId = ($isOk && isset($spResult['submission_id'])) ? $spResult['submission_id'] : null;

			$assignResults[] = [
				'kontrak' => $nomor_kontrak,
				'success' => $isOk,
				'message' => isset($spResult['message']) ? $spResult['message'] : '',
				'submission_id' => $submissionId,
				'pic_name' => $picName,
			];

			if ($isOk) {
				$totalSuccess++;
			} else {
				$totalFail++;
			}
		}
	}
}

function formatIDR($amount)
{
	if ($amount === null || $amount === '') {
		return '-';
	}

	return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

$filterBadges = [];

if ($no_kontrak !== '') {
	$filterBadges[] = ['label' => 'No. Kontrak', 'value' => $no_kontrak];
}

if ($contract_status !== '') {
	$filterBadges[] = ['label' => 'Status', 'value' => $contract_status];
}

$currentQuery = [];
if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
	parse_str($_SERVER['QUERY_STRING'], $currentQuery);
}
unset($currentQuery['no_kontrak']);
unset($currentQuery['contract_status']);
$currentQuery['sid'] = $sidParam;
$resetUrl = '?' . http_build_query($currentQuery);
?>

<style>
	/* Custom Select Professional UI untuk Desktop Dashboard */
	.custom-select-source {
		display: none !important;
	}

	.custom-select-wrapper {
		position: relative;
		width: 100%;
		user-select: none;
		font-size: 11px;
	}

	.custom-select-trigger {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		height: 34px;
		padding: 6px 12px;
		background-color: #fff;
		border: 1px solid #ccc;
		border-radius: 4px;
		cursor: pointer;
		color: #333;
		transition: all 0.2s ease;
	}

	.custom-select-trigger.placeholder {
		color: #888;
	}

	.custom-select-trigger:hover {
		border-color: #036a88;
	}

	.custom-select-wrapper.open .custom-select-trigger {
		border-color: #036a88;
		box-shadow: 0 0 0 2px rgba(3, 106, 136, 0.2);
	}

	.trigger-text {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-weight: 500;
	}

	.custom-select-trigger .arrow {
		width: 14px;
		height: 14px;
		stroke: #888;
		stroke-width: 2;
		stroke-linecap: round;
		stroke-linejoin: round;
		fill: none;
		transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		margin-left: 8px;
		flex-shrink: 0;
	}

	.custom-select-wrapper.open .arrow {
		transform: rotate(180deg);
		stroke: #036a88;
	}

	.custom-options {
		position: absolute;
		top: calc(100% + 4px);
		left: 0;
		right: 0;
		background-color: #fff;
		border: 1px solid #ddd;
		border-radius: 4px;
		box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
		z-index: 9999;
		max-height: 220px;
		overflow-y: auto;
		opacity: 0;
		visibility: hidden;
		transform: translateY(-5px);
		transition: all 0.2s ease;
	}

	.custom-select-wrapper.open .custom-options {
		opacity: 1;
		visibility: visible;
		transform: translateY(0);
	}

	.custom-option {
		padding: 8px 12px;
		cursor: pointer;
		transition: background-color 0.2s, color 0.2s;
		color: #444;
		font-weight: 500;
	}

	.custom-option:hover,
	.custom-option.selected {
		background-color: #e8f4f8;
		color: #036a88;
	}

	.custom-option:not(:last-child) {
		border-bottom: 1px solid #f5f5f5;
	}

	.table-responsive {
		overflow: visible !important;
	}
</style>

<div class="row">
	<div class="col-md-12">
		<div class="card">
			<div class="header mt-4 ml-4">
				<h4 class="title">Tambah Penugasan Resurvey ALDA</h4>
			</div>

			<div class="card-body">
				<div class="row ml-1">

					<?php if (count($assignResults) > 0): ?>
						<div class="col-12 mb-3">
							<?php if ($totalSuccess > 0 && $totalFail === 0): ?>
								<div class="alert alert-success py-2 mb-2">
									<strong>Penugasan untuk <?php echo $totalSuccess; ?> kontrak</strong> berhasil.
								</div>
							<?php elseif ($totalSuccess > 0 && $totalFail > 0): ?>
								<div class="alert alert-warning py-2 mb-2">
									<strong>Penugasan untuk <?php echo $totalSuccess; ?> kontrak</strong> berhasil,
									<strong><?php echo $totalFail; ?> gagal</strong>.
								</div>
							<?php else: ?>
								<div class="alert alert-danger py-2 mb-2">
									<strong>Penugasan untuk <?php echo $totalFail; ?> kontrak</strong> gagal diproses.
								</div>
							<?php endif; ?>

							<div class="table-responsive">
								<table class="table table-bordered" style="width: 100%;">
									<thead>
										<tr>
											<th
												style="width: 40px; text-align: center; background: #f5f5f5; font-weight: 600; white-space: nowrap; font-size: 11px; vertical-align: middle;">
												NO
											</th>
											<th
												style="background: #f5f5f5; font-weight: 600; white-space: nowrap; font-size: 11px; vertical-align: middle;">
												NO KONTRAK
											</th>
											<th
												style="width: 90px; text-align: center; background: #f5f5f5; font-weight: 600; white-space: nowrap; font-size: 11px; vertical-align: middle;">
												STATUS
											</th>
											<th
												style="background: #f5f5f5; font-weight: 600; white-space: nowrap; font-size: 11px; vertical-align: middle;">
												PIC DITUGASKAN
											</th>
											<th
												style="width: 130px; text-align: center; background: #f5f5f5; font-weight: 600; white-space: nowrap; font-size: 11px; vertical-align: middle;">
												ID PENUGASAN
											</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($assignResults as $i => $r): ?>
											<tr>
												<td style="text-align: center; font-size: 11px; vertical-align: middle;">
													<?php echo $i + 1; ?>
												</td>
												<td style="font-size: 11px; vertical-align: middle;">
													<?php echo htmlspecialchars($r['kontrak'], ENT_QUOTES, 'UTF-8'); ?>
												</td>
												<td style="text-align: center; font-size: 11px; vertical-align: middle;">
													<?php if ($r['success']): ?>
														<span
															style="display: inline-block; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; white-space: nowrap; background: #28a745;">
															BERHASIL
														</span>
													<?php else: ?>
														<span
															style="display: inline-block; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; white-space: nowrap; background: #dc3545;">
															GAGAL
														</span>
													<?php endif; ?>
												</td>
												<td style="font-size: 11px; vertical-align: middle;">
													<?php echo htmlspecialchars($r['pic_name'], ENT_QUOTES, 'UTF-8'); ?>
													<?php if (!$r['success'] && $r['message'] !== ''): ?>
														<br>
														<span style="color: #721c24; font-size: 10px;">
															<?php echo htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8'); ?>
														</span>
													<?php endif; ?>
												</td>
												<td style="text-align: center; font-size: 11px; vertical-align: middle;">
													<?php echo $r['submission_id'] !== null ? htmlspecialchars((string) $r['submission_id'], ENT_QUOTES, 'UTF-8') : '—'; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					<?php endif; ?>

					<div class="col-12">
						<form method="post">
							<input type="hidden" name="sid"
								value="<?php echo htmlspecialchars($sidParam, ENT_QUOTES, 'UTF-8'); ?>">
							<div class="row">
								<div class="col-md-4">
									<label>NOMOR KONTRAK</label>
									<input type="text" name="no_kontrak" class="form-control" value="">
								</div>
								<div class="col-md-4">
									<label>STATUS</label>
									<select name="contract_status" class="form-control custom-select-source">
										<option value="">-- Pilih Status --</option>
										<?php foreach ($dataStatus as $s): ?>
											<option value="<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>">
												<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-md-4 d-flex align-items-end">
									<button type="submit" class="btn w-100"
										style="border: 1px solid #036a88; color: #036a88; background: #fff;"
										onmouseover="this.style.background='#036a88';this.style.color='#fff';"
										onmouseout="this.style.background='#fff';this.style.color='#036a88';">
										CARI
									</button>
								</div>
							</div>
						</form>
					</div>

					<?php if (count($filterBadges) > 0): ?>
						<div class="col-12 mt-2">
							<div
								style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 10px; background: #eef6f9; border: 1px solid #cfe6ec; border-radius: 4px;">
								<span
									style="font-size: 11px; font-weight: 700; color: #035c7a; text-transform: uppercase; letter-spacing: 0.3px;">
									Filter Aktif
								</span>
								<?php foreach ($filterBadges as $badge): ?>
									<span
										style="font-size: 11px; background: #036a88; color: #fff; padding: 3px 10px; border-radius: 12px; white-space: nowrap;">
										<?php echo htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8'); ?>:
										<?php echo htmlspecialchars($badge['value'], ENT_QUOTES, 'UTF-8'); ?>
									</span>
								<?php endforeach; ?>
								<a href="<?php echo htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8'); ?>"
									style="font-size: 11px; margin-left: auto; color: #c0392b; font-weight: 600; text-decoration: none; white-space: nowrap;">
									✕ RESET FILTER
								</a>
							</div>
						</div>
					<?php endif; ?> <!-- PERBAIKAN: endif ditambahkan di sini, menggantikan div berlebih -->

					<div class="col-12">
						<hr>
					</div>

					<div class="col-12">
						<form method="post" id="formAssign">
							<input type="hidden" name="action" value="assign">
							<input type="hidden" name="sid"
								value="<?php echo htmlspecialchars($sidParam, ENT_QUOTES, 'UTF-8'); ?>">

							<div class="table-responsive mt-3">
								<table class="table table-bordered table-striped" id="tableAssign"
									style="width: 100%; table-layout: fixed; font-size: 11px;">
									<thead style="text-align: center; background: #035c7a;">
										<tr style="color: #fff !important;">
											<th
												style="width: 3%; text-align: center; vertical-align: middle; color: #fff !important;">
												NO
											</th>
											<th
												style="width: 13%; text-align: center; vertical-align: middle; color: #fff !important;">
												NO KONTRAK
											</th>
											<th
												style="width: 15%; text-align: center; vertical-align: middle; color: #fff !important;">
												NASABAH
											</th>
											<th
												style="width: 22%; text-align: center; vertical-align: middle; color: #fff !important;">
												ALAMAT
											</th>
											<th
												style="width: 12%; text-align: center; vertical-align: middle; color: #fff !important;">
												UNIT
											</th>
											<th
												style="width: 11%; text-align: center; vertical-align: middle; color: #fff !important;">
												AMOUNT
											</th>
											<th
												style="width: 18%; text-align: center; vertical-align: middle; color: #fff !important;">
												ASSIGN PIC
											</th>
											<th
												style="width: 6%; text-align: center; vertical-align: middle; color: #fff !important;">
												CHECK
											</th>
										</tr>
									</thead>
									<tbody>
										<?php
										$no = 0;
										$callList = '{call SP_ALDA_TASKLIST_PENUGASAN(?,?,?,?)}';
										$paramList = [
											[$branchidcbg, SQLSRV_PARAM_IN],
											[$no_kontrak, SQLSRV_PARAM_IN],
											[$contract_status, SQLSRV_PARAM_IN],
											['', SQLSRV_PARAM_IN],
										];
										$execList = sqlsrvQueryWithRetry($conn, $callList, $paramList) or die(print_r(sqlsrv_errors(), true));

										while ($data = sqlsrv_fetch_array($execList, SQLSRV_FETCH_ASSOC)):
											$no++;
											$kontrak = htmlspecialchars($data['NOMOR_KONTRAK'], ENT_QUOTES, 'UTF-8');
											$nasabah = htmlspecialchars(isset($data['CUSTOMER_NAME']) ? $data['CUSTOMER_NAME'] : '', ENT_QUOTES, 'UTF-8');
											$alamat = htmlspecialchars(isset($data['LEGAL_ADDRESS']) ? $data['LEGAL_ADDRESS'] : '', ENT_QUOTES, 'UTF-8');
											$unit = htmlspecialchars(isset($data['TYPE_KENDARAAN']) ? $data['TYPE_KENDARAAN'] : '', ENT_QUOTES, 'UTF-8');
											$amount = isset($data['AMOUNT_TO_BE_PAID']) ? $data['AMOUNT_TO_BE_PAID'] : null;
											?>
											<tr id="row-<?php echo $no; ?>">
												<td style="text-align: center; vertical-align: middle;">
													<?php echo $no; ?>
												</td>
												<td
													style="text-align: center; vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal;">
													<?php echo $kontrak; ?>
												</td>
												<td
													style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal;">
													<?php echo $nasabah; ?>
												</td>
												<td
													style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4;">
													<?php echo $alamat; ?>
												</td>
												<td
													style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4;">
													<?php echo $unit !== '' ? $unit : '-'; ?>
												</td>
												<td style="text-align: right; vertical-align: middle; white-space: nowrap;">
													<?php echo formatIDR($amount); ?>
												</td>
												<td style="vertical-align: middle;">
													<select name="pic[<?php echo $no; ?>]"
														class="form-control pic-select custom-select-source"
														style="width: 100%; font-size: 11px;" id="pic-<?php echo $no; ?>"
														data-row="<?php echo $no; ?>">
														<option value="" data-jabatan="">-- Pilih PIC --</option>
														<?php foreach ($dataPIC as $pic):
															$picValue = htmlspecialchars(isset($pic['VALUE']) ? $pic['VALUE'] : '', ENT_QUOTES, 'UTF-8');
															$picDisplay = htmlspecialchars(isset($pic['DATA_PIC']) ? $pic['DATA_PIC'] : '', ENT_QUOTES, 'UTF-8');
															$picJabatan = htmlspecialchars(isset($pic['JABATAN']) ? $pic['JABATAN'] : '', ENT_QUOTES, 'UTF-8');
															?>
															<option value="<?php echo $picValue; ?>"
																data-jabatan="<?php echo $picJabatan; ?>">
																<?php echo $picDisplay; ?>
															</option>
														<?php endforeach; ?>
													</select>
													<span
														style="display: none; margin-top: 4px; font-size: 10px; font-weight: 600; color: #035c7a; background: #e8f4f8; border: 1px solid #b8dce8; border-radius: 3px; padding: 2px 7px; white-space: nowrap; letter-spacing: 0.3px;"
														id="jabatan-<?php echo $no; ?>"></span>
												</td>
												<td style="text-align: center; vertical-align: middle;">
													<input type="hidden" name="nomor_kontrak[<?php echo $no; ?>]"
														value="<?php echo $kontrak; ?>">
													<input type="hidden" name="checked[<?php echo $no; ?>]" value="0">
													<input type="checkbox" name="checked[<?php echo $no; ?>]" value="1"
														class="row-check" data-row="<?php echo $no; ?>">
												</td>
											</tr>
										<?php endwhile; ?>

										<?php if ($no === 0): ?>
											<tr>
												<td colspan="8"
													style="text-align: center; padding: 20px !important; color: #999; vertical-align: middle;">
													Nomor kontrak tidak ditemukan.
												</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
								<button type="submit" class="btn btn-primary mt-2"
									style="font-size: 11px; background-color: #035c7a; border-color: #035c7a; color: #fff;">
									ASSIGN PIC
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	function initCustomSelects() {
		const selects = document.querySelectorAll('.custom-select-source:not(.select-initialized)');
		selects.forEach(select => {
			select.classList.add('select-initialized');
			if (select.nextElementSibling && select.nextElementSibling.classList.contains('custom-select-wrapper')) {
				select.nextElementSibling.remove();
			}

			const wrapper = document.createElement('div');
			wrapper.className = 'custom-select-wrapper';

			const trigger = document.createElement('div');
			trigger.className = 'custom-select-trigger';

			const textSpan = document.createElement('span');
			textSpan.className = 'trigger-text';
			textSpan.textContent = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '-- Pilih --';

			if (!select.value) {
				trigger.classList.add('placeholder');
			}

			const arrow = document.createElementNS("http://www.w3.org/2000/svg", "svg");
			arrow.setAttribute("class", "arrow");
			arrow.setAttribute("viewBox", "0 0 24 24");
			arrow.innerHTML = '<polyline points="6 9 12 15 18 9"></polyline>';

			trigger.appendChild(textSpan);
			trigger.appendChild(arrow);
			wrapper.appendChild(trigger);

			const optionsContainer = document.createElement('div');
			optionsContainer.className = 'custom-options';

			Array.from(select.options).forEach((option, index) => {
				if (option.value === "" && option.text.includes("--")) {
					return;
				}

				const opt = document.createElement('div');
				opt.className = 'custom-option';
				opt.textContent = option.text;
				if (option.hasAttribute('data-jabatan')) {
					opt.setAttribute('data-jabatan', option.getAttribute('data-jabatan'));
				}

				if (select.selectedIndex === index) {
					opt.classList.add('selected');
				}

				opt.addEventListener('click', function (e) {
					e.stopPropagation();
					select.selectedIndex = index;
					textSpan.textContent = option.text;
					trigger.classList.remove('placeholder');

					Array.from(optionsContainer.children).forEach(c => c.classList.remove('selected'));
					this.classList.add('selected');
					wrapper.classList.remove('open');

					const evt = document.createEvent("HTMLEvents");
					evt.initEvent("change", true, true);
					select.dispatchEvent(evt);
				});

				optionsContainer.appendChild(opt);
			});

			wrapper.appendChild(optionsContainer);
			select.parentNode.insertBefore(wrapper, select.nextSibling);

			trigger.addEventListener('click', function (e) {
				e.stopPropagation();
				const isOpen = wrapper.classList.contains('open');
				document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
				if (!isOpen) {
					wrapper.classList.add('open');
				}
			});
		});

		document.addEventListener('click', function () {
			document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
		});
	}

	let aldaAssignTable = null;

	function aldaAssignGetChecked() {
		const boxes = [];
		if (aldaAssignTable) {
			aldaAssignTable.rows().every(function () {
				const cb = this.node().querySelector('.row-check');
				if (cb) {
					boxes.push(cb);
				}
			});
		} else {
			const all = document.querySelectorAll('#tableAssign .row-check');
			for (let i = 0; i < all.length; i++) {
				boxes.push(all[i]);
			}
		}
		return boxes;
	}

	function aldaHighlightRow(cb) {
		const row = document.getElementById('row-' + cb.getAttribute('data-row'));
		if (!row) {
			return;
		}
		row.style.background = cb.checked ? '#e8f4f8' : '';
	}

	function aldaUpdateJabatanLabel(selectEl) {
		const label = document.getElementById('jabatan-' + selectEl.getAttribute('data-row'));
		if (!label) {
			return;
		}

		const selectedOption = selectEl.options[selectEl.selectedIndex];
		const jabatan = selectedOption ? (selectedOption.getAttribute('data-jabatan') || '') : '';

		if (jabatan !== '') {
			label.innerText = jabatan;
			label.style.display = 'inline-block';
		} else {
			label.innerText = '';
			label.style.display = 'none';
		}
	}

	function aldaAssignInitTable() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
			setTimeout(aldaAssignInitTable, 100);
			return;
		}

		if (jQuery.fn.DataTable.isDataTable('#tableAssign')) {
			return;
		}

		aldaAssignTable = jQuery('#tableAssign').DataTable({ paging: true, pageLength: 25, ordering: false, searching: false });
	}

	document.addEventListener('change', function (e) {
		if (e.target && e.target.classList.contains('row-check')) {
			aldaHighlightRow(e.target);
		}
		if (e.target && e.target.classList.contains('pic-select')) {
			aldaUpdateJabatanLabel(e.target);
		}
	});

	const aldaFormAssign = document.getElementById('formAssign');
	if (aldaFormAssign) {
		aldaFormAssign.addEventListener('submit', function (e) {
			const checked = aldaAssignGetChecked().filter(cb => cb.checked);
			if (checked.length === 0) {
				alert('Pilih kontrak untuk melakukan assign.');
				e.preventDefault();
				return;
			}

			let missingPIC = false;
			for (let i = 0; i < checked.length; i++) {
				const picSel = document.getElementById('pic-' + checked[i].getAttribute('data-row'));
				if (!picSel || picSel.value === '') {
					missingPIC = true;
					break;
				}
			}

			if (missingPIC) {
				alert('Semua kontrak yang dipilih harus memiliki PIC.');
				e.preventDefault();
			}
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		initCustomSelects();
	});
	aldaAssignInitTable();
</script>
```

## 1. penugasan-alda-record.php
```php
<!-- THIS FILE USE PHP 5.6.32 VERSION -->

<?php
require_once '../config/connection.php';

$usercreate = '';
if (!empty($_SESSION['username_cuser'])) {
    $usercreate = trim($_SESSION['username_cuser']);
} elseif (!empty($_POST['sid'])) {
    $usercreate = trim($_POST['sid']);
} elseif (!empty($_GET['sid'])) {
    $usercreate = trim($_GET['sid']);
}

$branchidcbg = isset($_SESSION['branch_cuser']) ? trim($_SESSION['branch_cuser']) : '';
$sidParam = $usercreate;
$no_kontrak = '';
$pic_nik = '';
$date_from = '';
$date_to = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_kontrak = isset($_POST['no_kontrak']) ? trim($_POST['no_kontrak']) : '';
    $pic_nik = isset($_POST['pic_nik']) ? trim($_POST['pic_nik']) : '';
    $date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
} else {
    $no_kontrak = isset($_GET['no_kontrak']) ? trim($_GET['no_kontrak']) : '';
    $pic_nik = isset($_GET['pic_nik']) ? trim($_GET['pic_nik']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
}

$paramDateFrom = ($date_from !== '') ? $date_from : null;
$paramDateTo = ($date_to !== '') ? $date_to : null;
$actionResult = null;
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_action'])) {
    $modalAction = trim($_POST['modal_action']);
    $modalKontrak = isset($_POST['modal_kontrak']) ? trim($_POST['modal_kontrak']) : '';
    $modalNotes = isset($_POST['modal_notes']) ? trim($_POST['modal_notes']) : '';

    $modalId = isset($_POST['modal_id']) ? (int) $_POST['modal_id'] : 0;
    $modalVersion = isset($_POST['modal_version']) ? (int) $_POST['modal_version'] : 0;

    if ($usercreate === '') {
        $actionResult = ['success' => false, 'message' => 'Identitas pengguna tidak ditemukan.'];
    } elseif ($modalId <= 0 || $modalVersion <= 0) {
        $actionResult = ['success' => false, 'message' => 'Validasi ID/Versi Penugasan gagal. Pastikan data tidak NULL.'];
    } elseif ($modalAction === 'update_pic') {
        $modalPicNik = isset($_POST['modal_pic_nik']) ? trim($_POST['modal_pic_nik']) : '';

        if ($modalPicNik === '') {
            $actionResult = ['success' => false, 'message' => 'PIC baru harus dipilih.'];
        } else {
            $callUpdate = '{call SP_ALDA_UPDATE_PIC(?,?,?,?,?)}';
            $paramUpdate = [
                [$usercreate, SQLSRV_PARAM_IN],
                [$modalPicNik, SQLSRV_PARAM_IN],
                [$modalId, SQLSRV_PARAM_IN],
                [$modalVersion, SQLSRV_PARAM_IN],
                [$modalNotes, SQLSRV_PARAM_IN],
            ];

            $execUpdate = sqlsrv_query($conn, $callUpdate, $paramUpdate);

            if ($execUpdate === false) {
                $errs = sqlsrv_errors();
                $errMsg = (is_array($errs) && isset($errs[0]['message'])) ? $errs[0]['message'] : 'Eksekusi query gagal.';
                $actionResult = ['success' => false, 'message' => $errMsg];
            } else {
                $spResult = sqlsrv_fetch_array($execUpdate, SQLSRV_FETCH_ASSOC);
                if ($spResult === false || $spResult === null) {
                    $actionResult = ['success' => false, 'message' => 'Stored procedure tidak menghasilkan output yang diharapkan.'];
                } else {
                    $actionResult = [
                        'success' => (int) $spResult['success'] === 1,
                        'message' => isset($spResult['message']) ? $spResult['message'] : '',
                    ];
                }
            }
        }
        $actionType = 'update_pic';

    } elseif ($modalAction === 'cancel_assign') {
        $cancelReason = ($modalNotes !== '') ? $modalNotes : null;

        $callCancel = '{call SP_ALDA_CANCEL_ASSIGN(?,?,?,?)}';
        $paramCancel = [
            [$usercreate, SQLSRV_PARAM_IN],
            [$modalId, SQLSRV_PARAM_IN],
            [$modalVersion, SQLSRV_PARAM_IN],
            [$cancelReason, SQLSRV_PARAM_IN],
        ];

        $execCancel = sqlsrv_query($conn, $callCancel, $paramCancel);

        if ($execCancel === false) {
            $errs = sqlsrv_errors();
            $errMsg = (is_array($errs) && isset($errs[0]['message'])) ? $errs[0]['message'] : 'Eksekusi query gagal.';
            $actionResult = ['success' => false, 'message' => $errMsg];
        } else {
            $spResult = sqlsrv_fetch_array($execCancel, SQLSRV_FETCH_ASSOC);
            if ($spResult === false || $spResult === null) {
                $actionResult = ['success' => false, 'message' => 'Stored procedure tidak menghasilkan output yang diharapkan.'];
            } else {
                $actionResult = [
                    'success' => (int) $spResult['success'] === 1,
                    'message' => isset($spResult['message']) ? $spResult['message'] : '',
                ];
            }
        }
        $actionType = 'cancel_assign';
    }
}

$callPIC = '{call SP_ALDA_DROPDOWN_PIC(?)}';
$execPIC = sqlsrv_query($conn, $callPIC, [[$branchidcbg, SQLSRV_PARAM_IN]])
    or die(print_r(sqlsrv_errors(), true));

$dataPIC = [];
while ($row = sqlsrv_fetch_array($execPIC, SQLSRV_FETCH_ASSOC)) {
    $dataPIC[] = $row;
}

usort($dataPIC, function ($a, $b) {
    $nameA = isset($a['DATA_PIC']) ? $a['DATA_PIC'] : '';
    $nameB = isset($b['DATA_PIC']) ? $b['DATA_PIC'] : '';
    return strcasecmp($nameA, $nameB);
});

function formatIDR($amount)
{
    if ($amount === null || $amount === '') {
        return '-';
    }

    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function formatTanggalPenugasan($date)
{
    if ($date === null || $date === '') {
        return '-';
    }

    if ($date instanceof DateTime) {
        return $date->format('d-m-Y');
    }

    $timestamp = strtotime((string) $date);
    return $timestamp !== false ? date('d-m-Y', $timestamp) : '-';
}

$filterBadges = [];

if ($no_kontrak !== '') {
    $filterBadges[] = ['label' => 'No. Kontrak', 'value' => $no_kontrak];
}

if ($pic_nik !== '') {
    $picLabelActive = $pic_nik;
    foreach ($dataPIC as $picRow) {
        $picRowValue = isset($picRow['VALUE']) ? (string) $picRow['VALUE'] : '';
        if ($picRowValue === $pic_nik) {
            $picLabelActive = isset($picRow['DATA_PIC']) ? $picRow['DATA_PIC'] : $pic_nik;
            break;
        }
    }
    $filterBadges[] = ['label' => 'PIC', 'value' => $picLabelActive];
}

if ($date_from !== '' || $date_to !== '') {
    $rangeFrom = ($date_from !== '') ? formatTanggalPenugasan($date_from) : '...';
    $rangeTo = ($date_to !== '') ? formatTanggalPenugasan($date_to) : '...';
    $filterBadges[] = ['label' => 'Periode', 'value' => $rangeFrom . ' s/d ' . $rangeTo];
}

$resetUrl = '?page=penugasan-alda-record&sid=' . urlencode($sidParam);
?>

<style>
    /* Custom Select Professional UI untuk Desktop Dashboard */
    .custom-select-source {
        display: none !important;
    }

    .custom-select-wrapper {
        position: relative;
        width: 100%;
        user-select: none;
        font-size: 11px;
    }

    .custom-select-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        height: 34px;
        padding: 6px 12px;
        background-color: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        cursor: pointer;
        color: #333;
        transition: all 0.2s ease;
    }

    .custom-select-trigger.placeholder {
        color: #888;
    }

    .custom-select-trigger:hover {
        border-color: #036a88;
    }

    .custom-select-wrapper.open .custom-select-trigger {
        border-color: #036a88;
        box-shadow: 0 0 0 2px rgba(3, 106, 136, 0.2);
    }

    .trigger-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }

    .custom-select-trigger .arrow {
        width: 14px;
        height: 14px;
        stroke: #888;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin-left: 8px;
        flex-shrink: 0;
    }

    .custom-select-wrapper.open .arrow {
        transform: rotate(180deg);
        stroke: #036a88;
    }

    .custom-options {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        z-index: 9999;
        max-height: 220px;
        overflow-y: auto;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-5px);
        transition: all 0.2s ease;
    }

    .custom-select-wrapper.open .custom-options {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .custom-option {
        padding: 8px 12px;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        color: #444;
        font-weight: 500;
    }

    .custom-option:hover,
    .custom-option.selected {
        background-color: #e8f4f8;
        color: #036a88;
    }

    .custom-option:not(:last-child) {
        border-bottom: 1px solid #f5f5f5;
    }

    .table-responsive {
        overflow: visible !important;
    }

    .modal-body {
        overflow: visible !important;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="header mt-4 ml-4">
                <h4 class="title">Riwayat Penugasan Resurvey ALDA</h4>
            </div>

            <div class="card-body">
                <div class="row ml-1">

                    <?php if ($actionResult !== null): ?>
                        <div class="col-12 mb-2">
                            <?php if ($actionResult['success']): ?>
                                <div class="alert alert-success py-2 mb-0">
                                    <?php echo htmlspecialchars($actionResult['message'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger py-2 mb-0">
                                    <strong>Proses gagal.</strong>
                                    <?php echo htmlspecialchars($actionResult['message'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <form method="get">
                            <input type="hidden" name="page" value="penugasan-alda-record">
                            <input type="hidden" name="sid"
                                value="<?php echo htmlspecialchars($sidParam, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>NOMOR KONTRAK</label>
                                    <input type="text" name="no_kontrak" class="form-control" value="">
                                </div>
                                <div class="col-md-3">
                                    <label>PIC</label>
                                    <select name="pic_nik" class="form-control custom-select-source">
                                        <option value="">-- PILIH PIC --</option>
                                        <?php foreach ($dataPIC as $pic):
                                            $picValue = htmlspecialchars(isset($pic['VALUE']) ? $pic['VALUE'] : '', ENT_QUOTES, 'UTF-8');
                                            $picDisplay = htmlspecialchars(isset($pic['DATA_PIC']) ? $pic['DATA_PIC'] : '', ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <option value="<?php echo $picValue; ?>"><?php echo $picDisplay; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>TANGGAL DARI</label>
                                    <input type="date" name="date_from" class="form-control" value="">
                                </div>
                                <div class="col-md-2">
                                    <label>TANGGAL SAMPAI</label>
                                    <input type="date" name="date_to" class="form-control" value="">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn w-100"
                                        style="border: 1px solid #036a88; color: #036a88; background: #fff;"
                                        onmouseover="this.style.background='#036a88';this.style.color='#fff';"
                                        onmouseout="this.style.background='#fff';this.style.color='#036a88';">CARI</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($filterBadges)): ?>
                        <div class="col-12 mt-2">
                            <div
                                style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 10px; background: #eef6f9; border: 1px solid #cfe6ec; border-radius: 4px;">
                                <span
                                    style="font-size: 11px; font-weight: 700; color: #035c7a; text-transform: uppercase; letter-spacing: 0.3px;">Filter
                                    Aktif</span>
                                <?php foreach ($filterBadges as $badge): ?>
                                    <span
                                        style="font-size: 11px; background: #036a88; color: #fff; padding: 3px 10px; border-radius: 12px; white-space: nowrap;">
                                        <?php echo htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8'); ?>:
                                        <?php echo htmlspecialchars($badge['value'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endforeach; ?>
                                <a href="<?php echo htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    style="font-size: 11px; margin-left: auto; color: #c0392b; font-weight: 600; text-decoration: none; white-space: nowrap;">✕
                                    RESET FILTER</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <hr>
                    </div>

                    <div class="col-12">
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped" id="tableRecord"
                                style="width: 100%; table-layout: fixed; font-size: 11px;">
                                <thead style="text-align: center; background: #035c7a;">
                                    <tr style="color: #fff !important;">
                                        <th
                                            style="width: 5%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            NO</th>
                                        <th
                                            style="width: 13%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            NO. KONTRAK</th>
                                        <th
                                            style="width: 18%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            NASABAH</th>
                                        <th
                                            style="width: 15%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            PIC</th>
                                        <th
                                            style="width: 13%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            TANGGAL PENUGASAN</th>
                                        <th
                                            style="width: 15%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            UNIT</th>
                                        <th
                                            style="width: 13%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            AMOUNT</th>
                                        <th
                                            style="width: 8%; text-align: center; vertical-align: middle; color: #fff !important;">
                                            ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 0;
                                    $callRecord = '{call SP_ALDA_RECORD_PENUGASAN(?,?,?,?,?)}';
                                    $paramRecord = [
                                        [$branchidcbg, SQLSRV_PARAM_IN],
                                        [$no_kontrak, SQLSRV_PARAM_IN],
                                        [$pic_nik, SQLSRV_PARAM_IN],
                                        [$paramDateFrom, SQLSRV_PARAM_IN],
                                        [$paramDateTo, SQLSRV_PARAM_IN],
                                    ];
                                    $execRecord = sqlsrv_query($conn, $callRecord, $paramRecord) or die(print_r(sqlsrv_errors(), true));

                                    while ($data = sqlsrv_fetch_array($execRecord, SQLSRV_FETCH_ASSOC)) {
                                        $no++;
                                        $penugasanId = (int) $data['PENUGASAN_ID'];
                                        $assignVersion = (int) $data['ASSIGN_VERSION'];
                                        $kontrak = htmlspecialchars($data['NOMOR_KONTRAK'], ENT_QUOTES, 'UTF-8');
                                        $picNik = htmlspecialchars(isset($data['PIC_NIK']) ? $data['PIC_NIK'] : '', ENT_QUOTES, 'UTF-8');
                                        $pic = htmlspecialchars($data['PIC'], ENT_QUOTES, 'UTF-8');
                                        $jabatan = htmlspecialchars(isset($data['JABATAN_PIC']) ? $data['JABATAN_PIC'] : '-', ENT_QUOTES, 'UTF-8');
                                        $tanggalPenugasan = htmlspecialchars(formatTanggalPenugasan(isset($data['CREATED_DATE']) ? $data['CREATED_DATE'] : null), ENT_QUOTES, 'UTF-8');
                                        $nasabah = htmlspecialchars(isset($data['CUSTOMER_NAME']) ? $data['CUSTOMER_NAME'] : '-', ENT_QUOTES, 'UTF-8');
                                        $unit = htmlspecialchars($data['UNIT'], ENT_QUOTES, 'UTF-8');
                                        $amount = isset($data['AMOUNT_TO_BE_PAID']) ? $data['AMOUNT_TO_BE_PAID'] : null;
                                        ?>
                                        <tr>
                                            <td style="text-align: center; vertical-align: middle;"><?php echo $no; ?></td>
                                            <td
                                                style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                <?php echo $kontrak; ?>
                                            </td>
                                            <td
                                                style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                <?php echo $nasabah; ?>
                                            </td>
                                            <td
                                                style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal;">
                                                <?php echo $pic; ?>
                                            </td>
                                            <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                                                <?php echo $tanggalPenugasan; ?>
                                            </td>
                                            <td
                                                style="vertical-align: middle; word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4;">
                                                <?php echo $unit; ?>
                                            </td>
                                            <td style="text-align: right; vertical-align: middle; white-space: nowrap;">
                                                <?php echo formatIDR($amount); ?>
                                            </td>
                                            <td style="text-align: center; vertical-align: middle;">
                                                <button type="button"
                                                    style="border: 1px solid #036a88; color: #036a88; background: #fff; padding: 2px 8px; font-size: 10px; border-radius: 3px; cursor: pointer; white-space: nowrap; line-height: 1.8;"
                                                    onmouseover="this.style.background='#036a88';this.style.color='#fff';"
                                                    onmouseout="this.style.background='#fff';this.style.color='#036a88';"
                                                    data-id="<?php echo $penugasanId; ?>"
                                                    data-version="<?php echo $assignVersion; ?>"
                                                    data-kontrak="<?php echo $kontrak; ?>"
                                                    data-nasabah="<?php echo $nasabah; ?>"
                                                    data-pic-nik="<?php echo $picNik; ?>"
                                                    data-pic-name="<?php echo $pic; ?>"
                                                    data-pic-jabatan="<?php echo $jabatan; ?>"
                                                    onclick="aldaOpenEditModal(this)">
                                                    EDIT
                                                </button>
                                            </td>
                                        </tr>
                                    <?php } ?>

                                    <?php if ($no === 0): ?>
                                        <tr>
                                            <td colspan="8"
                                                style="text-align: center; padding: 20px !important; color: #999; vertical-align: middle;">
                                                Tidak ada data penugasan yang ditemukan.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditPenugasan" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #035c7a; padding: 10px 15px;">
                <h5 class="modal-title" style="color: #fff; font-size: 13px; font-weight: 700; margin: 0;">UPDATE
                    PENUGASAN</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                    style="color: #fff; opacity: 1; font-size: 18px;"><span aria-hidden="true">&times;</span></button>
            </div>
            <form method="post" id="formModalAction">
                <input type="hidden" name="modal_action" id="inputModalAction" value="">

                <input type="hidden" name="modal_id" id="inputModalId" value="">
                <input type="hidden" name="modal_version" id="inputModalVersion" value="">

                <input type="hidden" name="modal_kontrak" id="inputModalKontrak" value="">
                <input type="hidden" name="modal_pic_nik" id="inputModalPicNik" value="">
                <input type="hidden" name="modal_notes" id="inputModalNotes" value="">
                <input type="hidden" name="sid" value="<?php echo htmlspecialchars($sidParam, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-body" style="padding: 16px 20px;">
                    <div
                        style="font-weight: 700; color: #035c7a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px; font-size: 11px;">
                        Detail Penugasan</div>
                    <div style="font-size: 11px; font-weight: 600; color: #555; margin-bottom: 2px;">No. Kontrak</div>
                    <div style="font-size: 12px; color: #222; margin-bottom: 10px; padding: 5px 8px; background: #f8f9fa; border-radius: 3px; border: 1px solid #e9ecef;"
                        id="displayKontrak">—</div>

                    <div style="font-size: 11px; font-weight: 600; color: #555; margin-bottom: 2px;">Nasabah</div>
                    <div style="font-size: 12px; color: #222; margin-bottom: 10px; padding: 5px 8px; background: #f8f9fa; border-radius: 3px; border: 1px solid #e9ecef;"
                        id="displayNasabah">—</div>

                    <div style="font-size: 11px; font-weight: 600; color: #555; margin-bottom: 2px;">PIC Ditugaskan
                    </div>
                    <div style="font-size: 12px; color: #222; margin-bottom: 10px; padding: 5px 8px; background: #f8f9fa; border-radius: 3px; border: 1px solid #e9ecef;"
                        id="displayPIC">—</div>

                    <div style="border-top: 1px solid #dee2e6; margin: 14px 0;"></div>

                    <div
                        style="font-weight: 700; color: #035c7a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px; font-size: 11px;">
                        UPDATE PIC PENUGASAN</div>
                    <div class="form-group mb-2">
                        <label style="font-size: 11px; font-weight: 600;">PIC BARU</label>
                        <select id="selectPICBaru" class="form-control custom-select-source"
                            style="font-size: 11px; width: 100%;">
                            <option value="">-- PILIH PIC --</option>
                            <?php foreach ($dataPIC as $pic):
                                $picValue = htmlspecialchars(isset($pic['VALUE']) ? $pic['VALUE'] : '', ENT_QUOTES, 'UTF-8');
                                $picDisplay = htmlspecialchars(isset($pic['DATA_PIC']) ? $pic['DATA_PIC'] : '', ENT_QUOTES, 'UTF-8');
                                $picJabatan = htmlspecialchars(isset($pic['JABATAN']) ? $pic['JABATAN'] : '', ENT_QUOTES, 'UTF-8');
                                $picLabel = ($picJabatan !== '') ? $picDisplay . ' - ' . $picJabatan : $picDisplay;
                                ?>
                                <option value="<?php echo $picValue; ?>"><?php echo $picLabel; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label style="font-size: 11px; font-weight: 600;">NOTE PERUBAHAN (Opsional)</label>
                        <input type="text" id="fieldUpdateNotes" class="form-control" style="font-size: 11px;"
                            placeholder="Masukkan alasan merubah PIC">
                    </div>

                    <div style="border-top: 1px solid #dee2e6; margin: 14px 0;"></div>

                    <div
                        style="font-weight: 700; color: #c0392b; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px; font-size: 11px;">
                        Batalkan Penugasan</div>
                    <p style="font-size: 11px; color: #555; margin-bottom: 10px;">Membatalkan penugasan akan
                        mengembalikan data nasabah ke antrian resurvey.</p>
                    <div class="form-group mb-0">
                        <label style="font-size: 11px; font-weight: 600;">NOTE PEMBATALAN (Opsional)</label>
                        <input type="text" id="fieldCancelReason" class="form-control" style="font-size: 11px;"
                            placeholder="Masukkan alasan membatalkan penugasan">
                    </div>
                </div>

                <div class="modal-footer" style="padding: 8px 15px;">
                    <button type="button" class="btn btn-danger btn-sm" style="font-size: 11px;"
                        onclick="aldaSubmitCancel()">HAPUS PENUGASAN</button>
                    <button type="button" class="btn btn-sm"
                        style="font-size: 11px; background-color: #035c7a; border-color: #035c7a; color: #fff;"
                        onclick="aldaSubmitUpdatePIC()">SIMPAN PERUBAHAN</button>
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal"
                        style="font-size: 11px;">TUTUP</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function initCustomSelects() {
        const selects = document.querySelectorAll('.custom-select-source:not(.select-initialized)');
        selects.forEach(select => {
            select.classList.add('select-initialized');
            if (select.nextElementSibling && select.nextElementSibling.classList.contains('custom-select-wrapper')) {
                select.nextElementSibling.remove();
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'custom-select-wrapper';

            const trigger = document.createElement('div');
            trigger.className = 'custom-select-trigger';

            const textSpan = document.createElement('span');
            textSpan.className = 'trigger-text';
            textSpan.textContent = select.options[select.selectedIndex]?.text || '-- PILIH --';

            if (!select.value) trigger.classList.add('placeholder');

            const arrow = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            arrow.setAttribute("class", "arrow");
            arrow.setAttribute("viewBox", "0 0 24 24");
            arrow.innerHTML = '<polyline points="6 9 12 15 18 9"></polyline>';

            trigger.appendChild(textSpan);
            trigger.appendChild(arrow);
            wrapper.appendChild(trigger);

            const optionsContainer = document.createElement('div');
            optionsContainer.className = 'custom-options';

            Array.from(select.options).forEach((option, index) => {
                if (option.value === "" && option.text.includes("--")) return;

                const opt = document.createElement('div');
                opt.className = 'custom-option';
                opt.textContent = option.text;

                if (select.selectedIndex === index) opt.classList.add('selected');

                opt.addEventListener('click', function (e) {
                    e.stopPropagation();
                    select.selectedIndex = index;
                    textSpan.textContent = option.text;
                    trigger.classList.remove('placeholder');

                    Array.from(optionsContainer.children).forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    wrapper.classList.remove('open');

                    var evt = document.createEvent("HTMLEvents");
                    evt.initEvent("change", true, true);
                    select.dispatchEvent(evt);
                });

                optionsContainer.appendChild(opt);
            });

            wrapper.appendChild(optionsContainer);
            select.parentNode.insertBefore(wrapper, select.nextSibling);

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = wrapper.classList.contains('open');
                document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
                if (!isOpen) wrapper.classList.add('open');
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
        });
    }

    function aldaRecordInitTable() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') { setTimeout(aldaRecordInitTable, 100); return; }
        if (jQuery.fn.DataTable.isDataTable('#tableRecord')) return;
        jQuery('#tableRecord').DataTable({ paging: true, pageLength: 25, ordering: false, searching: false });
    }

    function aldaOpenEditModal(btn) {
        var id = btn.getAttribute('data-id');
        var version = btn.getAttribute('data-version');
        var kontrak = btn.getAttribute('data-kontrak');
        var nasabah = btn.getAttribute('data-nasabah');
        var picName = btn.getAttribute('data-pic-name');
        var picJabatan = btn.getAttribute('data-pic-jabatan') || '';
        var picDisplay = (picJabatan !== '' && picJabatan !== '-') ? picName + ' - ' + picJabatan : picName;

        document.getElementById('inputModalId').value = id;
        document.getElementById('inputModalVersion').value = version;
        document.getElementById('inputModalKontrak').value = kontrak;
        document.getElementById('displayKontrak').innerText = kontrak;
        document.getElementById('displayNasabah').innerText = nasabah;
        document.getElementById('displayPIC').innerText = picDisplay;

        // Reset the Custom Select Form in Modal
        var picSelect = document.getElementById('selectPICBaru');
        picSelect.value = '';
        var wrapper = picSelect.nextElementSibling;
        if (wrapper && wrapper.classList.contains('custom-select-wrapper')) {
            wrapper.querySelector('.trigger-text').textContent = '-- PILIH PIC --';
            wrapper.querySelector('.custom-select-trigger').classList.add('placeholder');
            wrapper.querySelectorAll('.custom-option').forEach(opt => opt.classList.remove('selected'));
        }

        document.getElementById('fieldUpdateNotes').value = '';
        document.getElementById('fieldCancelReason').value = '';

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal !== 'undefined') {
            jQuery('#modalEditPenugasan').modal('show');
        } else {
            var el = document.getElementById('modalEditPenugasan');
            el.style.display = 'block'; el.classList.add('in'); document.body.classList.add('modal-open');
            var backdrop = document.createElement('div'); backdrop.className = 'modal-backdrop fade in'; backdrop.id = 'aldaModalBackdrop'; document.body.appendChild(backdrop);
            el.querySelector('[data-dismiss="modal"]').onclick = function () { aldaCloseModalFallback(); };
        }
    }

    function aldaCloseModalFallback() {
        var el = document.getElementById('modalEditPenugasan'); el.style.display = ''; el.classList.remove('in'); document.body.classList.remove('modal-open');
        var bd = document.getElementById('aldaModalBackdrop'); if (bd) bd.parentNode.removeChild(bd);
    }

    function aldaSubmitUpdatePIC() {
        var picBaru = document.getElementById('selectPICBaru').value;
        if (!picBaru || picBaru === '') { alert('Pilih PIC baru sebelum menyimpan perubahan.'); return; }
        if (!confirm('Konfirmasi: Ubah PIC penugasan untuk kontrak ' + document.getElementById('inputModalKontrak').value + '?')) return;
        document.getElementById('inputModalAction').value = 'update_pic';
        document.getElementById('inputModalPicNik').value = picBaru;
        document.getElementById('inputModalNotes').value = document.getElementById('fieldUpdateNotes').value;
        document.getElementById('formModalAction').submit();
    }

    function aldaSubmitCancel() {
        if (!confirm('Konfirmasi: Batalkan penugasan untuk kontrak ' + document.getElementById('inputModalKontrak').value + '?\n\nData nasabah akan dikembalikan ke antrian resurvey.')) return;
        document.getElementById('inputModalAction').value = 'cancel_assign';
        document.getElementById('inputModalPicNik').value = '';
        document.getElementById('inputModalNotes').value = document.getElementById('fieldCancelReason').value;
        document.getElementById('formModalAction').submit();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCustomSelects();
    });
    aldaRecordInitTable();
</script>
```