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