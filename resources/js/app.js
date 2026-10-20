import 'chart.js';
import './bootstrap';
import './infra-validation';

const navToggle = document.querySelector('[data-nav-toggle]');
const siteNav = document.querySelector('[data-site-nav]');
const navDropdown = document.querySelector('[data-nav-dropdown]');
const navDropdownToggle = navDropdown?.querySelector('[data-nav-dropdown-toggle]');
const navDropdownMenu = navDropdown?.querySelector('[data-nav-dropdown-menu]');
const mobileNavQuery = window.matchMedia('(max-width: 1024px)');

const setDropdownOpen = (open) => {
    if (!navDropdown || !navDropdownToggle || !navDropdownMenu) return;
    navDropdown.classList.toggle('is-open', open);
    navDropdownToggle.setAttribute('aria-expanded', String(open));
    navDropdownMenu.hidden = !open;
};

const setNavOpen = (open) => {
    if (!siteNav || !navToggle) return;
    siteNav.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', String(open));
    navToggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    if (open && mobileNavQuery.matches) {
        siteNav.querySelector('a, button')?.focus();
    }
    if (!open) setDropdownOpen(false);
};

navToggle?.addEventListener('click', () => {
    setNavOpen(navToggle.getAttribute('aria-expanded') !== 'true');
});

navDropdownToggle?.addEventListener('click', () => {
    setDropdownOpen(navDropdownToggle.getAttribute('aria-expanded') !== 'true');
});

navDropdownToggle?.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
    event.preventDefault();
    setDropdownOpen(true);
    const items = [...navDropdownMenu.querySelectorAll('a')];
    items[event.key === 'ArrowDown' ? 0 : items.length - 1]?.focus();
});

navDropdownMenu?.addEventListener('keydown', (event) => {
    const items = [...navDropdownMenu.querySelectorAll('a')];
    const index = items.indexOf(document.activeElement);
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const next = event.key === 'ArrowDown' ? index + 1 : index - 1;
        items[(next + items.length) % items.length]?.focus();
    } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        items[event.key === 'Home' ? 0 : items.length - 1]?.focus();
    } else if (event.key === 'Escape') {
        event.preventDefault();
        setDropdownOpen(false);
        navDropdownToggle?.focus();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (navDropdown?.classList.contains('is-open')) {
        setDropdownOpen(false);
        navDropdownToggle?.focus();
    } else if (siteNav?.classList.contains('is-open')) {
        setNavOpen(false);
        navToggle?.focus();
    }
});

document.addEventListener('click', (event) => {
    if (navDropdown && !navDropdown.contains(event.target)) setDropdownOpen(false);
});

navDropdown?.addEventListener('focusout', (event) => {
    if (!navDropdown.contains(event.relatedTarget)) setDropdownOpen(false);
});

mobileNavQuery.addEventListener('change', (event) => {
    if (!event.matches) setNavOpen(false);
});

siteNav?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setNavOpen(false));
});
document.querySelector('#incident-photo')?.addEventListener('change', (event) => {
    const input = event.currentTarget;
    const fileName = document.querySelector('.file-name');
    if (fileName) fileName.textContent = input.files?.[0]?.name ?? 'Aucun fichier sélectionné';
});
document.querySelector('.mobile-sidebar')?.addEventListener('click', () => {
    const sidebar = document.querySelector('.sidebar');
    const main = document.querySelector('.back-main');
    if (window.matchMedia('(min-width: 901px)').matches) {
        sidebar?.classList.toggle('collapsed');
        main?.classList.toggle('sidebar-collapsed');
    } else {
        sidebar?.classList.toggle('open');
    }
});

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach((element) => {
    if (prefersReducedMotion) element.classList.add('is-visible');
    else revealObserver.observe(element);
});

document.querySelectorAll('[data-counter]').forEach((counter) => {
    const target = Number(counter.dataset.counter.replace(/[^\d]/g, ''));
    const observer = new IntersectionObserver(([entry], observerInstance) => {
        if (!entry.isIntersecting) return;
        if (prefersReducedMotion) {
            counter.textContent = target.toLocaleString('fr-FR');
            observerInstance.disconnect();
            return;
        }
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 45));
        const timer = window.setInterval(() => {
            current = Math.min(target, current + step);
            counter.textContent = current.toLocaleString('fr-FR');
            if (current === target) window.clearInterval(timer);
        }, 25);
        observerInstance.disconnect();
    }, { threshold: 0.5 });
    observer.observe(counter);
});

document.querySelectorAll('.filter-tabs button').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.filter-tabs button').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        const filter = button.dataset.filter;
        document.querySelectorAll('[data-incident-type]').forEach((incident) => {
            incident.hidden = filter !== 'all' && incident.dataset.incidentType !== filter;
        });
    });
});

document.querySelector('.notification-toggle')?.addEventListener('click', (event) => {
    event.stopPropagation();
    document.querySelector('.notification-menu')?.classList.toggle('open');
});
document.addEventListener('click', () => document.querySelector('.notification-menu')?.classList.remove('open'));

const incidentSearch = document.querySelector('.toolbar .search');
const incidentFilters = document.querySelectorAll('.toolbar select');
const incidentRows = document.querySelectorAll('.table-wrap tbody tr');
const filterIncidentRows = () => {
    const query = incidentSearch?.value.toLowerCase() ?? '';
    const filters = [...incidentFilters].map((select) => select.value);
    incidentRows.forEach((row) => {
        const content = row.textContent.toLowerCase();
        const matchesText = content.includes(query);
        const matchesFilters = filters.every((filter) => filter.startsWith('Tous') || filter.startsWith('Toutes') || content.includes(filter.toLowerCase()));
        row.hidden = !(matchesText && matchesFilters);
    });
};
incidentSearch?.addEventListener('input', filterIncidentRows);
incidentFilters.forEach((filter) => filter.addEventListener('change', filterIncidentRows));
