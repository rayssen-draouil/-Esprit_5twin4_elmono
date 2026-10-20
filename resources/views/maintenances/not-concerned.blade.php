@extends('layouts.front', ['title' => 'Accès Non Concerné — AquaSecure'])

@section('content')
<style>
.not-concerned-hero {
    min-height: 85vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    background: radial-gradient(circle at 10% 20%, rgba(22, 183, 163, 0.08) 0%, rgba(7, 63, 74, 0.03) 90%), #f4f8f7;
    position: relative;
    overflow: hidden;
}
.not-concerned-card {
    max-width: 680px;
    width: 100%;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(7, 63, 74, 0.12), 0 1px 3px rgba(7, 63, 74, 0.05);
    border: 1px solid rgba(22, 183, 163, 0.2);
    overflow: hidden;
    position: relative;
    animation: slideUpFade 0.4s ease-out;
}
@keyframes slideUpFade {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.not-concerned-header {
    background: linear-gradient(135deg, #073f4a 0%, #0d5d6d 100%);
    padding: 40px 36px 36px;
    color: #ffffff;
    text-align: center;
    position: relative;
}
.not-concerned-header::after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 15px solid transparent;
    border-right: 15px solid transparent;
    border-top: 15px solid #0d5d6d;
}
.badge-role-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    padding: 6px 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #e6fffb;
    margin-bottom: 16px;
    backdrop-filter: blur(8px);
}
.not-concerned-body {
    padding: 44px 36px 36px;
}
.creative-info-box {
    background: linear-gradient(135deg, #f7faf9 0%, #edf6f5 100%);
    border: 1px solid #d2e8e4;
    border-left: 5px solid #16b7a3;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 28px;
}
.creative-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    margin-top: 32px;
}
.creative-btn-primary {
    background: linear-gradient(135deg, #073f4a 0%, #0e5b6b 100%);
    color: #ffffff !important;
    font-weight: 800;
    padding: 13px 26px;
    border-radius: 10px;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(7, 63, 74, 0.25);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.creative-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(7, 63, 74, 0.35);
}
.creative-btn-outline {
    background: #ffffff;
    border: 1.5px solid #16b7a3;
    color: #073f4a !important;
    font-weight: 700;
    padding: 12px 24px;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.creative-btn-outline:hover {
    background: #f0faf8;
    transform: translateY(-2px);
}
</style>

<div class="not-concerned-hero">
    <div class="not-concerned-card">
        <div class="not-concerned-header">
            <div class="badge-role-pill">
                <span>🛡️</span> Sécurité & Rôles Métier
            </div>
            <div style="font-size: 54px; margin-bottom: 12px; line-height: 1;">⚡👷</div>
            <h1 style="color: #ffffff; font-size: 26px; font-weight: 800; margin: 0 0 10px; letter-spacing: -0.5px;">
                Page Réservée aux Techniciens d'Intervention
            </h1>
            <p style="color: #bce7e1; font-size: 15px; margin: 0; line-height: 1.5;">
                Votre profil actuel n'est pas habilité à confirmer ou modifier cet ordre de mission technique.
            </p>
        </div>

        <div class="not-concerned-body">
            <div class="creative-info-box">
                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <div style="font-size: 24px; line-height: 1;">💡</div>
                    <div style="font-size: 14px; color: #172c32; line-height: 1.6;">
                        <strong>Pourquoi ce message apparaît-il ?</strong><br>
                        @auth
                            Vous êtes actuellement connecté en tant que <strong>{{ Auth::user()->name }}</strong> avec le rôle
                            <span style="background: #073f4a; color: #fff; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 700;">
                                {{ Auth::user()->roleLabel() ?? ucfirst(Auth::user()->role) }}
                            </span>.
                            <br><br>
                            Cet ordre de mission (<strong>{{ $maintenance->reference_code ?? 'MNT-#' . ($maintenance->id ?? '') }}</strong>)
                            est exclusivement assigné au technicien <strong>{{ $technician->name ?? 'désigné' }}</strong>.
                            Seul un compte disposant du rôle <em>Technicien</em> accrédité peut acter la confirmation et faire progresser l'intervention dans l'espace opérationnel.
                        @else
                            Vous n'êtes pas connecté à un compte disposant des habilitations requises de technicien de terrain.
                        @endauth
                    </div>
                </div>
            </div>

            @if(!empty($maintenance))
                <div style="background: #ffffff; border: 1px dashed #c8dbd8; border-radius: 10px; padding: 16px 20px; font-size: 13px; color: #526a6d; margin-bottom: 20px;">
                    <strong style="color: #073f4a; display: block; margin-bottom: 6px;">Détails de l'opération ciblée :</strong>
                    <div style="display: flex; flex-wrap: wrap; gap: 18px;">
                        <span>Référence : <strong>{{ $maintenance->reference_code ?? ('MNT-#' . $maintenance->id) }}</strong></span>
                        <span>Infrastructure : <strong>{{ $maintenance->infrastructure?->name ?? 'Ouvrage d\'eau' }}</strong></span>
                        <span>Statut : <span class="badge {{ $maintenance->status_badge_class }}">{{ $maintenance->display_status_label }}</span></span>
                    </div>
                </div>
            @endif

            <div class="creative-actions">
                @auth
                    @if(in_array(Auth::user()->role, ['manager', 'gestionnaire', 'admin']))
                        <a href="{{ route('manager.dashboard') }}" class="creative-btn-primary">
                            <span>📊</span> Accéder au Tableau de bord Gestionnaire
                        </a>
                        <a href="{{ route('maintenances.show', $maintenance) }}" class="creative-btn-outline">
                            <span>👁</span> Examiner la maintenance en mode Manager
                        </a>
                    @elseif(Auth::user()->role === 'citoyen')
                        <a href="{{ route('citoyen.dashboard') }}" class="creative-btn-primary">
                            <span>🏠</span> Retourner à mon Espace Citoyen
                        </a>
                    @else
                        <a href="{{ route('home') }}" class="creative-btn-primary">
                            <span>🏠</span> Retour à l'accueil
                        </a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="creative-btn-primary">
                        <span>🔑</span> Se connecter avec un compte Technicien
                    </a>
                    <a href="{{ route('home') }}" class="creative-btn-outline">
                        <span>🏠</span> Page d'accueil
                    </a>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
