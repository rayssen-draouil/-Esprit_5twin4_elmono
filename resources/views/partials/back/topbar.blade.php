<header class="topbar">
    <button class="mobile-sidebar" aria-label="Menu">☰</button>
    <div class="breadcrumb">
        AquaSecure <span>/</span> <strong>{{ $title ?? "Vue d'ensemble" }}</strong>
    </div>

    <div class="top-actions">
        <input class="topbar-search" type="search" placeholder="Rechercher...">

        <div class="notification-menu">
            <button class="notification-toggle" aria-label="Notifications" id="notificationBell">
                𔔨<span class="badge badge-danger" id="unreadCount">{{ Auth::user()->unreadNotifications()->count() }}</span>
            </button>
            <div class="notification-panel" id="notificationPanel">
                <strong>Centre de Notifications</strong>
                <div id="notificationList">
                    @forelse (Auth::user()->unreadNotifications()->take(6) as $notification)
                        <a href="{{ $notification->data['resource_url'] ?? '#' }}" class="notification-item">
                            <div class="notification-title" style="font-weight: 700; color: #073f4a;">
                                {{ $notification->data['title'] }}
                            </div>
                            <div class="notification-message" style="font-size: 13px; color: #526a6d; margin: 2px 0;">
                                {{ $notification->data['message'] }}
                            </div>
                            <div class="notification-meta" style="font-size: 11px; color: #8fa0a3; display: flex; justify-content: space-between; align-items: center;">
                                <span>{{ $notification->created_at->format('d/m/Y H:i') }} · <small>{{ $notification->data['actioned_by'] ?? '' }}</small></span>
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}" style="display:inline;" onclick="event.stopPropagation();">
                                    @csrf
                                    @method('PUT')
                                    <button class="button button-ghost button-small" type="submit" style="padding: 2px 6px; font-size: 11px;">Lu</button>
                                </form>
                            </div>
                        </a>
                    @empty
                        <p style="font-size:12px;color:#666; padding: 12px 0; text-align: center;">Aucune notification non lue.</p>
                    @endforelse
                </div>
                <div style="margin-top:10px; border-top: 1px solid var(--line); padding-top: 8px; text-align: right;">
                    <form method="POST" action="{{ route('notifications.read-all') }}" style="display:inline;">
                        @csrf
                        @method('PUT')
                        <button class="button button-ghost button-small" type="submit">Tout marquer comme lu</button>
                    </form>
                </div>
            </div>
        </div>

        <a href="{{ route('profile.edit') }}">
            <strong>{{ Auth::user()->name }}</strong>
            <small>{{ Auth::user()->roleLabel() }}</small>
        </a>
    </div>
</header>

{{-- In-App Pop-up Toast and Browser Push Notification for recent technician status updates --}}
@if($latestNotification = Auth::user()->unreadNotifications()->latest()->first())
    @php
        $isRecent = $latestNotification->created_at->diffInMinutes(now()) <= 120;
    @endphp
    @if($isRecent)
        <div id="managerNotificationToast" style="position: fixed; bottom: 25px; right: 25px; z-index: 999999; background: #ffffff; border-radius: 12px; box-shadow: 0 16px 45px rgba(7, 63, 74, 0.2), 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #d2e8e4; border-left: 6px solid #16b7a3; max-width: 400px; padding: 18px 20px; animation: toastSlideIn 0.4s ease-out;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px; line-height: 1;">⚡</span>
                    <strong style="color: #073f4a; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Statut Maintenance Actualisé</strong>
                </div>
                <button type="button" onclick="document.getElementById('managerNotificationToast').remove()" style="background:none; border:none; color:#708388; font-size:18px; cursor:pointer; line-height:1; padding: 0;">&times;</button>
            </div>
            <div style="margin-top: 8px; font-weight: 700; font-size: 14px; color: #172c32;">
                {{ $latestNotification->data['title'] ?? 'Mise à jour d\'intervention' }}
            </div>
            <p style="margin: 4px 0 14px; font-size: 13px; color: #526a6d; line-height: 1.45;">
                {{ $latestNotification->data['message'] ?? '' }}
            </p>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2ecea; padding-top: 10px;">
                <small style="color: #708388; font-size: 11px;">{{ $latestNotification->created_at->diffForHumans() }}</small>
                <a href="{{ $latestNotification->data['resource_url'] ?? '#' }}" style="background: #073f4a; color: #ffffff !important; text-decoration: none; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                    Consulter →
                </a>
            </div>
        </div>

        <script>
            (function() {
                if ("Notification" in window) {
                    if (Notification.permission === "granted") {
                        try {
                            new Notification("{{ addslashes($latestNotification->data['title'] ?? 'AquaSecure') }}", {
                                body: "{{ addslashes($latestNotification->data['message'] ?? '') }}",
                                icon: "/favicon.ico"
                            });
                        } catch (e) {
                            console.warn("Browser notification skipped:", e);
                        }
                    } else if (Notification.permission !== "denied") {
                        Notification.requestPermission();
                    }
                }
            })();
        </script>
    @endif
@endif
