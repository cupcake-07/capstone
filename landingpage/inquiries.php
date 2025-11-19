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
    body { font-family: Arial, Helvetica, sans-serif; margin: 20px; }
    table { width:100%; border-collapse: collapse; margin-top: 12px; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
    th { background: #f1f1f1; }
    .meta { font-size: 12px; color: #555; }
    .actions { margin-bottom: 12px; }
    .btn { padding: 8px 12px; border-radius: 4px; background: #2864fc; color: #fff; border: none; cursor: pointer; }
</style>
</head>
<body>
    <h1>Submitted Inquiries</h1>
    <p class="meta">A simple list of inquiries sent via the "Inquire Now" modal.</p>

    <div class="actions">
        <button id="exportCsvBtn" class="btn">Export CSV</button>
        <button id="clearBtn" class="btn" style="background:#cc2b2b">Clear All</button>
    </div>

    <?php if (empty($entries)): ?>
        <p>No inquiries yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Timestamp</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $i => $row): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($row['timestamp'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['message'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars($row['ip'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<script>
// Export the entries shown on this page as CSV
document.getElementById('exportCsvBtn').addEventListener('click', function () {
    const rows = Array.from(document.querySelectorAll('table tr'));
    const csv = rows.map(r => Array.from(r.querySelectorAll('th,td')).map(c => '"' + c.innerText.replace(/"/g, '""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'inquiries.csv';
    a.click();
    URL.revokeObjectURL(url);
});

// Clear all entries (deletes the file) - basic confirmation
document.getElementById('clearBtn').addEventListener('click', function () {
    if (!confirm('Clear all inquiries? This cannot be undone.')) return;
    fetch('clear_inquiries.php', { method: 'POST' }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.error || 'Failed to clear');
    }).catch(e => alert('Error: ' + e));
});
</script>
</body>
</html>
