<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) && !ctype_digit((string)$userId)) {
        return null;
    }

    $stmt = db()->prepare('
        SELECT UserID, CustomerID, Email, PasswordHash, Role, PaymentPinHash
        FROM Users
        WHERE UserID = ?
    ');
    $stmt->execute([(int)$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION = [];
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();

    if (($user['Role'] ?? '') !== 'admin') {
        header('Location: shop.php');
        exit;
    }

    return $user;
}
