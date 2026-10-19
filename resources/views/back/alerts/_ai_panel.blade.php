{{-- Panneau d'analyse IA de l'alerte (back-office) — ajouté sur la page de détail. --}}
@php
    $aiSeverityBadge = ['info' => 'info', 'warning' => 'warning', 'critical' => 'danger'];
    $aiDuplicates = $alert->ai_analyzed_at
        ? app(\App\Services\AI\AlertAiService::class)->findDuplicates($alert)
        : [];
@endphp
<section class="panel alert-ai-panel" id="alert-ai-panel" style="margin-bottom:20px; border-left:5px solid var(--aqua);">
    <div class="panel-heading" style="align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <span class="eyebrow" style="color:var(--aqua);">INTELLIGENCE ARTIFICIELLE</span>
            <h2>Analyse IA de l'alerte</h2>
            <small style="color:var(--muted);" id="alert-ai-timestamp">
                @if($alert->ai_analyzed_at)
                    Dernière analyse le {{ $alert->ai_analyzed_at->format('d/m/Y à H:i') }}
                @else
                    Aucune analyse pour le moment.
                @endif
            </small>
        </div>
        <button type="button" class="button button-ai-scan" data-alert-ai-btn onclick="analyzeAlertAi()">
            <span id="alert-ai-btn-text">⚡ Analyser avec l'IA</span>
        </button>
    </div>

    {{-- État de chargement --}}
    <div id="alert-ai-loading" class="panel" style="display:none; background:linear-gradient(135deg, #073f4a 0%, #0d5f6e 100%); color:#fff; padding:20px; margin-bottom:15px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div class="ai-radar-spinner">⚡</div>
            <div>
                <h3 style="margin:0 0 4px; color:#fff; font-size:16px;">Analyse IA de l'alerte en cours…</h3>
                <p style="margin:0; color:#bad1d0; font-size:13px;">Classification de la gravité, explication, cause probable, actions recommandées et recherche de doublons.</p>
            </div>
        </div>
    </div>

    <div id="alert-ai-error" class="flash-message flash-error" role="alert" style="display:none; margin-bottom:15px;"></div>
    <div id="alert-ai-notice" class="flash-message flash-warning" role="status" style="display:none; margin-bottom:15px;"></div>

    {{-- Résultats --}}
    <div id="alert-ai-body">
        @if($alert->ai_analyzed_at)
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:14px;">
                <x-ui.badge :variant="$aiSeverityBadge[$alert->ai_severity] ?? 'info'">Gravité IA : {{ $alert->ai_severity_label }}</x-ui.badge>
                <span style="font-size:12px; color:var(--muted);">Groupe : <code>{{ $alert->ai_group_key }}</code></span>
            </div>

            <div style="background:var(--paper); border:1px solid var(--line); border-radius:6px; padding:14px; margin-bottom:14px;">
                <strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:6px;">Explication</strong>
                <p style="margin:0; font-size:14px; line-height:1.55; color:var(--ink);" id="alert-ai-explanation">{{ $alert->ai_explanation }}</p>
            </div>

            <div style="background:#fff8eb; border:1px solid #fde68a; border-radius:6px; padding:12px 16px; margin-bottom:14px;">
                <strong style="display:block; font-size:12px; color:#92400e; text-transform:uppercase; margin-bottom:4px;">Cause probable</strong>
                <p style="margin:0; font-size:13px; line-height:1.5; color:#92400e;" id="alert-ai-cause">{{ $alert->ai_probable_cause }}</p>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">
                    <strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Actions recommandées pour le technicien</strong>
                    <ol id="alert-ai-actions" style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:var(--ink);">
                        @foreach($alert->ai_recommended_actions ?? [] as $action)
                            <li style="margin-bottom:8px;">
                                <strong>{{ is_array($action) ? ($action['action'] ?? '') : $action }}</strong>
                                @if(is_array($action) && !empty($action['reason']))
                                    <div style="font-size:11px; color:var(--muted);">{{ $action['reason'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">
                    <strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Doublons détectés dans la zone</strong>
                    <div id="alert-ai-duplicates">
                        @if(count($aiDuplicates) === 0)
                            <p style="margin:0; font-size:13px; color:var(--muted);">Aucune alerte similaire détectée sur les 14 derniers jours.</p>
                        @else
                            <p style="margin:0 0 8px; font-size:13px; color:var(--ink);"><strong>{{ count($aiDuplicates) }}</strong> alerte(s) similaire(s) regroupée(s) :</p>
                            <ul style="margin:0; padding-left:18px; font-size:12px; line-height:1.5; color:var(--muted);">
                                @foreach($aiDuplicates as $duplicate)
                                    <li style="margin-bottom:4px;">
                                        <a href="{{ route('back.alerts.show', $duplicate['id']) }}">#{{ $duplicate['id'] }} · {{ $duplicate['type'] }}</a>
                                        — {{ $duplicate['message'] }} ({{ $duplicate['created_at_display'] }})
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <p id="alert-ai-empty" style="margin:0; font-size:13px; color:var(--muted);">
                Aucune analyse IA enregistrée. Cliquez sur « ⚡ Analyser avec l'IA » pour classifier la gravité,
                expliquer l'alerte, identifier la cause probable et recevoir les actions recommandées.
            </p>
        @endif
    </div>
</section>

{{-- Notification toast --}}
<div id="alert-ai-toast" class="intelligence-toast" style="display:none;">
    <span id="alert-ai-toast-icon">✓</span>
    <span id="alert-ai-toast-message">Notification</span>
</div>

@push('styles')
<style>
.alert-ai-panel .ai-radar-spinner {
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(22, 183, 163, 0.2); border: 2px solid var(--aqua);
    color: var(--aqua); display: grid; place-items: center; font-size: 20px;
    animation: alertAiPulseRadar 1.5s infinite;
}
@keyframes alertAiPulseRadar {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 183, 163, 0.4); }
    50% { transform: scale(1.1); box-shadow: 0 0 15px 4px rgba(22, 183, 163, 0.6); }
}
</style>
@endpush

@push('scripts')
<script>
function alertAiEscape(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}

function alertAiToast(message, success = true) {
    const toast = document.getElementById('alert-ai-toast');
    document.getElementById('alert-ai-toast-icon').textContent = success ? '✓' : '⚠';
    document.getElementById('alert-ai-toast-message').textContent = message;
    toast.style.background = success ? 'var(--deep)' : '#a8473d';
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 4500);
}

function alertAiBadgeClass(severity) {
    return severity === 'critical' ? 'badge badge-danger' : (severity === 'warning' ? 'badge badge-warning' : 'badge badge-info');
}

function alertAiActionPriorityClass(priority) {
    return priority === 'high' ? 'badge badge-danger' : (priority === 'low' ? 'badge badge-success' : 'badge badge-warning');
}

function alertAiRender(a) {
    const actions = (a.recommended_actions || []).map(function (item) {
        const isObject = item !== null && typeof item === 'object';
        const action = isObject ? (item.action || '') : String(item || '');
        const reason = isObject ? (item.reason || '') : '';
        const priority = isObject ? (item.priority || 'medium') : 'medium';
        return '<li style="margin-bottom:8px;">'
            + '<strong>' + alertAiEscape(action) + '</strong> '
            + '<span class="' + alertAiActionPriorityClass(priority) + '" style="font-size:10px;">' + alertAiEscape(priority) + '</span>'
            + (reason ? '<div style="font-size:11px; color:var(--muted);">' + alertAiEscape(reason) + '</div>' : '')
            + '</li>';
    }).join('');

    let duplicatesHtml;
    if ((a.duplicate_count || 0) > 0) {
        const items = (a.duplicates || []).map(function (d) {
            return '<li style="margin-bottom:4px;"><a href="{{ route('back.alerts.index') }}?q=' + encodeURIComponent(d.type) + '">#'
                + alertAiEscape(d.id) + ' · ' + alertAiEscape(d.type) + '</a> — '
                + alertAiEscape(d.message) + ' (' + alertAiEscape(d.created_at_display) + ')</li>';
        }).join('');
        duplicatesHtml = '<p style="margin:0 0 8px; font-size:13px; color:var(--ink);"><strong>'
            + alertAiEscape(a.duplicate_count) + '</strong> alerte(s) similaire(s) regroupée(s) :</p>'
            + '<ul style="margin:0; padding-left:18px; font-size:12px; line-height:1.5; color:var(--muted);">' + items + '</ul>';
    } else {
        duplicatesHtml = '<p style="margin:0; font-size:13px; color:var(--muted);">Aucune alerte similaire détectée sur les 14 derniers jours.</p>';
    }

    return ''
        + '<div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:14px;">'
        +   '<span class="' + alertAiBadgeClass(a.severity || 'info') + '" style="font-size:11px;">Gravité IA : ' + alertAiEscape(a.severity_label || a.severity || '—') + '</span>'
        +   '<span style="font-size:12px; color:var(--muted);">Groupe : <code>' + alertAiEscape(a.group_key || '—') + '</code></span>'
        + '</div>'
        + '<div style="background:var(--paper); border:1px solid var(--line); border-radius:6px; padding:14px; margin-bottom:14px;">'
        +   '<strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:6px;">Explication</strong>'
        +   '<p style="margin:0; font-size:14px; line-height:1.55; color:var(--ink);">' + alertAiEscape(a.explanation || '') + '</p>'
        + '</div>'
        + '<div style="background:#fff8eb; border:1px solid #fde68a; border-radius:6px; padding:12px 16px; margin-bottom:14px;">'
        +   '<strong style="display:block; font-size:12px; color:#92400e; text-transform:uppercase; margin-bottom:4px;">Cause probable</strong>'
        +   '<p style="margin:0; font-size:13px; line-height:1.5; color:#92400e;">' + alertAiEscape(a.probable_cause || '') + '</p>'
        + '</div>'
        + '<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">'
        +   '<div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">'
        +     '<strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Actions recommandées pour le technicien</strong>'
        +     '<ol style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:var(--ink);">' + actions + '</ol>'
        +   '</div>'
        +   '<div style="border:1px solid var(--line); border-radius:6px; padding:14px; background:#fff;">'
        +     '<strong style="display:block; font-size:12px; color:var(--deep); text-transform:uppercase; margin-bottom:10px;">Doublons détectés dans la zone</strong>'
        +     duplicatesHtml
        +   '</div>'
        + '</div>';
}

function analyzeAlertAi() {
    const buttons = document.querySelectorAll('[data-alert-ai-btn]');
    const loading = document.getElementById('alert-ai-loading');
    const errorBox = document.getElementById('alert-ai-error');
    const noticeBox = document.getElementById('alert-ai-notice');

    buttons.forEach(function (b) { b.disabled = true; });
    errorBox.style.display = 'none';
    loading.style.display = 'block';

    fetch('{{ route('back.alerts.ai-analyze', $alert) }}', {
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
            alertAiToast(data.message, true);
            return;
        }

        const analysis = data.analysis;
        if (!analysis) return;

        document.getElementById('alert-ai-body').innerHTML = alertAiRender(analysis);
        document.getElementById('alert-ai-timestamp').textContent = 'Dernière analyse le ' + (analysis.analyzed_at_display || 'à l\'instant');

        if (analysis.notice) {
            noticeBox.textContent = analysis.notice;
            noticeBox.style.display = 'block';
        } else {
            noticeBox.style.display = 'none';
        }

        alertAiToast(data.message || 'Analyse IA réalisée avec succès.', true);
    })
    .catch(function (error) {
        errorBox.textContent = error.message || 'Erreur technique lors de l\'analyse IA.';
        errorBox.style.display = 'block';
        alertAiToast(error.message || 'Erreur technique lors de l\'analyse IA.', false);
    })
    .finally(function () {
        loading.style.display = 'none';
        buttons.forEach(function (b) { b.disabled = false; });
    });
}
</script>
@endpush
