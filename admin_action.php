<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_admin();

$action = (string)($_POST['action'] ?? '');

$pdo = db();

function admin_redirect(string $key, string $message): never
{
    header('Location: admin.php?' . http_build_query([$key => $message]));
    exit;
}

if ($action === 'add_category') {
    $name = trim((string)($_POST['category_name'] ?? ''));
    $description = trim((string)($_POST['category_description'] ?? ''));

    if ($name === '' || $description === '') {
        admin_redirect('error', 'Category name and description are required.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO Categories (CategoryName, Description)
        VALUES (?, ?)
    ");
    $stmt->execute([$name, $description]);
    admin_redirect('status', 'Category added successfully.');
}

if ($action === 'delete_category') {
    $categoryId = (int)($_POST['category_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM Products WHERE CategoryID = ?");
    $stmt->execute([$categoryId]);
    $total = (int)$stmt->fetch()['total'];

    if ($total > 0) {
        admin_redirect('error', 'Delete the products in this category first.');
    }

    $stmt = $pdo->prepare("DELETE FROM Categories WHERE CategoryID = ?");
    $stmt->execute([$categoryId]);
    admin_redirect('status', 'Category deleted successfully.');
}

if ($action === 'add_product') {
    $name = trim((string)($_POST['product_name'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);

    if ($name === '' || $categoryId <= 0 || $price < 0 || $stockQuantity < 0) {
        admin_redirect('error', 'Valid product data is required.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO Products (ProductName, CategoryID, Price, StockQuantity)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$name, $categoryId, $price, $stockQuantity]);
    admin_redirect('status', 'Product added successfully.');
}

if ($action === 'delete_product') {
    $productId = (int)($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM OrderItems WHERE ProductID = ?");
    $stmt->execute([$productId]);
    $total = (int)$stmt->fetch()['total'];

    if ($total > 0) {
        admin_redirect('error', 'This product exists in past orders and cannot be deleted.');
    }

    $stmt = $pdo->prepare("DELETE FROM Products WHERE ProductID = ?");
    $stmt->execute([$productId]);
    admin_redirect('status', 'Product deleted successfully.');
}

if ($action === 'adjust_stock') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $stockDelta = (int)($_POST['stock_delta'] ?? 0);
    $direction = (string)($_POST['direction'] ?? '');

    if ($productId <= 0 || $stockDelta <= 0) {
        admin_redirect('error', 'Choose a product and a valid quantity.');
    }

    $stmt = $pdo->prepare("SELECT StockQuantity FROM Products WHERE ProductID = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        admin_redirect('error', 'Product not found.');
    }

    $currentStock = (int)$product['StockQuantity'];
    $newStock = $direction === 'decrease'
        ? $currentStock - $stockDelta
        : $currentStock + $stockDelta;

    if ($newStock < 0) {
        admin_redirect('error', 'Stock cannot go below zero.');
    }

    $stmt = $pdo->prepare("UPDATE Products SET StockQuantity = ? WHERE ProductID = ?");
    $stmt->execute([$newStock, $productId]);
    admin_redirect('status', 'Stock updated successfully.');
}

if ($action !== 'accept' && $action !== 'refuse') {
    admin_redirect('error', 'Unsupported admin action.');
}

$orderId = (int)($_POST['order_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT o.*, u.UserID, u.Email
    FROM Orders o
    JOIN Customers c ON o.CustomerID = c.CustomerID
    JOIN Users u ON u.CustomerID = c.CustomerID
    WHERE o.OrderID = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || $order['Status'] !== 'pending') {
    admin_redirect('error', 'The selected order is no longer pending.');
}

if ($action === 'accept') {
    $deliveryDate = date('Y-m-d', strtotime('+14 days'));

    $stmt = db()->prepare("
        UPDATE Orders
        SET Status = 'accepted',
            EstimatedDeliveryDate = ?,
            AdminMessage = ?
        WHERE OrderID = ?
    ");
    $stmt->execute([
        $deliveryDate,
        "Order on its way until $deliveryDate.",
        $orderId,
    ]);
} elseif ($action === 'refuse') {
    $stmt = $pdo->prepare("
        UPDATE Orders
        SET Status = 'refused',
            AdminMessage = ?
        WHERE OrderID = ?
    ");
    $stmt->execute([
        'Money given back.',
        $orderId,
    ]);
    admin_redirect('status', 'Order refused.');
}

admin_redirect('status', 'Order accepted.');
