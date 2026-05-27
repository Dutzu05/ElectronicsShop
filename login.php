<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$existingUser = current_user();
if ($existingUser !== null) {
    header('Location: ' . ($existingUser['Role'] === 'admin' ? 'admin.php' : 'shop.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = db()->prepare('
            SELECT UserID, CustomerID, Email, PasswordHash, Role, PaymentPinHash
            FROM Users
            WHERE Email = ?
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, (string)$user['PasswordHash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['UserID'];
            session_write_close();

            header('Location: ' . ($user['Role'] === 'admin' ? 'admin.php' : 'shop.php'));
            exit;
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="auth-box">
        <h1>Login</h1>
        <?php if (isset($error)): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post" action="login.php">
            <label>
                Email
                <input type="email" name="email" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Log in</button>
        </form>
        <p class="auth-switch">
            Need a new account?
            <a class="button-link secondary" href="register.php">Create account</a>
        </p>
        <div class="demo">
            <p>Demo user: <code>user@shop.com</code> / <code>user123</code></p>
            <p>Demo admin: <code>admin@shop.com</code> / <code>admin123</code></p>
        </div>
    </main>
</body>
</html>
