<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = require_login();

if ($user['Role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];

if (isset($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    unset($_SESSION['cart'][$removeId]);
    header('Location: cart.php');
    exit;
}

$items = [];
$total = 0.0;

if ($cart) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = db()->prepare("SELECT * FROM Products WHERE ProductID IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $quantity = (int)$cart[$product['ProductID']];
        $subtotal = $quantity * (float)$product['Price'];
        $total += $subtotal;

        $items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart</title>
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
    <h1>Your cart</h1>

    <?php if (!$items): ?>
        <p>Your cart is empty.</p>
        <a class="button-link" href="shop.php">Go shopping</a>
    <?php else: ?>
        <table>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit price</th>
                <th>Subtotal</th>
                <th>Action</th>
            </tr>

            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['product']['ProductName']) ?></td>
                    <td><?= h((string)$item['quantity']) ?></td>
                    <td><?= h((string)$item['product']['Price']) ?> RON</td>
                    <td><?= h(number_format($item['subtotal'], 2)) ?> RON</td>
                    <td>
                        <a href="cart.php?remove=<?= h((string)$item['product']['ProductID']) ?>">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h2>Total: <?= h(number_format($total, 2)) ?> RON</h2>

        <form method="get" action="checkout.php">
            <button type="submit">Place order</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
