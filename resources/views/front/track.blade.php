@extends('layouts.front')

@section('title', 'Suivre mon signalement')
@section('meta_description', 'Suivez l’avancement de votre signalement AquaSecure avec votre référence de suivi, en toute confidentialité.')

@section('content')
    <section class="page-hero compact">
        <span class="eyebrow">SUIVI CITOYEN</span>
        <h1>Où en est<br><em>mon signalement ?</em></h1>
        <p>Saisissez la référence reçue lors de votre signalement pour suivre son avancement.</p>
    </section>

    <section class="section aq-front">
        <div class="aq-track-layout">
            <div class="aq-track-main">
                <form class="aq-track-form" method="GET" action="{{ route('front.track') }}" role="search">
                    <label for="track-reference">Référence de suivi</label>
                    <div class="aq-track-form__row">
                        <input id="track-reference"
                               type="text"
                               name="ref"
                               value="{{ $reference }}"
                               placeholder="AQ-XXXX-XXXX"
                               autocomplete="off"
                               spellcheck="false"
                               aria-describedby="track-help"
                               required>
                        <button type="submit" class="button">Suivre</button>
                    </div>
                    <p id="track-help" class="aq-inline-note">
                        Votre référence ressemble à <code>AQ-ABCD-1234</code>. Si vous êtes connecté, vous pouvez aussi saisir <code>SIG-0001</code>.
                    </p>
                </form>

                @if($error)
                    <div class="front-flash front-flash--error" role="alert">
                        <span aria-hidden="true">!</span>
                        <p>{{ $error }}</p>
                    </div>
                @endif

                @if($tracking)
                    <article class="aq-track-card">
                        <header class="aq-track-card__head">
                            <div>
                                <span class="eyebrow" style="color:var(--aqua)">DOSSIER</span>
                                <h2>{{ $tracking['reference'] }}</h2>
                                <p>{{ $tracking['type'] }} · {{ $tracking['location'] ?: 'Lieu non précisé' }}</p>
                            </div>
                            <span class="aq-status-pill" data-status="{{ $tracking['status'] }}">{{ $tracking['status'] }}</span>
                        </header>

                        <dl class="aq-facts">
                            @if($tracking['zone'])
                                <div><dt>Zone concernée</dt><dd>{{ $tracking['zone'] }}</dd></div>
                            @endif
                            <div><dt>Signalé le</dt><dd>{{ $tracking['reported_at'] ?? '—' }}</dd></div>
                            @if($tracking['resolved_at'])
                                <div><dt>Résolu le</dt><dd>{{ $tracking['resolved_at'] }}</dd></div>
                            @endif
                        </dl>

                        <ol class="aq-timeline">
                            @foreach($tracking['timeline'] as $step)
                                <li class="aq-timeline__item is-{{ $step['state'] }}">
                                    <span class="aq-timeline__dot" aria-hidden="true"></span>
                                    <div>
                                        <strong>{{ $step['label'] }}</strong>
                                        @if($step['date'])
                                            <small>{{ $step['date'] }}</small>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>

                        <footer class="aq-track-card__foot">
                            <p class="aq-inline-note">
                                Conservez cette référence. Elle est strictement personnelle et permet de consulter votre dossier.
                            </p>
                            <button type="button" class="button button-outline button-small" data-copy-text="{{ $tracking['reference'] }}">
                                Copier la référence
                            </button>
                        </footer>
                    </article>
                @elseif(! $error)
                    <div class="aq-track-empty">
                        <p>
                            Aucun dossier affiché pour le moment. Saisissez votre référence ci-dessus,
                            ou <a href="{{ route('front.incidents.create') }}">signalez un nouvel incident</a>.
                        </p>
                    </div>
                @endif
            </div>

            <aside class="aq-track-aside">
                <section class="aq-panel aq-panel-pad">
                    <h2 class="aq-panel__title">Comment ça marche ?</h2>
                    <ol class="aq-numbered">
                        <li><strong>Vous signalez</strong><span>Décrivez le problème et l’endroit concerné.</span></li>
                        <li><strong>Nous vérifions</strong><span>Nos équipes qualifient la situation sur le terrain.</span></li>
                        <li><strong>Vous êtes informé</strong><span>Le statut de votre dossier évolue ici, à chaque étape.</span></li>
                    </ol>
                </section>

                <x-emergency-contacts compact />
            </aside>
        </div>
    </section>

    <section class="section">
        <x-faq />
    </section>
@endsection
