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

// Get Data ON_PROCESS
$tsql = "{CALL SP_ALDA_PIC_GET_TASKS(?, ?)}";
$params = array(
    array($nik, SQLSRV_PARAM_IN),
    array('ON_PROCESS', SQLSRV_PARAM_IN)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tugas Sedang Diproses - Resurvey Alda</title>
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
                <h1 class="page-title">Tugas Diproses</h1>
            </div>

            <div class="task-list-container">
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks) && empty($error_message)): ?>
                    <div class="empty-state">
                        <?php echo svgIcon('tugas-proses-icon'); ?>
                        <h3>Tidak ada tugas diproses</h3>
                        <p>Semua penugasan Anda masih berstatus baru atau telah diselesaikan.</p>
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
                            'date' => $task['TANGGAL_ASSIGN'] ? $task['TANGGAL_ASSIGN']->format('d M Y H:i') : '-'
                        ]);
                        ?>
                        <div class="task-card" style="border-left: 5px solid var(--warning);">
                            <div class="task-header">
                                <span
                                    class="contract-no"><?php echo htmlspecialchars($task['CONTRACT_NO'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="task-date">
                                    Diproses: <?php echo $task['UPDATED_AT'] ? $task['UPDATED_AT']->format('d M Y') : '-'; ?>
                                </span>
                            </div>

                            <h3 class="customer-name">
                                <?php echo htmlspecialchars($task['CUSTOMER_NAME'], ENT_QUOTES, 'UTF-8'); ?></h3>

                            <div class="detail-row">
                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span><?php echo htmlspecialchars($task['LEGAL_ADDRESS'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <div class="action-group">
                                <button type="button" class="btn btn-outline" style="width: 100%;"
                                    onclick='openModal(<?php echo htmlspecialchars($payload, ENT_QUOTES, 'UTF-8'); ?>)'>Lihat
                                    Detail Nasabah</button>
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
                <h3 class="modal-title">Detail Nasabah (On Proses)</h3>
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
                    <span class="info-label">No Kontrak</span>
                    <span class="info-value" id="mdlContract">-</span>
                </div>
                <div class="info-group">
                    <span class="info-label">Nama Nasabah</span>
                    <span class="info-value" id="mdlCustomer">-</span>
                </div>
                <div class="info-group">
                    <span class="info-label">Alamat</span>
                    <span class="info-value" id="mdlAddress">-</span>
                </div>
                <div class="info-group">
                    <span class="info-label">Telepon</span>
                    <span class="info-value" id="mdlPhone">-</span>
                </div>
                <div class="info-group">
                    <span class="info-label">Unit Kendaraan</span>
                    <span class="info-value" id="mdlVehicle">-</span>
                </div>
                <div class="info-group">
                    <span class="info-label">Tagihan</span>
                    <span class="info-value" style="color: var(--error);" id="mdlAmount">-</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(data) {
            document.getElementById('mdlContract').innerText = data.contract_no;
            document.getElementById('mdlCustomer').innerText = data.customer;
            document.getElementById('mdlAddress').innerText = data.address;
            document.getElementById('mdlPhone').innerText = data.phone;
            document.getElementById('mdlVehicle').innerText = data.vehicle || '-';
            document.getElementById('mdlAmount').innerText = data.amount;
            document.getElementById('detailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        document.getElementById('detailModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>

</html>