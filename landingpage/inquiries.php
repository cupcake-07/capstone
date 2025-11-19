<?php
// Simple admin page: lists inquiries saved by submit_inquiry.php
$dataFile = __DIR__ . '/data/inquiries.json';
$entries = [];
if (file_exists($dataFile) && is_readable($dataFile)) {
    $raw = file_get_contents($dataFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $entries = $decoded;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Submitted Inquiries</title>
<link rel="stylesheet" href="style.css">
<style>
/* filepath: c:\xampp\htdocs\capstone\landingpage\inquiries.php (inline CSS overrides) */
body { font-family: 'Montserrat', Arial, sans-serif; margin: 20px; background: #f7f8ff; }
.inquiries-table { max-width:1100px; margin: 16px auto; background: #fff; padding: 18px; border-radius: 12px; box-shadow: 0 16px 30px rgba(12,20,60,0.06); border: 1px solid rgba(0,0,0,0.06); position: relative; }
.inquiries-table::before { content: ""; position: absolute; inset: 6px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.03); pointer-events: none; }
.inquiries-table:hover { border-color: #cbd8ff; transition: border-color 150ms ease; }
.inquiries-table:focus-within { box-shadow: 0 16px 30px rgba(12,20,60,0.06), 0 0 0 3px rgba(40,100,252,0.06); }
h1 { margin: 0 0 8px 0; color: #1230d6; }
.meta { font-size: 13px; color: #666; margin-bottom: 14px; }
.actions { margin-bottom: 12px; text-align:right; }
.btn { padding: 8px 12px; border-radius: 6px; color: #fff; border: none; cursor: pointer; background: linear-gradient(90deg,#2864fc,#ff25db); margin-left:8px; }
table { width:100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
thead th { text-align:left; padding: 12px; border-bottom: 2px solid rgba(0,0,0,0.06); background: linear-gradient(180deg,#fafbfd 0%, #fff 100%); color:#2b2b2b; }
th.sortable { cursor: default; user-select: none; display: flex; align-items: center; gap: 8px; }
.sort-btn { background: transparent; border: 1px solid rgba(0,0,0,0.06); padding: 6px 8px; border-radius: 6px; font-size: 13px; cursor: pointer; }
.sort-btn:focus { outline: 2px solid rgba(40,100,252,0.15); }
th.actions-col { width:110px; text-align:center; }
tbody td { padding: 12px 10px; border-bottom: 1px solid rgba(0,0,0,0.04); vertical-align: top; }
tbody tr:nth-child(odd) { background: #fbfbff; }
tbody tr:hover { background: rgba(40,100,252,0.04); }
td.message-cell { max-width: 360px; white-space: pre-wrap; word-wrap: break-word; }
/* removed checkbox cell & row checkbox styles */
.delete-btn { background: transparent; border: 1px solid #d94a4a; color: #d94a4a; border-radius: 6px; padding:6px 8px; cursor: pointer; }
.delete-btn:hover { background: #d94a4a; color: #fff; }
.count-badge { display:inline-block; font-weight:600; padding:4px 8px; border-radius:12px; background:#f0f6ff; color:#1230d6; margin-left:8px; font-size:12px; }
.info-note { margin-top:10px; color:#4d4d4d; font-size:13px; }
/* date filters UI */
.filter-controls { display:inline-flex; gap:8px; align-items:center; margin-right:12px; }
.filter-controls input[type="date"] { padding:6px 8px; border-radius:6px; border:1px solid rgba(0,0,0,0.06); background:#fff; font-size:13px; }
.filter-controls .filter-btn { padding:6px 10px; border-radius:6px; border: none; background: #fff; color:#1230d6; border: 1px solid rgba(0,0,0,0.06); cursor:pointer; }
.filter-controls .filter-btn:hover { background: #f3f6ff; }
/* quick styles for the new action bar */
.action-bar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; margin-bottom:12px; }
.action-controls-left { display:flex; gap:10px; align-items:center; }
.search-input { padding:8px 10px; border:1px solid rgba(0,0,0,0.06); border-radius:6px; min-width:220px; }
.bulk-btn { background:#ff5a5a; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; }
.bulk-btn.secondary { background: linear-gradient(90deg,#2864fc,#ff25db); }
.table-actions { display:flex; gap:8px; align-items:center; }
@media (max-width:860px) { .action-bar { flex-direction:column; align-items:stretch; } .action-controls-left{ width:100%; } .table-actions{ width:100%; justify-content:flex-start; } }
</style>
</head>
<body>
    <div class="inquiries-table">
    <header style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px;">
      <div>
        <h1>Submitted Inquiries <span id="countBadge" class="count-badge"><?php echo count($entries); ?></span></h1>
        <p class="meta">Manage inquiries sent via the "Inquire Now" modal.</p>
      </div>
      <div class="table-actions">
        <button id="clearBtn" class="btn" style="background:#d94a4a">Clear All</button>
        <a class="btn" href="inquiries.php" style="text-decoration:none; display:inline-block;">Refresh</a>
      </div>
    </header>

    <div class="action-bar" role="region" aria-label="Table actions">
      <div class="action-controls-left">
        <input id="searchBox" class="search-input" type="search" placeholder="Search name, email, or text..." aria-label="Search inquiries">
        <div class="filter-controls" role="group" aria-label="Filter by date range">
            <input type="date" id="filterFrom" aria-label="Filter start date">
            <input type="date" id="filterTo" aria-label="Filter end date">
            <button id="filterApplyBtn" class="filter-btn" title="Apply date filter">Filter</button>
            <button id="filterClearBtn" class="filter-btn" title="Clear date filter">Clear</button>
        </div>
      </div>

      <div class="table-actions" role="group" aria-label="Export">
        <button id="exportCsvBtn" class="btn">Export CSV</button>
        <!-- removed bulk delete button (no more selection) -->
      </div>
    </div>

    <?php if (empty($entries)): ?>
        <p class="info-note">No inquiries yet.</p>
    <?php else: ?>
        <table id="inquiriesTable" aria-live="polite">
            <thead>
                <tr>
                    <!-- removed checkbox column header -->
                    <th>#</th>
                    <th class="sortable">
                        Timestamp
                        <!-- Visible button for sorting -->
                        <button id="tsSortBtn" class="sort-btn" aria-label="Sort by timestamp">
                            <span id="tsSortIndicator">▼</span>
                        </button>
                    </th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $i => $row): ?>
                    <tr data-ts="<?php echo htmlspecialchars($row['timestamp'] ?? ''); ?>" data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>">
                        <!-- removed per-row checkbox cell -->
                        <td class="row-num"><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($row['timestamp'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                        <td class="message-cell"><?php echo nl2br(htmlspecialchars($row['message'] ?? '')); ?></td>
                        <td style="text-align:center;">
                            <!-- per-row delete still available -->
                            <button class="delete-btn" data-ts="<?php echo htmlspecialchars($row['timestamp'] ?? ''); ?>" data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>" title="Delete this inquiry">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>

<script>
/* Organized table JS (search, filter, sort, single delete, CSV export, reindex) */
(function () {
    const table = document.getElementById('inquiriesTable');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const countBadge = document.getElementById('countBadge');
    // removed selection and bulk delete variables
    const searchBox = document.getElementById('searchBox');

    // existing elements we reuse
    const tsSortBtn = document.getElementById('tsSortBtn');
    const tsSortIndicator = document.getElementById('tsSortIndicator');
    const filterFromInput = document.getElementById('filterFrom');
    const filterToInput = document.getElementById('filterTo');
    const filterApplyBtn = document.getElementById('filterApplyBtn');
    const filterClearBtn = document.getElementById('filterClearBtn');
    const exportCsvBtn = document.getElementById('exportCsvBtn');

    let sortAscending = false; // default newest first

    // Utilities
    function parseTs(ts) {
        const t = Date.parse(ts);
        return isNaN(t) ? NaN : t;
    }
    function toMsStart(dateStr) {
        if (!dateStr) return null;
        const ms = Date.parse(dateStr + 'T00:00:00');
        return isNaN(ms) ? null : ms;
    }
    function toMsEnd(dateStr) {
        if (!dateStr) return null;
        const ms = Date.parse(dateStr + 'T23:59:59');
        return isNaN(ms) ? null : ms;
    }

    // Reindex visible rows
    function reindexRows() {
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.style.display !== 'none');
        rows.forEach((tr, i) => {
            const numCell = tr.querySelector('.row-num');
            if (numCell) numCell.innerText = i + 1;
        });
        countBadge.innerText = rows.length;
    }

    // Search filter (text across name, email, message)
    function applySearchFilter() {
        const q = (searchBox.value || '').trim().toLowerCase();
        Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
            const name = (tr.cells[2]?.innerText || '').toLowerCase(); // updated indexes after removing checkbox col
            const email = (tr.cells[3]?.innerText || '').toLowerCase();
            const message = (tr.cells[4]?.innerText || '').toLowerCase();
            const flick = !q || name.includes(q) || email.includes(q) || message.includes(q);
            tr.style.display = flick ? '' : 'none';
        });
        reindexRows();
    }

    // Date filter
    function applyDateFilter() {
        const startMs = toMsStart(filterFromInput.value);
        const endMs = toMsEnd(filterToInput.value);
        Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
            const tsVal = tr.dataset.ts || '';
            const tMs = parseTs(tsVal);
            let show = true;
            if (isNaN(tMs)) {
                show = !startMs && !endMs;
            } else {
                if (startMs !== null && tMs < startMs) show = false;
                if (endMs !== null && tMs > endMs) show = false;
            }
            // also obey search filter
            if (show && (searchBox.value || '').trim()) {
                const q = (searchBox.value || '').trim().toLowerCase();
                const name = (tr.cells[2]?.innerText || '').toLowerCase();
                const email = (tr.cells[3]?.innerText || '').toLowerCase();
                const message = (tr.cells[4]?.innerText || '').toLowerCase();
                show = !q || name.includes(q) || email.includes(q) || message.includes(q);
            }
            tr.style.display = show ? '' : 'none';
        });
        reindexRows();
    }

    // Clear date filter
    function clearDateFilter() {
        filterFromInput.value = '';
        filterToInput.value = '';
        // reapply search to show only matches
        applySearchFilter();
    }

    // Sort by timestamp
    function sortTableByTimestamp(asc) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a,b) => {
            const ta = parseTs(a.dataset.ts || '');
            const tb = parseTs(b.dataset.ts || '');
            if (isNaN(ta) && isNaN(tb)) return 0;
            if (isNaN(ta)) return 1;
            if (isNaN(tb)) return -1;
            return asc ? ta - tb : tb - ta;
        });
        rows.forEach(r => tbody.appendChild(r));
        // reindex only the visible rows
        reindexRows();
    }

    // Attach per-row delete handlers
    function attachDeleteHandlers() {
        const buttons = tbody.querySelectorAll('.delete-btn');
        buttons.forEach(btn => {
            btn.removeEventListener('click', handleDelete);
            btn.addEventListener('click', handleDelete);
        });
    }
    function handleDelete(e) {
        const btn = e.currentTarget;
        const ts = btn.dataset.ts;
        const email = btn.dataset.email;
        if (!ts || !email) return alert('Invalid entry identifier');

        if (!confirm('Delete this inquiry? This cannot be undone.')) return;
        btn.disabled = true;

        fetch('delete_inquiry.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ ts: ts, email: email })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // remove the row then reindex
                const row = tbody.querySelector(`tr[data-ts="${cssEscape(ts)}"][data-email="${cssEscape(email)}"]`);
                if (row) row.remove();
                reindexRows();
            } else {
                alert(data.error || 'Failed to delete entry');
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Deletion error, check console');
            btn.disabled = false;
        });
    }

    // Export visible rows (with current filters) to CSV
    function exportCsv() {
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.style.display !== 'none');
        if (!rows.length) return alert('No entries to export.');
        const csvRows = [];
        csvRows.push(['#', 'Timestamp', 'Name', 'Email', 'Message'].map(h => `"${h}"`).join(','));
        rows.forEach((r, i) => {
            // adjust indexes - the first column is now the row number column
            const cols = Array.from(r.querySelectorAll('td')).filter(td => !td.classList.contains('actions-col'));
            // drop the row number column when composing CSV
            const dataCols = cols.slice(1); 
            const texts = dataCols.map(c => '"' + c.innerText.replace(/"/g, '""') + '"');
            csvRows.push(texts.join(','));
        });
        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'inquiries.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    // Simple utility to escape special CSS attribute selectors if needed
    function cssEscape(str) {
        return ('' + str).replace(/(["'\\])/g, '\\$1');
    }

    // Event wiring
    if (tsSortBtn) {
        tsSortBtn.addEventListener('click', () => {
            sortAscending = !sortAscending;
            tsSortIndicator.innerText = sortAscending ? '▲' : '▼';
            sortTableByTimestamp(sortAscending);
        });
    }
    if (filterApplyBtn) filterApplyBtn.addEventListener('click', applyDateFilter);
    if (filterClearBtn) filterClearBtn.addEventListener('click', () => { clearDateFilter(); applySearchFilter(); });
    if (searchBox) {
        searchBox.addEventListener('input', function () {
            // Debounce quick input for a better UX
            clearTimeout(searchBox._timer);
            searchBox._timer = setTimeout(() => applySearchFilter(), 250);
        });
    }
    if (exportCsvBtn) exportCsvBtn.addEventListener('click', exportCsv);

    // initialize handlers and UI
    attachDeleteHandlers();
    reindexRows();

    // Observe row changes to reattach handlers after reordering or deletion
    const observer = new MutationObserver(function () {
        attachDeleteHandlers();
    });
    if (tbody) observer.observe(tbody, { childList: true, subtree: false });

})();
</script>
</body>
</html>
