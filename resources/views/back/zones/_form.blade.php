@php($zone = $zone ?? null)
<section class="panel">
    <div class="contact-form">
        <label>Nom de la zone
            <input type="text" name="name" value="{{ old('name', $zone?->name) }}">
            @error('name')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Adresse
            <input type="text" name="address" value="{{ old('address', $zone?->address) }}">
            @error('address')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Description
            <textarea name="description" rows="4">{{ old('description', $zone?->description) }}</textarea>
            @error('description')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <label>Niveau de risque
            <select name="risk_level" style="display:block;width:100%;border:1px solid var(--line);padding:13px;border-radius:3px;margin-top:6px;background:#fff">
                @foreach(\App\Models\Zone::RISK_LEVELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('risk_level', $zone?->risk_level ?? 'low') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('risk_level')<small style="color:#bd5149">{{ $message }}</small>@enderror
        </label>

        <div class="actions">
            <x-ui.button type="submit">Enregistrer</x-ui.button>
            <x-ui.button :href="route('back.zones.index')" variant="outline">Annuler</x-ui.button>
        </div>
    </div>
</section>
