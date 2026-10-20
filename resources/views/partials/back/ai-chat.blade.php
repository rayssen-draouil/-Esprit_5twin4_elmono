{{--
    Assistant IA AquaSecure — widget flottant (lecture seule, zones + alertes).
    Affiché uniquement pour les rôles admin / manager / technician.
    Le rendu des réponses est strictement XSS-safe (textContent + nœuds DOM).
--}}
@php
    $chatUser = auth()->user();
    $chatAllowed = \App\Http\Controllers\Back\AiChatController::canUse($chatUser?->role);
    $chatHistory = $chatAllowed ? session(\App\Http\Controllers\Back\AiChatController::SESSION_KEY, []) : [];
@endphp

@if($chatAllowed)
<div id="ai-chat-root" data-ai-chat-root
     data-chat-url="{{ route('back.ai.chat') }}"
     data-status-url="{{ route('back.ai.chat.status') }}"
     data-csrf="{{ csrf_token() }}">

    <button type="button" id="ai-chat-toggle" class="ai-chat-toggle" aria-expanded="false" aria-controls="ai-chat-panel">
        <span class="ai-chat-toggle-icon" aria-hidden="true">💬</span>
        <span class="ai-chat-toggle-label">Assistant IA</span>
    </button>

    <section id="ai-chat-panel" class="ai-chat-panel" hidden aria-label="Assistant IA AquaSecure">
        <header class="ai-chat-header">
            <div class="ai-chat-header-title">
                <strong>Assistant IA</strong>
                <span class="ai-chat-status">
                    <span id="ai-chat-dot" class="ai-chat-dot ai-chat-dot--unknown" aria-hidden="true"></span>
                    <span id="ai-chat-status-label">Vérification…</span>
                    <span id="ai-chat-model" class="ai-chat-model"></span>
                </span>
            </div>
            <div class="ai-chat-header-actions">
                <button type="button" id="ai-chat-clear" class="ai-chat-mini-btn" title="Effacer la conversation">Effacer</button>
                <button type="button" id="ai-chat-close" class="ai-chat-mini-btn" title="Fermer l'assistant">✕</button>
            </div>
        </header>

        <div id="ai-chat-messages" class="ai-chat-messages" aria-live="polite">
            @if(count($chatHistory) === 0)
                <div class="ai-chat-bubble ai-chat-bubble--bot">
                    Bonjour ! Je suis l'assistant AquaSecure. Je réponds uniquement sur les zones de gestion
                    et leurs alertes (lecture seule). Posez votre question ci-dessous.
                </div>
            @else
                @foreach($chatHistory as $chatEntry)
                    @if(in_array($chatEntry['role'] ?? '', ['user', 'assistant'], true))
                        <div class="ai-chat-bubble ai-chat-bubble--{{ $chatEntry['role'] === 'user' ? 'user' : 'bot' }}">
                            {{ $chatEntry['content'] ?? '' }}
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        <div class="ai-chat-suggestions" id="ai-chat-suggestions">
            <button type="button" class="ai-chat-chip">Quelles zones sont les plus risquées ?</button>
            <button type="button" class="ai-chat-chip">Résumé de la zone Tunis</button>
            <button type="button" class="ai-chat-chip">Alertes des 7 derniers jours</button>
            <button type="button" class="ai-chat-chip">Combien d'alertes critiques ?</button>
            <button type="button" class="ai-chat-chip">Quelles sont les alertes prioritaires ?</button>
        </div>

        <form id="ai-chat-form" class="ai-chat-form" autocomplete="off">
            <label class="ai-chat-label" for="ai-chat-input">Votre question</label>
            <textarea id="ai-chat-input" class="ai-chat-input" rows="2" maxlength="500"
                      placeholder="Ex. : Quelles zones sont les plus risquées ?"></textarea>
            <div class="ai-chat-form-footer">
                <span id="ai-chat-counter" class="ai-chat-counter">0/500</span>
                <button type="submit" id="ai-chat-send" class="ai-chat-send">Envoyer</button>
            </div>
        </form>

        <footer class="ai-chat-footer">Lecture seule · zones &amp; alertes AquaSecure</footer>
    </section>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var root = document.getElementById('ai-chat-root');
    if (!root) return;

    var chatUrl = root.getAttribute('data-chat-url');
    var statusUrl = root.getAttribute('data-status-url');
    var csrf = root.getAttribute('data-csrf');

    var panel = document.getElementById('ai-chat-panel');
    var toggle = document.getElementById('ai-chat-toggle');
    var closeBtn = document.getElementById('ai-chat-close');
    var clearBtn = document.getElementById('ai-chat-clear');
    var form = document.getElementById('ai-chat-form');
    var input = document.getElementById('ai-chat-input');
    var send = document.getElementById('ai-chat-send');
    var counter = document.getElementById('ai-chat-counter');
    var messages = document.getElementById('ai-chat-messages');
    var dot = document.getElementById('ai-chat-dot');
    var statusLabel = document.getElementById('ai-chat-status-label');
    var modelLabel = document.getElementById('ai-chat-model');

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function safeUrl(url) {
        var value = String(url || '');
        if (value.charAt(0) === '/' && value.charAt(1) !== '/') return value;
        if (/^https?:\/\//i.test(value)) return '#';
        return '#';
    }

    function scrollDown() { messages.scrollTop = messages.scrollHeight; }

    function addBubble(role, text) {
        var bubble = el('div', 'ai-chat-bubble ai-chat-bubble--' + role, text);
        messages.appendChild(bubble);
        scrollDown();
        return bubble;
    }

    function addError(text) { addBubble('error', text); }

    function addNotice(text) {
        if (!text) return;
        messages.appendChild(el('div', 'ai-chat-notice', text));
        scrollDown();
    }

    function addSource(note) {
        messages.appendChild(el('div', 'ai-chat-source', note || 'Source : données AquaSecure'));
        scrollDown();
    }

    function addLinks(links) {
        if (!Array.isArray(links) || links.length === 0) return;
        var wrap = el('div', 'ai-chat-links');
        links.forEach(function (link) {
            if (!link || !link.url) return;
            var a = el('a', null, link.label || 'Voir la fiche');
            a.href = safeUrl(link.url);
            a.rel = 'noopener';
            wrap.appendChild(a);
        });
        messages.appendChild(wrap);
        scrollDown();
    }

    function showTyping() {
        var node = el('div', 'ai-chat-typing');
        node.id = 'ai-chat-typing';
        node.appendChild(el('span'));
        node.appendChild(el('span'));
        node.appendChild(el('span'));
        messages.appendChild(node);
        scrollDown();
    }

    function hideTyping() {
        var node = document.getElementById('ai-chat-typing');
        if (node && node.parentNode) node.parentNode.removeChild(node);
    }

    function checkStatus() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                dot.className = 'ai-chat-dot ' + (data.online ? 'ai-chat-dot--online' : 'ai-chat-dot--offline');
                statusLabel.textContent = data.online ? 'Ollama connecté' : 'Ollama hors ligne';
                modelLabel.textContent = data.model ? '(' + String(data.model) + ')' : '';
            })
            .catch(function () {
                dot.className = 'ai-chat-dot ai-chat-dot--offline';
                statusLabel.textContent = 'Ollama hors ligne';
            });
    }

    function sendMessage(text) {
        var trimmed = String(text || '').trim();
        if (!trimmed) return;

        addBubble('user', trimmed);
        input.value = '';
        counter.textContent = '0/500';
        send.disabled = true;
        showTyping();

        fetch(chatUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ message: trimmed })
        })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok || !data.success) {
                        var msg = (data && data.errors && data.errors.message)
                            ? data.errors.message[0]
                            : ((data && data.message) || 'Réponse impossible pour le moment.');
                        throw new Error(msg);
                    }
                    return data;
                });
            })
            .then(function (data) {
                hideTyping();
                if (data.reply) addBubble('bot', data.reply);
                addNotice(data.notice);
                addLinks(data.links);
                addSource(data.source_note);
            })
            .catch(function (error) {
                hideTyping();
                addError(error.message || 'Le serveur est injoignable. Réessayez dans un instant.');
            })
            .finally(function () {
                send.disabled = false;
                input.focus();
            });
    }

    function clearConversation() {
        fetch(chatUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ message: 'effacer', clear: true })
        })
            .then(function (r) { return r.json(); })
            .then(function () {
                messages.textContent = '';
                addBubble('bot', 'Conversation effacée. Posez une nouvelle question.');
            })
            .catch(function () { addError('Impossible d\'effacer la conversation.'); });
    }

    toggle.addEventListener('click', function () {
        var open = panel.hasAttribute('hidden');
        if (open) {
            panel.removeAttribute('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            checkStatus();
            input.focus();
        } else {
            panel.setAttribute('hidden', '');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    closeBtn.addEventListener('click', function () {
        panel.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
    });

    clearBtn.addEventListener('click', clearConversation);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage(input.value);
        }
    });

    input.addEventListener('input', function () {
        counter.textContent = String(input.value.length) + '/500';
    });

    document.querySelectorAll('.ai-chat-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            sendMessage(chip.textContent);
        });
    });

    scrollDown();
})();
</script>
@endpush
@endif
