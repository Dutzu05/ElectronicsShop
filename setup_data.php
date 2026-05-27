<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pdo = db();

$count = $pdo->query("SELECT COUNT(*) AS total FROM Users")->fetch()['total'];

if ((int)$count > 0) {
    echo "<h2>Demo data already exists.</h2>";
    echo "<p><a href='login.php'>Go to login</a></p>";
    exit;
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO Customers (FirstName, LastName, Email, Phone, City)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute(['Beatrice', 'Cretu', 'user@shop.com', null, 'Cluj-Napoca']);

    $customerId = (int)$pdo->lastInsertId();

    $userPassword = password_hash('user123', PASSWORD_DEFAULT);
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pinHash = password_hash('1234', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO Users (CustomerID, Email, PasswordHash, Role, PaymentPinHash)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$customerId, 'user@shop.com', $userPassword, 'user', $pinHash]);
    $stmt->execute([null, 'admin@shop.com', $adminPassword, 'admin', null]);

    $pdo->exec("
        INSERT INTO Categories (CategoryName, Description) VALUES
        ('Laptopuri', 'Laptopuri pentru facultate, birou si gaming'),
        ('Telefoane', 'Smartphone-uri si telefoane mobile'),
        ('Monitoare', 'Monitoare pentru birou si gaming'),
        ('Accesorii', 'Mouse-uri, tastaturi, casti si incarcatoare')
    ");

    $pdo->exec("
        INSERT INTO Products (ProductName, CategoryID, Price, StockQuantity) VALUES
        ('Lenovo IdeaPad 15', 1, 2800.00, 10),
        ('ASUS VivoBook', 1, 3200.00, 8),
        ('iPhone 13', 2, 3500.00, 6),
        ('Samsung Galaxy A55', 2, 1900.00, 12),
        ('Dell 24 inch Monitor', 3, 750.00, 15),
        ('LG UltraWide Monitor', 3, 1300.00, 7),
        ('Logitech Mouse', 4, 120.00, 30),
        ('Mechanical Keyboard', 4, 280.00, 20)
    ");

    $pdo->commit();

    echo "<h2>Demo data inserted successfully.</h2>";
    echo "<p><a href='login.php'>Go to login</a></p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . htmlspecialchars($e->getMessage());
}
