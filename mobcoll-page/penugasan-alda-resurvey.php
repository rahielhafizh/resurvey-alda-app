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
$contract_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$no_kontrak = isset($_POST['no_kontrak']) ? trim($_POST['no_kontrak']) : '';
	$contract_status = isset($_POST['contract_status']) ? trim($_POST['contract_status']) : '';
} else {
	$no_kontrak = isset($_GET['no_kontrak']) ? trim($_GET['no_kontrak']) : '';
	$contract_status = isset($_GET['contract_status']) ? trim($_GET['contract_status']) : '';
}

$callStatus = 'SELECT DISTINCT CONTRACT_STATUS
               FROM   MASTER_ALDA
               WHERE  BRANCH_ID = ?
               ORDER  BY CONTRACT_STATUS';

$execStatus = sqlsrv_query($conn, $callStatus, array(array($branchidcbg, SQLSRV_PARAM_IN)))
	or die(print_r(sqlsrv_errors(), true));

$dataStatus = array();
while ($row = sqlsrv_fetch_array($execStatus, SQLSRV_FETCH_ASSOC)) {
	$dataStatus[] = $row['CONTRACT_STATUS'];
}

$callPIC = '{call SP_ALDA_DROPDOWN_PIC(?)}';
$execPIC = sqlsrv_query($conn, $callPIC, array(array($branchidcbg, SQLSRV_PARAM_IN)))
	or die(print_r(sqlsrv_errors(), true));

$dataPIC = array();
while ($row = sqlsrv_fetch_array($execPIC, SQLSRV_FETCH_ASSOC)) {
	$dataPIC[] = $row;
}

$assignResults = array();
$totalSuccess = 0;
$totalFail = 0;

if (isset($_POST['action']) && $_POST['action'] === 'assign') {
	if ($usercreate === '') {
		$assignResults[] = array(
			'kontrak' => '-',
			'success' => false,
			'message' => 'Identitas pengguna tidak ditemukan. Periksa konfigurasi sesi atau pastikan parameter sid tersedia.',
			'submission_id' => null,
		);
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

			$callAssign = '{call SP_ALDA_SUBMIT_ASSIGN(?,?,?,?)}';
			$paramAssign = array(
				array($usercreate, SQLSRV_PARAM_IN),
				array($pic_nik, SQLSRV_PARAM_IN),
				array($nomor_kontrak, SQLSRV_PARAM_IN),
				array('Assigned via Web', SQLSRV_PARAM_IN),
			);

			$execAssign = sqlsrv_query($conn, $callAssign, $paramAssign);

			if ($execAssign === false) {
				$errs = sqlsrv_errors();
				$errMsg = (is_array($errs) && isset($errs[0]['message']))
					? $errs[0]['message']
					: 'Query execution failed.';

				$assignResults[] = array(
					'kontrak' => $nomor_kontrak,
					'success' => false,
					'message' => $errMsg,
					'submission_id' => null,
				);
				$totalFail++;
				continue;
			}

			$spResult = sqlsrv_fetch_array($execAssign, SQLSRV_FETCH_ASSOC);

			if ($spResult === false || $spResult === null) {
				$assignResults[] = array(
					'kontrak' => $nomor_kontrak,
					'success' => false,
					'message' => 'Stored procedure tidak menghasilkan output yang diharapkan.',
					'submission_id' => null,
				);
				$totalFail++;
				continue;
			}

			$isOk = (int) $spResult['success'] === 1;
			$submissionId = ($isOk && isset($spResult['submission_id'])) ? $spResult['submission_id'] : null;

			$assignResults[] = array(
				'kontrak' => $nomor_kontrak,
				'success' => $isOk,
				'message' => isset($spResult['message']) ? $spResult['message'] : '',
				'submission_id' => $submissionId,
			);

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

	.td-address {
		vertical-align: middle;
		max-width: 220px;
		word-wrap: break-word;
		word-break: break-word;
		white-space: normal;
		line-height: 1.4;
	}

	.td-unit {
		vertical-align: middle;
		max-width: 160px;
		word-wrap: break-word;
		word-break: break-word;
		white-space: normal;
		line-height: 1.4;
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

	.select-pic {
		min-width: 180px;
		font-size: 11px;
	}

	.pic-jabatan-label {
		display: none;
		margin-top: 4px;
		font-size: 10px;
		font-weight: 600;
		color: #035c7a;
		background-color: #e8f4f8;
		border: 1px solid #b8dce8;
		border-radius: 3px;
		padding: 2px 7px;
		white-space: nowrap;
		letter-spacing: 0.3px;
	}

	tbody tr.row-selected {
		background-color: #e8f4f8 !important;
	}

	.assign-result-table th {
		background-color: #f5f5f5;
		font-weight: 600;
		font-size: 11px;
		white-space: nowrap;
	}

	.assign-result-table td {
		font-size: 11px;
		vertical-align: middle;
	}

	.assign-result-table .badge-ok {
		display: inline-block;
		background-color: #28a745;
		color: #fff;
		padding: 2px 8px;
		border-radius: 3px;
		font-size: 10px;
		white-space: nowrap;
	}

	.assign-result-table .badge-fail {
		display: inline-block;
		background-color: #dc3545;
		color: #fff;
		padding: 2px 8px;
		border-radius: 3px;
		font-size: 10px;
		white-space: nowrap;
	}

	.result-message-fail {
		color: #721c24;
	}
</style>

<div class="row">
	<div class="col-md-12">
		<div class="card">
			<div class="header mt-4 ml-4">
				<h4 class="title">PENUGASAN RESURVEY — ALDA</h4>
			</div>

			<div class="card-body">
				<div class="row ml-1">

					<?php if (count($assignResults) > 0): ?>
						<div class="col-12 mb-3">
							<?php if ($totalSuccess > 0 && $totalFail === 0): ?>
								<div class="alert alert-success py-2 mb-2">
									<strong>Penugasan <?php echo $totalSuccess; ?> kontrak</strong> berhasil.
								</div>
							<?php elseif ($totalSuccess > 0 && $totalFail > 0): ?>
								<div class="alert alert-warning py-2 mb-2">
									<strong>Penugasan <?php echo $totalSuccess; ?> kontrak</strong> berhasil,
									<strong>Penugasan <?php echo $totalFail; ?> kontrak</strong> gagal.
								</div>
							<?php else: ?>
								<div class="alert alert-danger py-2 mb-2">
									<strong>Penugasan gagal.</strong>
									Seluruh <?php echo $totalFail; ?> kontrak tidak berhasil diproses.
								</div>
							<?php endif; ?>

							<div class="table-responsive">
								<table class="table table-bordered assign-result-table" style="width: 100%;">
									<thead>
										<tr>
											<th style="width: 40px; text-align: center;">NO</th>
											<th>NO KONTRAK</th>
											<th style="width: 90px; text-align: center;">STATUS</th>
											<th>KETERANGAN</th>
											<th style="width: 130px; text-align: center;">ID PENUGASAN</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($assignResults as $i => $r): ?>
											<tr>
												<td style="text-align: center;"><?php echo $i + 1; ?></td>
												<td><?php echo htmlspecialchars($r['kontrak'], ENT_QUOTES, 'UTF-8'); ?></td>
												<td style="text-align: center;">
													<?php if ($r['success']): ?>
														<span class="badge-ok">BERHASIL</span>
													<?php else: ?>
														<span class="badge-fail">GAGAL</span>
													<?php endif; ?>
												</td>
												<td>
													<?php if ($r['success']): ?>
														Penugasan berhasil.
													<?php else: ?>
														<span class="result-message-fail">
															<?php echo htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8'); ?>
														</span>
													<?php endif; ?>
												</td>
												<td style="text-align: center;">
													<?php
													if ($r['submission_id'] !== null) {
														echo htmlspecialchars((string) $r['submission_id'], ENT_QUOTES, 'UTF-8');
													} else {
														echo '&mdash;';
													}
													?>
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
									<input type="text" name="no_kontrak" class="form-control"
										value="<?php echo htmlspecialchars($no_kontrak, ENT_QUOTES, 'UTF-8'); ?>">
								</div>

								<div class="col-md-4">
									<label>STATUS</label>
									<select name="contract_status" class="form-control">
										<option value="">-- Pilih Status --</option>
										<?php foreach ($dataStatus as $s): ?>
											<option value="<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $contract_status === $s ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="col-md-4 d-flex align-items-end">
									<button type="submit" class="btn btn-custom w-100">CARI</button>
								</div>
							</div>
						</form>
					</div>

					<div class="col-12">
						<hr>
					</div>

					<div class="col-12">
						<form method="post" id="formAssign">
							<input type="hidden" name="action" value="assign">
							<input type="hidden" name="sid"
								value="<?php echo htmlspecialchars($sidParam, ENT_QUOTES, 'UTF-8'); ?>">
							<input type="hidden" name="no_kontrak"
								value="<?php echo htmlspecialchars($no_kontrak, ENT_QUOTES, 'UTF-8'); ?>">
							<input type="hidden" name="contract_status"
								value="<?php echo htmlspecialchars($contract_status, ENT_QUOTES, 'UTF-8'); ?>">

							<div class="table-responsive mt-3">
								<table class="table table-bordered table-striped" id="tableAssign"
									style="width: 100%; font-size: 11px;">
									<thead class="th-custom">
										<tr>
											<th style="width: 40px;">NO</th>
											<th>AREA</th>
											<th>CABANG</th>
											<th>NO KONTRAK</th>
											<th>NASABAH</th>
											<th>ALAMAT</th>
											<th>UNIT</th>
											<th>AMOUNT</th>
											<th>ASSIGN PIC</th>
											<th>CHECK</th>
										</tr>
									</thead>
									<tbody>
										<?php
										$no = 0;

										$callList = '{call SP_ALDA_TASKLIST_PENUGASAN(?,?,?,?)}';
										$paramList = array(
											array($branchidcbg, SQLSRV_PARAM_IN),
											array($no_kontrak, SQLSRV_PARAM_IN),
											array($contract_status, SQLSRV_PARAM_IN),
											array('', SQLSRV_PARAM_IN),
										);

										$execList = sqlsrv_query($conn, $callList, $paramList)
											or die(print_r(sqlsrv_errors(), true));

										while ($data = sqlsrv_fetch_array($execList, SQLSRV_FETCH_ASSOC)) {
											$no++;

											$area = htmlspecialchars(isset($data['AREA']) ? $data['AREA'] : '', ENT_QUOTES, 'UTF-8');
											$cabang = htmlspecialchars(isset($data['BRANCH_NAME']) ? $data['BRANCH_NAME'] : '', ENT_QUOTES, 'UTF-8');
											$kontrak = htmlspecialchars($data['NOMOR_KONTRAK'], ENT_QUOTES, 'UTF-8');
											$nasabah = htmlspecialchars(isset($data['CUSTOMER_NAME']) ? $data['CUSTOMER_NAME'] : '', ENT_QUOTES, 'UTF-8');
											$alamat = htmlspecialchars(isset($data['LEGAL_ADDRESS']) ? $data['LEGAL_ADDRESS'] : '', ENT_QUOTES, 'UTF-8');
											$unit = htmlspecialchars(isset($data['TYPE_KENDARAAN']) ? $data['TYPE_KENDARAAN'] : '', ENT_QUOTES, 'UTF-8');
											$amount = isset($data['AMOUNT_TO_BE_PAID']) ? $data['AMOUNT_TO_BE_PAID'] : null;
											?>
											<tr id="row-<?php echo $no; ?>">
												<td class="td-center"><?php echo $no; ?></td>
												<td class="td-center"><?php echo $area; ?></td>
												<td class="td-center"><?php echo $cabang; ?></td>
												<td class="td-center"><?php echo $kontrak; ?></td>
												<td><?php echo $nasabah; ?></td>
												<td class="td-address"><?php echo $alamat; ?></td>
												<td class="td-unit"><?php echo $unit !== '' ? $unit : '-'; ?></td>
												<td class="td-amount"><?php echo formatIDR($amount); ?></td>
												<td>
													<select name="pic[<?php echo $no; ?>]"
														class="form-control select-pic pic-select"
														id="pic-<?php echo $no; ?>" data-row="<?php echo $no; ?>">
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
													<span class="pic-jabatan-label" id="jabatan-<?php echo $no; ?>"></span>
												</td>
												<td class="td-center">
													<input type="hidden" name="nomor_kontrak[<?php echo $no; ?>]"
														value="<?php echo $kontrak; ?>">
													<input type="hidden" name="checked[<?php echo $no; ?>]" value="0">
													<input type="checkbox" name="checked[<?php echo $no; ?>]" value="1"
														class="row-check" data-row="<?php echo $no; ?>">
												</td>
											</tr>
										<?php } ?>

										<?php if ($no === 0): ?>
											<tr>
												<td colspan="10" class="td-center" style="padding: 20px; color: #999;">
													Nomor kontrak tidak tersedia/tidak ditemukan.
												</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>

								<button type="submit" class="btn btn-primary mt-2">ASSIGN PIC</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	var aldaAssignTable = null;

	function aldaAssignGetChecked() {
		var boxes = [];
		if (aldaAssignTable) {
			aldaAssignTable.rows().every(function () {
				var cb = this.node().querySelector('.row-check');
				if (cb) { boxes.push(cb); }
			});
		} else {
			var all = document.querySelectorAll('#tableAssign .row-check');
			for (var i = 0; i < all.length; i++) { boxes.push(all[i]); }
		}
		return boxes;
	}

	function aldaHighlightRow(cb) {
		var row = document.getElementById('row-' + cb.getAttribute('data-row'));
		if (!row) { return; }
		if (cb.checked) {
			row.classList.add('row-selected');
		} else {
			row.classList.remove('row-selected');
		}
	}

	function aldaUpdateJabatanLabel(selectEl) {
		var rowId = selectEl.getAttribute('data-row');
		var label = document.getElementById('jabatan-' + rowId);
		if (!label) { return; }

		var selectedOption = selectEl.options[selectEl.selectedIndex];
		var jabatan = selectedOption ? (selectedOption.getAttribute('data-jabatan') || '') : '';

		if (jabatan !== '') {
			label.innerText = jabatan;
			label.style.display = 'inline-block';
		} else {
			label.innerText = '';
			label.style.display = 'none';
		}
	}

	function aldaAssignInitTable() {
		if (typeof jQuery === 'undefined') {
			setTimeout(aldaAssignInitTable, 100);
			return;
		}
		if (typeof jQuery.fn.DataTable === 'undefined' && typeof jQuery.fn.dataTable === 'undefined') {
			setTimeout(aldaAssignInitTable, 100);
			return;
		}
		if (jQuery.fn.DataTable.isDataTable('#tableAssign')) {
			return;
		}
		aldaAssignTable = jQuery('#tableAssign').DataTable({
			paging: false,
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

	document.addEventListener('change', function (e) {
		if (e.target && e.target.classList.contains('row-check')) {
			aldaHighlightRow(e.target);
		}
		if (e.target && e.target.classList.contains('pic-select')) {
			aldaUpdateJabatanLabel(e.target);
		}
	});

	var aldaFormAssign = document.getElementById('formAssign');
	if (aldaFormAssign) {
		aldaFormAssign.addEventListener('submit', function (e) {
			var checked = aldaAssignGetChecked().filter(function (cb) { return cb.checked; });

			if (checked.length === 0) {
				alert('Pilih kontrak untuk melakukan assign.');
				e.preventDefault();
				return;
			}

			var missingPIC = false;
			for (var i = 0; i < checked.length; i++) {
				var picSel = document.getElementById('pic-' + checked[i].getAttribute('data-row'));
				if (!picSel || picSel.value === '') {
					missingPIC = true;
					break;
				}
			}

			if (missingPIC) {
				alert('Semua kontrak yang dipilih harus memiliki PIC yang dipilih.');
				e.preventDefault();
			}
		});
	}

	aldaAssignInitTable();
</script>