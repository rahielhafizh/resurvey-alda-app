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