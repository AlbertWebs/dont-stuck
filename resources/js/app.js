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
        const closeMenu = () => {
            menu.classList.remove('is-open');
            menuToggle.classList.remove('is-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open navigation');
        };

        menuToggle.addEventListener('click', () => {
            const isOpen = menu.classList.toggle('is-open');
            menuToggle.classList.toggle('is-open', isOpen);
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        });

        menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.classList.contains('is-open')) {
                closeMenu();
                menuToggle.focus();
            }
        });
        document.addEventListener('click', (event) => {
            if (window.innerWidth <= 760 && menu.classList.contains('is-open') && !menu.contains(event.target) && !menuToggle.contains(event.target)) {
                closeMenu();
            }
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
        const paymentSummary = stepForm.querySelector('[data-payment-summary]');
        const summaryMessage = paymentSummary?.querySelector('[data-payment-summary-message]');
        const summaryMethod = paymentSummary?.querySelector('[data-payment-summary-method]');
        const summaryBody = paymentSummary?.querySelector('[data-payment-summary-body]');
        const loanProductInput = stepForm.querySelector('[name="loan_product_id"]');
        const requestedAmountInput = stepForm.querySelector('[name="requested_amount"]');
        const requestedMonthsInput = stepForm.querySelector('[name="requested_months"]');
        const requestedAmountWords = stepForm.querySelector('[data-application-amount-words]');
        const requestedAmountWordsInput = stepForm.querySelector('[data-application-amount-words-input]');
        const borrowerSignature = stepForm.querySelector('[data-borrower-signature]');
        const borrowerSignaturePreview = stepForm.querySelector('[data-borrower-signature-preview]');
        const guarantorAmountFigures = [...stepForm.querySelectorAll('[data-guarantor-amount-figure]')];
        const guarantorAmountWords = [...stepForm.querySelectorAll('[data-guarantor-amount-words]')];
        const latePenaltyTerms = [...stepForm.querySelectorAll('[data-application-late-penalty]')];
        let current = 0;
        let quoteRequestId = 0;
        let quoteAbortController = null;
        let quoteDebounce;
        let quoteIsReady = false;

        const formatMoney = (amount) => `KSh ${Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        const updatePaymentSummary = async () => {
            if (!paymentSummary || !loanProductInput.value || !requestedAmountInput.value || !requestedMonthsInput.value) {
                quoteIsReady = false;
                if (summaryMessage) {
                    summaryMessage.textContent = 'Complete the loan amount and repayment period to see an estimate.';
                }
                if (summaryMethod) {
                    summaryMethod.textContent = 'Awaiting loan details';
                }
                if (summaryBody) {
                    summaryBody.replaceChildren();
                    summaryBody.hidden = true;
                }
                requestedAmountWords.textContent = 'Enter a loan amount to calculate its wording.';
                requestedAmountWordsInput.value = '';
                guarantorAmountFigures.forEach((element) => { element.textContent = 'KSh —'; });
                guarantorAmountWords.forEach((element) => { element.textContent = 'Complete the loan amount and repayment period to calculate the guarantee amount.'; });

                return;
            }

            quoteAbortController?.abort();
            quoteAbortController = new AbortController();
            const requestId = ++quoteRequestId;
            quoteIsReady = false;
            summaryMessage.textContent = 'Calculating your estimated payments…';

            const quoteUrl = new URL(stepForm.dataset.quoteUrl, window.location.origin);
            quoteUrl.search = new URLSearchParams({
                loan_product_id: loanProductInput.value,
                requested_amount: requestedAmountInput.value,
                requested_months: requestedMonthsInput.value,
            }).toString();

            try {
                const response = await fetch(quoteUrl, {
                    headers: { Accept: 'application/json' },
                    signal: quoteAbortController.signal,
                });
                const result = await response.json().catch(() => ({}));

                if (requestId !== quoteRequestId) {
                    return;
                }

                if (!response.ok) {
                    const firstError = Object.values(result.errors || {}).flat()[0];
                    summaryMethod.textContent = 'Estimate unavailable';
                    summaryMessage.textContent = firstError || 'We could not calculate this estimate. Check the amount, product and term.';
                    summaryBody.replaceChildren();
                    summaryBody.hidden = true;

                    return;
                }

                const totals = [
                    ['Approved principal requested', result.principal],
                    ['Interest over the loan term', result.total_interest],
                    ['Processing fee withheld', result.processing_fee],
                    ['Legal fee withheld', result.legal_fee],
                    ['Estimated payout after fees', result.net_disbursement],
                    ['Total scheduled repayment', result.total_repayment],
                    ['Total interest and fees', result.total_cost],
                    ['Processing fee rate', `${result.processing_fee_rate}%`],
                    ['Total repayment in words', result.total_repayment_words],
                    ['Daily late-payment penalty stated in the form', `${formatMoney(result.daily_late_fee)} per overdue day`],
                ];
                const totalsGrid = document.createElement('dl');
                totalsGrid.className = 'payment-summary-totals';
                totals.forEach(([label, amount]) => {
                    const row = document.createElement('div');
                    const term = document.createElement('dt');
                    const value = document.createElement('dd');
                    term.textContent = label;
                    value.textContent = label.includes('in words') || label.includes('penalty stated') || label.includes('fee rate') ? amount : formatMoney(amount);
                    row.append(term, value);
                    totalsGrid.append(row);
                });

                const installmentsHeading = document.createElement('h3');
                installmentsHeading.textContent = 'Estimated monthly installments';
                const installmentList = document.createElement('ol');
                installmentList.className = 'payment-installment-list';
                result.installments.forEach((installment) => {
                    const item = document.createElement('li');
                    const label = document.createElement('span');
                    const amount = document.createElement('strong');
                    label.textContent = `Installment ${installment.number} · Principal ${formatMoney(installment.principal)} + interest ${formatMoney(installment.interest)}`;
                    amount.textContent = formatMoney(installment.total);
                    item.append(label, amount);
                    installmentList.append(item);
                });

                const methodLabel = result.interest_basis === 'flat_monthly_equal_principal' ? 'Flat monthly' : 'Reducing balance';
                summaryMethod.textContent = `${result.interest_rate}% monthly · ${methodLabel}`;
                summaryMessage.textContent = 'Estimate based on current system settings. Late charges are excluded from scheduled installments and may accrue separately under the agreement.';
                summaryBody.replaceChildren(totalsGrid, installmentsHeading, installmentList);
                summaryBody.hidden = false;
                requestedAmountWords.textContent = result.principal_words;
                requestedAmountWordsInput.value = result.principal_words;
                guarantorAmountFigures.forEach((element) => { element.textContent = `${formatMoney(result.total_repayment)} guarantee`; });
                guarantorAmountWords.forEach((element) => { element.textContent = result.total_repayment_words; });
                latePenaltyTerms.forEach((element) => { element.textContent = formatMoney(result.daily_late_fee); });
                quoteIsReady = true;
            } catch (error) {
                if (error.name !== 'AbortError' && requestId === quoteRequestId) {
                    summaryMethod.textContent = 'Estimate unavailable';
                    summaryMessage.textContent = 'Payment estimates are temporarily unavailable. Your application details will remain here if you continue.';
                    summaryBody.replaceChildren();
                    summaryBody.hidden = true;
                }
            }
        };

        const syncFixedRepaymentTerm = () => {
            const fixedMonths = loanProductInput.selectedOptions[0]?.dataset.fixedMonths || '';
            [...requestedMonthsInput.options].forEach((option) => {
                option.disabled = Boolean(fixedMonths) && option.value !== fixedMonths;
            });
            if (fixedMonths) {
                requestedMonthsInput.value = fixedMonths;
            }
        };

        const render = () => {
            steps.forEach((step, index) => {
                step.classList.toggle('is-active', index === current);
                step.setAttribute('aria-hidden', String(index !== current));
            });
            labels.forEach((label, index) => {
                const isCurrent = index === current;
                const isNext = index === current + 1;
                const status = label.querySelector('[data-step-status]');
                label.hidden = !isCurrent && !isNext;
                label.classList.toggle('is-current', isCurrent);
                label.classList.toggle('is-next', isNext);
                if (status) {
                    status.textContent = isCurrent ? 'Current step' : isNext ? 'Next step' : '';
                }
                if (isCurrent) {
                    label.setAttribute('aria-current', 'step');
                } else {
                    label.removeAttribute('aria-current');
                }
            });
            count.textContent = `Step ${current + 1} of ${steps.length}`;
            progress.style.width = `${((current + 1) / steps.length) * 100}%`;
            back.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            submit.hidden = current !== steps.length - 1;
            if (current === steps.length - 1) {
                updatePaymentSummary();
            }
        };

        const schedulePaymentSummaryUpdate = () => {
            quoteIsReady = false;
            window.clearTimeout(quoteDebounce);
            quoteDebounce = window.setTimeout(updatePaymentSummary, 250);
        };

        loanProductInput.addEventListener('change', () => {
            syncFixedRepaymentTerm();
            schedulePaymentSummaryUpdate();
        });
        requestedAmountInput.addEventListener('input', schedulePaymentSummaryUpdate);
        requestedMonthsInput.addEventListener('input', schedulePaymentSummaryUpdate);
        requestedMonthsInput.addEventListener('change', schedulePaymentSummaryUpdate);
        borrowerSignature?.addEventListener('input', () => {
            if (borrowerSignaturePreview) {
                borrowerSignaturePreview.value = borrowerSignature.value;
            }
        });

        const validateCurrentStep = () => {
            const controls = [...steps[current].querySelectorAll('input, select, textarea')].filter((control) => {
                if (control.disabled) {
                    return false;
                }

                const collateralRow = control.closest('[data-collateral-row]');
                if (collateralRow && !collateralRow.querySelector('[name$="[description]"]').value.trim() && !control.name.endsWith('[description]')) {
                    return false;
                }

                return true;
            });
            const invalid = controls.find((control) => !control.checkValidity());

            if (invalid) {
                invalid.reportValidity();
                invalid.focus();
                return false;
            }

            if (current === 5 && !stepForm.querySelector('[name="guarantors[0][is_colleague]"]:checked, [name="guarantors[1][is_colleague]"]:checked')) {
                showFeedback('At least one of the two guarantors must be your colleague.', 'error');
                current = 4;
                render();
                stepForm.querySelector('[name="guarantors[0][is_colleague]"]').focus();
                return false;
            }

            if (current === steps.length - 1 && !quoteIsReady) {
                showFeedback('Wait for the payment summary to finish calculating before submitting.', 'error');
                updatePaymentSummary();

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

                if (name === 'guarantors') {
                    const error = stepForm.querySelector('[data-guarantor-general-error]');
                    if (error) {
                        error.textContent = message;
                        firstInvalid ??= stepForm.querySelector('[name="guarantors[0][is_colleague]"]');
                    }
                }

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
                    showFeedback(`${result.message || 'Please review the highlighted fields and try again.'} Your entries and selected documents are still here.`, 'error');
                    displayServerErrors(result.errors || {});
                    return;
                }

                if (response.status === 413) {
                    showFeedback('The upload is too large. Keep each document below 25 MB and try again. Your entries and selected documents are still here.', 'error');
                    return;
                }

                if (!response.ok || !result.reference) {
                    showFeedback(`${result.message || 'We could not submit your application. Please try again.'} Your entries and selected documents are still here.`, 'error');
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
                feedback.append(heading, reference);
                if (paymentSummary?.querySelector('[data-payment-summary-body]:not([hidden])')) {
                    feedback.append(paymentSummary.cloneNode(true));
                }
                feedback.append(confirmationLink);
                feedback.hidden = false;
            } catch {
                showFeedback('We could not reach the server. Check your connection and try again. Your entries and selected documents are still here.', 'error');
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
        syncFixedRepaymentTerm();
        render();
    }

    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
});
