@php($alert = $alert ?? null)
<section class="panel">
    <div class="contact-form">
        <label>Zone concernée
            <select name="zone_id" style="display:block;width:100%;border:1px solid var(--line);padding:13px;border-radius:3px;margin-top:6px;background:#fff">
                <option value="">— Choisir une zone —</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}"                     @selected((string) old('zone_id', $alert?->zone_id ?? request('zone_id')) === (string) $zone->id)>{{ $zone->name }}</option>
                @endforeach
            </select>
            @error('zone_id')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Incident lié (facultatif)
            <input type="number" name="incident_id" min="1" value="{{ old('incident_id', $alert?->incident_id) }}" placeholder="Ex. 3">
            @error('incident_id')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Type d'alerte
            <input type="text" name="type" value="{{ old('type', $alert?->type) }}" placeholder="Ex. Fuite, Risque élevé...">
            @error('type')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Gravité
            <select name="severity" style="display:block;width:100%;border:1px solid var(--line);padding:13px;border-radius:3px;margin-top:6px;background:#fff">
                @foreach(\App\Models\Alert::SEVERITIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('severity', $alert?->severity ?? 'medium') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('severity')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Message
            <textarea name="message" rows="4" placeholder="Décrivez l'alerte...">{{ old('message', $alert?->message) }}</textarea>
            @error('message')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label style="display:flex;gap:10px;align-items:center">
            <input type="checkbox" name="read" value="1" @checked(old('read', (bool) $alert?->read_at)) style="width:auto">
            Marquer comme lue
        </label>

        <div class="actions">
            <x-ui.button type="submit">Enregistrer</x-ui.button>
            <x-ui.button :href="route('back.alerts.index')" variant="outline">Annuler</x-ui.button>
        </div>
    </div>
</section>
