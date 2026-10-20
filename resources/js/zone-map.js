import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2xUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';

/**
 * AquaSecure — cartes des zones (Leaflet + OpenStreetMap, sans clé API).
 *
 * Trois modes pilotés par l'attribut `data-mode` du conteneur :
 *   - "form"  : formulaire create/edit — clic, marqueur déplaçable, rayon,
 *               recherche d'adresse (Nominatim) et géolocalisation navigateur ;
 *   - "zone"  : page de détail — marqueur de zone, cercle de rayon, popups
 *               et alertes récentes de la zone (affichage seul) ;
 *   - "index" : liste des zones — carte globale alimentée par back.zones.map-data
 *               + bascule liste/carte.
 *
 * Sécurité : toute donnée affichée passe par esc() (échappement HTML) ;
 * les couleurs proviennent uniquement de constantes internes.
 */

// Icônes par défaut Leaflet : chemins corrigés pour le bundling Vite.
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2xUrl,
    iconUrl: markerIconUrl,
    shadowUrl: markerShadowUrl,
});

const RISK_COLORS = {
    low: '#1f9d6b',
    medium: '#e0a419',
    high: '#e2673f',
    critical: '#c0332b',
};

const SEVERITY_COLORS = {
    info: '#17a8c4',
    warning: '#e0a419',
    critical: '#c0332b',
};

const DEFAULT_CENTER = [34.0, 9.5];
const DEFAULT_ZOOM = 6;
const VIEW_KEY = 'aquasecure:zones-view';

function esc(value) {
    const div = document.createElement('div');
    div.textContent = value === null || value === undefined ? '' : String(value);
    return div.innerHTML;
}

function parseConfig(container) {
    try {
        return JSON.parse(container.dataset.config || '{}');
    } catch (error) {
        return {};
    }
}

function riskColor(level) {
    return RISK_COLORS[level] || '#0e7490';
}

function severityColor(severity) {
    return SEVERITY_COLORS[severity] || SEVERITY_COLORS.info;
}

function dotIcon(color, size = 18) {
    return L.divIcon({
        className: 'zone-map-dot-icon',
        html: `<span class="zone-map-dot" style="background:${color}"></span>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
        popupAnchor: [0, -Math.round(size / 1.6)],
    });
}

function addTiles(map) {
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);
}

function createMap(container, center, zoom) {
    const map = L.map(container, {
        scrollWheelZoom: false,
        worldCopyJump: true,
    }).setView(center, zoom);

    addTiles(map);

    return map;
}

function panelNotice(container) {
    return container.closest('.zone-map-panel')?.querySelector('[data-zone-notice]') ?? null;
}

function setNotice(container, message, success = true) {
    const box = panelNotice(container);
    if (!box) return;

    if (!message) {
        box.style.display = 'none';
        box.textContent = '';
        return;
    }

    box.textContent = message;
    box.className = success ? 'flash-message flash-success' : 'flash-message flash-error';
    box.style.display = 'block';
}

function feedback(container, message) {
    container.closest('.zone-map-panel')?.querySelector('[data-zone-feedback]')?.remove();

    const box = document.createElement('div');
    box.className = 'flash-message flash-warning zone-map-feedback';
    box.setAttribute('data-zone-feedback', '');
    box.setAttribute('role', 'status');
    box.textContent = message;

    const panel = container.closest('.zone-map-panel');
    if (panel) panel.appendChild(box);
}

/* ------------------------------------------------------------------ *
 * Mode "form" — création / édition d'une zone
 * ------------------------------------------------------------------ */
function initFormMap(container, config) {
    const form = container.closest('form');
    const latInput = form ? form.querySelector('input[name="latitude"]') : null;
    const lngInput = form ? form.querySelector('input[name="longitude"]') : null;
    const radiusInput = form ? form.querySelector('input[name="radius_m"]') : null;
    const panel = container.closest('.zone-map-panel');

    const hasCenter = Array.isArray(config.center) && config.center.length === 2;
    const start = hasCenter ? config.center : (Array.isArray(config.defaultCenter) ? config.defaultCenter : DEFAULT_CENTER);

    const map = createMap(container, start, hasCenter ? 13 : (config.defaultZoom || DEFAULT_ZOOM));

    let marker = null;
    let circle = null;

    const readRadius = () => {
        const value = radiusInput ? parseInt(radiusInput.value, 10) : NaN;
        return Number.isFinite(value) && value > 0 ? value : 0;
    };

    const syncInputs = () => {
        if (!latInput || !lngInput) return;
        if (!marker) {
            latInput.value = '';
            lngInput.value = '';
            return;
        }
        const pos = marker.getLatLng();
        latInput.value = Number(pos.lat).toFixed(7);
        lngInput.value = Number(pos.lng).toFixed(7);
    };

    const renderCircle = () => {
        if (!marker) {
            if (circle) { map.removeLayer(circle); circle = null; }
            return;
        }
        const radius = readRadius();
        if (radius <= 0) {
            if (circle) { map.removeLayer(circle); circle = null; }
            return;
        }
        if (!circle) {
            circle = L.circle(marker.getLatLng(), {
                radius,
                color: '#0e7490',
                weight: 1,
                fillColor: '#16b7a4',
                fillOpacity: 0.12,
            }).addTo(map);
        } else {
            circle.setLatLng(marker.getLatLng()).setRadius(radius);
        }
    };

    const place = (latlng, options = {}) => {
        if (!marker) {
            marker = L.marker(latlng, { draggable: true, keyboard: true }).addTo(map);
            marker.on('dragend', () => {
                syncInputs();
                renderCircle();
                setNotice(container, null);
            });
        } else {
            marker.setLatLng(latlng);
        }

        syncInputs();
        renderCircle();
        setNotice(container, null);

        if (options.setView) {
            map.setView(latlng, options.zoom || 14);
        } else if (options.pan && !map.getBounds().contains(latlng)) {
            map.panTo(latlng);
        }
    };

    const clearPosition = (message = 'Position effacée.') => {
        if (marker) { map.removeLayer(marker); marker = null; }
        if (circle) { map.removeLayer(circle); circle = null; }
        syncInputs();
        setNotice(container, message, true);
    };

    if (hasCenter) {
        place(config.center);
    }

    map.on('click', (event) => place(event.latlng, { pan: true }));

    // Saisie directe des coordonnées -> marqueur synchronisé.
    const manualCoordChange = () => {
        if (!latInput || !lngInput) return;

        const latitude = parseFloat(latInput.value);
        const longitude = parseFloat(lngInput.value);

        if (latInput.value.trim() === '' && lngInput.value.trim() === '') {
            clearPosition('Position effacée.');
            return;
        }

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
        if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            setNotice(container, 'Coordonnées hors des plages autorisées (latitude -90…90, longitude -180…180).', false);
            return;
        }

        place([latitude, longitude], { setView: true, zoom: 14 });
    };

    latInput?.addEventListener('change', manualCoordChange);
    lngInput?.addEventListener('change', manualCoordChange);

    if (radiusInput) {
        radiusInput.addEventListener('input', renderCircle);
        radiusInput.addEventListener('change', renderCircle);
    }

    // Géolocalisation navigateur
    panel?.querySelector('[data-zone-geoloc]')?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setNotice(container, "Ce navigateur ne prend pas en charge la géolocalisation.", false);
            return;
        }
        setNotice(container, 'Localisation en cours…', true);
        navigator.geolocation.getCurrentPosition(
            (position) => place(
                [position.coords.latitude, position.coords.longitude],
                { setView: true, zoom: 15 }
            ),
            () => setNotice(container, "Position indisponible : vérifiez les autorisations du navigateur.", false),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    });

    // Effacement de la position
    panel?.querySelector('[data-zone-clear]')?.addEventListener('click', () => {
        clearPosition();
        map.setView(Array.isArray(config.defaultCenter) ? config.defaultCenter : DEFAULT_CENTER, config.defaultZoom || DEFAULT_ZOOM);
    });

    // Recherche d'adresse (Nominatim, sans clé API)
    const searchInput = panel?.querySelector('[data-zone-search]');
    const runSearch = async () => {
        const query = (searchInput?.value || '').trim();
        if (!query) {
            setNotice(container, 'Saisissez une adresse à rechercher.', false);
            return;
        }

        setNotice(container, 'Recherche en cours…', true);

        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&accept-language='
                + encodeURIComponent('fr')
                + '&q=' + encodeURIComponent(query);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const rows = await response.json();
            if (!Array.isArray(rows) || rows.length === 0) {
                setNotice(container, 'Aucun résultat pour cette adresse.', false);
                return;
            }

            const latitude = parseFloat(rows[0].lat);
            const longitude = parseFloat(rows[0].lon);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                setNotice(container, 'Résultat de recherche invalide.', false);
                return;
            }

            place([latitude, longitude], { setView: true, zoom: 14 });
            setNotice(container, 'Position mise à jour depuis l\'adresse recherchée.', true);
        } catch (error) {
            setNotice(container, "Service de recherche d'adresse indisponible pour le moment.", false);
        }
    };

    panel?.querySelector('[data-zone-search-btn]')?.addEventListener('click', runSearch);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runSearch();
        }
    });
}

/* ------------------------------------------------------------------ *
 * Mode "zone" — page de détail
 * ------------------------------------------------------------------ */
function zonePopupHtml(zone) {
    const score = zone.ai_risk_score !== null && zone.ai_risk_score !== undefined
        ? `<div class="zone-map-popup__row">Score IA : <strong>${esc(zone.ai_risk_score)}/100</strong>`
            + (zone.ai_risk_level_label ? ` · ${esc(zone.ai_risk_level_label)}` : '')
            + '</div>'
        : '';

    return '<div class="zone-map-popup">'
        + `<div class="zone-map-popup__title">${esc(zone.name)}</div>`
        + `<div class="zone-map-popup__row">Risque : <strong>${esc(zone.risk_label)}</strong></div>`
        + score
        + `<div class="zone-map-popup__row"><a href="${esc(zone.url)}">Voir la zone</a></div>`
        + '</div>';
}

function alertPopupHtml(alert) {
    const message = alert.message
        ? (String(alert.message).length > 160 ? String(alert.message).slice(0, 160) + '…' : String(alert.message))
        : '';

    return '<div class="zone-map-popup">'
        + `<div class="zone-map-popup__title">${esc(alert.type)}</div>`
        + `<div class="zone-map-popup__row">Gravité : <strong>${esc(alert.severity_label)}</strong></div>`
        + (message ? `<div class="zone-map-popup__message">${esc(message)}</div>` : '')
        + `<div class="zone-map-popup__row">${esc(alert.date)} · <a href="${esc(alert.url)}">Voir l'alerte</a></div>`
        + '</div>';
}

function initZoneMap(container, config) {
    const zone = config.zone || {};
    const center = [Number(zone.latitude), Number(zone.longitude)];
    const map = createMap(container, center, 13);

    const color = riskColor(zone.risk_level);

    L.marker(center)
        .addTo(map)
        .bindPopup(zonePopupHtml(zone), { maxWidth: 340 });

    if (Number(zone.radius_m) > 0) {
        const circle = L.circle(center, {
            radius: Number(zone.radius_m),
            color,
            weight: 1,
            fillColor: color,
            fillOpacity: 0.10,
        }).addTo(map);
        map.fitBounds(circle.getBounds(), { padding: [30, 30], maxZoom: 15 });
    }

    // Alertes récentes de la zone (lecture seule, décalées en spirale).
    (Array.isArray(config.alerts) ? config.alerts : []).forEach((alert, index) => {
        const angle = index * 2.399963; // angle d'or : évite les marqueurs superposés
        const distance = 0.0016 * (1 + Math.floor(index / 6));
        const lat = center[0] + distance * Math.cos(angle);
        const lng = center[1] + (distance * Math.sin(angle)) / Math.max(0.15, Math.cos(center[0] * Math.PI / 180));

        L.marker([lat, lng], { icon: dotIcon(severityColor(alert.severity), 16) })
            .addTo(map)
            .bindPopup(alertPopupHtml(alert), { maxWidth: 320 });
    });
}

/* ------------------------------------------------------------------ *
 * Mode "index" — carte globale + bascule liste/carte
 * ------------------------------------------------------------------ */
function initIndexMap(container, config) {
    const start = Array.isArray(config.defaultCenter) ? config.defaultCenter : DEFAULT_CENTER;
    const map = createMap(container, start, config.defaultZoom || DEFAULT_ZOOM);
    const layer = L.layerGroup().addTo(map);

    const state = { map, layer, bounds: null, config };

    const load = async () => {
        if (!config.endpoint) return;

        try {
            const response = await fetch(config.endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            const zones = data && Array.isArray(data.zones) ? data.zones : [];

            if (zones.length === 0) {
                feedback(container, 'Aucune zone géolocalisée pour le moment : renseignez les coordonnées depuis la fiche d\'une zone.');
                return;
            }

            const bounds = L.latLngBounds([]);

            zones.forEach((zone) => {
                const latitude = Number(zone.latitude);
                const longitude = Number(zone.longitude);
                if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

                const position = [latitude, longitude];
                const color = riskColor(zone.risk_level);

                L.marker(position, { icon: dotIcon(color) })
                    .addTo(layer)
                    .bindPopup(zonePopupHtml(zone), { maxWidth: 340 });

                if (Number(zone.radius_m) > 0) {
                    L.circle(position, {
                        radius: Number(zone.radius_m),
                        color,
                        weight: 1,
                        fillColor: color,
                        fillOpacity: 0.08,
                    }).addTo(layer);
                }

                bounds.extend(position);
            });

            if (bounds.isValid()) {
                state.bounds = bounds;
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
            }
        } catch (error) {
            feedback(container, 'Impossible de charger la carte des zones. Réessayez plus tard.');
        }
    };

    state.fit = () => {
        if (state.bounds) {
            map.fitBounds(state.bounds, { padding: [40, 40], maxZoom: 14 });
        }
    };

    window.__zoneIndexMap = state;

    load();
}

function initViewToggle() {
    const toggle = document.querySelector('[data-zone-view-toggle]');
    const listPanel = document.getElementById('zones-list-panel');
    const mapPanel = document.getElementById('zones-map-panel');

    if (!toggle || !listPanel || !mapPanel) return;

    const buttons = [...toggle.querySelectorAll('[data-zone-view]')];

    const apply = (view, persist = true) => {
        const showMap = view === 'map';

        listPanel.hidden = showMap;
        mapPanel.hidden = !showMap;

        buttons.forEach((button) => {
            button.classList.toggle('zone-view-toggle__btn--active', button.dataset.zoneView === view);
            button.setAttribute('aria-pressed', button.dataset.zoneView === view ? 'true' : 'false');
        });

        if (showMap) {
            const state = window.__zoneIndexMap;
            if (state?.map) {
                // Le conteneur était masqué : Leaflet doit recalculer sa taille.
                state.map.invalidateSize();
                state.fit?.();
            }
        }

        if (persist) {
            try { window.localStorage.setItem(VIEW_KEY, view); } catch (error) { /* stockage indisponible */ }
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => apply(button.dataset.zoneView));
    });

    let stored = 'list';
    try { stored = window.localStorage.getItem(VIEW_KEY) || 'list'; } catch (error) { stored = 'list'; }

    apply(stored === 'map' ? 'map' : 'list', false);
}

function init() {
    document.querySelectorAll('[data-zone-map]').forEach((container) => {
        const mode = container.dataset.mode;
        const config = parseConfig(container);

        if (mode === 'form') initFormMap(container, config);
        else if (mode === 'zone') initZoneMap(container, config);
        else if (mode === 'index') initIndexMap(container, config);
    });

    initViewToggle();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
