<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/product_assets.php';

$user = require_login();

if ($user['Role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$stmt = db()->prepare("
    SELECT
        o.*,
        (
            SELECT COUNT(*)
            FROM OrderItems oi
            WHERE oi.OrderID = o.OrderID
        ) AS ItemLines,
        (
            SELECT SUM(oi.Quantity * oi.UnitPrice)
            FROM OrderItems oi
            WHERE oi.OrderID = o.OrderID
        ) AS OrderTotal
    FROM Orders
    o
    WHERE o.CustomerID = ?
    ORDER BY OrderDate DESC
");
$stmt->execute([$user['CustomerID']]);
$orders = $stmt->fetchAll();

$orderItemsStmt = db()->prepare("
    SELECT
        oi.OrderID,
        p.ProductName,
        c.CategoryName,
        oi.Quantity,
        oi.UnitPrice
    FROM OrderItems oi
    JOIN Products p ON p.ProductID = oi.ProductID
    JOIN Categories c ON c.CategoryID = p.CategoryID
    WHERE oi.OrderID = ?
    ORDER BY p.ProductName
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav>
    <strong>Electronics Shop</strong>
    <a href="shop.php">Shop</a>
    <a href="cart.php">Cart</a>
    <a href="messages.php">Messages</a>
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h1>Your orders and messages</h1>

    <?php if (!$orders): ?>
        <p>You have no orders yet.</p>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
        <?php
            $orderItemsStmt->execute([$order['OrderID']]);
            $orderItems = $orderItemsStmt->fetchAll();
        ?>
        <div class="card">
            <h3>Order #<?= h((string)$order['OrderID']) ?></h3>
            <p><strong>Date:</strong> <?= h((string)$order['OrderDate']) ?></p>
            <p><strong>Status:</strong> <?= h((string)$order['Status']) ?></p>
            <p><strong>Products in order:</strong> <?= h((string)$order['ItemLines']) ?></p>
            <p><strong>Total:</strong> <?= h(number_format((float)($order['OrderTotal'] ?? 0), 2)) ?> RON</p>

            <?php foreach ($orderItems as $item): ?>
                <?php $asset = product_asset((string)$item['ProductName'], (string)$item['CategoryName']); ?>
                <div class="order-item">
                    <img
                        class="product-image small"
                        src="<?= h($asset['image']) ?>"
                        alt="<?= h((string)$item['ProductName']) ?>"
                    >
                    <div>
                        <strong><?= h((string)$item['ProductName']) ?></strong>
                        <div>Quantity: <?= h((string)$item['Quantity']) ?></div>
                        <div>Unit price: <?= h(number_format((float)$item['UnitPrice'], 2)) ?> RON</div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($order['Status'] === 'pending'): ?>
                <p class="info">Your order is pending. The admin will accept or refuse it.</p>
            <?php elseif ($order['Status'] === 'accepted'): ?>
                <p class="success">
                    Your order is on its way until <?= h((string)$order['EstimatedDeliveryDate']) ?>.
                </p>
            <?php elseif ($order['Status'] === 'refused'): ?>
                <p class="error">
                    Your order was refused. Money given back.
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
