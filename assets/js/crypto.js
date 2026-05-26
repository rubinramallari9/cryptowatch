/* =========================================================
   CryptoWatch — crypto.js
   Integron CoinGecko API dhe menaxhon UI-n live
   ========================================================= */

const API_BASE   = 'https://api.coingecko.com/api/v3';
const PROXY_BASE = '/api/proxy.php?endpoint=';
const REFRESH_MS = 60_000; // 60 sekonda

// Ndërto URL nëpër proxy-n tonë PHP (zgjidh CORS + rate limiting)
function apiUrl(endpoint, params = {}) {
    const qs = Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
    return PROXY_BASE + encodeURIComponent(endpoint) + (qs ? '&' + qs : '');
}

// Monedhat e disponueshme për grafikun
let allCoins    = [];
let chartInst   = null;
let favorites   = typeof FAVORITES    !== 'undefined' ? FAVORITES    : [];
let isDashboard = typeof IS_DASHBOARD !== 'undefined' ? IS_DASHBOARD : false;
let isLoggedIn  = typeof IS_LOGGED_IN !== 'undefined' ? IS_LOGGED_IN : false;

// ----------------------------------------------------------
// Format helpers
// ----------------------------------------------------------
function fmtUSD(n) {
    if (n === null || n === undefined) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD',
        minimumFractionDigits: n < 1 ? 4 : 2, maximumFractionDigits: n < 1 ? 6 : 2 }).format(n);
}

function fmtBig(n) {
    if (!n) return '—';
    if (n >= 1e12) return '$' + (n / 1e12).toFixed(2) + 'T';
    if (n >= 1e9)  return '$' + (n / 1e9).toFixed(2)  + 'B';
    if (n >= 1e6)  return '$' + (n / 1e6).toFixed(2)  + 'M';
    return '$' + n.toFixed(2);
}

function changeBadge(pct) {
    if (pct === null || pct === undefined) return '<span>—</span>';
    const cls = pct >= 0 ? 'positive' : 'negative';
    const arrow = pct >= 0 ? '▲' : '▼';
    return `<span class="change ${cls}">${arrow} ${Math.abs(pct).toFixed(2)}%</span>`;
}

// ----------------------------------------------------------
// Fetch markets (top 10)
// ----------------------------------------------------------
async function fetchMarkets() {
    // price_change_percentage=24h,7d — i nevojshëm për modalin pa API call shtesë
    const url = apiUrl('coins/markets', {
        vs_currency: 'usd',
        order: 'market_cap_desc',
        per_page: 10,
        page: 1,
        sparkline: false,
        price_change_percentage: '24h,7d',
    });
    const res = await fetch(url);
    if (!res.ok) throw new Error('API error ' + res.status);
    return res.json();
}

// ----------------------------------------------------------
// Render market table (index.php)
// ----------------------------------------------------------
function renderMarketTable(coins) {
    allCoins = coins;
    const tbody = document.getElementById('market-tbody');
    if (!tbody) return;

    tbody.innerHTML = coins.map((c, i) => {
        const isFav = favorites.includes(c.id);
        const favCol = isLoggedIn
            ? `<td>
                 <form method="POST" action="/pages/dashboard.php">
                   <input type="hidden" name="coin_id" value="${c.id}">
                   <input type="hidden" name="action"  value="${isFav ? 'remove' : 'add'}">
                   <button type="submit" class="btn-fav ${isFav ? 'active' : ''}" title="${isFav ? 'Largo' : 'Shto'}">
                     ${isFav ? '★' : '☆'}
                   </button>
                 </form>
               </td>`
            : '';

        return `
        <tr>
            <td class="rank">${i + 1}</td>
            <td>
                <span class="coin-link" onclick="openModal('${c.id}')" role="button" tabindex="0">
                    <img src="${c.image}" alt="${c.name}" width="24" height="24">
                    <strong>${c.name}</strong>
                    <span class="symbol">${c.symbol.toUpperCase()}</span>
                </span>
            </td>
            <td class="price">${fmtUSD(c.current_price)}</td>
            <td>${changeBadge(c.price_change_percentage_24h)}</td>
            <td>${fmtBig(c.market_cap)}</td>
            <td>${fmtBig(c.total_volume)}</td>
            ${favCol}
        </tr>`;
    }).join('');
}

// ----------------------------------------------------------
// Render dashboard table (dashboard.php)
// ----------------------------------------------------------
function renderDashboardTable(coins) {
    const tbody = document.getElementById('dashboard-tbody');
    if (!tbody) return;

    tbody.innerHTML = coins.map((c, i) => {
        const isFav = favorites.includes(c.id);
        return `
        <tr>
            <td class="rank">${i + 1}</td>
            <td>
                <span class="coin-link" onclick="openModal('${c.id}')" role="button" tabindex="0">
                    <img src="${c.image}" alt="${c.name}" width="24" height="24">
                    <strong>${c.name}</strong>
                    <span class="symbol">${c.symbol.toUpperCase()}</span>
                </span>
            </td>
            <td class="price">${fmtUSD(c.current_price)}</td>
            <td>${changeBadge(c.price_change_percentage_24h)}</td>
            <td>${fmtBig(c.market_cap)}</td>
            <td>
                <form method="POST">
                    <input type="hidden" name="coin_id" value="${c.id}">
                    <input type="hidden" name="action"  value="${isFav ? 'remove' : 'add'}">
                    <button type="submit" class="btn-fav ${isFav ? 'active' : ''}" title="${isFav ? 'Largo' : 'Shto'}">
                        ${isFav ? '★ Largo' : '☆ Shto'}
                    </button>
                </form>
            </td>
        </tr>`;
    }).join('');
}

// ----------------------------------------------------------
// Render favorites grid (dashboard.php)
// ----------------------------------------------------------
function renderFavoritesGrid(coins) {
    const grid = document.getElementById('favorites-grid');
    if (!grid) return;

    const favCoins = coins.filter(c => favorites.includes(c.id));
    if (favCoins.length === 0) {
        grid.innerHTML = '<p class="empty-msg">Nuk ke shtuar ende monedha të preferuara.</p>';
        return;
    }

    grid.innerHTML = favCoins.map(c => {
        const pct    = c.price_change_percentage_24h;
        const cls    = pct >= 0 ? 'positive' : 'negative';
        return `
        <div class="coin-card ${cls}">
            <div class="card-header">
                <img src="${c.image}" alt="${c.name}" width="36" height="36">
                <div>
                    <div class="card-name">${c.name}</div>
                    <div class="card-symbol">${c.symbol.toUpperCase()}</div>
                </div>
            </div>
            <div class="card-price">${fmtUSD(c.current_price)}</div>
            <div class="card-change">${changeBadge(pct)}</div>
            <form method="POST">
                <input type="hidden" name="coin_id" value="${c.id}">
                <input type="hidden" name="action"  value="remove">
                <button type="submit" class="btn btn-sm btn-danger">Largo ★</button>
            </form>
        </div>`;
    }).join('');
}

// ----------------------------------------------------------
// Ticker strip
// ----------------------------------------------------------
function renderTicker(coins) {
    const ticker = document.getElementById('ticker');
    if (!ticker) return;
    ticker.innerHTML = coins.map(c => {
        const pct = c.price_change_percentage_24h;
        const cls = pct >= 0 ? 'positive' : 'negative';
        return `<span class="tick-item"><strong>${c.symbol.toUpperCase()}</strong> ${fmtUSD(c.current_price)} <em class="${cls}">${pct >= 0 ? '+' : ''}${pct?.toFixed(2)}%</em></span>`;
    }).join('');
}

// ----------------------------------------------------------
// 7-Day Chart
// ----------------------------------------------------------
async function loadChart(coinId) {
    const url = apiUrl(`coins/${coinId}/market_chart`, { vs_currency: 'usd', days: 7 });
    const res  = await fetch(url);
    if (!res.ok) return;
    const data = await res.json();

    const labels = data.prices.map(p => {
        const d = new Date(p[0]);
        return d.toLocaleDateString('sq-AL', { month: 'short', day: 'numeric' });
    });
    const prices = data.prices.map(p => p[1]);

    const ctx = document.getElementById('price-chart')?.getContext('2d');
    if (!ctx) return;

    if (chartInst) chartInst.destroy();

    chartInst = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: coinId.charAt(0).toUpperCase() + coinId.slice(1) + ' (USD)',
                data: prices,
                borderColor: '#f7931a',
                backgroundColor: 'rgba(247,147,26,0.1)',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#e2e8f0' } } },
            scales: {
                x: { ticks: { color: '#94a3b8', maxTicksLimit: 7 }, grid: { color: '#1e293b' } },
                y: { ticks: { color: '#94a3b8', callback: v => fmtUSD(v) }, grid: { color: '#1e293b' } }
            }
        }
    });
}

// ----------------------------------------------------------
// Coin Detail Modal
// ----------------------------------------------------------
let modalChartInst  = null;
let currentModalCoin = null;

async function openModal(coinId) {
    currentModalCoin = coinId;
    const overlay = document.getElementById('coin-modal');
    if (!overlay) return;

    // Use already-fetched data from allCoins — zero extra API call for stats
    const coin = allCoins.find(c => c.id === coinId);

    // Reset UI
    document.getElementById('modal-name').textContent = coin ? `${coin.name} (${coin.symbol.toUpperCase()})` : 'Duke ngarkuar...';
    document.getElementById('modal-img').src          = coin?.image ?? '';
    document.getElementById('modal-img').alt          = coin?.name ?? '';
    document.getElementById('modal-rank').textContent = coin ? `#${coin.market_cap_rank}` : '';
    document.getElementById('modal-stats').innerHTML  = '<p class="loading-msg">Duke ngarkuar...</p>';
    document.getElementById('modal-chart-loading').style.display = 'flex';
    const chartCanvas = document.getElementById('modal-chart');
    if (chartCanvas) chartCanvas.style.opacity = '0';

    // Reset active timeframe button
    document.querySelectorAll('.tf-btn').forEach(b => b.classList.toggle('active', b.dataset.days === '1'));

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Render stats immediately from cached data, fetch only the chart
    if (coin) renderModalDetail(coin);

    try {
        await loadModalChart(coinId, 1);
    } catch (e) {
        document.getElementById('modal-chart-loading').textContent = 'Grafiku nuk mund të ngarkohet.';
    }
}

function closeModal() {
    const overlay = document.getElementById('coin-modal');
    if (!overlay) return;
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    currentModalCoin = null;
}

// Works with the flat object returned by /coins/markets (no extra API call needed)
function renderModalDetail(c) {
    const pct24 = c.price_change_percentage_24h;
    // 7d field name varies depending on API response shape
    const pct7  = c.price_change_percentage_7d_in_currency ?? c.price_change_percentage_7d ?? null;

    const stats = [
        { label: 'Çmimi Aktual',        value: fmtUSD(c.current_price) },
        { label: 'Ndryshim 24h',        value: changeBadge(pct24) },
        { label: 'Ndryshim 7 Ditë',     value: pct7 !== null ? changeBadge(pct7) : '—' },
        { label: 'Kapitalizim Tregu',   value: fmtBig(c.market_cap) },
        { label: 'Volumi 24h',          value: fmtBig(c.total_volume) },
        { label: 'ATH',                 value: fmtUSD(c.ath) },
        { label: '% nga ATH',           value: changeBadge(c.ath_change_percentage) },
        { label: 'Furnizim Qarkullues', value: fmtBig(c.circulating_supply) },
    ];

    document.getElementById('modal-stats').innerHTML = stats.map(s => `
        <div class="stat-box">
            <div class="stat-label">${s.label}</div>
            <div class="stat-value">${s.value}</div>
        </div>
    `).join('');
}

async function loadModalChart(coinId, days) {
    const loading = document.getElementById('modal-chart-loading');
    const canvas  = document.getElementById('modal-chart');
    if (!canvas) return;

    if (loading) loading.style.display = 'flex';
    canvas.style.opacity = '0';

    const res  = await fetch(apiUrl(`coins/${coinId}/market_chart`, { vs_currency: 'usd', days }));
    if (!res.ok) return;
    const data = await res.json();

    const isIntraday = days === 1;
    const labels = data.prices.map(p => {
        const d = new Date(p[0]);
        return isIntraday
            ? d.toLocaleTimeString('sq-AL', { hour: '2-digit', minute: '2-digit' })
            : d.toLocaleDateString('sq-AL', { month: 'short', day: 'numeric' });
    });
    const prices = data.prices.map(p => p[1]);

    const startPrice = prices[0] ?? 0;
    const endPrice   = prices[prices.length - 1] ?? 0;
    const isUp       = endPrice >= startPrice;
    const lineColor  = isUp ? '#22c55e' : '#ef4444';
    const fillColor  = isUp ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)';

    const ctx = canvas.getContext('2d');
    if (modalChartInst) modalChartInst.destroy();

    modalChartInst = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Çmimi (USD)',
                data: prices,
                borderColor: lineColor,
                backgroundColor: fillColor,
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    ticks: { color: '#94a3b8', maxTicksLimit: 6, font: { size: 11 } },
                    grid: { color: '#1e293b' }
                },
                y: {
                    ticks: { color: '#94a3b8', callback: v => fmtUSD(v), font: { size: 11 } },
                    grid: { color: '#1e293b' }
                }
            }
        }
    });

    if (loading) loading.style.display = 'none';
    canvas.style.opacity = '1';
}

// ----------------------------------------------------------
// Main refresh loop
// ----------------------------------------------------------
async function refresh() {
    try {
        const coins = await fetchMarkets();

        if (isDashboard) {
            renderDashboardTable(coins);
            renderFavoritesGrid(coins);
        } else {
            renderMarketTable(coins);
            renderTicker(coins);
        }
    } catch (err) {
        console.warn('CryptoWatch: API fetch dështoi —', err.message);
    }
}

// ----------------------------------------------------------
// Boot
// ----------------------------------------------------------
(async function init() {
    await refresh();
    setInterval(refresh, REFRESH_MS);

    // Main page chart selector
    const selector = document.getElementById('chart-coin');
    if (selector) {
        await loadChart(selector.value);
        selector.addEventListener('change', () => loadChart(selector.value));
    }

    // Modal close button
    document.getElementById('modal-close')?.addEventListener('click', closeModal);

    // Close on overlay click (outside modal box)
    document.getElementById('coin-modal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeModal();
    });

    // Close on Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    // Timeframe buttons
    document.querySelectorAll('.tf-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (currentModalCoin) loadModalChart(currentModalCoin, Number(btn.dataset.days));
        });
    });
})();
