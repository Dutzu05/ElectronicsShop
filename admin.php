<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = require_admin();

$messages = db()->query("
    SELECT m.*, u.Email
    FROM AdminMessages m
    JOIN Users u ON m.UserID = u.UserID
    ORDER BY m.CreatedAt DESC
")->fetchAll();

$orders = db()->query("
    SELECT o.*, c.FirstName, c.LastName, c.Email
    FROM Orders o
    JOIN Customers c ON o.CustomerID = c.CustomerID
    ORDER BY o.OrderDate DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav>
    <strong>Admin Panel</strong>
    <a href="admin.php">Dashboard</a>
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h1>Admin dashboard</h1>

    <section>
        <h2>User activity messages</h2>

        <?php if (!$messages): ?>
            <p>No messages yet.</p>
        <?php endif; ?>

        <?php foreach ($messages as $message): ?>
            <div class="message">
                <p><strong><?= h((string)$message['Email']) ?></strong></p>
                <p><?= h((string)$message['MessageText']) ?></p>
                <small><?= h((string)$message['CreatedAt']) ?></small>
            </div>
        <?php endforeach; ?>
    </section>

    <section>
        <h2>Orders</h2>

        <table>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?= h((string)$order['OrderID']) ?></td>
                    <td><?= h($order['FirstName'] . ' ' . $order['LastName']) ?><br><?= h((string)$order['Email']) ?></td>
                    <td><?= h((string)$order['OrderDate']) ?></td>
                    <td><?= h((string)$order['Status']) ?></td>
                    <td>
                        <?php if ($order['Status'] === 'pending'): ?>
                            <form method="post" action="admin_action.php" class="inline">
                                <input type="hidden" name="order_id" value="<?= h((string)$order['OrderID']) ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit">Accept</button>
                            </form>

                            <form method="post" action="admin_action.php" class="inline">
                                <input type="hidden" name="order_id" value="<?= h((string)$order['OrderID']) ?>">
                                <input type="hidden" name="action" value="refuse">
                                <button type="submit" class="danger">Refuse</button>
                            </form>
                        <?php else: ?>
                            Completed
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
</main>
</body>
</html>
