@extends('layouts.front', ['title' => 'Affectation d’intervention'])

@section('content')
<div style="background: linear-gradient(180deg, #edf6f4 0%, #f4f8f7 100%); min-height: 85vh; padding: 50px 20px;">
    <div style="max-width: 820px; margin: 0 auto;">

        {{-- Alerts --}}
        @if(session('success'))
            <div style="background: #def5ed; border-left: 5px solid #13886f; color: #13886f; padding: 18px 24px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(19, 136, 111, 0.1);">
                <div style="font-weight: 800; font-size: 16px; margin-bottom: 4px;">✓ Succès</div>
                <div style="font-size: 14px;">{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div style="background: #ffe4e1; border-left: 5px solid #bd5149; color: #bd5149; padding: 18px 24px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(189, 81, 73, 0.1);">
                <div style="font-weight: 800; font-size: 16px; margin-bottom: 4px;">⚠ Attention</div>
                <div style="font-size: 14px;">{{ session('error') }}</div>
            </div>
        @endif

        @if(session('info'))
            <div style="background: #e3f0f7; border-left: 5px solid #347b9a; color: #347b9a; padding: 18px 24px; border-radius: 8px; margin-bottom: 25px;">
                <div style="font-weight: 800; font-size: 16px; margin-bottom: 4px;">ℹ Information</div>
                <div style="font-size: 14px;">{{ session('info') }}</div>
            </div>
        @endif

        @if(!$invitation || !$maintenance)
            {{-- Invalid / Expired Token --}}
            <div style="background: #fff; border: 1px solid #dce7e5; border-radius: 12px; padding: 45px 35px; text-align: center; box-shadow: 0 10px 30px rgba(7, 63, 74, 0.06);">
                <div style="font-size: 48px; margin-bottom: 15px;">🔒</div>
                <h1 style="color: #073f4a; font-size: 26px; margin-bottom: 10px;">Lien d'invitation invalide ou introuvable</h1>
                <p style="color: #708388; font-size: 15px; max-width: 500px; margin: 0 auto 25px;">
                    Ce lien d'invitation n'est plus valide, a expiré ou a déjà été consommé. Si vous pensez qu'il s'agit d'une erreur, veuillez contacter le gestionnaire réseau AquaSecure.
                </p>
                <a href="{{ route('home') }}" class="button" style="background: #073f4a;">Retour à l'accueil</a>
            </div>
        @else
            {{-- Main Mission Card --}}
            <div style="background: #fff; border: 1px solid #dce7e5; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(7, 63, 74, 0.06);">
                {{-- Card Header --}}
                <div style="background: #073f4a; color: #fff; padding: 30px 35px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
                    <div>
                        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #16b7a3; font-weight: 800; margin-bottom: 5px;">
                            Ordre d'intervention technique
                        </div>
                        <h1 style="color: #fff; font-size: 24px; margin: 0; letter-spacing: -0.5px;">
                            {{ $maintenance->reference_code ?? ('MNT-#' . $maintenance->id) }}
                        </h1>
                    </div>

                    <div style="text-align: right;">
                        <span class="badge {{ $invitation->status_badge_class }}" style="font-size: 12px; padding: 6px 14px; border-radius: 20px;">
                            {{ $invitation->display_status_label }}
                        </span>
                        @if($invitation->expires_at && $invitation->isPending())
                            <div style="color: #a8c1c0; font-size: 11px; margin-top: 6px;">
                                Expire le {{ $invitation->expires_at->format('d/m/Y à H:i') }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Greeting & Overview --}}
                <div style="padding: 35px;">
                    <p style="font-size: 16px; color: #172c32; margin-top: 0; margin-bottom: 25px;">
                        Bonjour <strong>{{ $technician->name }}</strong>, vous avez été sollicité pour prendre en charge l'intervention suivante :
                    </p>

                    {{-- Technical Specifications Grid --}}
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 30px;">
                        <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 8px; padding: 18px;">
                            <span style="display: block; font-size: 11px; font-weight: 700; color: #708388; text-transform: uppercase;">Infrastructure</span>
                            <strong style="display: block; font-size: 16px; color: #073f4a; margin-top: 4px;">
                                {{ $maintenance->infrastructure?->name ?? 'Non spécifiée' }}
                            </strong>
                            <small style="color: #708388; font-size: 12px;">{{ $maintenance->infrastructure?->type }}</small>
                        </div>

                        <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 8px; padding: 18px;">
                            <span style="display: block; font-size: 11px; font-weight: 700; color: #708388; text-transform: uppercase;">Zone d'intervention</span>
                            <strong style="display: block; font-size: 16px; color: #073f4a; margin-top: 4px;">
                                {{ $maintenance->infrastructure?->zone?->name ?? 'Zone centrale' }}
                            </strong>
                            <small style="color: #708388; font-size: 12px;">{{ $maintenance->infrastructure?->zone?->address ?? 'Bassin hydraulique' }}</small>
                        </div>

                        <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 8px; padding: 18px;">
                            <span style="display: block; font-size: 11px; font-weight: 700; color: #708388; text-transform: uppercase;">Programmation</span>
                            <strong style="display: block; font-size: 16px; color: #073f4a; margin-top: 4px;">
                                {{ $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y à H:i') : 'Date à convenir' }}
                            </strong>
                            <small style="color: #708388; font-size: 12px;">Durée estimée : {{ $maintenance->duration_hours ? $maintenance->duration_hours . ' h' : '2 h' }}</small>
                        </div>

                        <div style="background: #f7faf9; border: 1px solid #e5eeee; border-radius: 8px; padding: 18px;">
                            <span style="display: block; font-size: 11px; font-weight: 700; color: #708388; text-transform: uppercase;">Priorité & Type</span>
                            <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                <span class="badge {{ $maintenance->priority_badge_class }}">
                                    {{ $maintenance->priority_label }}
                                </span>
                                <span style="font-size: 14px; font-weight: 700; color: #172c32;">
                                    {{ $maintenance->type }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Work Description --}}
                    <div style="background: #fbfdfc; border: 1px solid #dce7e5; border-radius: 8px; padding: 22px; margin-bottom: 30px;">
                        <h3 style="font-size: 14px; text-transform: uppercase; color: #708388; letter-spacing: 1px; margin-top: 0; margin-bottom: 10px;">
                            Détail de la mission & consignes techniques :
                        </h3>
                        <p style="font-size: 15px; color: #172c32; line-height: 1.6; margin: 0;">
                            {{ $maintenance->description ?: 'Aucune consigne particulière.' }}
                        </p>
                    </div>

                    {{-- Actions Section --}}
                    @if($invitation->isPending())
                        <div style="border-top: 1px solid #edf2f1; padding-top: 25px;">
                            <div style="background: #fff9eb; border-left: 4px solid #aa721c; padding: 14px 18px; border-radius: 6px; margin-bottom: 25px; font-size: 13px; color: #784a05;">
                                <strong>Veuillez confirmer votre décision :</strong> L'acceptation verrouillera l'intervention à votre nom et basculera son statut à <em>« En cours d'exécution »</em>.
                            </div>

                            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                                {{-- Accept Form (POST with CSRF) --}}
                                <form method="POST" action="{{ route('maintenances.invitation.accept', ['token' => $token]) }}" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="button" style="background: #13886f; color: #fff; padding: 14px 28px; font-size: 15px; font-weight: 800; border-radius: 6px; cursor: pointer; box-shadow: 0 4px 15px rgba(19, 136, 111, 0.25);">
                                        ✓ Accepter l'intervention
                                    </button>
                                </form>

                                {{-- Decline Trigger --}}
                                <button type="button" class="button button-outline" onclick="document.getElementById('decline-form-box').style.display = 'block'; this.style.display = 'none';" style="border-color: #bd5149; color: #bd5149; padding: 14px 24px; font-size: 14px; border-radius: 6px; cursor: pointer;">
                                    ✕ Décliner la mission
                                </button>
                            </div>

                            {{-- Hidden Decline Form Box --}}
                            <div id="decline-form-box" style="display: none; margin-top: 25px; background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px; padding: 22px;">
                                <h4 style="color: #bd5149; margin-top: 0; font-size: 15px;">Décliner cette intervention</h4>
                                <form method="POST" action="{{ route('maintenances.invitation.decline', ['token' => $token]) }}">
                                    @csrf
                                    <label for="decline_reason" style="display: block; font-size: 13px; font-weight: 700; color: #526a6d; margin-bottom: 8px;">
                                        Motif du refus (optionnel mais recommandé pour le gestionnaire) :
                                    </label>
                                    <textarea id="decline_reason" name="decline_reason" rows="3" placeholder="Ex: Indisponibilité sur ce créneau, compétences non adaptées, surcharge..." style="width: 100%; border: 1px solid #dce7e5; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px; margin-bottom: 15px;"></textarea>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="button" style="background: #bd5149; color: #fff; padding: 10px 20px; font-size: 13px;">
                                            Confirmer le refus
                                        </button>
                                        <button type="button" class="button button-ghost" onclick="document.getElementById('decline-form-box').style.display = 'none';">
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @elseif($invitation->isAccepted())
                        <div style="border-top: 1px solid #edf2f1; padding-top: 25px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
                            <div style="color: #13886f; font-weight: 700;">
                                ✓ Vous avez accepté cette intervention le {{ $invitation->responded_at?->format('d/m/Y à H:i') }}.
                            </div>
                            @auth
                                @if(Auth::user()->role === 'technician')
                                    <a href="{{ route('technicien.interventions.show', $maintenance) }}" class="button" style="background: #073f4a;">
                                        Accéder à mon ordre de mission →
                                    </a>
                                @endif
                            @endauth
                        </div>
                    @elseif($invitation->isDeclined())
                        <div style="border-top: 1px solid #edf2f1; padding-top: 25px; color: #708388;">
                            Cette intervention a été déclinée le {{ $invitation->responded_at?->format('d/m/Y à H:i') }}.
                            @if($invitation->decline_reason)
                                <div style="margin-top: 6px; font-size: 13px; font-style: italic;">
                                    Motif indiqué : « {{ $invitation->decline_reason }} »
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
