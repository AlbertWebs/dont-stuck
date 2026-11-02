document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const storedTheme = window.localStorage.getItem('dss-theme');

    if (storedTheme === 'dark') {
        body.classList.add('theme-dark');
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            body.classList.toggle('theme-dark');
            window.localStorage.setItem('dss-theme', body.classList.contains('theme-dark') ? 'dark' : 'light');
        });
    });

    const menuToggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');

    if (menuToggle && menu) {
        menuToggle.addEventListener('click', () => {
            const isOpen = menu.classList.toggle('is-open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    const adminToggle = document.querySelector('[data-admin-menu-toggle]');
    const adminSidebar = document.querySelector('[data-admin-sidebar]');

    if (adminToggle && adminSidebar) {
        adminToggle.addEventListener('click', () => adminSidebar.classList.toggle('is-open'));
        document.addEventListener('click', (event) => {
            if (window.innerWidth <= 760 && !adminSidebar.contains(event.target) && !adminToggle.contains(event.target)) {
                adminSidebar.classList.remove('is-open');
            }
        });
    }

    const clientMenuToggle = document.querySelector('[data-client-menu-toggle]');
    const clientSidebar = document.querySelector('[data-client-sidebar]');

    if (clientMenuToggle && clientSidebar) {
        clientMenuToggle.addEventListener('click', () => {
            const isOpen = clientSidebar.classList.toggle('is-open');
            clientMenuToggle.setAttribute('aria-expanded', String(isOpen));
        });
        document.addEventListener('click', (event) => {
            if (window.innerWidth <= 760 && !clientSidebar.contains(event.target) && !clientMenuToggle.contains(event.target)) {
                clientSidebar.classList.remove('is-open');
                clientMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    const statusSelect = document.querySelector('[data-status-select]');
    const approvalFields = document.querySelector('[data-approval-fields]');

    if (statusSelect && approvalFields) {
        const syncApprovalFields = () => {
            const isApproval = statusSelect.value === 'approved';
            approvalFields.hidden = !isApproval;
            approvalFields.querySelectorAll('input').forEach((input) => {
                input.required = isApproval;
            });
        };

        statusSelect.addEventListener('change', syncApprovalFields);
        syncApprovalFields();
    }

    const stepForm = document.querySelector('[data-multi-step]');

    if (stepForm) {
        const steps = [...stepForm.querySelectorAll('[data-step]')];
        const labels = [...stepForm.querySelectorAll('[data-step-label]')];
        const back = stepForm.querySelector('[data-step-back]');
        const next = stepForm.querySelector('[data-step-next]');
        const submit = stepForm.querySelector('[data-step-submit]');
        const count = stepForm.querySelector('[data-step-count]');
        const progress = stepForm.querySelector('[data-progress-bar]');
        let current = 0;

        const render = () => {
            steps.forEach((step, index) => {
                step.classList.toggle('is-active', index === current);
                step.setAttribute('aria-hidden', String(index !== current));
            });
            labels.forEach((label, index) => {
                label.classList.toggle('is-current', index === current);
                label.classList.toggle('is-complete', index < current);
            });
            count.textContent = `Step ${current + 1} of ${steps.length}`;
            progress.style.width = `${((current + 1) / steps.length) * 100}%`;
            back.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            submit.hidden = current !== steps.length - 1;
        };

        const validateCurrentStep = () => {
            const controls = [...steps[current].querySelectorAll('input, select, textarea')].filter((control) => !control.disabled);
            const invalid = controls.find((control) => !control.checkValidity());

            if (invalid) {
                invalid.reportValidity();
                invalid.focus();
                return false;
            }

            return true;
        };

        next.addEventListener('click', () => {
            if (validateCurrentStep() && current < steps.length - 1) {
                current += 1;
                render();
                stepForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        back.addEventListener('click', () => {
            current = Math.max(0, current - 1);
            render();
            stepForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        stepForm.addEventListener('submit', (event) => {
            if (!validateCurrentStep()) {
                event.preventDefault();
            }
        });
        render();
    }

    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
});
