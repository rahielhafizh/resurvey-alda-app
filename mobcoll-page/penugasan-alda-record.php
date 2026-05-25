<!-- THIS FILE USE PHP 5.6.32 VERSION -->
<?php
require_once('../config/connection.php');

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

    if ($usercreate === '') {
        $actionResult = array('success' => false, 'message' => 'Identitas pengguna tidak ditemukan.');
    } elseif ($modalKontrak === '') {
        $actionResult = array('success' => false, 'message' => 'Nomor kontrak tidak valid.');
    } elseif ($modalAction === 'update_pic') {
        $modalPicNik = isset($_POST['modal_pic_nik']) ? trim($_POST['modal_pic_nik']) : '';

        if ($modalPicNik === '') {
            $actionResult = array('success' => false, 'message' => 'PIC baru harus dipilih.');
        } else {
            $callUpdate = '{call SP_ALDA_UPDATE_PIC(?,?,?,?)}';
            $paramUpdate = array(
                array($usercreate, SQLSRV_PARAM_IN),
                array($modalPicNik, SQLSRV_PARAM_IN),
                array($modalKontrak, SQLSRV_PARAM_IN),
                array($modalNotes, SQLSRV_PARAM_IN),
            );

            $execUpdate = sqlsrv_query($conn, $callUpdate, $paramUpdate);

            if ($execUpdate === false) {
                $errs = sqlsrv_errors();
                $errMsg = (is_array($errs) && isset($errs[0]['message']))
                    ? $errs[0]['message']
                    : 'Eksekusi query gagal.';
                $actionResult = array('success' => false, 'message' => $errMsg);
            } else {
                $spResult = sqlsrv_fetch_array($execUpdate, SQLSRV_FETCH_ASSOC);
                if ($spResult === false || $spResult === null) {
                    $actionResult = array('success' => false, 'message' => 'Stored procedure tidak menghasilkan output yang diharapkan.');
                } else {
                    $actionResult = array(
                        'success' => (int) $spResult['success'] === 1,
                        'message' => isset($spResult['message']) ? $spResult['message'] : '',
                    );
                }
            }
        }

        $actionType = 'update_pic';

    } elseif ($modalAction === 'cancel_assign') {
        $cancelReason = ($modalNotes !== '') ? $modalNotes : null;

        $callCancel = '{call SP_ALDA_CANCEL_ASSIGN(?,?,?)}';
        $paramCancel = array(
            array($usercreate, SQLSRV_PARAM_IN),
            array($modalKontrak, SQLSRV_PARAM_IN),
            array($cancelReason, SQLSRV_PARAM_IN),
        );

        $execCancel = sqlsrv_query($conn, $callCancel, $paramCancel);

        if ($execCancel === false) {
            $errs = sqlsrv_errors();
            $errMsg = (is_array($errs) && isset($errs[0]['message']))
                ? $errs[0]['message']
                : 'Eksekusi query gagal.';
            $actionResult = array('success' => false, 'message' => $errMsg);
        } else {
            $spResult = sqlsrv_fetch_array($execCancel, SQLSRV_FETCH_ASSOC);
            if ($spResult === false || $spResult === null) {
                $actionResult = array('success' => false, 'message' => 'Stored procedure tidak menghasilkan output yang diharapkan.');
            } else {
                $actionResult = array(
                    'success' => (int) $spResult['success'] === 1,
                    'message' => isset($spResult['message']) ? $spResult['message'] : '',
                );
            }
        }

        $actionType = 'cancel_assign';
    }
}

$callPIC = '{call SP_ALDA_DROPDOWN_PIC(?)}';
$execPIC = sqlsrv_query($conn, $callPIC, array(array($branchidcbg, SQLSRV_PARAM_IN)))
    or die(print_r(sqlsrv_errors(), true));

$dataPIC = array();
while ($row = sqlsrv_fetch_array($execPIC, SQLSRV_FETCH_ASSOC)) {
    $dataPIC[] = $row;
}

function formatIDR($amount)
{
    if ($amount === null || $amount === '') {
        return '-';
    }
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}
?>

<style>
    .th-custom {
        text-align: center;
        background-color: #035c7a;
    }

    .th-custom th {
        color: #ffffff !important;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    .td-center {
        text-align: center;
        vertical-align: middle;
    }

    .td-amount {
        text-align: right;
        vertical-align: middle;
        white-space: nowrap;
    }

    .pagination>li>a,
    .pagination>li>span {
        background-color: #fff;
        color: #035c7a;
    }

    .pagination>li.active>a,
    .pagination>li.active>span {
        background-color: #035c7a;
        color: #fff;
    }

    .pagination>li.active>a:hover,
    .pagination>li.active>span:hover {
        background-color: #035c7a;
        color: #fff;
    }

    .btn-custom {
        border: 1px solid #036a88;
        color: #036a88;
        background: #fff;
    }

    .btn-custom:hover {
        background: #036a88;
        color: #fff;
    }

    .btn-edit {
        padding: 2px 10px;
        font-size: 10px;
        border: 1px solid #036a88;
        color: #036a88;
        background: #fff;
        border-radius: 3px;
        cursor: pointer;
        white-space: nowrap;
        line-height: 1.8;
    }

    .btn-edit:hover {
        background: #036a88;
        color: #fff;
    }

    .modal-info-label {
        font-size: 11px;
        font-weight: 600;
        color: #555;
        margin-bottom: 2px;
    }

    .modal-info-value {
        font-size: 12px;
        color: #222;
        margin-bottom: 10px;
        padding: 5px 8px;
        background: #f8f9fa;
        border-radius: 3px;
        border: 1px solid #e9ecef;
    }

    .modal-divider {
        border-top: 1px solid #dee2e6;
        margin: 14px 0;
    }

    .modal-section-title {
        font-size: 11px;
        font-weight: 700;
        color: #035c7a;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 10px;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="header mt-4 ml-4">
                <h4 class="title">RECORD PENUGASAN — ALDA</h4>
            </div>

            <div class="card-body">
                <div class="row ml-1">

                    <?php if ($actionResult !== null): ?>
                        <div class="col-12 mb-2">
                            <?php if ($actionResult['success']): ?>
                                <div class="alert alert-success py-2 mb-0">
                                    <?php if ($actionType === 'update_pic'): ?>
                                        Perubahan PIC berhasil disimpan.
                                    <?php else: ?>
                                        Penugasan berhasil dibatalkan. Data nasabah telah dikembalikan ke antrian resurvey.
                                    <?php endif; ?>
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
                                    <input type="text" name="no_kontrak" class="form-control"
                                        value="<?php echo htmlspecialchars($no_kontrak, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="col-md-3">
                                    <label>PIC</label>
                                    <select name="pic_nik" class="form-control">
                                        <option value="">-- PILIH PIC --</option>
                                        <?php foreach ($dataPIC as $pic):
                                            $picValue = htmlspecialchars(isset($pic['VALUE']) ? $pic['VALUE'] : '', ENT_QUOTES, 'UTF-8');
                                            $picDisplay = htmlspecialchars(isset($pic['DATA_PIC']) ? $pic['DATA_PIC'] : '', ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <option value="<?php echo $picValue; ?>" <?php echo $pic_nik === (isset($pic['VALUE']) ? $pic['VALUE'] : '') ? 'selected' : ''; ?>>
                                                <?php echo $picDisplay; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label>TANGGAL DARI</label>
                                    <input type="date" name="date_from" class="form-control"
                                        value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="col-md-2">
                                    <label>TANGGAL SAMPAI</label>
                                    <input type="date" name="date_to" class="form-control"
                                        value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-custom w-100">CARI</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-12">
                        <hr>
                    </div>

                    <div class="col-12">
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped" id="tableRecord"
                                style="width: 100%; font-size: 11px;">
                                <thead class="th-custom">
                                    <tr>
                                        <th style="width: 40px;">NO</th>
                                        <th>CABANG</th>
                                        <th>NO. KONTRAK</th>
                                        <th>NASABAH</th>
                                        <th>PIC</th>
                                        <th>JABATAN</th>
                                        <th>UNIT</th>
                                        <th>AMOUNT</th>
                                        <th>CREATED DATE</th>
                                        <th>CREATED BY</th>
                                        <th style="width: 80px;">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 0;

                                    $callRecord = '{call SP_ALDA_RECORD_PENUGASAN(?,?,?,?,?)}';
                                    $paramRecord = array(
                                        array($branchidcbg, SQLSRV_PARAM_IN),
                                        array($no_kontrak, SQLSRV_PARAM_IN),
                                        array($pic_nik, SQLSRV_PARAM_IN),
                                        array($paramDateFrom, SQLSRV_PARAM_IN),
                                        array($paramDateTo, SQLSRV_PARAM_IN),
                                    );

                                    $execRecord = sqlsrv_query($conn, $callRecord, $paramRecord)
                                        or die(print_r(sqlsrv_errors(), true));

                                    while ($data = sqlsrv_fetch_array($execRecord, SQLSRV_FETCH_ASSOC)) {
                                        $no++;

                                        $kontrak = htmlspecialchars($data['NOMOR_KONTRAK'], ENT_QUOTES, 'UTF-8');
                                        $picNik = htmlspecialchars(isset($data['PIC_NIK']) ? $data['PIC_NIK'] : '', ENT_QUOTES, 'UTF-8');
                                        $pic = htmlspecialchars($data['PIC'], ENT_QUOTES, 'UTF-8');
                                        $cabang = htmlspecialchars(isset($data['CABANG']) ? $data['CABANG'] : '-', ENT_QUOTES, 'UTF-8');
                                        $jabatan = htmlspecialchars(isset($data['JABATAN_PIC']) ? $data['JABATAN_PIC'] : '-', ENT_QUOTES, 'UTF-8');
                                        $nasabah = htmlspecialchars(isset($data['CUSTOMER_NAME']) ? $data['CUSTOMER_NAME'] : '-', ENT_QUOTES, 'UTF-8');
                                        $unit = htmlspecialchars($data['UNIT'], ENT_QUOTES, 'UTF-8');
                                        $amount = isset($data['AMOUNT_TO_BE_PAID']) ? $data['AMOUNT_TO_BE_PAID'] : null;
                                        $createdDate = htmlspecialchars($data['CREATED_DATE'], ENT_QUOTES, 'UTF-8');
                                        $createdBy = htmlspecialchars($data['CREATED_BY'], ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td class="td-center"><?php echo $no; ?></td>
                                            <td><?php echo $cabang; ?></td>
                                            <td><?php echo $kontrak; ?></td>
                                            <td><?php echo $nasabah; ?></td>
                                            <td><?php echo $pic; ?></td>
                                            <td><?php echo $jabatan; ?></td>
                                            <td><?php echo $unit; ?></td>
                                            <td class="td-amount"><?php echo formatIDR($amount); ?></td>
                                            <td class="td-center"><?php echo $createdDate; ?></td>
                                            <td class="td-center"><?php echo $createdBy; ?></td>
                                            <td class="td-center">
                                                <button type="button" class="btn-edit"
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
                                            <td colspan="11" class="td-center" style="padding: 20px; color: #999;">
                                                Tidak ada data penugasan yang ditemukan.
                                            </td>
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
                <h5 class="modal-title" style="color: #fff; font-size: 13px; font-weight: 700; margin: 0;">
                    UPDATE PENUGASAN
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                    style="color: #fff; opacity: 1; font-size: 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form method="post" id="formModalAction">
                <input type="hidden" name="modal_action" id="inputModalAction" value="">
                <input type="hidden" name="modal_kontrak" id="inputModalKontrak" value="">
                <input type="hidden" name="modal_pic_nik" id="inputModalPicNik" value="">
                <input type="hidden" name="modal_notes" id="inputModalNotes" value="">
                <input type="hidden" name="sid" value="<?php echo htmlspecialchars($sidParam, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="no_kontrak"
                    value="<?php echo htmlspecialchars($no_kontrak, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="pic_nik"
                    value="<?php echo htmlspecialchars($pic_nik, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="date_from"
                    value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="date_to"
                    value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="modal-body" style="padding: 16px 20px;">
                    <div class="modal-section-title">Detail Penugasan</div>

                    <div class="modal-info-label">No. Kontrak</div>
                    <div class="modal-info-value" id="displayKontrak">—</div>

                    <div class="modal-info-label">Nasabah</div>
                    <div class="modal-info-value" id="displayNasabah">—</div>

                    <div class="modal-info-label">PIC Ditugaskan</div>
                    <div class="modal-info-value" id="displayPIC">—</div>

                    <div class="modal-divider"></div>

                    <div class="modal-section-title">UPDATE PIC PENUGASAN</div>

                    <div class="form-group mb-2">
                        <label style="font-size: 11px; font-weight: 600;">PIC BARU</label>
                        <select id="selectPICBaru" class="form-control" style="font-size: 11px;">
                            <option value="">-- Pilih PIC --</option>
                            <?php foreach ($dataPIC as $pic):
                                $picValue = htmlspecialchars(isset($pic['VALUE']) ? $pic['VALUE'] : '', ENT_QUOTES, 'UTF-8');
                                $picDisplay = htmlspecialchars(isset($pic['DATA_PIC']) ? $pic['DATA_PIC'] : '', ENT_QUOTES, 'UTF-8');
                                $picJabatan = htmlspecialchars(isset($pic['JABATAN']) ? $pic['JABATAN'] : '', ENT_QUOTES, 'UTF-8');
                                $picLabel = ($picJabatan !== '') ? $picDisplay . ' - ' . $picJabatan : $picDisplay;
                                ?>
                                <option value="<?php echo $picValue; ?>">
                                    <?php echo $picLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label style="font-size: 11px; font-weight: 600;">NOTE PERUBAHAN (Opsional)</label>
                        <input type="text" id="fieldUpdateNotes" class="form-control" style="font-size: 11px;"
                            placeholder="Masukkan alasan merubah PIC dalam penugasan">
                    </div>

                    <div class="modal-divider"></div>

                    <div class="modal-section-title" style="color: #c0392b;">Batalkan Penugasan</div>
                    <p style="font-size: 11px; color: #555; margin-bottom: 10px;">
                        Membatalkan penugasan akan mengembalikan data nasabah ke antrian penugasan.
                    </p>

                    <div class="form-group mb-0">
                        <label style="font-size: 11px; font-weight: 600;">NOTE PEMBATALAN (Opsional)</label>
                        <input type="text" id="fieldCancelReason" class="form-control" style="font-size: 11px;"
                            placeholder="Masukkan alasan membatalkan penugasan.">
                    </div>

                </div>

                <div class="modal-footer" style="padding: 8px 15px;">
                    <button type="button" class="btn btn-danger btn-sm" style="font-size: 11px;"
                        onclick="aldaSubmitCancel()">
                        HAPUS PENUGASAN
                    </button>
                    <button type="button" class="btn btn-primary btn-sm"
                        style="font-size: 11px; background-color: #035c7a; border-color: #035c7a;"
                        onclick="aldaSubmitUpdatePIC()">
                        SIMPAN PERUBAHAN
                    </button>
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-size: 11px;">
                        TUTUP
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    function aldaRecordInitTable() {
        if (typeof jQuery === 'undefined') {
            setTimeout(aldaRecordInitTable, 100);
            return;
        }
        if (typeof jQuery.fn.DataTable === 'undefined' && typeof jQuery.fn.dataTable === 'undefined') {
            setTimeout(aldaRecordInitTable, 100);
            return;
        }
        if (jQuery.fn.DataTable.isDataTable('#tableRecord')) {
            return;
        }
        jQuery('#tableRecord').DataTable({
            paging: true,
            pageLength: 25,
            ordering: false,
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                zeroRecords: 'Data tidak ditemukan',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            }
        });
    }

    function aldaOpenEditModal(btn) {
        var kontrak = btn.getAttribute('data-kontrak');
        var nasabah = btn.getAttribute('data-nasabah');
        var picName = btn.getAttribute('data-pic-name');
        var picJabatan = btn.getAttribute('data-pic-jabatan') || '';
        var picDisplay = (picJabatan !== '' && picJabatan !== '-')
            ? picName + ' - ' + picJabatan
            : picName;

        document.getElementById('inputModalKontrak').value = kontrak;
        document.getElementById('displayKontrak').innerText = kontrak;
        document.getElementById('displayNasabah').innerText = nasabah;
        document.getElementById('displayPIC').innerText = picDisplay;
        document.getElementById('selectPICBaru').value = '';
        document.getElementById('fieldUpdateNotes').value = '';
        document.getElementById('fieldCancelReason').value = '';

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal !== 'undefined') {
            jQuery('#modalEditPenugasan').modal('show');
        } else {
            var el = document.getElementById('modalEditPenugasan');
            el.style.display = 'block';
            el.classList.add('in');
            document.body.classList.add('modal-open');

            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade in';
            backdrop.id = 'aldaModalBackdrop';
            document.body.appendChild(backdrop);

            el.querySelector('[data-dismiss="modal"]').onclick = function () {
                aldaCloseModalFallback();
            };
        }
    }

    function aldaCloseModalFallback() {
        var el = document.getElementById('modalEditPenugasan');
        el.style.display = '';
        el.classList.remove('in');
        document.body.classList.remove('modal-open');
        var bd = document.getElementById('aldaModalBackdrop');
        if (bd) { bd.parentNode.removeChild(bd); }
    }

    function aldaSubmitUpdatePIC() {
        var picBaru = document.getElementById('selectPICBaru').value;
        if (!picBaru || picBaru === '') {
            alert('Pilih PIC baru sebelum menyimpan perubahan.');
            return;
        }

        var kontrak = document.getElementById('inputModalKontrak').value;
        if (!confirm('Konfirmasi: Ubah PIC penugasan untuk kontrak ' + kontrak + '?')) {
            return;
        }

        document.getElementById('inputModalAction').value = 'update_pic';
        document.getElementById('inputModalPicNik').value = picBaru;
        document.getElementById('inputModalNotes').value = document.getElementById('fieldUpdateNotes').value;
        document.getElementById('formModalAction').submit();
    }

    function aldaSubmitCancel() {
        var kontrak = document.getElementById('inputModalKontrak').value;
        if (!confirm('Konfirmasi: Batalkan penugasan untuk kontrak ' + kontrak + '?\n\nData nasabah akan dikembalikan ke antrian resurvey.')) {
            return;
        }

        document.getElementById('inputModalAction').value = 'cancel_assign';
        document.getElementById('inputModalPicNik').value = '';
        document.getElementById('inputModalNotes').value = document.getElementById('fieldCancelReason').value;
        document.getElementById('formModalAction').submit();
    }

    aldaRecordInitTable();
</script>