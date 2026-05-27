<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/product_assets.php';

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

$customerSummaries = db()->query("
    SELECT
        c.CustomerID,
        c.FirstName,
        c.LastName,
        c.Email,
        COUNT(DISTINCT o.OrderID) AS TotalOrders,
        COALESCE(SUM(oi.Quantity * oi.UnitPrice), 0) AS TotalSpent,
        COALESCE(AVG(oi.UnitPrice), 0) AS AverageItemPrice
    FROM Customers c
    LEFT JOIN Orders o ON o.CustomerID = c.CustomerID
    LEFT JOIN OrderItems oi ON oi.OrderID = o.OrderID
    GROUP BY c.CustomerID, c.FirstName, c.LastName, c.Email
    ORDER BY TotalSpent DESC, c.LastName, c.FirstName
")->fetchAll();

$nullExamples = db()->query("
    SELECT
        u.Email,
        u.Role,
        COALESCE(c.FirstName + ' ' + c.LastName, 'No linked customer') AS CustomerName,
        COALESCE(c.Phone, 'Phone not provided') AS PhoneNumber
    FROM Users u
    LEFT JOIN Customers c ON c.CustomerID = u.CustomerID
    WHERE u.CustomerID IS NULL OR c.Phone IS NULL
    ORDER BY u.Role DESC, u.Email
")->fetchAll();

$orderDetailsStmt = db()->prepare("
    SELECT
        oi.OrderID,
        p.ProductName,
        oi.Quantity,
        oi.UnitPrice,
        oi.Quantity * oi.UnitPrice AS LineTotal
    FROM OrderItems oi
    JOIN Products p ON p.ProductID = oi.ProductID
    WHERE oi.OrderID = ?
    ORDER BY p.ProductName
");
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
    <a href="database_overview.php">Database overview</a>
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h1>Admin dashboard</h1>

    <section>
        <h2>Customer order summary</h2>
        <p class="muted">Uses JOIN with aggregate functions: COUNT, SUM and AVG.</p>

        <table>
            <tr>
                <th>Customer</th>
                <th>Email</th>
                <th>Orders</th>
                <th>Total spent</th>
                <th>Average item price</th>
            </tr>

            <?php foreach ($customerSummaries as $summary): ?>
                <tr>
                    <td><?= h($summary['FirstName'] . ' ' . $summary['LastName']) ?></td>
                    <td><?= h((string)$summary['Email']) ?></td>
                    <td><?= h((string)$summary['TotalOrders']) ?></td>
                    <td><?= h(number_format((float)$summary['TotalSpent'], 2)) ?> RON</td>
                    <td><?= h(number_format((float)$summary['AverageItemPrice'], 2)) ?> RON</td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>

    <section>
        <h2>Rows with NULL values</h2>
        <p class="muted">Uses IS NULL and COALESCE to highlight optional data.</p>

        <?php if (!$nullExamples): ?>
            <p>No NULL-based examples found.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Customer</th>
                    <th>Phone</th>
                </tr>

                <?php foreach ($nullExamples as $row): ?>
                    <tr>
                        <td><?= h((string)$row['Email']) ?></td>
                        <td><?= h((string)$row['Role']) ?></td>
                        <td><?= h((string)$row['CustomerName']) ?></td>
                        <td><?= h((string)$row['PhoneNumber']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </section>

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
                <?php
                    $orderDetailsStmt->execute([$order['OrderID']]);
                    $orderItems = $orderDetailsStmt->fetchAll();
                    $orderTotal = 0.0;
                    foreach ($orderItems as $item) {
                        $orderTotal += (float)$item['LineTotal'];
                    }
                ?>
                <tr>
                    <td>#<?= h((string)$order['OrderID']) ?></td>
                    <td><?= h($order['FirstName'] . ' ' . $order['LastName']) ?><br><?= h((string)$order['Email']) ?></td>
                    <td><?= h((string)$order['OrderDate']) ?></td>
                    <td>
                        <?= h((string)$order['Status']) ?><br>
                        <small>Total: <?= h(number_format($orderTotal, 2)) ?> RON</small><br>
                        <small>Items: <?= h((string)count($orderItems)) ?></small>
                        <?php foreach ($orderItems as $item): ?>
                            <?php $asset = product_asset((string)$item['ProductName']); ?>
                            <div class="order-item compact">
                                <img
                                    class="product-image thumb"
                                    src="<?= h($asset['image']) ?>"
                                    alt="<?= h((string)$item['ProductName']) ?>"
                                >
                                <div>
                                    <?= h((string)$item['ProductName']) ?> x<?= h((string)$item['Quantity']) ?>
                                    <br>
                                    <small><?= h(number_format((float)$item['LineTotal'], 2)) ?> RON</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </td>
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
