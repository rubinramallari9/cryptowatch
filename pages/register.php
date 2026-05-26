<?php
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';
    $confirm  =      $_POST['confirm']  ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'Ju lutem plotësoni të gjitha fushat.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Formati i email-it është i pavlefshëm.';
    } elseif (strlen($password) < 6) {
        $error = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
    } elseif ($password !== $confirm) {
        $error = 'Fjalëkalimet nuk përputhen.';
    } else {
        $result = registerUser($name, $email, $password);
        if ($result === true) {
            $success = 'Llogaria u krijua! <a href="/pages/login.php">Hyr tani</a>';
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
    <title>Regjistrohu — CryptoWatch</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="logo">
            <span class="logo-icon">₿</span>
            <span class="logo-text">CryptoWatch</span>
        </div>
        <h2>Krijo Llogari</h2>

        <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <form method="POST" novalidate>
            <div class="form-group">
                <label for="name">Emri i plotë</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Fjalëkalimi</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm">Konfirmo Fjalëkalimin</label>
                <input type="password" id="confirm" name="confirm" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Regjistrohu</button>
        </form>
        <p class="auth-link">Ke llogari? <a href="/pages/login.php">Hyr këtu</a></p>
    </div>
</body>
</html>
