import './bootstrap';
import './infra-validation';

document.querySelector('.menu-toggle')?.addEventListener('click', () => {
    document.querySelector('.site-header nav')?.classList.toggle('mobile-open');
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
