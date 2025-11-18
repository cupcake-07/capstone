<div class="content-wrapper">
	<!-- Content Header -->
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1>Account balance</h1>
				</div>
				<div class="col-sm-6">
					<!-- breadcrumb or actions -->
					<ol class="breadcrumb float-sm-right">
						<li class="breadcrumb-item"><a href="./index.php">Home</a></li>
						<li class="breadcrumb-item active">Account balance</li>
					</ol>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="card card-dark"> <!-- use a dark style card -->
				<div class="card-header">
					<h3 class="card-title">Students balances (excluding Other Fees & Scholarships)</h3>
				</div>

				<div class="card-subtitle" style="padding:6px 16px; color:#9aa6b2; font-size:13px;">
					Account Balance = Total Fee - Total Paid
				</div>

				<?php if (!empty($errorMsg)) : ?>
					<div class="card-body">
						<div class="alert alert-warning" role="alert" style="margin-bottom:0;">
							<?= htmlspecialchars($errorMsg) ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="card-body table-responsive p-0">
					<table class="table table-account-balance">
						<thead>
							<tr>
								<th style="width:40px">#</th>
								<th>Student</th>
								<th class="text-right">Total Fee</th>
								<th class="text-right">Total Paid</th>
								<th class="text-right">Account Balance</th>
								<th>Manage</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($balances)) : ?>
								<?php foreach ($balances as $i => $row) :
									$balance = (float)$row['balance'];
								?>
									<tr data-student-id="<?= (int)$row['student_id'] ?>">
										<td><?= $i + 1 ?></td>
										<td><?= htmlspecialchars($row['student_name']) ?></td>
										<td class="text-right total_fee"><?= number_format((float)$row['total_fees'], 2) ?></td>
										<td class="text-right total_paid"><?= number_format((float)$row['total_payments'], 2) ?></td>
										<td class="text-right balance <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
											<?= number_format($balance, 2) ?>
										</td>
										<td>
											<button class="btn btn-sm btn-secondary manage-payment" data-student-id="<?= (int)$row['student_id'] ?>"
												data-student-name="<?= htmlspecialchars($row['student_name']) ?>">Manage</button>
											<!-- Quick Pay button; disabled if balance <= 0 -->
											<button class="btn btn-sm btn-primary btn-pay" data-student-id="<?= (int)$row['student_id'] ?>"
												data-student-name="<?= htmlspecialchars($row['student_name']) ?>" data-balance="<?= htmlspecialchars(number_format((float)$balance, 2, '.', '')) ?>"
												<?= ($balance <= 0) ? 'disabled' : '' ?>>Pay</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr>
									<td colspan="6" class="text-center">No records found.</td>
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
	</section>
</div>

<!-- Payment modal -->
<div id="paymentModal" class="payment-modal" aria-hidden="true" role="dialog">
	<div class="payment-modal-dialog">
		<header class="payment-modal-header">
			<h3 id="paymentModalTitle">Manage Payments</h3>
			<button id="closePaymentModal" class="btn btn-clear" title="Close">×</button>
		</header>

		<section class="payment-modal-body">
			<div id="paymentAlert" style="display:none;" class="alert"></div>

			<div style="margin-bottom: 12px;">
				<strong>Payments</strong>
				<table id="paymentsTable" style="width:100%; border-collapse:collapse; margin-top:8px;">
					<thead>
						<tr>
							<th style="text-align:left; width:120px;">Date</th>
							<th style="text-align:left;">Description</th>
							<th style="text-align:right; width:110px;">Amount</th>
							<th style="width:80px;"></th>
						</tr>
					</thead>
					<tbody>
						<tr><td colspan="4" id="paymentsLoading">Loading…</td></tr>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2"><strong>Total Paid</strong></td>
							<td style="text-align:right;"><strong id="paymentsTotal">0.00</strong></td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>

			<div>
				<strong>Add Payment</strong>
				<!-- quick-pay flag hidden input added to the add payment form -->
				<form id="addPaymentForm" style="display:grid; gap:8px; margin-top:8px;">
					<input type="hidden" name="student_id" id="paymentStudentId" value="">
					<input type="hidden" name="quick_pay" id="quickPayFlag" value="0" />
					<div style="display:flex; gap:8px;">
						<input name="amount" id="paymentAmount" placeholder="Amount" type="number" step="0.01" required style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px;" />
						<input name="date" id="paymentDate" type="date" style="padding:8px;border:1px solid #ddd;border-radius:6px;" />
					</div>
					<input name="description" id="paymentDescription" placeholder="Description (optional)" style="padding:8px;border:1px solid #ddd;border-radius:6px;" />
					<div style="display:flex; gap:8px; justify-content:flex-end;">
						<button type="submit" class="btn btn-primary">Add Payment</button>
						<button type="button" id="cancelAddPayment" class="btn btn-secondary">Cancel</button>
					</div>
				</form>
			</div>
		</section>
	</div>
</div>

<style>
/* File-specific styling that matches the screenshot's dark table appearance.
   These rules keep consistent spacing with existing admin styles while applying
   the dark theme locally for this page only. */
.card-dark { background: #0b1114; color: #e6eef5; border: 1px solid #111827; }
.card-dark .card-header { background: transparent; border-bottom: 1px solid rgba(255,255,255,0.03); color: #e6eef5; padding: 12px 16px; }
.card-dark .card-body { background: transparent; color: #e6eef5; }

/* Table appearance */
.table-account-balance { width: 100%; border-collapse: collapse; font-family: inherit; }
.table-account-balance thead th {
	background: #0c1116;
	color: #cbd5e1;
	font-weight: 700;
	padding: 14px 12px;
	border-bottom: 1px solid rgba(255,255,255,0.03);
	text-align: left;
	font-size: 14px;
}
.table-account-balance tbody tr { background: transparent; border-bottom: 1px solid rgba(255,255,255,0.03); }
.table-account-balance tbody tr:hover { background: rgba(255,255,255,0.02); }
.table-account-balance td { padding: 12px; vertical-align: middle; color: #e6eef5; font-size: 14px; }

/* Right aligned numeric columns */
.table-account-balance td.text-right { text-align: right; font-variant-numeric: tabular-nums; }

/* Balance colors: green for cleared (0 or negative), red for outstanding positive balance. */
.table-account-balance .text-success { color: #10b981; } /* Tailwind emerald-500 */
.table-account-balance .text-danger { color: #ef4444; } /* Tailwind red-500 */

/* Subtle number styling */
.table-account-balance td { letter-spacing: 0.01em; }
.table-account-balance tbody tr td:first-child { color: #94a3b8; } /* index column */
.table-account-balance tbody tr td:nth-child(2) { font-weight: 600; color: #e6eef5; }

/* Responsive: ensure small screens still align right for numbers */
@media (max-width: 768px) {
	.table-account-balance thead th, .table-account-balance td { padding: 10px 8px; font-size: 13px; }
}

/* modal minimal styling */
.payment-modal { display:none; position:fixed; inset:0; align-items:center; justify-content:center; background: rgba(0,0,0,0.48); z-index:1200; }
.payment-modal[aria-hidden="false"] { display:flex; }
.payment-modal-dialog { background:#fff; width: 800px; max-width: 95%; border-radius:8px; box-shadow:0 18px 60px rgba(2,6,23,0.3); overflow:hidden; }
.payment-modal-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #f0f0f0; }
.payment-modal-body { padding: 16px; max-height:65vh; overflow:auto; }
.btn-clear { background:none; border:none; font-size:22px; cursor:pointer; }
.btn-secondary { background:#f1f5f9; color:#111; padding:6px 12px; border-radius:8px; border:none; cursor:pointer; }
.btn-primary { background:#2563eb; color:#fff; padding:6px 12px; border-radius:8px; border:none; cursor:pointer; }
.table td, .table th { padding:12px; }
.alert { padding:8px 12px; border-radius:6px; margin-bottom:12px; }
.alert-success { background:#e6ffed; color:#065f46; border:1px solid #b7f2c0; }
.alert-error { background:#ffebe6; color:#7a1b0b; border:1px solid #f5c2b7; }
</style>

<script>
(function(){
	const modal = document.getElementById('paymentModal');
	const closeBtn = document.getElementById('closePaymentModal');
	const paymentsTableBody = document.querySelector('#paymentsTable tbody');
	const paymentsTotalEl = document.getElementById('paymentsTotal');
	const paymentsLoadingRow = document.getElementById('paymentsLoading');
	const alertEl = document.getElementById('paymentAlert');
	const addForm = document.getElementById('addPaymentForm');
	const studentIdInput = document.getElementById('paymentStudentId');
	const amountInput = document.getElementById('paymentAmount');
	const dateInput = document.getElementById('paymentDate');
	const descInput = document.getElementById('paymentDescription');

	function showAlert(type, text) {
		alertEl.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
		alertEl.textContent = text;
		alertEl.style.display = 'block';
		setTimeout(() => { alertEl.style.display = 'none'; }, 3750);
	}

	function openModal(studentId, studentName, rowEl) {
		document.getElementById('paymentModalTitle').textContent = 'Manage Payments · ' + studentName;
		studentIdInput.value = studentId;
		amountInput.value = '';
		descInput.value = '';
		dateInput.value = new Date().toISOString().slice(0,10);
		fetchPayments(studentId, rowEl);
		modal.setAttribute('aria-hidden', 'false');
	}

	function closeModal() {
		modal.setAttribute('aria-hidden', 'true');
	}

	closeBtn.addEventListener('click', closeModal);
	document.getElementById('cancelAddPayment').addEventListener('click', function(e){ e.preventDefault(); closeModal(); });

	document.addEventListener('click', function(e){
		const btn = e.target.closest ? e.target.closest('.manage-payment') : null;
		if (!btn) return;
		const studentId = btn.dataset.studentId;
		const studentName = btn.dataset.studentName;
		const rowEl = btn.closest('tr');
		openModal(studentId, studentName, rowEl);
	});

	// Quick Pay handler
	document.addEventListener('click', function(e){
		const payBtn = e.target.closest ? e.target.closest('.btn-pay') : null;
		if (!payBtn) return;
		const studentId = payBtn.dataset.studentId;
		const studentName = payBtn.dataset.studentName || '';
		const rawBalance = payBtn.dataset.balance || '';
		// parse balance, remove commas if any
		const balanceVal = Number(String(rawBalance).replace(/,/g,'') || 0);

		// set form values and quick flag
		document.getElementById('paymentStudentId').value = studentId;
		document.getElementById('paymentAmount').value = balanceVal > 0 ? balanceVal.toFixed(2) : '0.00';
		document.getElementById('paymentDescription').value = 'Quick payment';
		document.getElementById('paymentDate').value = new Date().toISOString().slice(0,10);
		document.getElementById('quickPayFlag').value = '1';

		// find row element to update after payment
		const rowEl = payBtn.closest('tr');
		openModal(studentId, studentName, rowEl);
	});

	function fetchPayments(studentId, rowEl) {
		paymentsLoadingRow.textContent = 'Loading…';
		paymentsTableBody.innerHTML = '<tr><td colspan="4" id="paymentsLoading">Loading…</td></tr>';
		fetch('api/get_student_payments.php?student_id=' + encodeURIComponent(studentId), { credentials: 'same-origin' })
			.then(r => { if (!r.ok) throw new Error('Network'); return r.json(); })
			.then(json => {
				if (!json || !json.success) {
					showAlert('error', (json && json.message) || 'Failed to load payments');
					paymentsTableBody.innerHTML = '<tr><td colspan="4">Unable to load payments</td></tr>';
					return;
				}
				const rows = json.data || [];
				let total = 0;
				if (rows.length === 0) {
					paymentsTableBody.innerHTML = '<tr><td colspan="4">No payments</td></tr>';
				} else {
					paymentsTableBody.innerHTML = '';
					rows.forEach(p => {
						const tr = document.createElement('tr');
						const date = new Date(p.created_at || p.payment_date || p.created || p.date || p.ts || '').toLocaleDateString();
						const desc = p.description || (p.note || '') || '';
						const amount = Number(p.amount || 0).toFixed(2);
						total += Number(p.amount || 0);
						tr.innerHTML = `
							<td style="text-align:left">${date}</td>
							<td style="text-align:left">${escapeHtml(desc)}</td>
							<td style="text-align:right">${numberFormat(amount)}</td>
							<td style="text-align:center"><button class="btn btn-secondary delete-payment" data-payment-id="${p.id}">Delete</button></td>
						`;
						paymentsTableBody.appendChild(tr);
					});
				}
				paymentsTotalEl.textContent = numberFormat(total.toFixed(2));
				// Update totals in the row if provided
				if (rowEl) {
					const totalPaidCell = rowEl.querySelector('.total_paid');
					const balanceCell = rowEl.querySelector('.balance');
					if (totalPaidCell) totalPaidCell.textContent = numberFormat(total.toFixed(2));
					if (balanceCell) {
						const fee = parseFloat(rowEl.querySelector('.total_fee')?.textContent.replace(/,/g, '') || '0');
						const newBalance = (fee - total).toFixed(2);
						balanceCell.textContent = numberFormat(newBalance);
						// toggle color
						if (Number(newBalance) > 0) {
							balanceCell.classList.remove('text-success'); balanceCell.classList.add('text-danger');
						} else {
							balanceCell.classList.remove('text-danger'); balanceCell.classList.add('text-success');
						}
					}
				}
			})
			.catch(err => {
				console.error('Failed to fetch payments', err);
				paymentsTableBody.innerHTML = '<tr><td colspan="4">Error loading payments</td></tr>';
			});
	}

	addForm.addEventListener('submit', function(e){
		e.preventDefault();
		const data = new FormData(addForm);
		const studentId = studentIdInput.value || '';
		if (!studentId) { showAlert('error', 'Missing student'); return; }
		const amount = parseFloat(data.get('amount') || 0);
		if (!amount || amount <= 0) { showAlert('error', 'Enter a valid amount'); return; }
		// capture quick flag
		const isQuick = (data.get('quick_pay') === '1');
		// send
		fetch('api/add_payment.php', { method: 'POST', credentials: 'same-origin', body: data })
			.then(r => r.json())
			.then(json => {
				if (!json || !json.success) {
					showAlert('error', (json && json.message) || 'Failed to add payment');
					return;
				}
				showAlert('success', 'Payment added');
				// refresh list and update table row totals
				const rowEl = document.querySelector('tr[data-student-id="'+studentId+'"]');
				fetchPayments(studentId, rowEl);
				// reset quick flag
				if (isQuick) {
					document.getElementById('quickPayFlag').value = '0';
					// close modal on quick pay
					closeModal();
				}
				amountInput.value = '';
				descInput.value = '';
			})
			.catch(err => {
				console.error('Add payment failed', err);
				showAlert('error', 'Request failed');
			});
	});

	// Delete handler (delegated)
	paymentsTableBody.addEventListener('click', function(e){
		const btn = e.target.closest ? e.target.closest('.delete-payment') : null;
		if (!btn) return;
		const paymentId = btn.dataset.paymentId;
		if (!confirm('Delete this payment?')) return;
		fetch('api/delete_payment.php', { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ payment_id: paymentId }) })
			.then(r => r.json())
			.then(json => {
				if (!json || !json.success) { showAlert('error', (json && json.message) || 'Delete failed'); return; }
				showAlert('success', 'Payment removed');
				// Refresh table data for current student
				const currentStudent = document.getElementById('paymentStudentId').value;
				const rowEl = document.querySelector('tr[data-student-id="'+currentStudent+'"]');
				fetchPayments(currentStudent, rowEl);
			})
			.catch(err => {
				console.error('Delete failed', err);
				showAlert('error', 'Request failed');
			});
	});

	// Utilities
	function numberFormat(n) {
		return Number(n).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
	}
	function escapeHtml(s){ if(!s) return ''; return s.replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

	// close modal on ESC
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal(); });

})();
</script>
