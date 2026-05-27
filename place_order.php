<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = require_login();

if ($user['Role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];

if (!$cart) {
    header('Location: cart.php');
    exit;
}

$pin = (string)($_POST['pin'] ?? '');

if ($pin === '' || !password_verify($pin, (string)($user['PaymentPinHash'] ?? ''))) {
    die("Wrong PIN. <a href='cart.php'>Go back</a>");
}

$pdo = db();
$pdo->beginTransaction();

try {
    $customerId = $user['CustomerID'];

    $stmt = $pdo->prepare("
        INSERT INTO Orders (CustomerID, Status)
        VALUES (?, 'pending')
    ");
    $stmt->execute([$customerId]);
    $orderId = (int)$pdo->lastInsertId();

    foreach ($cart as $productId => $quantity) {
        $stmt = $pdo->prepare('SELECT * FROM Products WITH (UPDLOCK, ROWLOCK) WHERE ProductID = ?');
        $stmt->execute([(int)$productId]);
        $product = $stmt->fetch();

        if (!$product || (int)$quantity > (int)$product['StockQuantity']) {
            throw new Exception('Product not available.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO OrderItems (OrderID, ProductID, Quantity, UnitPrice)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $productId, $quantity, $product['Price']]);

        $stmt = $pdo->prepare("
            UPDATE Products
            SET StockQuantity = StockQuantity - ?
            WHERE ProductID = ?
        ");
        $stmt->execute([$quantity, $productId]);
    }

    $message = "User {$user['Email']} placed order #{$orderId}.";
    $stmt = $pdo->prepare('INSERT INTO AdminMessages (UserID, MessageText) VALUES (?, ?)');
    $stmt->execute([$user['UserID'], $message]);

    $pdo->commit();
    $_SESSION['cart'] = [];

    header('Location: messages.php');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die('Error placing order: ' . h($e->getMessage()));
}
