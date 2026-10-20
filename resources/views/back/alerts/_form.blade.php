@php($alert = $alert ?? null)

<section class="aq-formsection">
    <div class="aq-formsection__head">
        <span class="eyebrow">ALERTE</span>
        <h2>Informations de l'alerte</h2>
        <p>Zone concernée, gravité et message transmis aux équipes.</p>
    </div>

    <div class="aq-fieldgrid aq-fieldgrid--2">
        <div class="aq-field">
            <label for="alert-zone">Zone concernée</label>
            <select id="alert-zone" name="zone_id" aria-invalid="{{ $errors->has('zone_id') ? 'true' : 'false' }}">
                <option value="">— Choisir une zone —</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}" @selected((string) old('zone_id', $alert?->zone_id ?? request('zone_id')) === (string) $zone->id)>{{ $zone->name }}</option>
                @endforeach
            </select>
            @error('zone_id')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field">
            <label for="alert-incident">Incident lié (facultatif)</label>
            <input id="alert-incident" type="number" name="incident_id" min="1" value="{{ old('incident_id', $alert?->incident_id) }}" placeholder="Ex. 3" aria-invalid="{{ $errors->has('incident_id') ? 'true' : 'false' }}">
            @error('incident_id')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field">
            <label for="alert-type">Type d'alerte</label>
            <input id="alert-type" type="text" name="type" value="{{ old('type', $alert?->type) }}" placeholder="Ex. Fuite, Risque élevé…" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}">
            @error('type')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field">
            <label for="alert-severity">Gravité</label>
            <select id="alert-severity" name="severity" aria-invalid="{{ $errors->has('severity') ? 'true' : 'false' }}">
                @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('severity', $alert?->severity ?? 'medium') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('severity')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field" style="grid-column:1 / -1">
            <label for="alert-message">Message</label>
            <textarea id="alert-message" name="message" rows="4" placeholder="Décrivez l'alerte…" aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}">{{ old('message', $alert?->message) }}</textarea>
            @error('message')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field">
            <label class="aq-checkbox">
                <input type="checkbox" name="read" value="1" @checked(old('read', (bool) $alert?->read_at))>
                Marquer comme lue
            </label>
        </div>
    </div>
</section>

<div class="aq-formbar">
    <x-ui.button type="submit">Enregistrer</x-ui.button>
    <x-ui.button :href="route('back.alerts.index')" variant="outline">Annuler</x-ui.button>
</div>
