@if(session('success'))
    <div class="front-flash front-flash--success" role="status">
        <span aria-hidden="true">✓</span>
        <p>{{ session('success') }}</p>
    </div>
@endif

@if(session('error'))
    <div class="front-flash front-flash--error" role="alert">
        <span aria-hidden="true">!</span>
        <p>{{ session('error') }}</p>
    </div>
@endif

@if($errors->any())
    <div class="front-flash front-flash--error" role="alert">
        <span aria-hidden="true">!</span>
        <p>Merci de vérifier les informations saisies.</p>
    </div>
@endif
