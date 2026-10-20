@php($zone = $zone ?? null)

{{-- Carte interactive : clic / marqueur déplaçable, rayon, recherche d'adresse, géoloc.
     Les champs latitude / longitude / radius_m sont rendus dans la partial et
     restent synchronisés avec la carte (additif, tous facultatifs). --}}
@include('back.zones._map', ['mode' => 'form', 'zone' => $zone])

<section class="aq-formsection">
    <div class="aq-formsection__head">
        <span class="eyebrow">INFORMATIONS</span>
        <h2>Détails de la zone</h2>
        <p>Nom, adresse et description de la zone surveillée.</p>
    </div>

    <div class="aq-fieldgrid aq-fieldgrid--2">
        <div class="aq-field">
            <label for="zone-name">Nom de la zone</label>
            <input id="zone-name" type="text" name="name" value="{{ old('name', $zone?->name) }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
            @error('name')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field">
            <label for="zone-address">Adresse</label>
            <input id="zone-address" type="text" name="address" value="{{ old('address', $zone?->address) }}" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">
            @error('address')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field" style="grid-column:1 / -1">
            <label for="zone-description">Description</label>
            <textarea id="zone-description" name="description" rows="4" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}">{{ old('description', $zone?->description) }}</textarea>
            @error('description')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>

        <div class="aq-field">
            <label for="zone-risk">Niveau de risque</label>
            <select id="zone-risk" name="risk_level" aria-invalid="{{ $errors->has('risk_level') ? 'true' : 'false' }}">
                @foreach(\App\Models\Zone::RISK_LEVELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('risk_level', $zone?->risk_level ?? 'low') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('risk_level')<span class="aq-field__error">{{ $message }}</span>@enderror
        </div>
    </div>
</section>

<section class="aq-formsection">
    <div class="aq-formsection__head">
        <span class="eyebrow">MÉDIA</span>
        <h2>Photo de la zone</h2>
        <p>Cette image illustre la zone dans le front-office (JPEG, PNG, WEBP ou SVG — 4 Mo max).</p>
    </div>

    <div class="aq-fieldgrid aq-fieldgrid--2">
        <div class="aq-field">
            <label for="zone-image">Choisir une image</label>
            <input id="zone-image" type="file" name="image" accept="image/*" data-image-input aria-invalid="{{ $errors->has('image') ? 'true' : 'false' }}">
            <span class="aq-inline-note">Laisser vide pour conserver la photo actuelle.</span>
            @error('image')<span class="aq-field__error">{{ $message }}</span>@enderror

            @if($zone?->image_path)
                <label class="aq-checkbox" style="margin-top:10px">
                    <input type="checkbox" name="remove_image" value="1" data-image-remove @checked(old('remove_image'))>
                    <span>Supprimer la photo actuelle</span>
                </label>
            @endif
        </div>

        <div class="aq-field">
            <span class="aq-label">Aperçu</span>
            <div class="aq-image-drop" data-image-preview @if(! $zone?->image_path) hidden @endif>
                <img src="{{ $zone?->image_path ? asset('storage/'.$zone->image_path) : '' }}" alt="Aperçu de la photo de la zone">
            </div>
            <div class="aq-image-drop aq-image-drop--empty" data-image-placeholder @if($zone?->image_path) hidden @endif>
                <span>Aucune photo</span>
            </div>
        </div>
    </div>
</section>

<div class="aq-formbar">
    <x-ui.button type="submit">Enregistrer</x-ui.button>
    <x-ui.button :href="route('back.zones.index')" variant="outline">Annuler</x-ui.button>
    <span class="aq-formbar__spacer"></span>
    <span class="aq-formbar__hint">Les champs de géolocalisation sont facultatifs.</span>
</div>
