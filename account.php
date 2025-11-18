<?php
// Use same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once 'config/database.php';

// --- NEW: helpers (guarded) ------------------------------------------------
if (!function_exists('tableExists')) {
    function tableExists($conn, $table) {
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$t}'");
        return ($res && $res->num_rows > 0);
    }
}
if (!function_exists('columnExists')) {
    function columnExists($conn, $table, $column) {
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
        return ($res && $res->num_rows > 0);
    }
}
if (!function_exists('detectPaymentsTimestampColumn')) {
    function detectPaymentsTimestampColumn($conn) {
        $candidates = ['created_at','paid_at','payment_date','date','timestamp','paid_on'];
        foreach ($candidates as $col) {
            if (columnExists($conn, 'payments', $col)) return $col;
        }
        return null;
    }
}
if (!function_exists('detectPaymentsAmountColumn')) {
    function detectPaymentsAmountColumn($conn) {
        $candidates = ['amount','paid_amount','payment_amount','amt'];
        foreach ($candidates as $col) {
            if (columnExists($conn, 'payments', $col)) return $col;
        }
        return null;
    }
}
// ---------------------------------------------------------------------------

// (GET STUDENT ID)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$studentId = intval($_SESSION['user_id']);

// Build payment rows: date + amount only, prefer fees -> payments relation and exclude categories
$paymentsList = [];
$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');
$paymentsTs = detectPaymentsTimestampColumn($conn) ?? 'created_at';
$paymentsAmtCol = detectPaymentsAmountColumn($conn) ?? 'amount';
$excludeCategories = ["Other Fees", "Other Fee", "Scholarships", "Scholarship"];

if ($paymentsExists && $feesExists && columnExists($conn, 'payments', 'fee_id')) {
    $sql = "SELECT p.{$paymentsTs} AS paid_at, p.{$paymentsAmtCol} AS amount
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            WHERE f.student_id = ?
              AND f.category NOT IN ('" . implode("','", array_map(function($s){ return addslashes($s); }, $excludeCategories)) . "')
            ORDER BY p.{$paymentsTs} DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $paymentsList[] = $r;
        }
        $stmt->close();
    }
} elseif ($paymentsExists && columnExists($conn, 'payments', 'student_id')) {
    // Fallback: payments by student_id (can't exclude fees categories)
    $sql = "SELECT p.{$paymentsTs} AS paid_at, p.{$paymentsAmtCol} AS amount
            FROM payments p
            WHERE p.student_id = ?
            ORDER BY p.{$paymentsTs} DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $paymentsList[] = $r;
        }
        $stmt->close();
    }
} else {
    // No payments or fees table -> paymentsList stays empty
}

// --- NEW: Compute current balance mirroring admin AccountBalance.php logic ---
if (!defined('FIXED_TOTAL_FEE')) define('FIXED_TOTAL_FEE', 15000.00);

$totalFees = (float)FIXED_TOTAL_FEE;
$studentPaid = 0.0;
$studentBalance = $totalFees;

// if payments exist and are linked to fees, exclude categories; else sum by student_id
if ($paymentsExists && $feesExists && columnExists($conn, 'payments', 'fee_id')) {
    $sql = "SELECT IFNULL(SUM(p.{$paymentsAmtCol}), 0) AS total_payments
            FROM payments p
            JOIN fees f2 ON p.fee_id = f2.id
            WHERE f2.student_id = ?
              AND f2.category NOT IN ('" . implode("','", array_map(function($s){ return addslashes($s); }, $excludeCategories)) . "')
              AND p.{$paymentsAmtCol} IS NOT NULL";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $stmt->bind_result($sumPayments);
        if ($stmt->fetch()) {
            $studentPaid = (float)$sumPayments;
        }
        $stmt->close();
    }
} elseif ($paymentsExists && columnExists($conn, 'payments', 'student_id')) {
    $sql = "SELECT IFNULL(SUM(p.{$paymentsAmtCol}), 0) AS total_payments FROM payments p WHERE p.student_id = ? AND p.{$paymentsAmtCol} IS NOT NULL";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $stmt->bind_result($sumPayments);
        if ($stmt->fetch()) {
            $studentPaid = (float)$sumPayments;
        }
        $stmt->close();
    }
} else {
    $studentPaid = 0.0;
}

$studentBalance = round($totalFees - $studentPaid, 2);
$studentBalanceFormatted = '₱' . number_format($studentBalance, 2);
$totalFeesFormatted = '₱' . number_format($totalFees, 2);
// --- END NEW CODE ---

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$name = htmlspecialchars($_SESSION['user_name'] ?? 'Student', ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Account Balance</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- TOP NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
      <div class="navbar-logo">GGF</div>
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <div class="user-menu">
        <span><?php echo $name; ?></span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Account Balance</h1>
      </header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <!-- ...existing content from account.html... -->
          <div class="card small-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="mini-card" style="background: linear-gradient(180deg, #0b1220 0%, #0d1114 100%); color: #ffffff; border: 1px solid rgba(218, 218, 24, 0.2);">
              <div class="mini-head" style="color: #ffffff;">Current Balance</div>
              <div class="mini-val" style="color: #ffffff;"><?php echo htmlspecialchars($studentBalanceFormatted); ?></div>
              
            </div>
            <div class="mini-card">
              <div class="mini-head">Tuition Due</div>
              <div class="mini-val"><?php echo htmlspecialchars($totalFeesFormatted); ?></div>
              
            </div>
            <!-- Removed Other Fees and Scholarships per design update -->
          </div>

          <div class="card large">
            <div class="card-head">
              <h3>Payment History</h3>
            </div>
            <div class="card-body">
              <table class="grades-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th class="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($paymentsList)): ?>
                      <?php foreach ($paymentsList as $p): 
                          $paidAt = htmlspecialchars($p['paid_at'] ?? '');
                          // Format date if usable
                          $dateText = '-';
                          $ts = strtotime($paidAt);
                          if ($ts !== false && $ts !== -1) {
                              $dateText = date('M d, Y', $ts);
                          } elseif ($paidAt !== '') {
                              $dateText = htmlspecialchars($paidAt);
                          }
                          $amountVal = number_format((float)($p['amount'] ?? 0), 2);
                          $amountText = '₱' . $amountVal;
                      ?>
                      <tr>
                          <td><?php echo $dateText; ?></td>
                          <td class="text-right"><?php echo $amountText; ?></td>
                      </tr>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <tr>
                          <td colspan="2" class="text-center">No payment history found.</td>
                      </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    (function(){
      const year = document.getElementById('year');
      if(year) year.textContent = new Date().getFullYear();
    })();
  </script>
</body>
</html>