<?php
// Keep admin session consistent with admin.php
$_SESSION_NAME = 'ADMIN_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

$user = getAdminSession();

// Utility: Check table/column existence
function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return ($res && $res->num_rows > 0);
}
function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return ($res && $res->num_rows > 0);
}

// ----- Added: Build a safe student name expression based on your schema -----
$studentNameExpr = 's.id'; // fallback to id if no name column exists
if (columnExists($conn, 'students', 'name')) {
    // Use simple name column if available
    $studentNameExpr = 's.name';
} elseif (columnExists($conn, 'students', 'first_name') && columnExists($conn, 'students', 'last_name')) {
    // Use first_name + last_name if both available
    $studentNameExpr = "TRIM(CONCAT_WS(' ', s.first_name, s.last_name))";
} elseif (columnExists($conn, 'students', 'first_name')) {
    // Only first_name available
    $studentNameExpr = 's.first_name';
} elseif (columnExists($conn, 'students', 'last_name')) {
    // Only last_name available
    $studentNameExpr = 's.last_name';
}
// ---------------------------------------------------------------------------

// ----- Added: Build a safe student grade expression and detect actual column name -----
$gradeExpr = "''";
$hasGradeColumn = false;
$gradeColumnName = null;
$gradeCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];
foreach ($gradeCandidates as $gcol) {
    if (columnExists($conn, 'students', $gcol)) {
        $gradeColumnName = $gcol;
        $hasGradeColumn = true;
        $gradeExpr = "IFNULL(s.`{$gradeColumnName}`, '')";
        break;
    }
}
// ---------------------------------------------------------------------------

const FIXED_TOTAL_FEE = 15000.00; // all students will have this as total_fee

// Determine what the DB contains
$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');

$errorMsg = '';
$balances = [];

if ($feesExists) {
    // If 'fees' exists, determine payments column relationships
    $paymentsHasFeeId = $paymentsExists && columnExists($conn, 'payments', 'fee_id');
    $paymentsHasStudentId = $paymentsExists && columnExists($conn, 'payments', 'student_id');

    if ($paymentsExists && $paymentsHasFeeId) {
        // Full query: payments linked to fees; total_fees fixed to FIXED_TOTAL_FEE
        $sql = "
        SELECT s.id AS student_id,
               {$studentNameExpr} AS student_name,
               {$gradeExpr} AS grade,
               " . FIXED_TOTAL_FEE . " AS total_fees,
               IFNULL((
                   SELECT SUM(p.amount)
                   FROM payments p
                   JOIN fees f2 ON p.fee_id = f2.id
                   WHERE f2.student_id = s.id
                     AND f2.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')
               ), 0) AS total_payments,
               " . FIXED_TOTAL_FEE . " - IFNULL((
                   SELECT SUM(p.amount)
                   FROM payments p
                   JOIN fees f2 ON p.fee_id = f2.id
                   WHERE f2.student_id = s.id
                     AND f2.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')
               ), 0) AS balance
        FROM students s
        GROUP BY s.id
        ORDER BY student_name ASC
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $balances[] = $row;
            }
        } else {
            $errorMsg = 'Failed to fetch balances (DB query error). Check the database structure and permissions.';
        }
    } else if ($paymentsExists && $paymentsHasStudentId) {
        // payments by student fallback; total_fees fixed to FIXED_TOTAL_FEE
        $sql = "
        SELECT s.id AS student_id,
               {$studentNameExpr} AS student_name,
               {$gradeExpr} AS grade,
               " . FIXED_TOTAL_FEE . " AS total_fees,
               IFNULL(( SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id ), 0) AS total_payments,
               " . FIXED_TOTAL_FEE . " - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS balance
        FROM students s
        GROUP BY s.id
        ORDER BY student_name ASC
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $balances[] = $row;
            }
            // note: when payments are only by student_id we cannot exclude payments belonging to excluded fee categories
            $errorMsg = ($errorMsg ? $errorMsg . ' ' : '') . 'Payments are summed by student; payments may include amounts for categories excluded from fee totals.';
        } else {
            $errorMsg = 'Failed to fetch balances (DB query error); payments table exists but lacks fee_id.';
        }
    } else {
        // No payments table, total_paid is 0; total_fees fixed to FIXED_TOTAL_FEE
        $sql = "
        SELECT s.id AS student_id,
               {$studentNameExpr} AS student_name,
               {$gradeExpr} AS grade,
               " . FIXED_TOTAL_FEE . " AS total_fees,
               0 AS total_payments,
               " . FIXED_TOTAL_FEE . " AS balance
        FROM students s
        GROUP BY s.id
        ORDER BY student_name ASC
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $balances[] = $row;
            }
        } else {
            $errorMsg = 'Failed to compute balances. Database read error or permissions issue.';
        }
    }
} else {
    // fees table missing — fallback using payments by student; total_fees fixed to FIXED_TOTAL_FEE
    if ($paymentsExists && columnExists($conn, 'payments', 'student_id')) {
        $sql = "
            SELECT s.id AS student_id,
                   {$studentNameExpr} AS student_name,
                   {$gradeExpr} AS grade,
                   " . FIXED_TOTAL_FEE . " AS total_fees,
                   IFNULL(( SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id ), 0) AS total_payments,
                   " . FIXED_TOTAL_FEE . " - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS balance
            FROM students s
            GROUP BY s.id
            ORDER BY student_name ASC
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $balances[] = $row;
            }
        } else {
            $errorMsg = 'Failed to fetch payments by student.';
        }

        $errorMsg = 'The "fees" table was not found; total fees are shown as fixed 15,000 and payments (if available) were computed. Ensure the fees table exists to calculate fees from records if desired.';
    } else {
        $errorMsg = 'Unable to compute balances: required "payments" table is missing or has unexpected columns. Check your database schema.';
    }
}

// Normalize numeric fields to floats and round
foreach ($balances as &$b) {
    $b['total_fees'] = (float)($b['total_fees'] ?? 0);
    $b['total_payments'] = (float)($b['total_payments'] ?? 0);
    $b['balance'] = round((float)($b['balance'] ?? ($b['total_fees'] - $b['total_payments'])), 2);
}
unset($b);

// ----- New: Fetch distinct grades to build grade buttons (if column exists) -----
$grades = [];
if ($hasGradeColumn && $gradeColumnName) {
    $col = $conn->real_escape_string($gradeColumnName);
    $res = $conn->query("SELECT DISTINCT IFNULL(`{$col}`, '') AS g FROM students ORDER BY g ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $g = trim((string)$r['g']);
            if ($g !== '') $grades[] = $g;
        }
    }
}
// ---------------------------------------------------------------------------

// Render full page using the same HTML/CSS structure as admin.php
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Admin · Account balance</title>
	<link rel="stylesheet" href="../css/admin.css" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
	<style>
	/* Export CSV button consistent with admin.php */
	.btn-export{
		background: #000;
		color: #fff;
		border: 1px solid rgba(255,255,255,0.06);
		padding: 8px 14px;
		border-radius: 8px;
		font-weight: 700;
		cursor: pointer;
		box-shadow: 0 6px 18px rgba(0,0,0,0.12);
		transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
	}
	.btn-export:hover { transform: translateY(-2px); opacity: 0.98; }
	.btn-export:active { transform: translateY(-1px); }
	.btn-export[disabled] { opacity: 0.6; cursor: not-allowed; transform: none; }

	.explanation {
		margin-top: 20px;
		padding: 10px;
		background-color: #f9f9f9;
		border-left: 4px solid #007bff;
		font-size: 0.9em;
	}

	/* Manage button */
	.btn-manage {
		background: #007bff;
		color: #fff;
		border: 0;
		padding: 6px 12px;
		border-radius: 6px;
		font-weight: 700;
		cursor: pointer;
	}
	.btn-manage:disabled { opacity: .6; cursor: not-allowed; }

	/* Sort by Grade button */
	.btn-sort {
		background: #10b981;
		color: #fff;
		border: 0;
		padding: 8px 12px;
		border-radius: 8px;
		font-weight: 700;
		cursor: pointer;
		margin-left: 8px;
	}
	.btn-sort.toggle-desc { background: #ef4444; } /* optional color when descending */

	/* Simple modal styles */
	.modal-backdrop {
		position: fixed;
		inset: 0;
		background: rgba(0,0,0,0.5);
		display: none;
		align-items: center;
		justify-content: center;
		z-index: 1000;
	}
	.modal-backdrop.show { display: flex; }
	.modal {
		background: #fff;
		padding: 20px;
		border-radius: 8px;
		width: 420px;
		max-width: calc(100% - 40px);
		box-shadow: 0 10px 30px rgba(0,0,0,.2);
	}
	.modal .row { margin-bottom: 10px; }
	.modal label { display: block; font-weight:600; margin-bottom: 6px; }
	.modal input[type='number'] { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; }
	.modal .modal-actions { text-align: right; margin-top:10px; }
	.modal .btn-cancel { background: #f2f2f2; border: 0; padding: 6px 14px; border-radius: 6px; margin-right:8px; cursor:pointer; }
	.modal .btn-submit { background: #28a745; color: #fff; border: 0; padding: 6px 14px; border-radius: 6px; cursor:pointer; }
	.alert-inline { color: #b21f2d; font-size: 0.9em; margin-top:6px; }

	/* Grade filter buttons */
	.grade-filters {
		display: inline-flex;
		gap: 8px;
		align-items: center;
	}
	.grade-filter-btn {
		background: #f3f3f3;
		color: #222;
		border: 1px solid #ddd;
		padding: 6px 10px;
		border-radius: 6px;
		cursor: pointer;
		font-weight: 600;
	}
	.grade-filter-btn.active {
		background: #111827;
		color: #fff;
		border-color: #111827;
	}
	.grade-filter-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.12); }

	/* Dropdown select for grade sorting */
	.grade-sort-select {
		background: #f8f8f8;
		border: 1px solid #ddd;
		padding: 6px 8px;
		border-radius: 6px;
		margin-left: 6px;
		font-weight: 600;
		cursor: pointer;
	}
	.grade-sort-select[disabled] { opacity: 0.6; cursor: not-allowed; }
	</style>
</head>
<body>
	<div class="app">
		<aside class="sidebar">
			<div class="brand">Glorious God's Family<span>Christian School</span></div>
			<nav>
				<a href="../admin.php">Dashboard</a>
				<a href="students.php">Students</a>
				<a href="schedule.php">Schedule</a>
				<a href="teachers.php">Teachers</a>
				<a href="reports.php">Reports</a>
				<a class="active" href="AccountBalance.php">Account Balance</a>
				<a href="settings.php">Settings</a>
				<a href="../logout.php?type=admin">Logout</a>
			</nav>
			<div class="sidebar-foot">Logged in as <strong><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></strong></div>
		</aside>

		<main class="main">
			<header class="topbar">
				<h1>Account balance</h1>
				<div class="top-actions">
					<!-- Export -->
					<button id="exportCsv" class="btn-export" type="button" title="Download account balance CSV">Export CSV</button>

					<!-- Replace grade filters and sort button with a single dropdown select -->
					<label for="gradeSortSelect" style="margin-left: 12px; font-weight: 600;">Sort:</label>
					<select id="gradeSortSelect" class="grade-sort-select" title="<?= $hasGradeColumn ? 'Sort by grade' : 'No grade column in students table' ?>" <?= $hasGradeColumn ? '' : 'disabled' ?>>
						<option value="__default__">Name A → Z</option>
						<?php if ($hasGradeColumn): ?>
							<option value="grade_asc">Grade Ascending</option>
							<option value="grade_desc">Grade Descending</option>
						<?php endif; ?>
					</select>
				</div>
			</header>

			<section class="content">
				<div class="container-fluid">
					<div class="card">
						<div class="card-header">
							<h3 class="card-title">Students balances (excluding Other Fees & Scholarships)</h3>
						</div>
						<div class="card-body table-responsive p-0">
							<table class="table table-hover table-striped">
								<thead>
									<tr>
										<th>#</th>
										<th>Student</th>
										<th class="text-right">Total Fee</th>
										<th class="text-right">Total Paid</th>
										<th class="text-right">Account Balance</th>
										<th>Grade</th> <!-- show grade column -->
										<th>Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php if (!empty($balances)) : ?>
										<?php foreach ($balances as $i => $row) :
											$balance = (float)$row['balance'];
											$studentId = (int)$row['student_id'];
											$studentName = htmlspecialchars($row['student_name'], ENT_QUOTES);
											$gradeVal = isset($row['grade']) ? (string)$row['grade'] : '';
											$gradeDisplay = $gradeVal === '' ? '—' : htmlspecialchars($gradeVal);
										?>
											<tr id="student-row-<?= $studentId ?>" data-grade="<?= htmlspecialchars($gradeVal, ENT_QUOTES) ?>">
												<td><?= $i + 1 ?></td>
												<td><?= htmlspecialchars($row['student_name']) ?></td>
												<td class="text-right" id="total-fees-<?= $studentId ?>"><?= number_format((float)$row['total_fees'], 2) ?></td>
												<td class="text-right" id="total-paid-<?= $studentId ?>"><?= number_format((float)$row['total_payments'], 2) ?></td>
												<td class="text-right <?= $balance > 0 ? 'text-danger' : 'text-success' ?>" id="balance-<?= $studentId ?>">
													<?= number_format($balance, 2) ?>
												</td>
												<td><?= $gradeDisplay ?></td>
												<td>
													<button
														class="btn-manage"
														type="button"
														data-student-id="<?= $studentId ?>"
														data-student-name="<?= $studentName ?>"
														data-total-fees="<?= (float)$row['total_fees'] ?>"
														data-total-paid="<?= (float)$row['total_payments'] ?>"
														data-balance="<?= $balance ?>"
													>Manage</button>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php else: ?>
										<tr>
											<td colspan="7" class="text-center">No records found.</td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<?php if ($errorMsg): // Show error/warning message if set ?>
					<div class="alert alert-warning">
						<?php echo $errorMsg; ?>
					</div>
				<?php endif; ?>

				<div class="explanation">
					<p><strong>Note:</strong> Account Balance is calculated as <em>Total Fee</em> - <em>Total Paid</em>.</p>
				</div>
			</section>

			<footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
		</main>
	</div>

	<!-- Modal markup (hidden by default) -->
	<div id="manageModalBackdrop" class="modal-backdrop" role="dialog" aria-hidden="true">
		<div class="modal" role="document" aria-modal="true">
			<h3 id="modalTitle">Record Payment</h3>
			<div class="row">
				<label>Student</label>
				<div id="modalStudentName">—</div>
			</div>
			<div class="row">
				<label>Total Fee</label>
				<div id="modalTotalFee">—</div>
			</div>
			<div class="row">
				<label>Already Paid</label>
				<div id="modalTotalPaid">—</div>
			</div>
			<div class="row">
				<label for="modalAmount">Payment Amount</label>
				<input id="modalAmount" type="number" min="0" step="0.01" placeholder="Enter amount paid" />
				<div id="modalError" class="alert-inline" style="display:none;"></div>
			</div>
			<div class="modal-actions">
				<button id="modalCancel" class="btn-cancel" type="button">Cancel</button>
				<button id="modalSubmit" class="btn-submit" type="button">Save</button>
			</div>
		</div>
	</div>

	<script>
	// Simple export CSV helper for the page (adapt server side export endpoint)
	async function downloadResponseAsFile(response, fallbackName) {
		if (!response.ok) throw new Error('Network response was not ok');
		const blob = await response.blob();
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = fallbackName;
		document.body.appendChild(a);
		a.click();
		a.remove();
		URL.revokeObjectURL(url);
	}

	(async function() {
		const btn = document.getElementById('exportCsv');
		if (!btn) return;
		btn.addEventListener('click', async function() {
			btn.disabled = true;
			const prevText = btn.textContent;
			btn.textContent = 'Preparing...';
			try {
				const res = await fetch('export_account_balance.php', { credentials: 'same-origin' });
				await downloadResponseAsFile(res, 'account_balance.csv');
			} catch (err) {
				console.error(err);
				alert('Failed to download CSV. Check console for details.');
			} finally {
				btn.disabled = false;
				btn.textContent = prevText;
			}
		});
	})();

	// Manage payment modal logic
	(function() {
		const modalBackdrop = document.getElementById('manageModalBackdrop');
		const modalStudentName = document.getElementById('modalStudentName');
		const modalTotalFee = document.getElementById('modalTotalFee');
		const modalTotalPaid = document.getElementById('modalTotalPaid');
		const modalAmount = document.getElementById('modalAmount');
		const modalError = document.getElementById('modalError');
		const modalSubmit = document.getElementById('modalSubmit');
		const modalCancel = document.getElementById('modalCancel');

		let activeStudentId = null;

		function openModal(studentId, studentName, totalFees, totalPaid) {
			activeStudentId = studentId;
			modalStudentName.textContent = studentName;
			modalTotalFee.textContent = parseFloat(totalFees).toFixed(2);
			modalTotalPaid.textContent = parseFloat(totalPaid).toFixed(2);
			modalAmount.value = '';
			modalError.style.display = 'none';
			modalBackdrop.classList.add('show');
			modalBackdrop.setAttribute('aria-hidden', 'false');
			modalAmount.focus();
		}
		function closeModal() {
			activeStudentId = null;
			modalBackdrop.classList.remove('show');
			modalBackdrop.setAttribute('aria-hidden', 'true');
		}

		document.addEventListener('click', function (e) {
			const el = e.target;
			if (el.matches('.btn-manage')) {
				const sid = el.getAttribute('data-student-id');
				const studentName = el.getAttribute('data-student-name') || 'Student';
				const totalFees = el.getAttribute('data-total-fees') || '0';
				const totalPaid = el.getAttribute('data-total-paid') || '0';
				openModal(sid, studentName, totalFees, totalPaid);
			}
		});

		modalCancel.addEventListener('click', closeModal);
		modalBackdrop.addEventListener('click', function(e) {
			if (e.target === modalBackdrop) closeModal();
		});

		modalSubmit.addEventListener('click', async function() {
			// Validate
			modalError.style.display = 'none';
			const val = modalAmount.value.trim();
			if (!val || isNaN(val) || Number(val) <= 0) {
				modalError.textContent = 'Please enter a valid positive amount.';
				modalError.style.display = 'block';
				return;
			}

			modalSubmit.disabled = true;
			const amount = parseFloat(val);
			try {
				const res = await fetch('save_payment.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify({ student_id: activeStudentId, amount: amount })
				});
				const data = await res.json();
				if (!res.ok) {
					throw new Error(data.message || 'Server error');
				}
				// Success -> update row UI
				const sid = activeStudentId;
				const totalPaidTD = document.getElementById('total-paid-' + sid);
				const balanceTD = document.getElementById('balance-' + sid);
				if (data.totals) {
					const totalFees = parseFloat(data.totals.total_fees || '0');
					const totalPaid = parseFloat(data.totals.total_payments || '0');
					const balance = parseFloat(data.totals.balance || (totalFees - totalPaid));
					// Update DOM (formatted)
					totalPaidTD.textContent = totalPaid.toFixed(2);
					balanceTD.textContent = balance.toFixed(2);
					// Update color based on sign (balance > 0 => owing, else green)
					if (balance > 0) {
						balanceTD.classList.remove('text-success');
						balanceTD.classList.add('text-danger');
					} else {
						balanceTD.classList.remove('text-danger');
						balanceTD.classList.add('text-success');
					}
					// Also update dataset attributes on Manage button for subsequent opens
					const rowBtn = document.querySelector('button.btn-manage[data-student-id="' + sid + '"]');
					if (rowBtn) {
						rowBtn.setAttribute('data-total-paid', totalPaid);
						rowBtn.setAttribute('data-balance', balance);
					}
				}
				closeModal();
			} catch (err) {
				console.error(err);
				modalError.textContent = err.message || 'Failed to save payment.';
				modalError.style.display = 'block';
			} finally {
				modalSubmit.disabled = false;
			}
		});
	})();

	// New: simple grade sorting via dropdown select
	(function() {
		const sortSelect = document.getElementById('gradeSortSelect');
		const tbody = document.querySelector('table.table tbody');
		if (!sortSelect || !tbody) return;

		function getRowsArray() {
			return Array.from(tbody.querySelectorAll('tr'));
		}

		function compareName(a, b) {
			const na = a.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
			const nb = b.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
			if (na === nb) return 0;
			return na > nb ? 1 : -1;
		}

		function cmpGrade(a, b) {
			const ga = (a.getAttribute('data-grade') || '').trim();
			const gb = (b.getAttribute('data-grade') || '').trim();

			// numeric compare if both numeric
			const na = parseFloat(ga);
			const nb = parseFloat(gb);
			const aNum = !isNaN(na) && ga !== '';
			const bNum = !isNaN(nb) && gb !== '';

			if (aNum && bNum) {
				if (na < nb) return -1;
				if (na > nb) return 1;
			} else if (aNum && !bNum) {
				return -1;
			} else if (!aNum && bNum) {
				return 1;
			}

			// otherwise compare grade as string, numeric-aware
			const gcmp = ga.localeCompare(gb, undefined, { numeric: true, sensitivity: 'base' });
			if (gcmp !== 0) return gcmp;

			// tie-breaker: student name
			return compareName(a, b);
		}

		sortSelect.addEventListener('change', function() {
			const val = sortSelect.value;
			let rows = getRowsArray();

			if (val === '__default__') {
				rows.sort((a, b) => compareName(a, b));
			} else if (val === 'grade_asc') {
				rows.sort((a, b) => cmpGrade(a, b));
			} else if (val === 'grade_desc') {
				rows.sort((a, b) => -cmpGrade(a, b));
			}

			rows.forEach(r => tbody.appendChild(r));
		});
	})();
	</script>
</body>
</html>
