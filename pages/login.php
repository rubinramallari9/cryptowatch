<?php
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Ju lutem plotësoni të gjitha fushat.';
    } else {
        $result = loginUser($email, $password);
        if ($result === true) {
            header('Location: /pages/dashboard.php');
            exit;
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hyr — CryptoWatch</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="logo">
            <span class="logo-icon">₿</span>
            <span class="logo-text">CryptoWatch</span>
        </div>
        <h2>Hyr në Llogari</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Fjalëkalimi</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Hyr</button>
        </form>
        <p class="auth-link">S'ke llogari? <a href="/pages/register.php">Regjistrohu</a></p>
    </div>
</body>
</html>
