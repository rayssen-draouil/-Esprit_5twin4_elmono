@if($errors->any())
    <div class="form-error-summary-banner" role="alert" aria-live="polite">
        <div class="summary-banner-title">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span>Veuillez corriger {{ $errors->count() }} erreur{{ $errors->count() > 1 ? 's' : '' }} avant d'enregistrer :</span>
        </div>
        <ul class="summary-error-list">
            @foreach($errors->getMessages() as $field => $messages)
                @foreach($messages as $message)
                    <li>
                        <a href="#{{ $field }}">{{ $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }} : {{ $message }}</a>
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
