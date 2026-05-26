/* =========================================================
   CryptoWatch — chat.js
   AI Chat Widget me SSE streaming
   ========================================================= */

(function () {
    const panel      = document.getElementById('chat-panel');
    const fab        = document.getElementById('chat-fab');
    const closeBtn   = document.getElementById('chat-close');
    const clearBtn   = document.getElementById('chat-clear');
    const messagesEl = document.getElementById('chat-messages');
    const inputEl    = document.getElementById('chat-input');
    const sendBtn    = document.getElementById('chat-send');
    const suggestions = document.getElementById('chat-suggestions');

    if (!panel) return;

    // Conversation history (kept for context)
    let history  = [];
    let streaming = false;

    // ----------------------------------------------------------
    // Panel open/close
    // ----------------------------------------------------------
    fab.addEventListener('click', () => {
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) inputEl.focus();
    });

    closeBtn.addEventListener('click', () => panel.classList.remove('open'));

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            panel.classList.remove('open');
        }
    });

    // ----------------------------------------------------------
    // Clear conversation
    // ----------------------------------------------------------
    clearBtn.addEventListener('click', () => {
        history = [];
        // Keep only the welcome message
        const welcome = messagesEl.querySelector('.chat-msg');
        messagesEl.innerHTML = '';
        if (welcome) messagesEl.appendChild(welcome);
        if (suggestions) suggestions.style.display = 'flex';
    });

    // ----------------------------------------------------------
    // Auto-resize textarea
    // ----------------------------------------------------------
    inputEl.addEventListener('input', () => {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
    });

    // Send on Enter (Shift+Enter = newline)
    inputEl.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!streaming) send();
        }
    });

    sendBtn.addEventListener('click', () => { if (!streaming) send(); });

    // Suggested prompts
    document.querySelectorAll('.chat-suggestion').forEach(btn => {
        btn.addEventListener('click', () => {
            inputEl.value = btn.dataset.prompt;
            if (suggestions) suggestions.style.display = 'none';
            send();
        });
    });

    // ----------------------------------------------------------
    // Render helpers
    // ----------------------------------------------------------
    function now() {
        return new Date().toLocaleTimeString('sq-AL', { hour: '2-digit', minute: '2-digit' });
    }

    function appendMessage(role, html, id = null) {
        const div = document.createElement('div');
        div.className = `chat-msg ${role}`;
        if (id) div.id = id;
        div.innerHTML = `
            <div class="chat-bubble">${html}</div>
            <span class="chat-msg-time">${now()}</span>
        `;
        messagesEl.appendChild(div);
        scrollToBottom();
        return div;
    }

    function showTyping() {
        const div = document.createElement('div');
        div.className = 'chat-msg assistant chat-typing';
        div.id = 'chat-typing';
        div.innerHTML = `
            <div class="chat-bubble">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>`;
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    function removeTyping() {
        document.getElementById('chat-typing')?.remove();
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Basic markdown → HTML (bold, italic, line breaks)
    function mdToHtml(text) {
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    // ----------------------------------------------------------
    // Send message
    // ----------------------------------------------------------
    async function send() {
        const text = inputEl.value.trim();
        if (!text || streaming) return;

        streaming = true;
        sendBtn.disabled = true;
        inputEl.value = '';
        inputEl.style.height = 'auto';
        if (suggestions) suggestions.style.display = 'none';

        // Add user message to UI and history
        appendMessage('user', mdToHtml(text));
        history.push({ role: 'user', content: text });

        // Show typing indicator while connecting
        showTyping();

        let assistantText = '';
        let bubbleEl = null;

        try {
            const resp = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messages: history }),
            });

            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            const reader = resp.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            removeTyping();

            // Create assistant bubble for streaming
            const msgDiv = appendMessage('assistant', '', 'streaming-msg');
            bubbleEl = msgDiv.querySelector('.chat-bubble');

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop(); // keep incomplete line

                for (const line of lines) {
                    if (line.startsWith('event: delta')) continue;
                    if (line.startsWith('event: error')) continue;
                    if (line.startsWith('event: done'))  continue;

                    if (line.startsWith('data: ')) {
                        try {
                            const payload = JSON.parse(line.slice(6));

                            if (payload.text !== undefined) {
                                assistantText += payload.text;
                                bubbleEl.innerHTML = mdToHtml(assistantText);
                                scrollToBottom();
                            }

                            if (payload.message) {
                                // Error event
                                bubbleEl.innerHTML = `<span style="color:var(--red)">⚠️ ${mdToHtml(payload.message)}</span>`;
                            }
                        } catch (_) { /* ignore parse errors */ }
                    }
                }
            }

            // Remove streaming id
            document.getElementById('streaming-msg')?.removeAttribute('id');

            if (assistantText) {
                history.push({ role: 'assistant', content: assistantText });
            }

        } catch (err) {
            removeTyping();
            if (bubbleEl) {
                bubbleEl.innerHTML = `<span style="color:var(--red)">⚠️ Gabim lidhje: ${err.message}</span>`;
            } else {
                appendMessage('assistant', `<span style="color:var(--red)">⚠️ Gabim: ${err.message}</span>`);
            }
        } finally {
            streaming = false;
            sendBtn.disabled = false;
            inputEl.focus();
        }
    }

})();
