<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_admin();

$orderId = (int)($_POST['order_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

$stmt = db()->prepare("
    SELECT o.*, u.UserID, u.Email
    FROM Orders o
    JOIN Customers c ON o.CustomerID = c.CustomerID
    JOIN Users u ON u.CustomerID = c.CustomerID
    WHERE o.OrderID = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || $order['Status'] !== 'pending') {
    header('Location: admin.php');
    exit;
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
    $stmt = db()->prepare("
        UPDATE Orders
        SET Status = 'refused',
            AdminMessage = ?
        WHERE OrderID = ?
    ");
    $stmt->execute([
        'Money given back.',
        $orderId,
    ]);
}

header('Location: admin.php');
exit;
