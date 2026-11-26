<?php
require_once __DIR__ . '/db.php';

$message = '';
$message_type = '';

$validPriorities = ['high', 'medium', 'low'];
$validStatuses = ['pending', 'in_progress', 'completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', $validPriorities, true) ? $_POST['priority'] : 'medium';
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if ($title !== '') {
            $stmt = $db->prepare('INSERT INTO tasks (title, description, priority, due_date) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $description, $priority, $due_date]);
            $message = 'Task added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Task title cannot be empty.';
            $message_type = 'warning';
        }
    } elseif ($action === 'update_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', $validStatuses, true) ? $_POST['status'] : 'pending';
        $stmt = $db->prepare('UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $id]);
        $message = 'Task status updated!';
        $message_type = 'success';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Task deleted.';
        $message_type = 'success';
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', $validPriorities, true) ? $_POST['priority'] : 'medium';
        $status = in_array($_POST['status'] ?? '', $validStatuses, true) ? $_POST['status'] : 'pending';
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if ($id > 0 && $title !== '') {
            $stmt = $db->prepare('UPDATE tasks SET title = ?, description = ?, priority = ?, status = ?, due_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$title, $description, $priority, $status, $due_date, $id]);
            $message = 'Task updated!';
            $message_type = 'success';
        }
    }
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = [];
$params = [];
if ($filter_status !== 'all' && in_array($filter_status, $validStatuses, true)) {
    $where[] = 'status = ?';
    $params[] = $filter_status;
} else {
    $filter_status = 'all';
}
if ($filter_priority !== 'all' && in_array($filter_priority, $validPriorities, true)) {
    $where[] = 'priority = ?';
    $params[] = $filter_priority;
} else {
    $filter_priority = 'all';
}
if ($search !== '') {
    $where[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("SELECT * FROM tasks $where_clause ORDER BY
    CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
    CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END,
    created_at DESC");
$stmt->execute($params);
$taskList = $stmt->fetchAll();

$total = (int) $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
$pending = (int) $db->query("SELECT COUNT(*) FROM tasks WHERE status='pending'")->fetchColumn();
$in_progress = (int) $db->query("SELECT COUNT(*) FROM tasks WHERE status='in_progress'")->fetchColumn();
$completed = (int) $db->query("SELECT COUNT(*) FROM tasks WHERE status='completed'")->fetchColumn();

$edit_task = null;
if (isset($_GET['edit'])) {
    $editStmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');
    $editStmt->execute([(int) $_GET['edit']]);
    $edit_task = $editStmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Task Manager — Demo Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-wrapper">

        <!-- Header -->
        <header class="app-header">
            <div class="header-brand">
                <div class="brand-icon">✓</div>
                <div>
                    <h1>Task Manager</h1>
                    <span>Powered by PHP + PDO (<?= strtoupper(htmlspecialchars($db_engine)) ?>)</span>
                </div>
            </div>
            <button class="btn btn-primary" onclick="toggleModal('add-modal')">+ New Task</button>
        </header>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card">
                <span class="stat-num"><?= $total ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-card pending">
                <span class="stat-num"><?= $pending ?></span>
                <span class="stat-label">Pending</span>
            </div>
            <div class="stat-card in-progress">
                <span class="stat-num"><?= $in_progress ?></span>
                <span class="stat-label">In Progress</span>
            </div>
            <div class="stat-card completed">
                <span class="stat-num"><?= $completed ?></span>
                <span class="stat-label">Completed</span>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($message): ?>
        <div class="alert <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="Search tasks..." value="<?= htmlspecialchars($search) ?>" class="filter-input">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
                <select name="priority" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $filter_priority === 'all' ? 'selected' : '' ?>>All Priority</option>
                    <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
                </select>
                <button type="submit" class="btn btn-outline">Filter</button>
                <a href="index.php" class="btn btn-ghost">Clear</a>
            </form>
        </div>

        <!-- Task List -->
        <div class="task-grid">
            <?php if (count($taskList) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>No tasks found</h3>
                <p>Add your first task to get started!</p>
            </div>
            <?php else: ?>
            <?php foreach ($taskList as $task): ?>
            <div class="task-card priority-<?= htmlspecialchars($task['priority']) ?> status-<?= htmlspecialchars($task['status']) ?>">
                <div class="task-header">
                    <div class="task-badges">
                        <span class="badge priority-badge <?= htmlspecialchars($task['priority']) ?>"><?= strtoupper(htmlspecialchars($task['priority'])) ?></span>
                        <span class="badge status-badge <?= htmlspecialchars($task['status']) ?>"><?= str_replace('_', ' ', strtoupper(htmlspecialchars($task['status']))) ?></span>
                    </div>
                    <div class="task-actions">
                        <a href="?edit=<?= (int)$task['id'] ?>" class="action-btn edit" title="Edit">✏️</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this task?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                            <button class="action-btn delete" title="Delete">🗑️</button>
                        </form>
                    </div>
                </div>
                <h3 class="task-title <?= $task['status'] === 'completed' ? 'strikethrough' : '' ?>"><?= htmlspecialchars($task['title']) ?></h3>
                <?php if (!empty($task['description'])): ?>
                <p class="task-desc"><?= htmlspecialchars(mb_strimwidth($task['description'], 0, 120, '...')) ?></p>
                <?php endif; ?>
                <?php if (!empty($task['due_date'])): ?>
                <div class="task-due <?= strtotime($task['due_date']) < time() && $task['status'] !== 'completed' ? 'overdue' : '' ?>">
                    Due: <?= date('M d, Y', strtotime($task['due_date'])) ?>
                </div>
                <?php endif; ?>
                <div class="task-footer">
                    <form method="POST" class="status-form">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                        <select name="status" class="status-select" onchange="this.form.submit()">
                            <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </form>
                    <small class="task-date">Added <?= date('M d', strtotime($task['created_at'])) ?></small>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Add Task Modal -->
    <div class="modal-overlay" id="add-modal">
        <div class="modal">
            <div class="modal-header">
                <h2>Add New Task</h2>
                <button class="modal-close" onclick="toggleModal('add-modal')">✕</button>
            </div>
            <form method="POST" class="task-form">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Task Title *</label>
                    <input type="text" name="title" placeholder="Enter task title..." required class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Task description..." rows="3" class="form-control"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="toggleModal('add-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <?php if ($edit_task): ?>
    <div class="modal-overlay" id="edit-modal" style="display:flex">
        <div class="modal">
            <div class="modal-header">
                <h2>Edit Task</h2>
                <a href="index.php" class="modal-close">✕</a>
            </div>
            <form method="POST" class="task-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$edit_task['id'] ?>">
                <div class="form-group">
                    <label>Task Title *</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($edit_task['title']) ?>" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars($edit_task['description']) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="high" <?= $edit_task['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="medium" <?= $edit_task['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="low" <?= $edit_task['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="pending" <?= $edit_task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $edit_task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $edit_task['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= htmlspecialchars((string)$edit_task['due_date']) ?>" class="form-control">
                </div>
                <div class="form-actions">
                    <a href="index.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function toggleModal(id) {
        const modal = document.getElementById(id);
        modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
    }
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) { alert.style.opacity = '0'; alert.style.transform = 'translateY(-10px)'; }
    }, 3000);
    </script>
</body>
</html>
