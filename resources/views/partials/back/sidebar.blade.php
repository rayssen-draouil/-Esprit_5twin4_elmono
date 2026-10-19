<aside class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}">
        <span class="brand-mark">A</span>
        <span>Aqua<span>Secure</span></span>
    </a>

    <p class="sidebar-label">OVERVIEW</p>

    <nav>
        <a class="{{ request()->routeIs('dashboard', 'back.dashboard', '*.dashboard') ? 'active' : '' }}"
           href="{{ route('dashboard') }}">
            ◈ <span>Dashboard</span>
        </a>

        @if(in_array(Auth::user()->role, ['admin', 'manager', 'gestionnaire'], true))
            <p class="sidebar-label">MONITORING</p>

            <a class="{{ request()->routeIs('back.incidents') ? 'active' : '' }}"
               href="{{ route('back.incidents') }}">
                ⚠ <span>Incidents</span>
            </a>

            <a class="{{ request()->routeIs('back.zones.*', 'back.alerts.*') ? 'active' : '' }}"
               href="{{ route('back.zones.index') }}">
                ◎ <span>Zones</span>
            </a>

            <a class="{{ request()->routeIs('infrastructures.*') || request()->routeIs('back.infrastructures') ? 'active' : '' }}"
               href="{{ route('infrastructures.index') }}">
                ▦ <span>Infrastructures</span>
            </a>

            <a class="{{ request()->routeIs('maintenances.*') ? 'active' : '' }}"
               href="{{ route('maintenances.index') }}">
                ⛯ <span>Maintenances</span>
            </a>

            <a class="{{ request()->routeIs('maintenance-intelligence.*') ? 'active' : '' }}"
               href="{{ route('maintenance-intelligence.index') }}">
                ✨ <span>AI Maintenance Intelligence</span>
            </a>

            <a class="{{ request()->routeIs('interventions.*') ? 'active' : '' }}"
               href="{{ route('interventions.index') }}">
                ⚒ <span>Interventions</span>
            </a>

            <a class="{{ request()->routeIs('techniciens.*') ? 'active' : '' }}"
               href="{{ route('techniciens.index') }}">
                ♙ <span>Techniciens</span>
            </a>

            <p class="sidebar-label">PROJECTS</p>

            <a class="{{ request()->routeIs('projects.*') || request()->routeIs('back.projects') ? 'active' : '' }}"
               href="{{ route('projects.index') }}">
                ◫ <span>Projets</span>
            </a>

            <a class="{{ request()->routeIs('financements.*') || request()->routeIs('back.funding') ? 'active' : '' }}"
               href="{{ route('financements.index') }}">
                ◒ <span>Financement</span>
            </a>

            <p class="sidebar-label">MANAGEMENT</p>

            @if(Auth::user()->role === 'admin')
                <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                   href="{{ route('admin.users.index') }}">
                    ♙ <span>Utilisateurs</span>
                </a>
            @endif
        @endif

        @if(in_array(Auth::user()->role, ['technician'], true))
            <p class="sidebar-label">ESPACE OPÉRATIONNEL</p>

            <a class="{{ request()->routeIs('technicien.interventions.*') ? 'active' : '' }}"
               href="{{ route('technicien.interventions.index') }}">
                ⚒ <span>Mes interventions</span>
            </a>
        @endif
    </nav>

    <div class="sidebar-bottom">
        <a href="{{ route('profile.edit') }}">
            ◉ <span>Mon profil</span>
        </a>

        <a href="{{ route('home') }}">
            ↩ <span>Voir le site public</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="sidebar-logout" type="submit">
                ⇥ <span>Se déconnecter</span>
            </button>
        </form>

        <div class="profile">
            <span class="avatar">
                {{ collect(explode(' ', Auth::user()->name))->map(fn ($name) => substr($name, 0, 1))->join('') }}
            </span>
            <span>
                <strong>{{ Auth::user()->name }}</strong>
                <small>{{ ucfirst(Auth::user()->role) }}</small>
            </span>
        </div>
    </div>
</aside>
