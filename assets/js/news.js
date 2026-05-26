/* =========================================================
   CryptoWatch — news.js
   Merr dhe shfaq lajmet e kriptomonedhave nga CryptoCompare
   ========================================================= */

const NEWS_REFRESH_MS = 15 * 60 * 1000; // 15 minuta

let currentCategory = '';

// ----------------------------------------------------------
// Format koha relative (p.sh. "2 orë më parë")
// ----------------------------------------------------------
function timeAgo(ts) {
    const diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60)   return 'Tani';
    if (diff < 3600) return Math.floor(diff / 60) + ' min më parë';
    if (diff < 86400) return Math.floor(diff / 3600) + ' orë më parë';
    return Math.floor(diff / 86400) + ' ditë më parë';
}

// ----------------------------------------------------------
// Fetch lajmet nga proxy-i PHP
// ----------------------------------------------------------
async function fetchNews(category = '') {
    const url = '/api/news.php' + (category ? '?category=' + encodeURIComponent(category) : '');
    const res  = await fetch(url);
    if (!res.ok) throw new Error('News fetch failed');
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data.articles ?? [];
}

// ----------------------------------------------------------
// Render grid lajmesh
// ----------------------------------------------------------
function renderNews(articles) {
    const grid = document.getElementById('news-grid');
    if (!grid) return;

    if (!articles.length) {
        grid.innerHTML = '<p class="empty-msg">Nuk ka lajme për këtë kategori.</p>';
        return;
    }

    grid.innerHTML = articles.map((a, i) => {
        const img     = a.imageurl
            ? `<img src="${a.imageurl}" alt="${escHtml(a.title)}" loading="lazy" onerror="this.parentElement.classList.add('no-img')">`
            : '';
        const srcImg  = a.source_img
            ? `<img src="${a.source_img}" alt="${escHtml(a.source)}" class="source-logo" width="16" height="16">`
            : '';
        const tags    = a.categories
            ? a.categories.split('|').slice(0, 3).map(t => `<span class="news-tag">${escHtml(t.trim())}</span>`).join('')
            : '';

        return `
        <article class="news-card${i === 0 ? ' news-card-featured' : ''}" onclick="window.open('${escHtml(a.url)}','_blank')" role="link" tabindex="0">
            ${img ? `<div class="news-img-wrap">${img}</div>` : ''}
            <div class="news-body">
                ${tags ? `<div class="news-tags">${tags}</div>` : ''}
                <h3 class="news-title">${escHtml(a.title)}</h3>
                <p class="news-excerpt">${escHtml(a.body)}${a.body.length >= 220 ? '…' : ''}</p>
                <div class="news-meta">
                    <span class="news-source">${srcImg} ${escHtml(a.source)}</span>
                    <span class="news-time">🕐 ${timeAgo(a.published_on)}</span>
                </div>
            </div>
        </article>`;
    }).join('');
}

function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ----------------------------------------------------------
// Skeleton loader
// ----------------------------------------------------------
function showSkeleton() {
    const grid = document.getElementById('news-grid');
    if (!grid) return;
    grid.innerHTML = Array(6).fill(`
        <article class="news-card news-skeleton">
            <div class="news-img-wrap skeleton-box"></div>
            <div class="news-body">
                <div class="skeleton-line w60"></div>
                <div class="skeleton-line w100"></div>
                <div class="skeleton-line w80"></div>
                <div class="skeleton-line w40"></div>
            </div>
        </article>`).join('');
}

// ----------------------------------------------------------
// Load + rifresko
// ----------------------------------------------------------
async function loadNews(category = '') {
    showSkeleton();
    try {
        const articles = await fetchNews(category);
        renderNews(articles);
    } catch (err) {
        const grid = document.getElementById('news-grid');
        if (grid) grid.innerHTML = `<p class="empty-msg">⚠️ ${err.message}</p>`;
    }
}

// ----------------------------------------------------------
// Boot
// ----------------------------------------------------------
(function init() {
    const tabs = document.querySelectorAll('.news-tab');
    if (!tabs.length) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentCategory = tab.dataset.cat;
            loadNews(currentCategory);
        });
    });

    // keyboard nav pentru artikull
    document.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.classList.contains('news-card')) {
            e.target.click();
        }
    });

    loadNews('');
    setInterval(() => loadNews(currentCategory), NEWS_REFRESH_MS);
})();
