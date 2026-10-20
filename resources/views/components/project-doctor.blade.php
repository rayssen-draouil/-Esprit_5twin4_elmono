@props(['focusProjectId' => null])

@push('styles')
<style>
    /* Project Doctor — panneau d'analyse IA */
    .doctor-wrap {
        position: fixed;
        inset: 0 0 0 auto;
        width: 420px;
        max-width: 92vw;
        background: var(--paper);
        border-left: 1px solid var(--line);
        box-shadow: -8px 0 24px #073f4a0a;
        display: flex;
        flex-direction: column;
        z-index: 60;
        transform: translateX(100%);
        transition: transform 0.28s ease;
        font: 14px/1.55 Inter, ui-sans-serif, system-ui, sans-serif;
    }
    .doctor-wrap.open { transform: translateX(0); }
    .doctor-wrap .doctor-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: var(--deep);
        color: #fff;
    }
    .doctor-wrap .doctor-mark {
        display: grid; place-items: center; width: 30px; height: 30px;
        background: var(--aqua); color: #073f4a; border-radius: 8px;
        font-weight: 800; font-size: 16px;
    }
    .doctor-wrap .doctor-title { flex: 1; font-weight: 800; letter-spacing: -.02em; }
    .doctor-wrap .doctor-title em { color: var(--aqua); font-style: normal; font-weight: 700; }
    .doctor-wrap .doctor-status {
        font-size: 11px; padding: 2px 8px; border-radius: 999px;
        background: rgba(255,255,255,0.15); color: #dff7f3;
    }
    .doctor-wrap .doctor-status.off { background: rgba(112,131,136,0.2); color: #c9d6d8; }
    .doctor-wrap .doctor-close {
        background: transparent; border: 0; color: #fff; opacity: 0.8;
        width: 32px; height: 32px; display: grid; place-items: center;
        cursor: pointer; border-radius: 6px;
    }
    .doctor-wrap .doctor-close:hover { opacity: 1; background: rgba(255,255,255,0.12); }

    .doctor-wrap .doctor-body {
        flex: 1; overflow: auto; display: flex; flex-direction: column;
        padding: 16px; gap: 12px; background: #fbfdfc;
    }
    .doctor-wrap .doctor-empty {
        display: flex; flex-direction: column; align-items: center; gap: 10px;
        color: var(--muted); font-size: 14px; padding-top: 30px;
    }
    .doctor-wrap .doctor-welcome { font-size: 13px; text-align: center; color: var(--muted); }

    .doctor-wrap .doctor-suggestions {
        display: flex; flex-direction: column; gap: 8px;
        border: 1px dashed var(--line); border-radius: 10px;
        padding: 12px 14px; background: #fff;
    }
    .doctor-wrap .doctor-sugg {
        display: flex; align-items: center; gap: 10px;
        width: 100%; text-align: left; padding: 10px 12px;
        background: #fbfdfc; border: 1px solid var(--line); border-radius: 8px;
        cursor: pointer; font-size: 13.5px; color: var(--ink);
        transition: background .15s ease, border-color .15s ease;
    }
    .doctor-wrap .doctor-sugg:hover { background: var(--soft); border-color: var(--aqua); }
    .doctor-wrap .doctor-sugg .k { font-weight: 800; color: var(--deep); }
    .doctor-wrap .doctor-sugg .v { margin-left: auto; font-size: 11px; color: var(--muted); }

    .doctor-wrap .doctor-messages { display: flex; flex-direction: column; gap: 10px; flex: 1; }
    .doctor-wrap .doctor-msg {
        display: flex; gap: 10px; max-width: 92%;
        animation: doctorFade .22s ease both;
    }
    .doctor-wrap .doctor-msg.user { margin-left: auto; flex-direction: row-reverse; }
    .doctor-wrap .doctor-msg.bot { margin-right: auto; }
    .doctor-wrap .doctor-bubble {
        padding: 10px 14px; border-radius: 12px; font-size: 13.5px;
        line-height: 1.55; white-space: pre-wrap;
    }
    .doctor-wrap .doctor-msg.bot .doctor-bubble {
        background: #fff; border: 1px solid var(--line);
        color: var(--ink);
    }
    .doctor-wrap .doctor-msg.user .doctor-bubble {
        background: var(--deep); color: #fff;
    }
    .doctor-wrap .doctor-msg .doctor-meta {
        font-size: 10px; color: var(--muted); margin-top: 4px; text-align: right;
    }
    .doctor-wrap .doctor-msg.user .doctor-meta { margin-left: auto; }

    .doctor-wrap .doctor-loading {
        display: flex; gap: 10px; align-items: center;
        padding: 10px 14px; background: #fff; border: 1px solid var(--line);
        border-radius: 12px; font-size: 13px; color: var(--muted);
    }
    .doctor-wrap .doctor-dot {
        width: 8px; height: 8px; background: var(--aqua); border-radius: 50%;
        animation: doctorPulse 1.4s ease-in-out infinite;
    }
    .doctor-wrap .doctor-dot:nth-child(2) { animation-delay: 0.2s; }
    .doctor-wrap .doctor-dot:nth-child(3) { animation-delay: 0.4s; }

    .doctor-wrap .doctor-error {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 10px 14px; background: #fde9e7; border: 1px solid #f1b6ad;
        border-radius: 12px; font-size: 12.5px; color: #a8473d;
    }
    .doctor-wrap .doctor-error b { color: #a8473d; }

    .doctor-wrap .doctor-input {
        display: flex; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--line);
        background: var(--paper);
    }
    .doctor-wrap .doctor-input textarea {
        flex: 1; resize: vertical; min-height: 44px; max-height: 120px;
        padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px;
        font: inherit; color: var(--ink); background: #fff;
    }
    .doctor-wrap .doctor-input button {
        padding: 10px 16px; background: var(--deep); color: #fff; border: 0;
        border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px;
    }
    .doctor-wrap .doctor-input button:disabled { opacity: 0.6; cursor: not-allowed; }
    .doctor-wrap .doctor-input button:hover:not(:disabled) { background: #075a66; }

    .doctor-wrap .doctor-hint {
        font-size: 11px; color: var(--muted); text-align: center;
        padding: 8px 16px; border-top: 1px solid var(--line);
    }

    .doctor-toggle {
        position: fixed; right: 24px; top: 50%; transform: translateY(-50%);
        z-index: 55; width: 56px; height: 56px;
        background: var(--deep); color: #fff; border: 0; border-radius: 50%;
        display: grid; place-items: center; font-size: 22px;
        box-shadow: 0 10px 24px #073f4a26; cursor: pointer;
    }
    .doctor-toggle:hover { background: #075a66; }

    @media (max-width: 900px) {
        .doctor-wrap { inset: auto 0 0 auto; width: 100vw; height: 72vh;
            transform: translateY(100%); border-left: none;
            border-top: 1px solid var(--line); border-radius: 16px 16px 0 0; }
        .doctor-wrap.open { transform: translateY(0); }
        .doctor-toggle { right: 20px; top: auto; bottom: 24px; transform: none;
            width: 50px; height: 50px; font-size: 20px; }
        .doctor-msg { max-width: 96%; }
    }
    @keyframes doctorFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @keyframes doctorPulse { 0%, 100% { opacity: .5; transform: scale(1); } 50% { opacity: 1; transform: scale(1.25); } }
</style>
@endpush

<div id="project-doctor" data-route="{{ route('projects.doctor.message') }}"
     data-status-route="{{ route('projects.doctor.status') }}"
     data-focus="{{ $focusProjectId ?? '' }}"
     class="doctor-wrap" aria-label="Project Doctor">
    <div class="doctor-header">
        <div class="doctor-mark">🏥</div>
        <div class="doctor-title">Project Doctor<span>IA</span></div>
        <span class="doctor-status" id="doctor-status">● Service IA</span>
        <button class="doctor-close" aria-label="Fermer" title="Fermer">✕</button>
    </div>

    <div class="doctor-body">
        <div class="doctor-empty" id="doctor-empty">
            <div class="doctor-welcome">Bienvenue sur Project Doctor.</div>
            <div class="doctor-welcome">Posez vos questions sur vos projets.</div>

            <div class="doctor-suggestions">
                <button class="doctor-sugg" data-intent="cost_estimation">
                    <span class="k">💰</span><span>Estimer le coût final</span>
                    <span class="v">Coût projeté</span>
                </button>
                <button class="doctor-sugg" data-intent="delay_risk">
                    <span class="k">⏱</span><span>Évaluer le risque de retard</span>
                    <span class="v">Planning</span>
                </button>
                <button class="doctor-sugg" data-intent="funding_forecast">
                    <span class="k">💳</span><span>Prévoir le besoin de financement</span>
                    <span class="v">Couverture</span>
                </button>
            </div>
        </div>

        <div class="doctor-messages" id="doctor-messages"></div>
        <div id="doctor-loading" class="doctor-loading" style="display:none;">
            <div class="doctor-dot"></div><div class="doctor-dot"></div><div class="doctor-dot"></div>
            <span>Analyse en cours...</span>
        </div>
        <div id="doctor-error" class="doctor-error" style="display:none;"></div>
    </div>

    <div class="doctor-input">
        <textarea id="doctor-input" placeholder="Tapez votre question sur les projets…" aria-label="Question Project Doctor"></textarea>
        <button id="doctor-send" type="button">Envoyer</button>
    </div>
    <div class="doctor-hint" id="doctor-hint">
        Les données nécessaires sont transmises à Google Gemini. Les estimations sont indicatives.
    </div>
</div>

<button class="doctor-toggle" id="doctor-toggle" aria-label="Ouvrir Project Doctor" title="Project Doctor">🏥</button>

@push('scripts')
<script>
(function () {    var wrap = document.getElementById('project-doctor');
    var toggle = document.getElementById('doctor-toggle');
    var closeBtn = wrap.querySelector('.doctor-close');
    var messagesEl = document.getElementById('doctor-messages');
    var emptyEl = document.getElementById('doctor-empty');
    var inputEl = document.getElementById('doctor-input');
    var sendBtn = document.getElementById('doctor-send');
    var loadingEl = document.getElementById('doctor-loading');
    var errorEl = document.getElementById('doctor-error');
    var statusEl = document.getElementById('doctor-status');
    var route = wrap.dataset.route;
    var statusRoute = wrap.dataset.statusRoute;
    var focusProject = wrap.dataset.focus || null;

    var open = false;
    var busy = false;

    function setOpen(value) {
        open = value;
        wrap.classList.toggle('open', open);
        toggle.style.display = open ? 'none' : 'grid';
    }
    toggle.addEventListener('click', function () { setOpen(true); });
    closeBtn.addEventListener('click', function () { setOpen(false); });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function appendMessage(role, text, meta) {
        emptyEl.style.display = 'none';
        var div = document.createElement('div');
        div.className = 'doctor-msg ' + (role === 'user' ? 'user' : 'bot');
        var bubble = document.createElement('div');
        bubble.className = 'doctor-bubble';
        bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
        div.appendChild(bubble);
        if (meta) {
            var m = document.createElement('div');
            m.className = 'doctor-meta';
            m.textContent = meta;
            div.appendChild(m);
        }
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function showError(text) {
        errorEl.style.display = 'flex';
        errorEl.querySelector('b') ? '' : null;
        errorEl.innerHTML = '<b>Erreur :</b> ' + escapeHtml(text);
        setBusy(false);
    }

    function clearError() { errorEl.style.display = 'none'; }

    function setBusy(value) {
        busy = value;
        loadingEl.style.display = value ? 'flex' : 'none';
        sendBtn.disabled = value;
        inputEl.disabled = value;
    }

    function autoGrow() {
        inputEl.style.height = 'auto';
        inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
    }
    inputEl.addEventListener('input', autoGrow);

    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !busy) {
            e.preventDefault();
            send();
        }
    });

    sendBtn.addEventListener('click', send);

    function send(intent, presetMessage) {
        if (busy) return;
        clearError();
        var message = presetMessage || inputEl.value.trim();
        if (!message) return;
        inputEl.value = '';
        inputEl.style.height = 'auto';
        appendMessage('user', message, 'Vous');

        setBusy(true);
        fetch(route, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                intent: intent || 'general_question',
                message: message,
                project_id: focusProject || null
            })
        }).then(function (res) {
            return res.json().then(function (body) { return { ok: res.ok, status: res.status, body: body }; });
        }).then(function (result) {
            var body = result.body;
            if (body && body.success && body.answer) {
                appendMessage('bot', body.answer, body.disclaimer ? 'Project Doctor · ' + body.disclaimer : undefined);
            } else if (body && body.answer) {
                appendMessage('bot', body.answer, 'Erreur (' + (body.code || result.status) + ')');
            } else {
                showError(result.status === 429 ? 'Quota d\'appels atteint. Réessayez plus tard.'
                    : result.status === 403 ? 'Accès interdit à ce projet.'
                    : 'Réponse invalide du service.');
            }
        }).catch(function () {
            showError('Impossible de joindre le service. Vérifiez votre connexion.');
        }).finally(function () { setBusy(false); });
    }

    document.querySelectorAll('.doctor-sugg').forEach(function (btn) {
        btn.addEventListener('click', function () {
            send(btn.dataset.intent, btn.querySelector('span').textContent.trim());
        });
    });

    // Disponibilité du service
    fetch(statusRoute, { headers: { 'Accept': 'application/json' } }).then(function (res) {
        return res.json();
    }).then(function (data) {
        var avail = data && data.available;
        statusEl.textContent = avail ? '● Service IA disponible' : '● Service non configuré';
        statusEl.className = 'doctor-status ' + (avail ? '' : 'off');
        if (!avail) {
            appendMessage('bot', 'Project Doctor est disponible en mode analyse déterministe. La clé Gemini n\'est pas configurée sur le serveur ; les estimations s\'appuieront sur les calculs de base sans interprétation IA.', 'Indication');
        }
    }).catch(function () {
        statusEl.textContent = '● Service indisponible';
        statusEl.className = 'doctor-status off';
    });
})();
</script>
@endpush
