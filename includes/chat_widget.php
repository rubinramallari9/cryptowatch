<!-- =====================================================
     CryptoWatch — AI Chat Widget (shared partial)
     ===================================================== -->

<!-- Floating Action Button -->
<button class="chat-fab" id="chat-fab" title="Fol me CryptoAnalyst AI">
    🤖
    <span class="chat-fab-badge"></span>
</button>

<!-- Chat Panel -->
<div class="chat-panel" id="chat-panel" role="dialog" aria-label="AI Chat">

    <!-- Header -->
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-avatar">🤖</div>
            <div>
                <div class="chat-header-name">CryptoAnalyst AI</div>
                <div class="chat-header-sub">● Online — Hulumtim Tregu</div>
            </div>
        </div>
        <div class="chat-header-actions">
            <button class="chat-btn-icon" id="chat-clear" title="Pastro bisedën">🗑</button>
            <button class="chat-btn-icon" id="chat-close" title="Mbyll">✕</button>
        </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chat-messages">
        <div class="chat-msg assistant">
            <div class="chat-bubble">
                Mirë se vini! Jam <strong>CryptoAnalyst</strong> — asistenti juaj për hulumtim të tregut të kriptomonedhave. 📊<br><br>
                Mund t'ju ndihmoj me analiza teknike, shpjegime konceptesh, krahasime projektesh dhe tendencave të tregut.
            </div>
            <span class="chat-msg-time">Tani</span>
        </div>
    </div>

    <!-- Suggested prompts -->
    <div class="chat-suggestions" id="chat-suggestions">
        <button class="chat-suggestion" data-prompt="Analizo Bitcoin-in për këtë javë">₿ Analizo BTC</button>
        <button class="chat-suggestion" data-prompt="Çfarë është DeFi dhe si funksionon?">🔗 Çfarë është DeFi?</button>
        <button class="chat-suggestion" data-prompt="Krahasim Ethereum vs Solana">⚡ ETH vs SOL</button>
        <button class="chat-suggestion" data-prompt="Cilët janë rreziqet kryesore të investimit në kripto?">⚠️ Rreziqet</button>
    </div>

    <!-- Input -->
    <div class="chat-input-area">
        <textarea
            class="chat-input"
            id="chat-input"
            placeholder="Pyet për tregun, projektet, trendët..."
            rows="1"
        ></textarea>
        <button class="chat-send" id="chat-send" title="Dërgo">➤</button>
    </div>
</div>

<script src="/assets/js/chat.js"></script>
