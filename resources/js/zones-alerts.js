/**
 * AquaSecure — interactions Zones & Alertes (back + front office).
 *
 * S'appuie sur le design system `back-zones-alerts.css` (préfixe `aq-`).
 * Fonctionnalités, sans dépendance lourde (Chart.js chargé à la demande) :
 *   - toasts accessibles (`.aq-toastwrap` / `.aq-toast`) ;
 *   - modale de confirmation native (`<dialog class="aq-modal">`) ;
 *   - bascule vue tableau / cartes persistée (localStorage) ;
 *   - onglets accessibles ;
 *   - sélection groupée d'alertes (barre `.aq-bulkbar`) ;
 *   - rafraîchissement live (polling) des KPI, en pause quand l'onglet est caché ;
 *   - graphique 30 jours (Chart.js, import dynamique) ;
 *   - recherche automatique (débounce).
 *
 * Sécurité : toutes les données dynamiques sont insérées via textContent /
 * attributs maîtrisés ; jamais d'innerHTML avec du contenu serveur.
 */

const VIEW_KEY = 'aquasecure:zones-layout';
const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------------ *
 * Toasts
 * ------------------------------------------------------------------ */
function toast(message, tone = 'success') {
    let wrap = document.querySelector('.aq-toastwrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'aq-toastwrap';
        wrap.setAttribute('role', 'status');
        wrap.setAttribute('aria-live', 'polite');
        document.body.appendChild(wrap);
    }

    const node = document.createElement('div');
    node.className = 'aq-toast';
    node.setAttribute('data-tone', tone === 'error' ? 'error' : (tone === 'warning' ? 'warning' : 'success'));

    const text = document.createElement('span');
    text.textContent = String(message ?? '');
    node.appendChild(text);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'aq-toast__close';
    close.setAttribute('aria-label', 'Fermer la notification');
    close.textContent = '×';
    close.addEventListener('click', () => node.remove());
    node.appendChild(close);

    wrap.appendChild(node);
    window.setTimeout(() => node.remove(), 5000);
}

/* ------------------------------------------------------------------ *
 * Modale de confirmation (data-confirm sur <form> ou <a>)
 * ------------------------------------------------------------------ */
function openConfirm(message, onConfirm) {
    if (typeof window.HTMLDialogElement === 'undefined') {
        if (window.confirm(message)) onConfirm();
        return;
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'aq-modal';

    const body = document.createElement('div');
    body.className = 'aq-modal__body';

    const title = document.createElement('h2');
    title.textContent = 'Confirmation';

    const text = document.createElement('p');
    text.textContent = message;

    body.append(title, text);

    const actions = document.createElement('div');
    actions.className = 'aq-modal__actions';

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'button button-outline';
    cancel.textContent = 'Annuler';

    const confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.className = 'button';
    confirm.textContent = 'Confirmer';

    actions.append(cancel, confirm);
    dialog.append(body, actions);
    document.body.appendChild(dialog);

    const close = () => {
        dialog.close();
        dialog.remove();
    };

    cancel.addEventListener('click', close);
    confirm.addEventListener('click', () => {
        close();
        onConfirm();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    dialog.showModal();
    cancel.focus();
}

function initConfirm() {
    document.querySelectorAll('form[data-confirm], a[data-confirm]').forEach((element) => {
        const message = element.dataset.confirm;

        if (element.tagName === 'FORM') {
            element.addEventListener('submit', (event) => {
                if (element.dataset.confirmed === '1') return;
                event.preventDefault();
                openConfirm(message, () => {
                    element.dataset.confirmed = '1';
                    element.submit();
                });
            });
        } else {
            element.addEventListener('click', (event) => {
                event.preventDefault();
                openConfirm(message, () => { window.location.href = element.href; });
            });
        }
    });
}

/* ------------------------------------------------------------------ *
 * Bascule vue tableau / cartes (persistée)
 * ------------------------------------------------------------------ */
function initViewToggle() {
    const toggle = document.querySelector('[data-layout-toggle]');
    if (!toggle) return;

    const table = document.querySelector('[data-layout-table]');
    const cards = document.querySelector('[data-layout-cards]');
    const buttons = Array.from(toggle.querySelectorAll('[data-layout]'));

    const apply = (view, persist = true) => {
        const showCards = view === 'cards';
        if (table) table.hidden = showCards;
        if (cards) cards.hidden = !showCards;
        buttons.forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.layout === view ? 'true' : 'false');
        });
        if (persist) {
            try { window.localStorage.setItem(VIEW_KEY, view); } catch (error) { /* stockage indisponible */ }
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => apply(button.dataset.layout));
    });

    let stored = 'table';
    try { stored = window.localStorage.getItem(VIEW_KEY) || 'table'; } catch (error) { stored = 'table'; }
    apply(stored === 'cards' ? 'cards' : 'table', false);
}

/* ------------------------------------------------------------------ *
 * Onglets
 * ------------------------------------------------------------------ */
function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((group) => {
        const tabs = Array.from(group.querySelectorAll('[data-tab]'));
        const panels = Array.from(group.querySelectorAll('[data-tab-panel]'));
        if (tabs.length === 0) return;

        const activate = (name) => {
            tabs.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.tab === name ? 'true' : 'false'));
            panels.forEach((panel) => {
                const isActive = panel.dataset.tabPanel === name;
                panel.hidden = !isActive;
                // Leaflet / Chart.js recalculent leur taille lorsqu'ils deviennent visibles.
                if (isActive && (panel.querySelector('[data-zone-map]') || panel.querySelector('canvas'))) {
                    window.setTimeout(() => window.dispatchEvent(new Event('resize')), 60);
                }
            });
        };

        tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.tab)));
    });
}

/* ------------------------------------------------------------------ *
 * Sélection groupée d'alertes
 * ------------------------------------------------------------------ */
function initBulk() {
    document.querySelectorAll('[data-bulk-form]').forEach((form) => {
        const all = form.querySelector('[data-bulk-all]');
        const boxes = Array.from(form.querySelectorAll('input[name="ids[]"]'));
        const bar = form.querySelector('[data-bulk-bar]');
        const count = form.querySelector('[data-bulk-count]');

        const refresh = () => {
            const selected = boxes.filter((box) => box.checked).length;
            if (bar) bar.dataset.visible = selected > 0 ? 'true' : 'false';
            if (count) count.textContent = selected + ' sélectionnée' + (selected > 1 ? 's' : '');
        };

        if (all) {
            all.addEventListener('change', () => {
                boxes.forEach((box) => { box.checked = all.checked; });
                refresh();
            });
        }

        boxes.forEach((box) => box.addEventListener('change', refresh));
        refresh();

        form.querySelectorAll('[data-bulk-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const selected = boxes.filter((box) => box.checked);
                if (selected.length === 0) return;

                openConfirm('Confirmer l\'action « ' + button.textContent.trim() + ' » sur ' + selected.length + ' alerte(s) ?', () => {
                    const body = new URLSearchParams();
                    body.append('_token', form.dataset.csrf || '');
                    body.append('action', button.dataset.bulkAction);
                    selected.forEach((box) => body.append('ids[]', box.value));

                    fetch(form.dataset.bulkUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: body.toString(),
                    })
                        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            if (!ok || !data.success) {
                                throw new Error(data.message || 'Action impossible.');
                            }
                            toast(data.message || 'Action appliquée.', 'success');
                            window.location.reload();
                        })
                        .catch((error) => toast(error.message || 'Action impossible.', 'error'));
                });
            });
        });
    });
}

/* ------------------------------------------------------------------ *
 * Rafraîchissement live des KPI d'alertes
 * ------------------------------------------------------------------ */
function initLive() {
    const root = document.querySelector('[data-alerts-live]');
    if (!root || !root.dataset.url) return;

    const interval = parseInt(root.dataset.interval, 10) || 45000;
    const banner = document.querySelector('[data-alerts-new-banner]');
    const bannerText = document.querySelector('[data-alerts-new-text]');
    let timer = null;
    let since = root.dataset.since || new Date().toISOString();
    let lastCritical = root.dataset.criticalSince || since;

    const beep = () => {
        if (root.dataset.sound !== '1') return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = 880;
            gain.gain.value = 0.04;
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            window.setTimeout(() => { osc.stop(); ctx.close(); }, 180);
        } catch (error) { /* audio indisponible */ }
    };

    const setKpi = (key, value) => {
        document.querySelectorAll('[data-live-kpi="' + key + '"]').forEach((node) => {
            if (node.textContent !== String(value)) node.textContent = String(value);
        });
    };

    const poll = () => {
        if (document.hidden) return;

        const url = new URL(root.dataset.url, window.location.origin);
        url.searchParams.set('since', since);

        fetch(url.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data || !data.success) return;

                setKpi('open', data.kpi.open);
                setKpi('critical', data.kpi.critical);
                setKpi('resolved_today', data.kpi.resolved_today);
                setKpi('average_age', data.kpi.average_age);

                if (data.latest_at) since = data.latest_at;

                if (data.new_count > 0 && banner) {
                    if (bannerText) bannerText.textContent = data.new_count + ' nouvelle(s) alerte(s) depuis votre dernière actualisation.';
                    banner.hidden = false;
                }

                if (data.new_critical_count > 0 && data.latest_critical_at && data.latest_critical_at > lastCritical) {
                    lastCritical = data.latest_critical_at;
                    beep();
                    toast('Alerte critique détectée', 'error');
                }
            })
            .catch(() => { /* réseau indisponible : silencieux */ });
    };

    const start = () => { if (!timer) timer = window.setInterval(poll, interval); };
    const stop = () => { if (timer) { window.clearInterval(timer); timer = null; } };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop(); else { poll(); start(); }
    });

    if (!document.hidden) start();
}

/* ------------------------------------------------------------------ *
 * Graphique 30 jours (Chart.js)
 * ------------------------------------------------------------------ */
function initChart() {
    const canvas = document.querySelector('[data-zone-alerts-chart]');
    if (!canvas) return;

    let labels = [];
    let values = [];
    try {
        labels = JSON.parse(canvas.dataset.labels || '[]');
        values = JSON.parse(canvas.dataset.values || '[]');
    } catch (error) {
        return;
    }

    let drawn = false;
    const build = () => {
        if (drawn) return;
        drawn = true;

        import('chart.js/auto').then(({ default: Chart }) => {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Alertes',
                        data: values,
                        backgroundColor: '#16b7a3',
                        borderRadius: 4,
                        maxBarThickness: 26,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: REDUCED_MOTION ? false : undefined,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, font: { size: 10 } } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
                    },
                },
            });
        }).catch(() => { /* Chart.js indisponible : le tableau reste lisible */ });
    };

    // Le canvas peut être dans un onglet masqué : on attend qu'il soit visible.
    if (canvas.offsetWidth > 0) {
        build();
    } else if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(() => {
            if (canvas.offsetWidth > 0) {
                observer.disconnect();
                build();
            }
        });
        observer.observe(canvas.parentElement || canvas);
    } else {
        build();
    }
}

/* ------------------------------------------------------------------ *
 * Recherche automatique (débounce)
 * ------------------------------------------------------------------ */
function initAutoSearch() {
    document.querySelectorAll('[data-auto-search]').forEach((form) => {
        const input = form.querySelector('input[type="search"]');
        let timer = null;

        if (input) {
            input.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => form.submit(), 450);
            });
        }

        // Les listes déroulantes et champs à choix soumettent immédiatement.
        form.querySelectorAll('select, input[type="checkbox"], input[type="date"]').forEach((field) => {
            field.addEventListener('change', () => {
                window.clearTimeout(timer);
                form.submit();
            });
        });
    });
}

/* ------------------------------------------------------------------ *
 * Aperçu de la photo de zone (formulaire back-office)
 * ------------------------------------------------------------------ */
function initImagePreview() {
    document.querySelectorAll('[data-image-input]').forEach((input) => {
        const wrapper = input.closest('form') || document;
        const preview = wrapper.querySelector('[data-image-preview]');
        const image = preview ? preview.querySelector('img') : null;
        const placeholder = wrapper.querySelector('[data-image-placeholder]');
        const remove = wrapper.querySelector('[data-image-remove]');

        if (remove) {
            remove.addEventListener('change', () => {
                if (!preview) return;
                preview.hidden = remove.checked;
                if (placeholder) placeholder.hidden = remove.checked || !!image;
            });
        }

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file || !preview || !image) return;
            image.src = URL.createObjectURL(file);
            preview.hidden = false;
            if (placeholder) placeholder.hidden = true;
            if (remove) remove.checked = false;
        });
    });
}

function init() {
    initConfirm();
    initViewToggle();
    initTabs();
    initBulk();
    initLive();
    initChart();
    initAutoSearch();
    initImagePreview();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { toast };
