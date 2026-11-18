<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../includes/helpers.php';
    require_role(['super_admin']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        require_once __DIR__ . '/../includes/header.php';
        echo '<div class="panel"><h1 class="h3 page-heading">Database upgrades</h1><p>Run the repeatable additive migrations. Existing records and tables are preserved.</p><form method="post"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><button class="btn btn-success">Apply pending migrations</button></form></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
    verify_csrf();
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="panel"><h1 class="h3 page-heading">Database upgrades</h1><pre>';
}

$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS lamas_schema_migrations (
    migration VARCHAR(191) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

function migration_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function migration_index_exists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

$migrations = [
    '20261118_001_roles_and_demo_markers' => static function (PDO $pdo): void {
        // Expand first so old and new role values can coexist during the conversion.
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','librarian','super_admin','moderator') NOT NULL DEFAULT 'librarian'");
        $pdo->exec("UPDATE users SET role = CASE role WHEN 'admin' THEN 'super_admin' WHEN 'librarian' THEN 'moderator' ELSE role END");
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin','moderator') NOT NULL DEFAULT 'moderator'");

        if (!migration_column_exists($pdo, 'persons', 'is_demo')) {
            $pdo->exec('ALTER TABLE persons ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!migration_column_exists($pdo, 'attendance_records', 'is_demo')) {
            $pdo->exec('ALTER TABLE attendance_records ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!migration_index_exists($pdo, 'attendance_records', 'idx_att_demo_datetime')) {
            $pdo->exec('CREATE INDEX idx_att_demo_datetime ON attendance_records(is_demo, attendance_datetime)');
        }
    },
];

foreach ($migrations as $name => $apply) {
    $check = $pdo->prepare('SELECT 1 FROM lamas_schema_migrations WHERE migration = ?');
    $check->execute([$name]);
    if ($check->fetchColumn()) {
        echo "Already applied: {$name}\n";
        continue;
    }

    // MySQL/MariaDB DDL can auto-commit, so each operation is guarded and safe to retry.
    $apply($pdo);
    $record = $pdo->prepare('INSERT INTO lamas_schema_migrations(migration) VALUES (?)');
    $record->execute([$name]);
    echo "Applied: {$name}\n";
}
if (PHP_SAPI !== 'cli') {
    echo '</pre><a class="btn btn-outline-success" href="' . e(BASE_URL) . '/dashboard.php">Return to dashboard</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
}
