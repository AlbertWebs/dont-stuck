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
        const feedback = stepForm.querySelector('[data-application-feedback]');
        const fileInputs = [...stepForm.querySelectorAll('[data-document-upload]')];
        const navigationButtons = [back, next, submit];
        const maximumDocumentSize = 25 * 1024 * 1024;
        const submitLabel = submit.innerHTML;
        let isSubmitting = false;

        const showFeedback = (message, state) => {
            feedback.className = `application-feedback is-${state}`;
            feedback.setAttribute('role', state === 'error' ? 'alert' : 'status');
            feedback.textContent = message;
            feedback.hidden = false;
        };

        const clearServerErrors = () => {
            stepForm.querySelectorAll('.application-ajax-error').forEach((error) => error.remove());
            stepForm.querySelectorAll('[aria-invalid="true"]').forEach((control) => control.removeAttribute('aria-invalid'));
        };

        const displayServerErrors = (errors) => {
            const normalizeFieldName = (name) => name.replace(/[\[\]]/g, '.').replace(/\.+/g, '.').replace(/^\.|\.$/g, '');
            let firstInvalid = null;

            Object.entries(errors).forEach(([name, messages]) => {
                const control = [...stepForm.elements].find((element) => element.name && normalizeFieldName(element.name) === name);
                const container = control?.closest('.field, .upload-field, .check-row');
                const message = Array.isArray(messages) ? messages[0] : String(messages);

                if (control) {
                    control.setAttribute('aria-invalid', 'true');
                    firstInvalid ??= control;
                }

                if (container) {
                    const error = document.createElement('small');
                    error.className = 'field-error application-ajax-error';
                    error.textContent = message;
                    container.append(error);
                }
            });

            if (firstInvalid) {
                const invalidStep = steps.indexOf(firstInvalid.closest('[data-step]'));
                if (invalidStep >= 0) {
                    current = invalidStep;
                    render();
                    firstInvalid.focus();
                }
            }
        };

        fileInputs.forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                const extension = file?.name.split('.').pop()?.toLowerCase();
                let errorMessage = '';

                if (file && file.size > maximumDocumentSize) {
                    errorMessage = 'Each document must be no larger than 25 MB.';
                } else if (file && !['pdf', 'jpg', 'jpeg', 'png'].includes(extension)) {
                    errorMessage = 'Choose a PDF, JPG or PNG file.';
                }

                input.setCustomValidity(errorMessage);
                if (!errorMessage) {
                    input.removeAttribute('aria-invalid');
                    input.closest('.upload-field')?.querySelector('.application-ajax-error')?.remove();
                }
            });
        });

        stepForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (isSubmitting || !validateCurrentStep()) {
                return;
            }

            clearServerErrors();
            feedback.hidden = true;
            isSubmitting = true;
            stepForm.setAttribute('aria-busy', 'true');
            navigationButtons.forEach((button) => {
                button.disabled = true;
            });
            submit.textContent = 'Submitting…';
            let applicationSubmitted = false;

            try {
                const response = await fetch(stepForm.action, {
                    method: 'POST',
                    body: new FormData(stepForm),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json().catch(() => ({}));

                if (response.status === 422) {
                    showFeedback(result.message || 'Please review the highlighted fields and try again.', 'error');
                    displayServerErrors(result.errors || {});
                    return;
                }

                if (response.status === 413) {
                    showFeedback('The upload is too large. Keep each document below 25 MB and try again.', 'error');
                    return;
                }

                if (!response.ok || !result.reference) {
                    showFeedback(result.message || 'We could not submit your application. Please try again.', 'error');
                    return;
                }

                applicationSubmitted = true;
                stepForm.querySelector('.form-progress').hidden = true;
                steps.forEach((step) => {
                    step.hidden = true;
                });
                stepForm.querySelector('.form-navigation').hidden = true;
                feedback.className = 'application-feedback is-success';
                feedback.setAttribute('role', 'status');
                feedback.replaceChildren();

                const heading = document.createElement('strong');
                heading.textContent = result.message || 'Your application has been received.';
                const reference = document.createElement('span');
                reference.textContent = `Application reference: ${result.reference}`;
                const confirmationLink = document.createElement('a');
                confirmationLink.href = result.confirmation_url;
                confirmationLink.textContent = 'View confirmation';
                feedback.append(heading, reference, confirmationLink);
                feedback.hidden = false;
            } catch {
                showFeedback('We could not reach the server. Check your connection and try again.', 'error');
            } finally {
                isSubmitting = false;
                stepForm.removeAttribute('aria-busy');

                if (!applicationSubmitted) {
                    navigationButtons.forEach((button) => {
                        button.disabled = false;
                    });
                    submit.innerHTML = submitLabel;
                }
            }
        });
        render();
    }

    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
});
