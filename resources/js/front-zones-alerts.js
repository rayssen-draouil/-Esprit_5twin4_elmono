const STORAGE_PREFIX = 'aquasecure:';
const followedZones = new Set();

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function readFollowedZones() {
    const raw = document.body?.dataset.followedZones;
    if (!raw) return [];
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (error) {
        return [];
    }
}

function persistFollowedZones() {
    if (document.body) {
        document.body.dataset.followedZones = JSON.stringify([...followedZones]);
    }
    document.dispatchEvent(new CustomEvent('aquasecure:favorites'));
}

function goToLogin() {
    const login = document.body?.dataset.loginUrl || '/login';
    const redirect = window.location.pathname + window.location.search;
    window.location.href = login + '?redirect=' + encodeURIComponent(redirect);
}

function initSwitches() {
    document.querySelectorAll('[data-aq-switch]').forEach((group) => {
        const storageKey = group.dataset.aqSwitchKey;
        const fallback = group.dataset.aqDefault || 'cards';
        let current = fallback;
        try {
            current = localStorage.getItem(storageKey) || fallback;
        } catch (error) {
            current = fallback;
        }

        const buttons = Array.from(group.querySelectorAll('[data-aq-layout]'));
        const panels = Array.from(document.querySelectorAll('[data-aq-layout-panel]'));

        const apply = (layout) => {
            current = layout;
            try {
                localStorage.setItem(storageKey, layout);
            } catch (error) {
                /* stockage indisponible */
            }
            buttons.forEach((button) => {
                button.setAttribute('aria-pressed', String(button.dataset.aqLayout === layout));
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.aqLayoutPanel !== layout;
            });
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => apply(button.dataset.aqLayout));
        });

        if (!buttons.some((button) => button.dataset.aqLayout === current)) {
            current = fallback;
        }
        apply(current);
    });
}

function initLive() {
    const box = document.getElementById('aq-live');
    if (!box) return;

    const baseUrl = box.dataset.liveUrl || window.location.href;
    const interval = Number(box.dataset.liveInterval || 45000);
    const stamp = document.querySelector('[data-live-stamp]');
    const label = document.querySelector('[data-live-label]');
    const refreshButton = document.querySelector('[data-live-refresh]');
    const sinceInit = Math.floor(Date.now() / 1000);

    const kpiValue = (name) => document.querySelector('[data-kpi="' + name + '"] [data-stat-value]');

    const formatTime = () => new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

    const tick = async () => {
        if (document.hidden) return;
        try {
            const separator = baseUrl.includes('?') ? '&' : '?';
            const response = await fetch(baseUrl + separator + 'live=1&since=' + sinceInit, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (response.status === 429) {
                if (stamp) stamp.textContent = 'Trop de requêtes · nouvelle tentative dans 1 min';
                return;
            }
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();

            Object.entries(data.kpis || {}).forEach(([name, value]) => {
                const target = kpiValue(name);
                if (!target) return;
                const valueElement = target.querySelector('[data-count-number]') || target;
                if (String(valueElement.textContent).trim() === String(value)) return;
                const card = target.closest('.aq-stat');
                if (card) card.classList.add('is-refreshing');
                window.setTimeout(() => {
                    valueElement.textContent = String(value);
                    if (card) card.classList.remove('is-refreshing');
                }, 160);
            });

            const newCount = Number(data.new_count || 0);
            if (newCount > 0) {
                box.hidden = false;
                if (label) label.textContent = newCount + ' nouvelle(s) alerte(s) depuis votre visite';
                if (refreshButton) refreshButton.hidden = false;
            }
            if (stamp) stamp.textContent = 'Dernière mise à jour ' + formatTime();
        } catch (error) {
            if (stamp) stamp.textContent = 'Actualisation en pause — vérifiez votre connexion';
        }
    };

    if (refreshButton) {
        refreshButton.addEventListener('click', () => window.location.reload());
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) tick();
    });

    window.setInterval(tick, interval);
    tick();
}

async function initChart() {
    const canvas = document.getElementById('aq-front-chart');
    const dataElement = document.getElementById('aq-chart-data');
    if (!canvas || !dataElement) return;

    const skeleton = document.querySelector('[data-chart-skeleton]');
    let payload;
    try {
        payload = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        if (skeleton) skeleton.remove();
        return;
    }

    const labels = (payload.labels || []).map((iso) => {
        const date = new Date(iso + 'T00:00:00');
        return Number.isNaN(date.getTime())
            ? iso
            : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    });
    const counts = payload.counts || [];

    try {
        const chartjs = await import('chart.js');
        chartjs.Chart.register(...chartjs.registerables);
        new chartjs.Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Alertes',
                    data: counts,
                    backgroundColor: 'rgba(22, 183, 163, 0.75)',
                    hoverBackgroundColor: '#16b7a3',
                    borderRadius: 4,
                    maxBarThickness: 18,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => ' ' + context.parsed.y + ' alerte(s)',
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        ticks: { precision: 0, font: { size: 10 } },
                    },
                },
            },
        });
        if (skeleton) skeleton.remove();
    } catch (error) {
        if (skeleton) {
            skeleton.classList.remove('aq-skeleton');
            skeleton.textContent = 'Graphique indisponible pour le moment.';
        }
    }
}

function initAutoSearch() {
    document.querySelectorAll('[data-auto-search]').forEach((form) => {
        const input = form.querySelector('input[type="search"]');
        if (!input) return;

        let timer = null;
        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }, 500);
        });
    });
}

function initCountUp() {
    const nodes = document.querySelectorAll('[data-countup]');
    if (!nodes.length) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const run = (node) => {
        const target = Number(node.dataset.countup);
        if (!Number.isFinite(target)) return;

        const valueElement = node.querySelector('[data-count-number]') || node;
        if (reduceMotion) {
            valueElement.textContent = target.toLocaleString('fr-FR');
            return;
        }

        const duration = 700;
        const start = performance.now();
        const step = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            valueElement.textContent = Math.round(target * eased).toLocaleString('fr-FR');
            if (progress < 1) window.requestAnimationFrame(step);
        };
        window.requestAnimationFrame(step);
    };

    if (!('IntersectionObserver' in window)) {
        nodes.forEach(run);
        return;
    }

    const observer = new IntersectionObserver((entries, instance) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            run(entry.target);
            instance.unobserve(entry.target);
        });
    }, { threshold: 0.4 });

    nodes.forEach((node) => observer.observe(node));
}

function paintFavorite(button) {
    const id = String(button.dataset.favoriteZone);
    const active = followedZones.has(id);
    const isLarge = button.classList.contains('button') || button.classList.contains('aq-follow');
    const label = active
        ? (isLarge ? 'Zone suivie' : 'Suivi')
        : (isLarge ? 'Suivre cette zone' : 'Suivre');

    button.setAttribute('aria-pressed', String(active));
    button.classList.toggle('is-followed', active);
    button.setAttribute('aria-label', active ? 'Ne plus suivre cette zone' : 'Suivre cette zone');

    const icon = button.querySelector('[data-favorite-icon]');
    if (icon) icon.textContent = active ? '★' : '☆';

    const text = button.querySelector('[data-favorite-text]');
    if (text) text.textContent = label;

    button.closest('.zone-card-front')?.classList.toggle('is-followed', active);
}

function initFavorites() {
    readFollowedZones().forEach((id) => followedZones.add(id));

    const buttons = document.querySelectorAll('[data-favorite-zone]');
    if (!buttons.length) return;

    buttons.forEach((button) => paintFavorite(button));

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (document.body.dataset.authenticated !== '1') {
                goToLogin();
                return;
            }

            const id = String(button.dataset.favoriteZone);
            const active = followedZones.has(id);
            const base = (document.body.dataset.subscribeUrl || '/mes-zones').replace(/\/+$/, '');

            button.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(base + '/' + encodeURIComponent(id), {
                    method: active ? 'DELETE' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    credentials: 'same-origin',
                });

                if (response.status === 401 || response.status === 419) {
                    goToLogin();
                    return;
                }

                if (!response.ok) throw new Error('HTTP ' + response.status);

                if (active) followedZones.delete(id);
                else followedZones.add(id);

                persistFollowedZones();
                document.querySelectorAll('[data-favorite-zone="' + id + '"]').forEach(paintFavorite);
            } catch (error) {
                /* l'état est conservé si le réseau échoue */
            } finally {
                button.removeAttribute('aria-busy');
            }
        });
    });
}

function initFollowedFilter() {
    const toggle = document.querySelector('[data-followed-filter]');
    if (!toggle) return;

    const cards = Array.from(document.querySelectorAll('[data-alert-feed] [data-zone-id]'));
    const empty = document.querySelector('[data-followed-empty]');
    let active = false;

    const apply = () => {
        toggle.setAttribute('aria-pressed', String(active));
        toggle.classList.toggle('is-active', active);

        let visible = 0;
        cards.forEach((card) => {
            const matches = !active || followedZones.has(String(card.dataset.zoneId));
            card.hidden = !matches;
            if (matches) visible += 1;
        });

        if (empty) empty.hidden = !(active && visible === 0);
    };

    toggle.addEventListener('click', () => {
        if (document.body.dataset.authenticated !== '1') {
            goToLogin();
            return;
        }
        active = !active;
        apply();
    });

    document.addEventListener('aquasecure:favorites', () => {
        if (active) apply();
    });

    apply();
}

function renderNearest(result, zone) {
    result.hidden = false;
    result.textContent = '';
    result.dataset.tone = 'success';

    const name = document.createElement('span');
    name.className = 'aq-nearest__zone';
    name.textContent = zone.name;

    const distance = Number(zone.distance_km);
    const distanceLabel = Number.isFinite(distance)
        ? (distance < 1
            ? Math.max(50, Math.round(distance * 1000)) + ' m'
            : distance.toFixed(1).replace('.', ',') + ' km')
        : '';

    const meta = document.createElement('span');
    meta.className = 'aq-nearest__meta';
    meta.textContent = [distanceLabel, zone.risk_label, (zone.alerts || 0) + ' alerte(s) active(s)']
        .filter(Boolean)
        .join(' · ');

    const link = document.createElement('a');
    link.className = 'text-link';
    link.href = zone.url;
    link.textContent = 'Voir la zone →';

    result.append(name, meta, link);
}

function initNearest() {
    document.querySelectorAll('[data-nearest-zone]').forEach((box) => {
        const trigger = box.querySelector('[data-nearest-trigger]');
        const result = box.querySelector('[data-nearest-result]');
        if (!trigger || !result) return;

        const endpoint = box.dataset.endpoint;
        if (!endpoint) return;

        const setMessage = (message, tone = 'warning') => {
            result.hidden = false;
            result.textContent = message;
            result.dataset.tone = tone;
        };

        trigger.addEventListener('click', () => {
            if (!navigator.geolocation) {
                setMessage('Ce navigateur ne prend pas en charge la géolocalisation.');
                return;
            }

            const original = trigger.textContent;
            trigger.disabled = true;
            trigger.textContent = 'Localisation en cours…';
            setMessage('Recherche de la zone la plus proche…', 'info');

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    try {
                        const url = new URL(endpoint, window.location.origin);
                        url.searchParams.set('lat', position.coords.latitude);
                        url.searchParams.set('lng', position.coords.longitude);

                        const response = await fetch(url.toString(), {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok || !data.success || !data.zone) {
                            setMessage(data.message || 'Aucune zone géolocalisée n’est disponible pour le moment.');
                            return;
                        }

                        renderNearest(result, data.zone);
                    } catch (error) {
                        setMessage('Impossible de contacter le service. Réessayez dans un instant.', 'error');
                    } finally {
                        trigger.disabled = false;
                        trigger.textContent = original;
                    }
                },
                () => {
                    setMessage('Localisation refusée. Autorisez la géolocalisation ou explorez les zones manuellement.');
                    trigger.disabled = false;
                    trigger.textContent = original;
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
            );
        });
    });
}

function initShare() {
    document.querySelectorAll('[data-share-report]').forEach((button) => {
        button.addEventListener('click', async () => {
            const title = button.dataset.shareTitle || document.title;
            const url = button.dataset.shareUrl || window.location.href;
            const original = button.textContent;

            try {
                if (navigator.share) {
                    await navigator.share({ title, url });
                    return;
                }

                await navigator.clipboard.writeText(url);
                button.textContent = 'Lien copié !';
                window.setTimeout(() => { button.textContent = original; }, 2000);
            } catch (error) {
                /* partage annulé ou presse-papiers indisponible */
            }
        });
    });
}

function initCopy() {
    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyText || '';
            if (!value) return;

            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(value);
                button.textContent = 'Copié !';
                window.setTimeout(() => { button.textContent = original; }, 2000);
            } catch (error) {
                /* presse-papiers indisponible */
            }
        });
    });
}

function init() {
    initSwitches();
    initLive();
    initChart();
    initCountUp();
    initAutoSearch();
    initFavorites();
    initFollowedFilter();
    initNearest();
    initShare();
    initCopy();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
