<?php
session_start();
require_once __DIR__ . '/../config/connection.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || !isset($_SESSION['user_nik'])) {
    header('Location: login.php');
    exit();
}

$nik = $_SESSION['user_nik'];
$tasks = [];
$error_message = '';

$tsql = "{CALL SP_ALDA_PIC_GET_TASKS(?, ?)}";
$params = array(
    array($nik, SQLSRV_PARAM_IN),
    array('ASSIGNED', SQLSRV_PARAM_IN)
);

$stmt = sqlsrv_query($conn, $tsql, $params);

if ($stmt === false) {
    $error_message = "Terjadi kesalahan saat mengambil data penugasan.";
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $tasks[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

function formatRupiah($angka)
{
    return "Rp " . number_format((float) $angka, 0, ',', '.');
}

function svgIcon(string $name, string $class = 'icon'): string
{
    $path = __DIR__ . '/assets/icons/' . $name . '.svg';
    if (!file_exists($path))
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24"></svg>';
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
    <title>Tugas Baru - Resurvey Alda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Tambahan CSS Khusus untuk Card Penugasan agar rapi dan responsif di Mobile */
        .page-header {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: #fff;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .back-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            margin-right: 12px;
            display: flex;
            align-items: center;
            color: var(--primary-dark);
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        .task-list-container {
            padding: 16px;
            padding-bottom: 40px;
        }

        .task-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
            transition: transform 0.2s ease;
        }

        .task-card:active {
            transform: scale(0.98);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 12px;
        }

        .contract-no {
            font-weight: 700;
            color: var(--primary);
            font-size: 14px;
        }

        .task-date {
            font-size: 12px;
            color: var(--text-muted);
        }

        .customer-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0 0 4px 0;
        }

        .customer-vehicle {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: inline-block;
            background: #f8fafc;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }

        .detail-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 8px;
            font-size: 13px;
            color: var(--text-dark);
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            color: var(--primary-light);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .action-container {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }

        .btn-process {
            display: block;
            width: 100%;
            background: var(--primary);
            color: #fff;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }
    </style>
</head>

<body>
    <div class="page-background" style="align-items: flex-start; min-height: 100vh;">
        <div class="page-header" style="width: 100%; max-width: 480px; margin: 0 auto;">
            <button class="back-btn" onclick="window.location.href='dashboard.php'">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </button>
            <h1 class="page-title">Tugas Baru</h1>
        </div>

        <div class="task-list-container" style="width: 100%; max-width: 480px; margin: 0 auto;">

            <?php if (!empty($error_message)): ?>
                <div
                    style="padding: 12px; background: #FEE2E2; color: var(--error); border-radius: 8px; margin-bottom: 16px; font-size: 13px; text-align: center;">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($tasks) && empty($error_message)): ?>
                <div class="empty-state">
                    <?php echo svgIcon('empty-folder-icon'); ?>
                    <h3>Belum ada tugas baru</h3>
                    <p style="font-size: 14px; margin-top: 8px;">Saat ini tidak ada penugasan baru yang dialokasikan untuk
                        Anda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <span
                                class="contract-no"><?php echo htmlspecialchars($task['CONTRACT_NO'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="task-date">
                                <?php
                                $date = $task['TANGGAL_ASSIGN'];
                                echo $date ? $date->format('d M Y H:i') : '-';
                                ?>
                            </span>
                        </div>

                        <h3 class="customer-name"><?php echo htmlspecialchars($task['CUSTOMER_NAME'], ENT_QUOTES, 'UTF-8'); ?>
                        </h3>

                        <?php if (trim($task['KENDARAAN']) !== ''): ?>
                            <div class="customer-vehicle">
                                🚗 <?php echo htmlspecialchars($task['KENDARAAN'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="detail-row">
                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span><?php echo htmlspecialchars($task['LEGAL_ADDRESS'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="detail-row">
                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                </path>
                            </svg>
                            <span><?php echo htmlspecialchars($task['CUSTOMER_PHONE'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="detail-row">
                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            <span style="font-weight: 600; color: var(--error);">
                                <?php echo formatRupiah($task['AMOUNT_TO_BE_PAID']); ?>
                            </span>
                        </div>

                        <div class="action-container">
                            <a href="proses-tugas.php?id=<?php echo urlencode($task['PENUGASAN_ID']); ?>" class="btn-process">
                                Proses Tugas
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</body>

</html>