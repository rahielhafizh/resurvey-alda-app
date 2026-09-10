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
                        <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">Format JPG/PNG, Maks 5MB.</small>
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