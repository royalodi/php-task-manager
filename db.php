<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'task_manager_db');

$db = null;
$db_engine = 'sqlite';

// Try MySQL first when available (e.g. XAMPP), fallback cleanly to zero-config SQLite
if (in_array('mysql', PDO::getAvailableDrivers(), true)) {
    try {
        $pdoTmp = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 1,
        ]);
        $pdoTmp->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db_engine = 'mysql';
        $db->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            due_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        $db = null;
    }
}

if ($db === null) {
    $sqliteFile = __DIR__ . '/database.db';
    $isNew = !file_exists($sqliteFile) || filesize($sqliteFile) === 0;
    $db = new PDO('sqlite:' . $sqliteFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db_engine = 'sqlite';

    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        priority TEXT DEFAULT 'medium',
        status TEXT DEFAULT 'pending',
        due_date TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    if ($isNew) {
        $seed = $db->prepare('INSERT INTO tasks (title, description, priority, status, due_date) VALUES (?, ?, ?, ?, ?)');
        $seed->execute(['Configure CI/CD Deployment Pipeline', 'Automate syntax linting, unit verification, and build checks on main branch pushes.', 'high', 'in_progress', date('Y-m-d', strtotime('+3 days'))]);
        $seed->execute(['Implement Prepared Statement Layer', 'Refactor database queries to unified PDO prepared statements with zero-config SQLite fallback.', 'medium', 'completed', date('Y-m-d', strtotime('+1 day'))]);
        $seed->execute(['Optimize Mobile Viewport Breakpoints', 'Audit dashboard grid cards across 375px and 768px responsive breakpoints.', 'low', 'pending', date('Y-m-d', strtotime('+7 days'))]);
    }
}
?>
