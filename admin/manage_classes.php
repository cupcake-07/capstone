<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database config
require_once '../config/database.php';
require_once '../config/admin-session.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

// DENY ACCESS - Classes page is disabled
header('Location: ../admin.php');
exit;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        nav {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        nav h2 {
            margin: 0;
        }

        nav a {
            color: white;
            text-decoration: none;
            background-color: #e74c3c;
            padding: 8px 15px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        nav a:hover {
            background-color: #c0392b;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .form-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .form-container h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table-container h2 {
            padding: 20px 25px 0 25px;
            color: #2c3e50;
            font-size: 18px;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-buttons a {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background-color: #3498db;
            color: white;
        }

        .action-buttons a:hover {
            background-color: #2980b9;
        }

        .action-buttons a.schedule {
            background-color: #27ae60;
        }

        .action-buttons a.schedule:hover {
            background-color: #229954;
        }

        .empty-state {
            padding: 40px;
            text-align: center;
            color: #7f8c8d;
        }

        .required {
            color: #e74c3c;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

<div class="container">
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <h2><?php echo $edit_class ? 'Edit Class' : 'Create New Class'; ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="<?php echo $edit_class ? 'update' : 'create'; ?>">
            <?php if ($edit_class): ?>
                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($edit_class['id']); ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Class Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($edit_class['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="subject">Subject <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" required value="<?php echo htmlspecialchars($edit_class['subject'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="room">Room <span class="required">*</span></label>
                    <input type="text" id="room" name="room" required value="<?php echo htmlspecialchars($edit_class['room'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="teacher_id">Assigned Teacher <span class="required">*</span></label>
                <select id="teacher_id" name="teacher_id" required>
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo htmlspecialchars($teacher['id']); ?>" 
                            <?php echo ($edit_class && $edit_class['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($teacher['name']); ?>
                            <?php if ($teacher['subject']): ?>(<?php echo htmlspecialchars($teacher['subject']); ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($edit_class['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="button-group">
                <button type="submit" class="btn btn-primary"><?php echo $edit_class ? 'Update Class' : 'Create Class'; ?></button>
                <?php if ($edit_class): ?>
                    <a href="manage_classes.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="table-container">
        <h2>All Classes</h2>
        <?php if (empty($classes)): ?>
            <div class="empty-state">
                <p>No classes found. Create your first class to get started.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Subject</th>
                        <th>Room</th>
                        <th>Teacher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['name']); ?></td>
                            <td><?php echo htmlspecialchars($class['subject'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($class['room'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Unassigned'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="manage_classes.php?edit=<?php echo htmlspecialchars($class['id']); ?>">Edit</a>
                                    <a href="manage_schedule.php?class_id=<?php echo htmlspecialchars($class['id']); ?>" class="schedule">Schedule</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this class?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
