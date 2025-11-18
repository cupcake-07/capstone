<?php
// Sidebar include — used by files inside the admin/ folder.
// Assumes $user = getAdminSession(); is defined in the including file.
?>
<aside class="sidebar">
	<div class="brand">Glorious God's Family<span>Christian School</span></div>
	<nav>
		<a href="../admin.php">Dashboard</a>
		<a href="students.php">Students</a>
		<a href="schedule.php">Schedule</a>
		<a href="teachers.php">Teachers</a>
		<a href="reports.php">Reports</a>
		<a href="AccountBalance.php">Account Balance</a>
		<a href="settings.php">Settings</a>
		<a href="../logout.php?type=admin">Logout</a>
	</nav>
	<div class="sidebar-foot">Logged in as <strong><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></strong></div>
</aside>