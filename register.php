<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$existingUser = current_user();
if ($existingUser !== null) {
    header('Location: ' . ($existingUser['Role'] === 'admin' ? 'admin.php' : 'shop.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $paymentPin = trim((string)($_POST['payment_pin'] ?? ''));

    if ($firstName === '' || $lastName === '' || $email === '' || $city === '' || $password === '' || $confirmPassword === '' || $paymentPin === '') {
        $error = 'All fields except phone are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must have at least 6 characters.';
    } elseif (!preg_match('/^\d{4}$/', $paymentPin)) {
        $error = 'Payment PIN must contain exactly 4 digits.';
    } else {
        $pdo = db();

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM Users WHERE Email = ?');
        $stmt->execute([$email]);
        $exists = (int)$stmt->fetch()['total'] > 0;

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO Customers (FirstName, LastName, Email, Phone, City)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $city,
                ]);

                $customerId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO Users (CustomerID, Email, PasswordHash, Role, PaymentPinHash)
                    VALUES (?, ?, ?, 'user', ?)
                ");
                $stmt->execute([
                    $customerId,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    password_hash($paymentPin, PASSWORD_DEFAULT),
                ]);

                $pdo->commit();

                header('Location: login.php');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Could not create the account.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="auth-box auth-box-wide">
        <h1>Create account</h1>
        <?php if (isset($error)): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post" action="register.php" class="stack-form">
            <label>
                First name
                <input type="text" name="first_name" value="<?= h((string)($_POST['first_name'] ?? '')) ?>" required>
            </label>
            <label>
                Last name
                <input type="text" name="last_name" value="<?= h((string)($_POST['last_name'] ?? '')) ?>" required>
            </label>
            <label>
                Email
                <input type="email" name="email" value="<?= h((string)($_POST['email'] ?? '')) ?>" required>
            </label>
            <label>
                Phone
                <input type="text" name="phone" value="<?= h((string)($_POST['phone'] ?? '')) ?>">
            </label>
            <label>
                City
                <input type="text" name="city" value="<?= h((string)($_POST['city'] ?? '')) ?>" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <label>
                Confirm password
                <input type="password" name="confirm_password" required>
            </label>
            <label>
                Payment PIN
                <input type="password" name="payment_pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required>
            </label>
            <button type="submit">Create account</button>
        </form>
        <p class="auth-switch">
            Already have an account?
            <a class="button-link secondary" href="login.php">Back to login</a>
        </p>
    </main>
</body>
</html>
