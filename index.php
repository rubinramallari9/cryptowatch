<?php
require_once __DIR__ . '/includes/auth.php';

$loggedIn = !empty($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$favorites = $loggedIn ? getFavorites($_SESSION['user_id']) : [];
$favoritesJson = json_encode($favorites);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoWatch — Monitorim Kriptomonedhash</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="app-page">

    <nav class="navbar">
        <div class="nav-brand">
            <span class="logo-icon">₿</span>
            <span class="logo-text">CryptoWatch</span>
        </div>
        <div class="nav-links">
            <a href="/index.php" class="active">Tregjet</a>
            <?php if ($loggedIn): ?>
                <a href="/pages/dashboard.php">Paneli im</a>
                <span class="nav-user">👤 <?= htmlspecialchars($userName) ?></span>
                <a href="/logout.php" class="btn btn-outline btn-sm">Dil</a>
            <?php else: ?>
                <a href="/pages/login.php">Hyr</a>
                <a href="/pages/register.php" class="btn btn-primary btn-sm">Regjistrohu</a>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero">
        <h1>Monitorimi i Kriptomonedhave <span class="accent">në Kohë Reale</span></h1>
        <p>Çmimet live, ndryshimet 24h dhe kapitalizimi i tregut — gjithçka në një vend.</p>
    </header>

    <main class="container">

        <!-- Price Ticker Strip -->
        <div class="ticker-wrap">
            <div class="ticker" id="ticker"></div>
        </div>

        <!-- Top 10 Table -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Top 10 Kriptomonedhave</h2>
                <span class="live-badge">🟢 LIVE</span>
            </div>
            <div class="table-wrapper">
                <table class="coin-table" id="market-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Monedha</th>
                            <th>Çmimi (USD)</th>
                            <th>Ndryshim 24h</th>
                            <th>Kapitalizim</th>
                            <th>Volumi 24h</th>
                            <?php if ($loggedIn): ?>
                            <th>Preferuara</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="market-tbody">
                        <tr><td colspan="7" class="loading-msg">Duke ngarkuar të dhënat...</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="refresh-note">Rifreskim automatik çdo 60 sekonda &bull; Burimi: CoinGecko API</p>
        </section>

        <!-- 7-Day Chart -->
        <section class="section">
            <h2 class="section-title">Grafiku 7-Ditor</h2>
            <div class="chart-controls">
                <label for="chart-coin">Zgjidh monedhën:</label>
                <select id="chart-coin">
                    <option value="bitcoin">Bitcoin (BTC)</option>
                    <option value="ethereum">Ethereum (ETH)</option>
                    <option value="binancecoin">BNB</option>
                    <option value="solana">Solana (SOL)</option>
                    <option value="ripple">XRP</option>
                </select>
            </div>
            <div class="chart-container">
                <canvas id="price-chart"></canvas>
            </div>
        </section>

        <!-- News Feed -->
        <section class="section" id="news-section">
            <div class="section-header">
                <h2 class="section-title">Lajmet e Fundit</h2>
                <span class="live-badge">📰 LIVE</span>
            </div>

            <!-- Category Tabs -->
            <div class="news-tabs" id="news-tabs">
                <button class="news-tab active" data-cat="">Të gjitha</button>
                <button class="news-tab" data-cat="BTC">Bitcoin</button>
                <button class="news-tab" data-cat="ETH">Ethereum</button>
                <button class="news-tab" data-cat="SOL">Solana</button>
                <button class="news-tab" data-cat="Regulation">Rregullim</button>
                <button class="news-tab" data-cat="DeFi">DeFi</button>
                <button class="news-tab" data-cat="NFT">NFT</button>
            </div>

            <div class="news-grid" id="news-grid">
                <p class="loading-msg">Duke ngarkuar lajmet...</p>
            </div>

            <p class="refresh-note">Rifreskim çdo 15 minuta &bull; Burimi: CryptoCompare</p>
        </section>

    </main>

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

    <!-- AI Chat Widget -->
    <?php include __DIR__ . '/includes/chat_widget.php'; ?>

    <footer class="footer">
        <p>CryptoWatch &copy; 2026 &bull; Shkolla "Hermann Gmeiner" &bull; Klasa 11-A &bull; Të dhënat nga CoinGecko API</p>
    </footer>

    <script>
        const FAVORITES    = <?= $favoritesJson ?>;
        const IS_LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
        const IS_DASHBOARD = false;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="/assets/js/crypto.js"></script>
    <script src="/assets/js/news.js"></script>
</body>
</html>
