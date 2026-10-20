{{-- Panneau d'analyse IA de la zone (back-office) — ajouté sur la page de détail. --}}
@php($aiRiskBadge = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'])
<section class="panel zone-ai-panel" id="zone-ai-panel" style="margin-bottom:20px; border-left:5px solid var(--aqua);">
    <div class="panel-heading" style="align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <span class="eyebrow" style="color:var(--aqua);">INTELLIGENCE ARTIFICIELLE</span>
            <h2>Analyse IA de la zone</h2>
            <small style="color:var(--muted);" id="zone-ai-timestamp">
                @if($zone->ai_analyzed_at)
                    Dernière analyse le {{ $zone->ai_analyzed_at->format('d/m/Y à H:i') }}
                @else
                    Aucune analyse pour le moment.
                @endif
            </small>
        </div>
        <button type="button" class="button button-ai-scan" data-zone-ai-btn onclick="analyzeZoneAi()">
            <span id="zone-ai-btn-text">⚡ Analyser avec l'IA</span>
        </button>
    </div>

    {{-- État de chargement --}}
    <div id="zone-ai-loading" class="panel" style="display:none; background:linear-gradient(135deg, #073f4a 0%, #0d5f6e 100%); color:#fff; padding:20px; margin-bottom:15px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div class="ai-radar-spinner">⚡</div>
            <div>
                <h3 style="margin:0 0 4px; color:#fff; font-size:16px;">Analyse IA de la zone en cours…</h3>
                <p style="margin:0; color:#bad1d0; font-size:13px;">Calcul du score de risque, synthèse de la situation et formulation des recommandations de prévention.</p>
            </div>
        </div>
    </div>

    {{-- Erreur éventuelle (repli élégant, jamais d'exception brute) --}}
    <div id="zone-ai-error" class="flash-message flash-error" role="alert" style="display:none; margin-bottom:15px;"></div>

    {{-- Avertissement de repli (IA indisponible -> analyse locale) --}}
    <div id="zone-ai-notice" class="flash-message flash-warning" role="status" style="display:none; margin-bottom:15px;"></div>

    {{-- Résultats --}}
    <div id="zone-ai-body">
        @if($zone->ai_analyzed_at)
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:16px;">
                <div style="text-align:center; background:var(--paper); border:1px solid var(--line); border-radius:6px; padding:14px;">
                    <span style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted);">Score de risque IA</span>
                    <div style="font-size:38px; font-weight:800; letter-spacing:-0.04em; color:{{ ($zone->ai_risk_score ?? 0) >= 75 ? 'var(--coral)' : ((($zone->ai_risk_score ?? 0) >= 50) ? '#aa721c' : 'var(--aqua)') }};">
                        {{ $zone->ai_risk_score ?? '—' }}<span style="font-size:16px;">/100</span>
                    </div>
                    <x-ui.badge :variant="$aiRiskBadge[$zone->ai_risk_level ?? ''] ?? 'info'">Risque IA : {{ $zone->ai_risk_level_label }}</x-ui.badge>
                </div>
                <div style="grid-column:1 / -1; background:var(--paper); border:1px solid var(--line); border-radius:6px; padding:14px;">
                    <strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:6px;">Synthèse automatique</strong>
                    <p style="margin:0; font-size:14px; line-height:1.55; color:var(--ink);" id="zone-ai-summary">{{ $zone->ai_summary }}</p>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">
                    <strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Recommandations de prévention</strong>
                    <ol id="zone-ai-recommendations" style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:var(--ink);">
                        @foreach($zone->ai_recommendations ?? [] as $recommendation)
                            <li style="margin-bottom:8px;">
                                <strong>{{ is_array($recommendation) ? ($recommendation['action'] ?? '') : $recommendation }}</strong>
                                @if(is_array($recommendation) && !empty($recommendation['reason']))
                                    <div style="font-size:11px; color:var(--muted);">{{ $recommendation['reason'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">
                    <strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Points d'attention</strong>
                    <ul id="zone-ai-highlights" style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:var(--ink);"></ul>
                </div>
            </div>
        @else
            <p id="zone-ai-empty" style="margin:0; font-size:13px; color:var(--muted);">
                Aucune analyse IA enregistrée. Cliquez sur « ⚡ Analyser avec l'IA » pour calculer le score de risque,
                la synthèse de la situation et les recommandations de prévention de cette zone.
            </p>
        @endif
    </div>
</section>

{{-- Notification toast --}}
<div id="zone-ai-toast" class="intelligence-toast" style="display:none;">
    <span id="zone-ai-toast-icon">✓</span>
    <span id="zone-ai-toast-message">Notification</span>
</div>

@push('styles')
<style>
.zone-ai-panel .ai-radar-spinner {
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(22, 183, 163, 0.2); border: 2px solid var(--aqua);
    color: var(--aqua); display: grid; place-items: center; font-size: 20px;
    animation: zoneAiPulseRadar 1.5s infinite;
}
@keyframes zoneAiPulseRadar {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 183, 163, 0.4); }
    50% { transform: scale(1.1); box-shadow: 0 0 15px 4px rgba(22, 183, 163, 0.6); }
}
.intelligence-toast {
    position: fixed; bottom: 24px; right: 24px;
    background: var(--deep); color: #fff; padding: 14px 22px; border-radius: 6px;
    display: flex; align-items: center; gap: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    z-index: 10000; font-size: 14px; font-weight: 600;
    animation: zoneAiSlideUp 0.3s ease;
}
@keyframes zoneAiSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>
@endpush

@push('scripts')
<script>
function zoneAiEscape(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}

function zoneAiToast(message, success = true) {
    const toast = document.getElementById('zone-ai-toast');
    document.getElementById('zone-ai-toast-icon').textContent = success ? '✓' : '⚠';
    document.getElementById('zone-ai-toast-message').textContent = message;
    toast.style.background = success ? 'var(--deep)' : '#a8473d';
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 4500);
}

function zoneAiBadgeClass(priority) {
    return priority === 'high' ? 'badge badge-danger' : (priority === 'low' ? 'badge badge-success' : 'badge badge-warning');
}

function zoneAiRiskBadgeVariant(level) {
    const variants = { low: 'success', medium: 'info', high: 'warning', critical: 'danger' };
    return variants[level] || 'info';
}

function zoneAiRender(a) {
    const score = Number(a.risk_score ?? 0);
    const color = score >= 75 ? 'var(--coral)' : (score >= 50 ? '#aa721c' : 'var(--aqua)');

    const recommendations = (a.recommendations || []).map(function (r) {
        const isObject = r !== null && typeof r === 'object';
        const action = isObject ? (r.action || '') : String(r || '');
        const reason = isObject ? (r.reason || '') : '';
        const priority = isObject ? (r.priority || 'medium') : 'medium';
        return '<li style="margin-bottom:8px;">'
            + '<strong>' + zoneAiEscape(action) + '</strong> '
            + '<span class="' + zoneAiBadgeClass(priority) + '" style="font-size:10px;">' + zoneAiEscape(priority) + '</span>'
            + (reason ? '<div style="font-size:11px; color:var(--muted);">' + zoneAiEscape(reason) + '</div>' : '')
            + '</li>';
    }).join('');

    const highlights = (a.highlights || []).map(function (h) {
        return '<li style="margin-bottom:6px;">' + zoneAiEscape(h) + '</li>';
    }).join('');

    return ''
        + '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:16px;">'
        +   '<div style="text-align:center; background:var(--paper); border:1px solid var(--line); border-radius:6px; padding:14px;">'
        +     '<span style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted);">Score de risque IA</span>'
        +     '<div style="font-size:38px; font-weight:800; letter-spacing:-0.04em; color:' + color + ';">' + score + '<span style="font-size:16px;">/100</span></div>'
        +     '<span class="badge badge-' + zoneAiRiskBadgeVariant(a.risk_level) + '" style="font-size:11px;">Risque IA : ' + zoneAiEscape(a.risk_level_label || a.risk_level || '—') + '</span>'
        +   '</div>'
        +   '<div style="grid-column:1 / -1; background:var(--paper); border:1px solid var(--line); border-radius:6px; padding:14px;">'
        +     '<strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:6px;">Synthèse automatique</strong>'
        +     '<p style="margin:0; font-size:14px; line-height:1.55; color:var(--ink);">' + zoneAiEscape(a.summary || '') + '</p>'
        +   '</div>'
        + '</div>'
        + '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">'
        +   '<div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">'
        +     '<strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Recommandations de prévention</strong>'
        +     '<ol style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:var(--ink);">' + recommendations + '</ol>'
        +   '</div>'
        +   '<div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">'
        +     '<strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Points d\'attention</strong>'
        +     '<ul style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:var(--ink);">' + highlights + '</ul>'
        +   '</div>'
        + '</div>';
}

function analyzeZoneAi() {
    const buttons = document.querySelectorAll('[data-zone-ai-btn]');
    const loading = document.getElementById('zone-ai-loading');
    const errorBox = document.getElementById('zone-ai-error');
    const noticeBox = document.getElementById('zone-ai-notice');

    buttons.forEach(function (b) { b.disabled = true; });
    errorBox.style.display = 'none';
    loading.style.display = 'block';

    fetch('{{ route('back.zones.ai-analyze', $zone) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ force: true })
    })
    .then(function (response) {
        return response.json().then(function (data) {
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erreur lors de l\'analyse IA.');
            }
            return data;
        });
    })
    .then(function (data) {
        if (data.queued) {
            zoneAiToast(data.message, true);
            return;
        }

        const analysis = data.analysis;
        if (!analysis) return;

        document.getElementById('zone-ai-body').innerHTML = zoneAiRender(analysis);
        document.getElementById('zone-ai-timestamp').textContent = 'Dernière analyse le ' + (analysis.analyzed_at_display || 'à l\'instant');

        if (analysis.notice) {
            noticeBox.textContent = analysis.notice;
            noticeBox.style.display = 'block';
        } else {
            noticeBox.style.display = 'none';
        }

        zoneAiToast(data.message || 'Analyse IA réalisée avec succès.', true);
    })
    .catch(function (error) {
        errorBox.textContent = error.message || 'Erreur technique lors de l\'analyse IA.';
        errorBox.style.display = 'block';
        zoneAiToast(error.message || 'Erreur technique lors de l\'analyse IA.', false);
    })
    .finally(function () {
        loading.style.display = 'none';
        buttons.forEach(function (b) { b.disabled = false; });
    });
}
</script>
@endpush
