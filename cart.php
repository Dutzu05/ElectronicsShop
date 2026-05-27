<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/product_assets.php';

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
$cartIds = [];
$cartProductComparisons = [];

if ($cart) {
    $ids = array_keys($cart);
    $cartIds = array_map('intval', $ids);
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

    $comparisonStmt = db()->prepare("
        SELECT
            p.ProductID,
            p.ProductName,
            c.CategoryName,
            p.Price,
            (
                SELECT AVG(p2.Price)
                FROM Products p2
                WHERE p2.CategoryID = p.CategoryID
            ) AS CategoryAveragePrice,
            COALESCE(MIN(alt.ProductName), 'No alternative in category') AS AlternativeProduct
        FROM Products p
        JOIN Categories c ON c.CategoryID = p.CategoryID
        LEFT JOIN Products alt
            ON alt.CategoryID = p.CategoryID
           AND alt.ProductID <> p.ProductID
        WHERE p.ProductID IN ($placeholders)
        GROUP BY p.ProductID, p.ProductName, c.CategoryName, p.Price, p.CategoryID
        ORDER BY c.CategoryName, p.ProductName
    ");
    $comparisonStmt->execute($cartIds);
    $cartProductComparisons = $comparisonStmt->fetchAll();
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
                    <?php $asset = product_asset((string)$item['product']['ProductName']); ?>
                    <td>
                        <img
                            class="product-image small"
                            src="<?= h($asset['image']) ?>"
                            alt="<?= h((string)$item['product']['ProductName']) ?>"
                        >
                        <?= h($item['product']['ProductName']) ?>
                    </td>
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

        <section>
            <h2>Price comparison for products in cart</h2>
            <p class="muted">Each product is compared with the average price from its category.</p>

            <table>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Category average</th>
                    <th>Difference</th>
                </tr>

                <?php foreach ($cartProductComparisons as $comparison): ?>
                    <?php $difference = (float)$comparison['Price'] - (float)$comparison['CategoryAveragePrice']; ?>
                    <tr>
                        <td><?= h((string)$comparison['ProductName']) ?></td>
                        <td><?= h((string)$comparison['CategoryName']) ?></td>
                        <td><?= h(number_format((float)$comparison['Price'], 2)) ?> RON</td>
                        <td><?= h(number_format((float)$comparison['CategoryAveragePrice'], 2)) ?> RON</td>
                        <td><?= h(number_format($difference, 2)) ?> RON</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>

        <section>
            <h2>Alternative products for your cart</h2>
            <p class="muted">This uses a SELF JOIN to show another product from the same category.</p>

            <table>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Alternative</th>
                </tr>

                <?php foreach ($cartProductComparisons as $comparison): ?>
                    <tr>
                        <td><?= h((string)$comparison['ProductName']) ?></td>
                        <td><?= h((string)$comparison['CategoryName']) ?></td>
                        <td><?= h((string)$comparison['AlternativeProduct']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>

        <form method="get" action="checkout.php">
            <button type="submit">Place order</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
