<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_admin();

$pdo = db();
$tableNames = [
    'Customers',
    'Users',
    'Categories',
    'Products',
    'Orders',
    'OrderItems',
    'AdminMessages',
];

$tableData = [];

foreach ($tableNames as $tableName) {
    $stmt = $pdo->query("SELECT * FROM {$tableName}");
    $rows = $stmt->fetchAll();

    $tableData[] = [
        'name' => $tableName,
        'rows' => $rows,
        'columns' => $rows ? array_keys($rows[0]) : [],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Overview</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav>
    <strong>Admin Panel</strong>
    <a href="admin.php">Dashboard</a>
    <a href="database_overview.php">Database overview</a>
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h1>Database overview</h1>
    <p class="muted">This page shows the rows currently stored in the main application tables.</p>

    <?php foreach ($tableData as $table): ?>
        <section>
            <h2><?= h($table['name']) ?></h2>

            <?php if (!$table['rows']): ?>
                <p>No rows found in this table.</p>
            <?php else: ?>
                <div class="table-scroll table-scroll-tall">
                    <table>
                        <tr>
                            <?php foreach ($table['columns'] as $column): ?>
                                <th><?= h((string)$column) ?></th>
                            <?php endforeach; ?>
                        </tr>

                        <?php foreach ($table['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($table['columns'] as $column): ?>
                                    <td><?= h($row[$column] === null ? 'NULL' : (string)$row[$column]) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
