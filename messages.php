<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = require_login();

if ($user['Role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$stmt = db()->prepare("
    SELECT *
    FROM Orders
    WHERE CustomerID = ?
    ORDER BY OrderDate DESC
");
$stmt->execute([$user['CustomerID']]);
$orders = $stmt->fetchAll();
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
        <div class="card">
            <h3>Order #<?= h((string)$order['OrderID']) ?></h3>
            <p><strong>Date:</strong> <?= h((string)$order['OrderDate']) ?></p>
            <p><strong>Status:</strong> <?= h((string)$order['Status']) ?></p>

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
