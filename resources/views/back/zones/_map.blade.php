{{--
    Carte des zones (Leaflet + OpenStreetMap) — partial réutilisable.

    Paramètres :
      $mode    : 'form' | 'zone' | 'index'
      $zone    : Zone|null (modes form et zone)
      $alerts  : Collection|null (mode zone) — alertes de la zone, lecture seule

    Toutes les données dynamiques sont échappées côté Blade ({{ }}) et
    côté JavaScript (helper esc()) : aucun contenu non filtré en HTML.
--}}
@php
    $mode = $mode ?? 'form';
    $zone = $zone ?? null;
    $alerts = collect($alerts ?? []);
    $isForm = $mode === 'form';
    $isZone = $mode === 'zone';
    $isIndex = $mode === 'index';

    // Intitulés
    $title = $isForm ? 'Position de la zone'
        : ($isIndex ? 'Carte des zones' : 'Localisation de la zone');

    if ($isForm) {
        $subtitle = 'Cliquez sur la carte ou déplacez le marqueur pour placer la zone. La recherche d\'adresse utilise OpenStreetMap Nominatim.';
    } elseif ($isIndex) {
        $subtitle = 'Toutes les zones disposant de coordonnées. Couleur du marqueur = niveau de risque.';
    } else {
        $subtitle = $zone?->hasCoordinates()
            ? sprintf(
                'Latitude %s · Longitude %s%s',
                number_format((float) $zone->latitude, 5, '.', ''),
                number_format((float) $zone->longitude, 5, '.', ''),
                $zone->radius_m ? ' · Rayon '.number_format((int) $zone->radius_m, 0, '.', ' ').' m' : ''
            )
            : 'Aucune position renseignée pour cette zone.';
    }

    // Configuration injectée dans data-config (échappée par {{ }}).
    $config = ['defaultCenter' => [34.0, 9.5], 'defaultZoom' => 6];

    if ($isForm) {
        $oldLat = old('latitude', $zone?->latitude);
        $oldLng = old('longitude', $zone?->longitude);

        $config += [
            'center' => is_numeric($oldLat) && is_numeric($oldLng)
                ? [(float) $oldLat, (float) $oldLng]
                : null,
            'radius' => old('radius_m', $zone?->radius_m),
        ];
    } elseif ($isZone && $zone?->hasCoordinates()) {
        $config['zone'] = [
            'id' => $zone->id,
            'name' => $zone->name,
            'url' => route('back.zones.show', $zone),
            'risk_level' => $zone->risk_level,
            'risk_label' => $zone->risk_label,
            'latitude' => (float) $zone->latitude,
            'longitude' => (float) $zone->longitude,
            'radius_m' => $zone->radius_m,
            'ai_risk_score' => $zone->ai_risk_score,
            'ai_risk_level_label' => $zone->ai_risk_level ? $zone->ai_risk_level_label : null,
        ];

        // Lecture seule : les alertes sont simplement affichées sur la carte.
        $config['alerts'] = $alerts->take(30)->map(function ($alert) {
            $severity = in_array($alert->ai_severity, ['info', 'warning', 'critical'], true)
                ? $alert->ai_severity
                : (['low' => 'info', 'medium' => 'warning', 'high' => 'critical'][(string) $alert->severity] ?? 'warning');

            $label = in_array($alert->ai_severity, ['info', 'warning', 'critical'], true)
                ? $alert->ai_severity_label
                : $alert->severity_label;

            return [
                'id' => $alert->id,
                'type' => $alert->type,
                'url' => route('back.alerts.show', $alert),
                'severity' => $severity,
                'severity_label' => $label,
                'message' => mb_substr((string) ($alert->message ?? ''), 0, 200),
                'date' => $alert->created_at?->format('d/m/Y H:i') ?? '—',
            ];
        })->values()->all();
    } elseif ($isIndex) {
        $config['endpoint'] = route('back.zones.map-data');
    }

    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $geoErrors = ['latitude', 'longitude', 'radius_m'];
    $hasGeoError = collect($geoErrors)->contains(fn (string $key) => $errors->has($key));
@endphp

<section class="panel zone-map-panel" style="margin-bottom:20px"
         @if($isIndex) id="zones-map-panel" hidden @endif>
    <div class="panel-heading" style="align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <span class="eyebrow" style="color:var(--aqua);">CARTOGRAPHIE</span>
            <h2>{{ $title }}</h2>
            <small style="color:var(--muted);">{{ $subtitle }}</small>
        </div>

        @if($isForm)
            <div class="zone-map-actions">
                <button type="button" class="button button-outline" data-zone-geoloc>◎ Utiliser ma position</button>
                <button type="button" class="button button-outline" data-zone-clear>✕ Effacer la position</button>
            </div>
        @endif
    </div>

    @if($isForm)
        <div class="zone-map-search">
            <input type="search" data-zone-search value="{{ old('address', $zone?->address) }}"
                   placeholder="⌕ Rechercher une adresse (ex. : Tunis, Lac 1)…">
            <button type="button" class="button button-primary" data-zone-search-btn>Rechercher</button>
        </div>

        @if($hasGeoError)
            <div class="flash-message flash-error" style="margin-bottom:12px">
                @foreach($geoErrors as $key)
                    @error($key)<div>{{ $message }}</div>@enderror
                @endforeach
            </div>
        @endif
    @endif

    {{-- Messages d'état (recherche, géolocalisation, erreur de chargement) --}}
    <div data-zone-notice class="flash-message" role="status" style="display:none; margin-bottom:12px"></div>

    @if($isZone && ! $zone?->hasCoordinates())
        <div class="zone-map-empty">
            <strong>Aucune position géographique pour cette zone.</strong>
            <p style="margin:8px 0 0">Ajoutez des coordonnées pour la localiser sur la carte, afficher son rayon de surveillance et positionner ses alertes.</p>
            <p style="margin:12px 0 0">
                <a href="{{ route('back.zones.edit', $zone) }}">Ajouter une position sur la carte →</a>
            </p>
        </div>
    @else
        <div class="zone-map-canvas"
             id="zone-map-canvas-{{ $mode }}{{ $zone?->id ? '-'.$zone->id : '' }}"
             data-zone-map
             data-mode="{{ $mode }}"
             data-config="{{ $configJson }}"></div>

        <div class="zone-map-legend">
            @if($isZone)
                @php($zoneMarkerColor = ['low' => '#1f9d6b', 'medium' => '#e0a419', 'high' => '#e2673f', 'critical' => '#c0332b'][$zone->risk_level] ?? '#0e7490')
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:{{ $zoneMarkerColor }}"></i> Marqueur de zone · risque {{ $zone->risk_label }}</span>
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#17a8c4"></i> Alerte · information</span>
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#e0a419"></i> Alerte · avertissement</span>
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#c0332b"></i> Alerte · critique</span>
            @else
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#1f9d6b"></i> Risque faible</span>
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#e0a419"></i> Risque moyen</span>
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#e2673f"></i> Risque élevé</span>
                <span class="zone-map-legend__item"><i class="zone-map-dot" style="background:#c0332b"></i> Risque critique</span>
            @endif
            <span class="zone-map-legend__item" style="margin-left:auto">Fonds © OpenStreetMap contributors</span>
        </div>
    @endif

    @if($isForm)
        <div class="zone-map-fields">
            <label>Latitude
                <input type="number" name="latitude" step="any" min="-90" max="90" inputmode="decimal"
                       value="{{ old('latitude', $zone?->latitude) }}" placeholder="ex. 36.8065">
            </label>
            <label>Longitude
                <input type="number" name="longitude" step="any" min="-180" max="180" inputmode="decimal"
                       value="{{ old('longitude', $zone?->longitude) }}" placeholder="ex. 10.1815">
            </label>
            <label>Rayon de surveillance (m)
                <input type="number" name="radius_m" min="1" max="100000" step="1" inputmode="numeric"
                       value="{{ old('radius_m', $zone?->radius_m) }}" placeholder="ex. 2500">
            </label>
        </div>
        <small style="display:block; color:var(--muted); font-size:12px; margin-top:8px">
            Champs facultatifs : laissez la latitude et la longitude vides pour une zone non géolocalisée.
            Modifiez les valeurs directement, la carte se met à jour.
        </small>
    @endif
</section>

@push('styles')
<style>
    .zone-map-canvas { height: 420px; border: 1px solid var(--line); border-radius: 6px; overflow: hidden; background: var(--paper); }
    .zone-map-canvas .leaflet-container { font: inherit; }
    .zone-map-dot-icon { background: none !important; border: none !important; }
    .zone-map-dot { display: block; width: 100%; height: 100%; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.28), 0 2px 6px rgba(0,0,0,.32); }
    .zone-map-legend { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin-top: 10px; font-size: 12px; color: var(--muted); }
    .zone-map-legend__item { display: inline-flex; align-items: center; gap: 6px; }
    .zone-map-legend__item .zone-map-dot { display: inline-block; width: 12px; height: 12px; }
    .zone-map-search { display: flex; gap: 8px; margin-bottom: 12px; }
    .zone-map-search input { flex: 1; min-width: 0; }
    .zone-map-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .zone-map-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
    .zone-map-fields label { font-size: 13px; }
    .zone-map-fields input { display: block; width: 100%; border: 1px solid var(--line); padding: 11px 12px; border-radius: 3px; margin-top: 6px; background: #fff; }
    .zone-map-empty { padding: 28px 22px; text-align: center; background: var(--paper); border: 1px dashed var(--line); border-radius: 6px; color: var(--muted); font-size: 14px; }
    .zone-map-feedback { margin-top: 12px; }
    .zone-map-popup__title { font-weight: 700; margin-bottom: 4px; color: var(--deep); }
    .zone-map-popup__row { font-size: 13px; color: var(--ink); margin-top: 2px; }
    .zone-map-popup__message { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.45; }
    .zone-map-popup a { color: var(--aqua, #0e7490); font-weight: 600; }

    .zone-view-toggle { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
    .zone-view-toggle__btn { cursor: pointer; }
    .zone-view-toggle__btn--active { background: var(--deep) !important; color: #fff !important; border-color: var(--deep) !important; }

    @media (max-width: 720px) {
        .zone-map-fields { grid-template-columns: 1fr; }
        .zone-map-canvas { height: 320px; }
    }
</style>
@endpush

@push('scripts')
    @vite(['resources/js/zone-map.js'])
@endpush
