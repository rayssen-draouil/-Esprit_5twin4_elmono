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

    .doctor-wrap .doctor-messages { display: flex; flex-direction: column; gap: 12px; flex: 1; }
    .doctor-wrap .doctor-msg {
        display: flex; gap: 10px; max-width: 94%;
        animation: doctorFade .24s ease both;
    }
    .doctor-wrap .doctor-msg.user { margin-left: auto; flex-direction: row-reverse; }
    .doctor-wrap .doctor-msg.bot { margin-right: auto; }
    .doctor-wrap .doctor-bubble {
        padding: 10px 14px; border-radius: 12px; font-size: 13.5px;
        line-height: 1.55; min-width: 0; word-break: break-word;
    }
    .doctor-wrap .doctor-msg.bot .doctor-bubble {
        background: #fff; border: 1px solid var(--line);
        color: var(--ink);
    }
    .doctor-wrap .doctor-msg.user .doctor-bubble {
        background: var(--deep); color: #fff; white-space: pre-wrap;
    }
    .doctor-wrap .doctor-msg .doctor-meta {
        font-size: 10px; color: var(--muted); margin-top: 4px;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .doctor-wrap .doctor-msg.user .doctor-meta { margin-left: auto; justify-content: flex-end; }
    .doctor-wrap .doctor-copy {
        border: 1px solid var(--line); background: #fff; color: var(--muted);
        border-radius: 999px; padding: 1px 8px; font-size: 10px; cursor: pointer;
        font-family: inherit; line-height: 1.6;
    }
    .doctor-wrap .doctor-copy:hover { color: var(--aqua); border-color: var(--aqua); }

    /* Markdown rendu dans les réponses */
    .doctor-wrap .doctor-md > *:first-child { margin-top: 0; }
    .doctor-wrap .doctor-md > *:last-child { margin-bottom: 0; }
    .doctor-wrap .doctor-p { margin: 0 0 8px; }
    .doctor-wrap .doctor-h {
        margin: 12px 0 6px; font-size: 13.5px; font-weight: 800; color: var(--deep);
        line-height: 1.3;
    }
    .doctor-wrap .doctor-h:first-child { margin-top: 0; }
    .doctor-wrap .doctor-list { margin: 0 0 8px; padding-left: 18px; }
    .doctor-wrap .doctor-list li { margin-bottom: 3px; }
    .doctor-wrap .doctor-md strong { font-weight: 800; color: var(--deep); }
    .doctor-wrap .doctor-md em { font-style: italic; }
    .doctor-wrap .doctor-md code {
        background: #f1f5f9; border-radius: 4px; padding: 1px 4px;
        font-size: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }
    .doctor-wrap .doctor-rule { border: 0; border-top: 1px solid var(--line); margin: 10px 0; }

    /* Carte de données structurées */
    .doctor-wrap .doctor-card {
        margin-top: 10px; background: #fff; border: 1px solid var(--line);
        border-radius: 10px; padding: 12px 14px;
    }
    .doctor-wrap .doctor-card-head {
        display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
        padding-bottom: 8px; border-bottom: 1px solid var(--line);
    }
    .doctor-wrap .doctor-card-icon { font-size: 15px; }
    .doctor-wrap .doctor-card-head strong {
        font-size: 11px; font-weight: 800; letter-spacing: .06em;
        text-transform: uppercase; color: var(--muted);
    }
    .doctor-wrap .doctor-card-grid {
        margin: 0; display: grid; gap: 8px 12px;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    }
    .doctor-wrap .doctor-card-item { min-width: 0; }
    .doctor-wrap .doctor-card-item dt {
        font-size: 10px; color: var(--muted); text-transform: uppercase;
        letter-spacing: .04em; margin-bottom: 2px;
    }
    .doctor-wrap .doctor-card-item dd {
        margin: 0; font-size: 13px; font-weight: 700; color: var(--deep);
        word-break: break-word;
    }
    .doctor-wrap .doctor-card-strong { color: var(--aqua); }
    .doctor-wrap .doctor-list-projects {
        list-style: none; margin: 10px 0 0; padding: 8px 0 0;
        border-top: 1px dashed var(--line); display: flex; flex-direction: column; gap: 6px;
    }
    .doctor-wrap .doctor-list-projects li {
        display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink);
    }
    .doctor-wrap .doctor-list-projects .n { font-weight: 700; color: var(--deep); }
    .doctor-wrap .doctor-list-projects .p { margin-left: auto; color: var(--muted); font-size: 11px; }

    /* Badges de niveau de risque */
    .doctor-wrap .doctor-risk {
        display: inline-block; padding: 2px 8px; border-radius: 999px;
        font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
    }
    .doctor-wrap .doctor-risk-low { background: #dcfce7; color: #166534; }
    .doctor-wrap .doctor-risk-medium { background: #fef3c7; color: #92400e; }
    .doctor-wrap .doctor-risk-high { background: #fee2e2; color: #991b1b; }
    .doctor-wrap .doctor-risk-unknown { background: #e5e7eb; color: #4b5563; }

    /* Notes IA (hypothèses, recommandations…) */
    .doctor-wrap .doctor-notes {
        margin-top: 10px; background: #f8fbfb; border: 1px dashed var(--line);
        border-radius: 10px; padding: 10px 14px;
    }
    .doctor-wrap .doctor-note + .doctor-note { margin-top: 8px; }
    .doctor-wrap .doctor-note-title {
        font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
        color: var(--deep); margin-bottom: 3px;
    }
    .doctor-wrap .doctor-note ul { margin: 0; padding-left: 16px; }
    .doctor-wrap .doctor-note li { font-size: 12px; color: var(--ink); margin-bottom: 2px; }
    .doctor-wrap .doctor-sources {
        margin-top: 8px; font-size: 10px; color: var(--muted);
    }

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
        .doctor-wrap .doctor-msg { max-width: 98%; }
        .doctor-wrap .doctor-card-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
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
            <span id="doctor-loading-text">Analyse en cours...</span>
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
(function () {
    'use strict';

    var wrap = document.getElementById('project-doctor');
    if (!wrap) { return; }

    var toggle = document.getElementById('doctor-toggle');
    var closeBtn = wrap.querySelector('.doctor-close');
    var messagesEl = document.getElementById('doctor-messages');
    var emptyEl = document.getElementById('doctor-empty');
    var inputEl = document.getElementById('doctor-input');
    var sendBtn = document.getElementById('doctor-send');
    var loadingEl = document.getElementById('doctor-loading');
    var loadingTextEl = document.getElementById('doctor-loading-text');
    var errorEl = document.getElementById('doctor-error');
    var statusEl = document.getElementById('doctor-status');
    var route = wrap.dataset.route;
    var statusRoute = wrap.dataset.statusRoute;
    var focusProject = wrap.dataset.focus || null;

    var open = false;
    var busy = false;

    /* ------------------------------------------------------------------ */
    /* Utilitaires de formatage                                            */
    /* ------------------------------------------------------------------ */

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function num(value) {
        if (typeof value === 'number' && isFinite(value)) { return value; }
        if (typeof value === 'string' && value.trim() !== '' && isFinite(Number(value))) {
            return Number(value);
        }
        return null;
    }

    function money(value) {
        var n = num(value);
        if (n === null) { return '—'; }
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' DT';
    }

    function percent(value) {
        var n = num(value);
        if (n === null) { return '—'; }
        return (Math.round(n * 10) / 10).toLocaleString('fr-FR') + ' %';
    }

    function days(value) {
        var n = num(value);
        if (n === null) { return '—'; }
        return n.toLocaleString('fr-FR') + ' jour(s)';
    }

    function dateText(value) {
        if (typeof value !== 'string' || value.trim() === '') { return '—'; }
        var parts = value.split('-');
        if (parts.length !== 3) { return value; }
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function plainText(value) {
        return typeof value === 'string' && value.trim() !== '' ? value : '—';
    }

    function labelFrom(map, key, fallback) {
        if (typeof key !== 'string') { return fallback; }
        if (!Object.prototype.hasOwnProperty.call(map, key)) { return fallback; }
        return map[key];
    }

    function formatTime(date) {
        var hours = date.getHours();
        var minutes = date.getMinutes();
        return (hours < 10 ? '0' + hours : hours) + ':' + (minutes < 10 ? '0' + minutes : minutes);
    }

    /* ------------------------------------------------------------------ */
    /* Markdown léger (rendu sur texte échappé)                            */
    /* ------------------------------------------------------------------ */

    function inlineMarkdown(value) {
        return value
            .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*\w])\*([^*\n]+)\*/g, '$1<em>$2</em>')
            .replace(/`([^`\n]+)`/g, '<code>$1</code>');
    }

    function renderMarkdown(raw) {
        if (raw === null || raw === undefined) { return ''; }

        var lines = escapeHtml(String(raw)).split(/\r\n|\r|\n/);
        var out = [];
        var list = null;
        var paragraph = [];

        function flushList() {
            if (list) { out.push('</' + list + '>'); list = null; }
        }

        function flushParagraph() {
            if (paragraph.length) {
                out.push('<p class="doctor-p">' + paragraph.map(inlineMarkdown).join(' ') + '</p>');
                paragraph = [];
            }
        }

        for (var i = 0; i < lines.length; i += 1) {
            var line = lines[i].replace(/\s+$/, '');
            var trimmed = line.trim();

            if (trimmed === '') { flushParagraph(); flushList(); continue; }

            if (/^(-{3,}|\*{3,}|_{3,})$/.test(trimmed)) {
                flushParagraph(); flushList();
                out.push('<hr class="doctor-rule">');
                continue;
            }

            var heading = /^(#{1,6})\s+(.+)$/.exec(trimmed);
            if (heading) {
                flushParagraph(); flushList();
                var level = Math.min(5, heading[1].length + 1);
                out.push('<h' + level + ' class="doctor-h">' + inlineMarkdown(heading[2]) + '</h' + level + '>');
                continue;
            }

            var bullet = /^[-*•]\s+(.+)$/.exec(trimmed);
            if (bullet) {
                flushParagraph();
                if (list !== 'ul') { flushList(); out.push('<ul class="doctor-list">'); list = 'ul'; }
                out.push('<li>' + inlineMarkdown(bullet[1]) + '</li>');
                continue;
            }

            var ordered = /^\d+[.)]\s+(.+)$/.exec(trimmed);
            if (ordered) {
                flushParagraph();
                if (list !== 'ol') { flushList(); out.push('<ol class="doctor-list">'); list = 'ol'; }
                out.push('<li>' + inlineMarkdown(ordered[1]) + '</li>');
                continue;
            }

            paragraph.push(line);
        }

        flushParagraph();
        flushList();
        return out.join('');
    }

    /* ------------------------------------------------------------------ */
    /* Cartes de données structurées                                       */
    /* ------------------------------------------------------------------ */

    var INTENT_META = {
        cost_estimation: { icon: '💰', label: 'Estimation du coût' },
        delay_risk: { icon: '⏱', label: 'Risque de retard' },
        funding_forecast: { icon: '💳', label: 'Besoin de financement' },
        project_overview: { icon: '📊', label: "Vue d'ensemble" },
        general_question: { icon: '💬', label: 'Réponse' }
    };

    var RISK_CLASSES = {
        low: 'doctor-risk-low',
        medium: 'doctor-risk-medium',
        high: 'doctor-risk-high',
        unknown: 'doctor-risk-unknown'
    };

    var RISK_LABELS = {
        low: 'Faible',
        medium: 'Moyen',
        high: 'Élevé',
        unknown: 'Inconnu'
    };

    var CONFIDENCE_LABELS = { high: 'Élevée', medium: 'Moyenne', low: 'Faible' };

    var METHOD_LABELS = {
        extrapolation_lineaire: 'Extrapolation linéaire',
        indicatif: 'Indicatif',
        aucun: 'Non calculé'
    };

    function riskBadge(value) {
        var key = typeof value === 'string' ? value.toLowerCase() : 'unknown';
        return {
            className: 'doctor-risk ' + labelFrom(RISK_CLASSES, key, 'doctor-risk-unknown'),
            label: labelFrom(RISK_LABELS, key, 'Inconnu')
        };
    }

    function cardRows(intent, data) {
        var rows = [];

        if (intent === 'cost_estimation') {
            rows.push({ label: 'Budget initial', value: money(data.budget_initial) });
            rows.push({ label: 'Dépenses réelles', value: money(data.depenses_reelles) });
            rows.push({ label: 'Progression', value: percent(data.progression) });
            rows.push({ label: 'Coût final estimé', value: money(data.cout_final_estime), strong: true });
            rows.push({ label: 'Écart', value: money(data.ecart_estime) });
            rows.push({ label: 'Méthode', value: labelFrom(METHOD_LABELS, data.methode, plainText(data.methode)) });
            rows.push({ label: 'Niveau de confiance', value: labelFrom(CONFIDENCE_LABELS, data.niveau_confiance, '—') });
        } else if (intent === 'delay_risk') {
            rows.push({ label: 'Progression actuelle', value: percent(data.progression_actuelle) });
            rows.push({ label: 'Progression temporelle attendue', value: percent(data.progression_temporelle_attendue) });
            rows.push({ label: 'Écart', value: percent(data.ecart) });
            rows.push({ label: 'Durée écoulée', value: days(data.duree_ecoule_jours) });
            rows.push({ label: 'Durée totale', value: days(data.duree_totale_jours) });
            rows.push({ label: 'Date de fin prévue', value: dateText(data.date_fin_prevue) });
            rows.push({ label: 'Date estimée', value: dateText(data.date_estimee) });
            var risk = riskBadge(data.niveau_risque);
            rows.push({ label: 'Niveau de risque', value: risk.label, badge: risk.className });
        } else if (intent === 'funding_forecast') {
            rows.push({ label: 'Budget initial', value: money(data.budget_initial) });
            rows.push({ label: 'Financements confirmés', value: money(data.financements_confirmes) });
            rows.push({ label: 'Financements en attente', value: money(data.financements_en_attente) });
            rows.push({ label: 'Besoin de financement', value: money(data.besoin_financement_estime), strong: true });
            rows.push({ label: 'Couverture', value: percent(data.pourcentage_couverture) });
        } else if (intent === 'project_overview') {
            var totals = isObject(data.totals) ? data.totals : {};
            rows.push({ label: 'Projets analysés', value: plainText(totals.count !== undefined ? String(totals.count) : null) });
            rows.push({ label: 'Budget total initial', value: money(totals.budget_initial) });
            rows.push({ label: 'Financements confirmés', value: money(totals.financements_confirmes) });
            rows.push({ label: 'Besoin de financement total', value: money(totals.besoin_financement_total), strong: true });
        }

        return rows;
    }

    function buildCard(intent, data) {
        if (!isObject(data)) { return null; }

        var rows = cardRows(intent, data);
        var projects = intent === 'project_overview' && Array.isArray(data.projects) ? data.projects : [];

        if (!rows.length && !projects.length) { return null; }

        var meta = INTENT_META[intent] || INTENT_META.general_question;

        var card = document.createElement('section');
        card.className = 'doctor-card';

        var head = document.createElement('header');
        head.className = 'doctor-card-head';
        var icon = document.createElement('span');
        icon.className = 'doctor-card-icon';
        icon.textContent = meta.icon;
        var title = document.createElement('strong');
        title.textContent = meta.label;
        head.appendChild(icon);
        head.appendChild(title);
        card.appendChild(head);

        if (rows.length) {
            var grid = document.createElement('dl');
            grid.className = 'doctor-card-grid';

            rows.forEach(function (row) {
                var item = document.createElement('div');
                item.className = 'doctor-card-item';

                var dt = document.createElement('dt');
                dt.textContent = row.label;

                var dd = document.createElement('dd');
                if (row.badge) {
                    var badge = document.createElement('span');
                    badge.className = row.badge;
                    badge.textContent = row.value;
                    dd.appendChild(badge);
                } else {
                    dd.textContent = row.value;
                    if (row.strong) { dd.className = 'doctor-card-strong'; }
                }

                item.appendChild(dt);
                item.appendChild(dd);
                grid.appendChild(item);
            });

            card.appendChild(grid);
        }

        if (projects.length) {
            var list = document.createElement('ul');
            list.className = 'doctor-list-projects';

            projects.slice(0, 10).forEach(function (project) {
                if (!isObject(project)) { return; }

                var li = document.createElement('li');

                var name = document.createElement('span');
                name.className = 'n';
                name.textContent = plainText(project.name);

                var progress = document.createElement('span');
                progress.className = 'p';
                progress.textContent = percent(project.progress);

                var risk = riskBadge(project.risque);
                var badge = document.createElement('span');
                badge.className = risk.className;
                badge.textContent = risk.label;

                li.appendChild(name);
                li.appendChild(progress);
                li.appendChild(badge);
                list.appendChild(li);
            });

            card.appendChild(list);
        }

        return card;
    }

    var NOTE_BLOCKS = [
        { key: 'hypotheses', title: 'Hypothèses' },
        { key: 'recommandations', title: 'Recommandations' },
        { key: 'actions_recommandees', title: 'Actions recommandées' },
        { key: 'causes_possibles', title: 'Causes possibles' },
        { key: 'informations_manquantes', title: 'Informations manquantes' },
        { key: 'limites', title: "Limites de l'analyse" },
        { key: 'alertes', title: 'Alertes' }
    ];

    function buildNotes(payload) {
        if (!isObject(payload)) { return null; }

        var container = document.createElement('div');
        container.className = 'doctor-notes';
        var filled = 0;

        NOTE_BLOCKS.forEach(function (block) {
            var items = payload[block.key];
            if (!Array.isArray(items) || !items.length) { return; }

            var wrapper = document.createElement('div');
            wrapper.className = 'doctor-note';

            var title = document.createElement('div');
            title.className = 'doctor-note-title';
            title.textContent = block.title;

            var list = document.createElement('ul');
            items.slice(0, 8).forEach(function (item) {
                if (typeof item !== 'string' && typeof item !== 'number') { return; }
                var li = document.createElement('li');
                li.textContent = String(item);
                list.appendChild(li);
            });

            if (!list.children.length) { return; }

            wrapper.appendChild(title);
            wrapper.appendChild(list);
            container.appendChild(wrapper);
            filled += 1;
        });

        return filled ? container : null;
    }

    function buildSources(sources) {
        if (!Array.isArray(sources) || !sources.length) { return null; }

        var labels = sources
            .filter(function (id) { return id !== null && id !== undefined; })
            .map(function (id) { return '#' + id; });

        if (!labels.length) { return null; }

        var el = document.createElement('div');
        el.className = 'doctor-sources';
        el.textContent = 'Sources : ' + labels.join(', ');
        return el;
    }

    /* ------------------------------------------------------------------ */
    /* Affichage des messages                                               */
    /* ------------------------------------------------------------------ */

    function scrollToBottom() {
        window.requestAnimationFrame(function () {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
    }

    function copyText(value, button) {
        var previous = button.textContent;
        var done = function () {
            button.textContent = 'Copié ✓';
            window.setTimeout(function () { button.textContent = previous; }, 1800);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(value)).then(done, function () {
                fallbackCopy(value, done);
            });
        } else {
            fallbackCopy(value, done);
        }
    }

    function fallbackCopy(value, done) {
        var area = document.createElement('textarea');
        area.value = String(value);
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); done(); } catch (error) { /* copie indisponible */ }
        document.body.removeChild(area);
    }

    function appendMessage(role, options) {
        emptyEl.style.display = 'none';

        var div = document.createElement('div');
        div.className = 'doctor-msg ' + (role === 'user' ? 'user' : 'bot');

        var bubble = document.createElement('div');
        bubble.className = 'doctor-bubble';

        if (role === 'user') {
            bubble.textContent = options.text;
        } else {
            var markdown = document.createElement('div');
            markdown.className = 'doctor-md';
            markdown.innerHTML = renderMarkdown(options.text);
            bubble.appendChild(markdown);

            var card = buildCard(options.intent, options.data);
            if (card) { bubble.appendChild(card); }

            var notes = buildNotes(options.data ? (options.data.ia || options.data) : null);
            if (notes) { bubble.appendChild(notes); }

            var sources = buildSources(options.sources);
            if (sources) { bubble.appendChild(sources); }
        }

        div.appendChild(bubble);

        var meta = document.createElement('div');
        meta.className = 'doctor-meta';

        var stamp = document.createElement('span');
        stamp.textContent = formatTime(new Date());
        meta.appendChild(stamp);

        if (options.meta) {
            var label = document.createElement('span');
            label.textContent = options.meta;
            meta.appendChild(label);
        }

        if (role === 'bot') {
            var copy = document.createElement('button');
            copy.type = 'button';
            copy.className = 'doctor-copy';
            copy.textContent = 'Copier';
            copy.addEventListener('click', function () { copyText(options.text, copy); });
            meta.appendChild(copy);
        }

        div.appendChild(meta);
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    /* ------------------------------------------------------------------ */
    /* Ouverture / fermeture                                               */
    /* ------------------------------------------------------------------ */

    function setOpen(value) {
        open = value;
        wrap.classList.toggle('open', open);
        toggle.style.display = open ? 'none' : 'grid';
        if (open) { scrollToBottom(); }
    }

    toggle.addEventListener('click', function () { setOpen(true); });
    closeBtn.addEventListener('click', function () { setOpen(false); });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && open) { setOpen(false); }
    });

    function showError(text) {
        errorEl.style.display = 'flex';
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

    inputEl.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !busy) {
            event.preventDefault();
            send();
        }
    });

    sendBtn.addEventListener('click', function () { send(); });

    /* ------------------------------------------------------------------ */
    /* Envoi                                                               */
    /* ------------------------------------------------------------------ */

    var LOADING_LABELS = {
        cost_estimation: 'Analyse du coût en cours...',
        delay_risk: 'Évaluation du risque de retard...',
        funding_forecast: 'Prévision du besoin de financement...',
        project_overview: "Construction de la vue d'ensemble...",
        general_question: 'Analyse de votre question...'
    };

    function setLoadingText(intent) {
        if (!loadingTextEl) { return; }
        loadingTextEl.textContent = labelFrom(LOADING_LABELS, intent, LOADING_LABELS.general_question);
    }

    function send(intent, presetMessage) {
        if (busy) { return; }

        clearError();

        var message = presetMessage || inputEl.value.trim();
        if (!message) { return; }

        inputEl.value = '';
        inputEl.style.height = 'auto';

        var requestedIntent = intent || 'general_question';

        appendMessage('user', { text: message, meta: 'Vous' });

        setBusy(true);
        setLoadingText(requestedIntent);

        var csrfToken = document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            : '';

        fetch(route, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                intent: requestedIntent,
                message: message,
                project_id: focusProject || null
            })
        }).then(function (res) {
            return res.json().then(function (body) {
                return { ok: res.ok, status: res.status, body: body };
            });
        }).then(function (result) {
            var body = result.body || {};

            // Debug logging
            console.log('Project Doctor response:', {
                status: result.status,
                ok: result.ok,
                body: body
            });

            if (body.success && body.answer) {
                appendMessage('bot', {
                    text: body.answer,
                    intent: body.intent || requestedIntent,
                    data: isObject(body.data) ? body.data : {},
                    sources: body.sources,
                    meta: body.disclaimer ? 'Project Doctor · ' + body.disclaimer : 'Project Doctor'
                });
            } else if (body.answer) {
                appendMessage('bot', {
                    text: body.answer,
                    intent: requestedIntent,
                    data: {},
                    meta: 'Erreur (' + (body.code || result.status) + ')'
                });
            } else {
                console.error('Invalid response structure:', body);
                showError(result.status === 429 ? 'Quota d\'appels atteint. Réessayez plus tard.'
                    : result.status === 403 ? 'Accès interdit à ce projet.'
                    : 'Réponse invalide du service. (status: ' + result.status + ', success: ' + body.success + ', answer: ' + !!body.answer + ')');
            }
        }).catch(function (error) {
            console.error('Project Doctor fetch error:', error);
            showError('Impossible de joindre le service. Vérifiez votre connexion.');
        }).finally(function () { setBusy(false); });
    }

    document.querySelectorAll('.doctor-sugg').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var span = btn.querySelector('span');
            send(btn.dataset.intent, span ? span.textContent.trim() : '');
        });
    });

    /* ------------------------------------------------------------------ */
    /* Disponibilité du service                                            */
    /* ------------------------------------------------------------------ */

    fetch(statusRoute, { headers: { 'Accept': 'application/json' } }).then(function (res) {
        return res.json();
    }).then(function (data) {
        var avail = data && data.available;
        statusEl.textContent = avail ? '● Service IA disponible' : '● Service non configuré';
        statusEl.className = 'doctor-status ' + (avail ? '' : 'off');
        if (!avail) {
            appendMessage('bot', {
                text: 'Project Doctor est disponible en mode analyse déterministe. La clé Gemini n\'est pas configurée sur le serveur ; les estimations s\'appuieront sur les calculs de base sans interprétation IA.',
                intent: 'general_question',
                data: {},
                meta: 'Indication'
            });
        }
    }).catch(function () {
        statusEl.textContent = '● Service indisponible';
        statusEl.className = 'doctor-status off';
    });
})();
</script>
@endpush
