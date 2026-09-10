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