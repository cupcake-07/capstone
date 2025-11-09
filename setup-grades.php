<?php
require_once __DIR__ . '/config/database.php';

if (!$conn) {
    die("Database connection failed!");
}

$updateResult = $conn->query("UPDATE students SET grade_level = '1', section = 'A' WHERE grade_level IS NULL OR grade_level = '' OR grade_level = 'Not Set'");

if ($updateResult) {
    $affected = $conn->affected_rows;
    echo "<h2>✓ Success!</h2>";
    echo "<p>Updated <strong>$affected</strong> students to Grade 1, Section A</p>";
    echo "<p><a href='admin.php'>Go to Admin Dashboard</a></p>";
} else {
    echo "<h2>✗ Error:</h2>";
    echo "<p>" . $conn->error . "</p>";
}

$conn->close();
