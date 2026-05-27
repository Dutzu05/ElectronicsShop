<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/product_assets.php';

$user = require_login();

if ($user['Role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stmt = db()->prepare('SELECT * FROM Products WHERE ProductID = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        $availableStock = (int)$product['StockQuantity'];
        $currentQuantity = (int)($_SESSION['cart'][$productId] ?? 0);

        if ($quantity <= $availableStock && $currentQuantity + $quantity <= $availableStock) {
            $_SESSION['cart'][$productId] = $currentQuantity + $quantity;
        }
    }

    header('Location: cart.php');
    exit;
}

$products = db()->query('
    SELECT
        p.ProductID,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        c.CategoryName,
        COALESCE(MIN(alt.ProductName), \'No similar product in this category\') AS SimilarProductName
    FROM Products p
    JOIN Categories c ON p.CategoryID = c.CategoryID
    LEFT JOIN Products alt
        ON alt.CategoryID = p.CategoryID
       AND alt.ProductID <> p.ProductID
    GROUP BY
        p.ProductID,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        c.CategoryName
    ORDER BY c.CategoryName, p.ProductName
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop</title>
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
    <h1>Available products</h1>

    <div class="grid">
        <?php foreach ($products as $product): ?>
            <div class="card">
                <?php $asset = product_asset((string)$product['ProductName'], (string)$product['CategoryName']); ?>
                <img
                    class="product-image"
                    src="<?= h($asset['image']) ?>"
                    alt="<?= h((string)$product['ProductName']) ?>"
                >
                <h3><?= h($product['ProductName']) ?></h3>
                <p><strong>Category:</strong> <?= h($product['CategoryName']) ?></p>
                <p><strong>Price:</strong> <?= h((string)$product['Price']) ?> RON</p>
                <p><strong>Stock:</strong> <?= h((string)$product['StockQuantity']) ?></p>
                <p><strong>Similar product:</strong> <?= h((string)$product['SimilarProductName']) ?></p>
                <form method="post">
                    <input type="hidden" name="product_id" value="<?= h((string)$product['ProductID']) ?>">
                    <label>Quantity</label>
                    <input
                        type="number"
                        name="quantity"
                        value="1"
                        min="1"
                        max="<?= h((string)$product['StockQuantity']) ?>"
                    >
                    <button type="submit">Add to cart</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
