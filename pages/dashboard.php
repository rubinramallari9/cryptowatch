<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId    = $_SESSION['user_id'];
$userName  = $_SESSION['user_name'];
$favorites = getFavorites($userId);

// Handle add/remove favorite via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $coinId = trim($_POST['coin_id'] ?? '');

    if ($coinId && $action === 'add' && !in_array($coinId, $favorites, true)) {
        $favorites[] = $coinId;
        saveFavorites($userId, $favorites);
    } elseif ($coinId && $action === 'remove') {
        $favorites = array_filter($favorites, fn($f) => $f !== $coinId);
        saveFavorites($userId, $favorites);
    }
    header('Location: /pages/dashboard.php');
    exit;
}

$favoritesJson = json_encode($favorites);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CryptoWatch</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-page">

    <nav class="navbar">
        <div class="nav-brand">
            <span class="logo-icon">₿</span>
            <span class="logo-text">CryptoWatch</span>
        </div>
        <div class="nav-links">
            <a href="/index.php">Tregjet</a>
            <span class="nav-user">👤 <?= htmlspecialchars($userName) ?></span>
            <a href="/logout.php" class="btn btn-outline btn-sm">Dil</a>
        </div>
    </nav>

    <main class="container">
        <h1 class="page-title">Paneli im</h1>

        <!-- Favorites Section -->
        <section class="section">
            <h2 class="section-title">Monedhat e Mia të Preferuara</h2>
            <?php if (empty($favorites)): ?>
                <p class="empty-msg">Nuk ke shtuar ende monedha të preferuara. Shko tek <a href="/index.php">Tregjet</a> dhe shto!</p>
            <?php else: ?>
                <div id="favorites-grid" class="coin-grid">
                    <p class="loading-msg">Duke ngarkuar të dhënat...</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- All Markets Section -->
        <section class="section">
            <h2 class="section-title">Tregjet Live</h2>
            <div class="table-wrapper">
                <table class="coin-table" id="dashboard-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Monedha</th>
                            <th>Çmimi (USD)</th>
                            <th>Ndryshim 24h</th>
                            <th>Kapitalizim</th>
                            <th>Veprim</th>
                        </tr>
                    </thead>
                    <tbody id="dashboard-tbody">
                        <tr><td colspan="6" class="loading-msg">Duke ngarkuar...</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="refresh-note">Rifreskim automatik çdo 60 sekonda.</p>
        </section>
    </main>

    <!-- AI Chat Widget -->
    <?php include __DIR__ . '/../includes/chat_widget.php'; ?>

    <!-- Coin Detail Modal -->
    <div class="modal-overlay" id="coin-modal" role="dialog" aria-modal="true">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-coin-title" id="modal-title">
                    <img id="modal-img" src="" alt="" width="42" height="42">
                    <div>
                        <h2 id="modal-name">—</h2>
                    </div>
                    <span class="modal-rank" id="modal-rank"></span>
                </div>
                <button class="modal-close" id="modal-close" aria-label="Mbyll">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-stats" id="modal-stats"></div>
                <div class="modal-chart-header">
                    <h3>Grafiku i Çmimit</h3>
                    <div class="timeframe-btns">
                        <button class="tf-btn active" data-days="1">1D</button>
                        <button class="tf-btn" data-days="7">7D</button>
                        <button class="tf-btn" data-days="30">30D</button>
                        <button class="tf-btn" data-days="365">1V</button>
                    </div>
                </div>
                <div class="modal-chart-wrap">
                    <div class="modal-chart-loading" id="modal-chart-loading">Duke ngarkuar grafikun...</div>
                    <canvas id="modal-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        const FAVORITES    = <?= $favoritesJson ?>;
        const IS_DASHBOARD = true;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="/assets/js/crypto.js"></script>
</body>
</html>
