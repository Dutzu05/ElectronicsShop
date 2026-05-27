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

$topProducts = db()->query("
    SELECT TOP 3
        p.ProductName,
        c.CategoryName,
        SUM(oi.Quantity) AS TotalUnitsSold,
        COUNT(DISTINCT oi.OrderID) AS TimesOrdered
    FROM OrderItems oi
    JOIN Products p ON p.ProductID = oi.ProductID
    JOIN Categories c ON c.CategoryID = p.CategoryID
    GROUP BY p.ProductName, c.CategoryName
    ORDER BY TotalUnitsSold DESC, TimesOrdered DESC, p.ProductName
")->fetchAll();

$categories = db()->query("
    SELECT
        c.CategoryID,
        c.CategoryName,
        c.Description,
        COUNT(p.ProductID) AS ProductCount
    FROM Categories c
    LEFT JOIN Products p ON p.CategoryID = c.CategoryID
    GROUP BY c.CategoryID, c.CategoryName, c.Description
    ORDER BY c.CategoryName
")->fetchAll();

$productSearch = trim((string)($_GET['product_search'] ?? ''));

$productsSql = "
    SELECT
        p.ProductID,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        c.CategoryName
    FROM Products p
    JOIN Categories c ON c.CategoryID = p.CategoryID
";
$productParams = [];

if ($productSearch !== '') {
    $productsSql .= "
        WHERE p.ProductName LIKE ?
           OR c.CategoryName LIKE ?
    ";
    $term = '%' . $productSearch . '%';
    $productParams = [$term, $term];
}

$productsSql .= " ORDER BY c.CategoryName, p.ProductName";

$stmt = db()->prepare($productsSql);
$stmt->execute($productParams);
$productsForAdmin = $stmt->fetchAll();

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
        c.CategoryName,
        oi.Quantity,
        oi.UnitPrice,
        oi.Quantity * oi.UnitPrice AS LineTotal
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
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-page">
<nav>
    <strong>Admin Panel</strong>
    <a href="admin.php">Dashboard</a>
    <a href="database_overview.php">Database overview</a>
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h1>Admin dashboard</h1>

    <?php if (isset($_GET['status'])): ?>
        <p class="success-banner"><?= h((string)$_GET['status']) ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <p class="error-banner"><?= h((string)$_GET['error']) ?></p>
    <?php endif; ?>

    <section>
        <h2>Category management</h2>

        <div class="admin-layout">
            <div class="card management-card">
                <h3>Add or delete category</h3>
                <div class="management-split">
                    <form method="post" action="admin_action.php" class="stack-form">
                        <input type="hidden" name="action" value="add_category">
                        <label>
                            Category name
                            <input type="text" name="category_name" required>
                        </label>
                        <label>
                            Description
                            <input type="text" name="category_description" required>
                        </label>
                        <button type="submit">Add category</button>
                    </form>

                    <form method="post" action="admin_action.php" class="stack-form management-delete">
                        <input type="hidden" name="action" value="delete_category">
                        <label>
                            Category
                            <select name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= h((string)$category['CategoryID']) ?>">
                                        <?= h((string)$category['CategoryName']) ?> (<?= h((string)$category['ProductCount']) ?> products)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="danger">Delete category</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section>
        <h2>Product management</h2>

        <form method="get" action="admin.php" class="search-bar">
            <input
                type="search"
                name="product_search"
                placeholder="Find a product to edit"
                value="<?= h($productSearch) ?>"
            >
            <button type="submit">Search</button>
            <?php if ($productSearch !== ''): ?>
                <a class="button-link secondary" href="admin.php">Clear</a>
            <?php endif; ?>
        </form>

        <div class="admin-layout">
            <div class="card management-card">
                <h3>Add or delete product</h3>
                <div class="management-split">
                    <form method="post" action="admin_action.php" class="stack-form">
                        <input type="hidden" name="action" value="add_product">
                        <label>
                            Product name
                            <input type="text" name="product_name" required>
                        </label>
                        <label>
                            Category
                            <select name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= h((string)$category['CategoryID']) ?>"><?= h((string)$category['CategoryName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Price
                            <input type="number" name="price" min="0" step="0.01" required>
                        </label>
                        <label>
                            Stock quantity
                            <input type="number" name="stock_quantity" min="0" step="1" required>
                        </label>
                        <button type="submit">Add product</button>
                    </form>

                    <form method="post" action="admin_action.php" class="stack-form management-delete">
                        <input type="hidden" name="action" value="delete_product">
                        <label>
                            Product
                            <select name="product_id" required>
                                <?php foreach ($productsForAdmin as $product): ?>
                                    <option value="<?= h((string)$product['ProductID']) ?>">
                                        <?= h((string)$product['ProductName']) ?> (<?= h((string)$product['CategoryName']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="danger">Delete product</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($productSearch !== '' && !$productsForAdmin): ?>
            <p>No products matched this search.</p>
        <?php endif; ?>

        <div class="table-scroll">
            <table>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Adjust stock</th>
                </tr>

                <?php foreach ($productsForAdmin as $product): ?>
                    <?php $asset = product_asset((string)$product['ProductName'], (string)$product['CategoryName']); ?>
                    <tr>
                        <td>
                            <img
                                class="product-image thumb"
                                src="<?= h($asset['image']) ?>"
                                alt="<?= h((string)$product['ProductName']) ?>"
                            >
                        </td>
                        <td><?= h((string)$product['ProductName']) ?></td>
                        <td><?= h((string)$product['CategoryName']) ?></td>
                        <td><?= h(number_format((float)$product['Price'], 2)) ?> RON</td>
                        <td><?= h((string)$product['StockQuantity']) ?></td>
                        <td>
                            <form method="post" action="admin_action.php" class="inline-form">
                                <input type="hidden" name="action" value="adjust_stock">
                                <input type="hidden" name="product_id" value="<?= h((string)$product['ProductID']) ?>">
                                <input type="number" name="stock_delta" value="1" min="1" step="1" class="compact-input" required>
                                <button type="submit" name="direction" value="increase">Add</button>
                                <button type="submit" name="direction" value="decrease" class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </section>

    <section>
        <h2>Customer order summary</h2>

        <div class="table-scroll">
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
        </div>
    </section>

    <section>
        <h2>Top 3 most bought items</h2>

        <?php if (!$topProducts): ?>
            <p>No sold products yet.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Total units sold</th>
                        <th>Number of orders</th>
                    </tr>

                    <?php foreach ($topProducts as $product): ?>
                        <tr>
                            <td><?= h((string)$product['ProductName']) ?></td>
                            <td><?= h((string)$product['CategoryName']) ?></td>
                            <td><?= h((string)$product['TotalUnitsSold']) ?></td>
                            <td><?= h((string)$product['TimesOrdered']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <h2>Rows with NULL values</h2>

        <?php if (!$nullExamples): ?>
            <p>No NULL-based examples found.</p>
        <?php else: ?>
            <div class="table-scroll">
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
            </div>
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

        <div class="table-scroll table-scroll-tall">
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
                                <?php $asset = product_asset((string)$item['ProductName'], (string)$item['CategoryName']); ?>
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
        </div>
    </section>
</main>
</body>
</html>
