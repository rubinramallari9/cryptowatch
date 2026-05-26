<?php
require_once __DIR__ . '/../db/connection.php';

function registerUser(string $name, string $email, string $password): bool|string {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return 'Ky email është tashmë i regjistruar.';
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, $hash]);
    return true;
}

function loginUser(string $email, string $password): bool|string {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, password FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return 'Email ose fjalëkalim i gabuar.';
    }
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    return true;
}

function getFavorites(int $userId): array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT favorites FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row  = $stmt->fetch();
    if (!$row || !$row['favorites']) return [];
    return json_decode($row['favorites'], true) ?? [];
}

function saveFavorites(int $userId, array $favorites): void {
    $db   = getDB();
    $stmt = $db->prepare('UPDATE users SET favorites = ? WHERE id = ?');
    $stmt->execute([json_encode(array_values($favorites)), $userId]);
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /pages/login.php');
        exit;
    }
}
