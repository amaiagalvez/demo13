import $ from 'jquery';
import select2 from 'select2';

window.$ = window.jQuery = $;
select2(window, $);

const formatLocalDateTime = (value, format = 'datetime') => {
    if (!value) {
        return '';
    }

    const locale = document.documentElement.lang || undefined;
    const normalizedLocale = locale?.toLowerCase() || '';
    const isBasque = /^eu(?:-|$)/.test(normalizedLocale);
    const isSpanish = /^es(?:-|$)/.test(normalizedLocale);

    if (format === 'date') {
        // Accept YYYY-MM-DD, YYYY/M/D, DD-MM-YYYY, etc.
        const parts = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/.exec(value) ||
                      /^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/.exec(value);

        if (!parts) {
            // If parsing fails, return the original value as last resort
            return value;
        }

        let year, month, day;

        if (parts[1].length === 4) {
            // YYYY-MM-DD or YYYY/M/D
            [, year, month, day] = parts;
        } else {
            // DD-MM-YYYY
            [, day, month, year] = parts;
        }

        month = month.padStart(2, '0');
        day = day.padStart(2, '0');

        if (isBasque) {
            return `${year}-${month}-${day}`;
        }

        if (isSpanish) {
            return `${day}-${month}-${year}`;
        }

        const date = new Date(`${year}-${month}-${day}T12:00:00`);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat(locale, { dateStyle: 'short' }).format(date);
    }

    // datetime format: try parsing as ISO first, then fall back to Date constructor
    let date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        // Try parsing common formats
        const isoMatch = /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})[T ](\d{1,2}):(\d{1,2}):?(\d{1,2})?/.exec(value);
        if (isoMatch) {
            const [, y, m, d, h, min, s = '0'] = isoMatch;
            date = new Date(`${y}-${m.padStart(2,'0')}-${d.padStart(2,'0')}T${h.padStart(2,'0')}:${min.padStart(2,'0')}:${s.padStart(2,'0')}`);
        }
    }

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    if (isBasque || isSpanish) {
        const year = String(date.getFullYear()).padStart(4, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hour = String(date.getHours()).padStart(2, '0');
        const minute = String(date.getMinutes()).padStart(2, '0');

        return isBasque
            ? `${year}-${month}-${day} ${hour}:${minute}`
            : `${day}-${month}-${year} ${hour}:${minute}`;
    }

    return new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' }).format(date);
};

window.formatLocalDateTime = formatLocalDateTime;

const localizeDateTimes = (root = document) => {
    const elements = [];

    if (root instanceof Element && root.matches('[data-local-datetime]')) {
        elements.push(root);
    }

    if (root instanceof Element || root instanceof Document) {
        elements.push(...root.querySelectorAll('[data-local-datetime]'));
    }

    elements.forEach((element) => {
        const formatted = formatLocalDateTime(element.dateTime, element.dataset.localDatetime);

        if (element.textContent.trim() !== formatted) {
            element.textContent = formatted;
        }
    });
};

const localDateTimeObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                localizeDateTimes(node);
            }
        });
    });
});

if (document.documentElement) {
    localDateTimeObserver.observe(document.documentElement, { childList: true, subtree: true });
}

localizeDateTimes();

const trackedFormStates = new WeakMap();

function trackedFormValues(form) {
    return Array.from(form.elements)
        .filter((field) => {
            if (!field.name || field.disabled) {
                return false;
            }

            return !(field instanceof HTMLInputElement && [
                'button',
                'hidden',
                'reset',
                'submit',
            ].includes(field.type));
        })
        .map((field) => {
            if (field instanceof HTMLInputElement && ['checkbox', 'radio'].includes(field.type)) {
                return [field.name, field.checked ? field.value : null];
            }

            if (field instanceof HTMLSelectElement && field.multiple) {
                return [field.name, Array.from(field.selectedOptions, (option) => option.value)];
            }

            return [field.name, field.value];
        });
}

function dispatchFormDirtyState(form, state) {
    state.isDirty = JSON.stringify(trackedFormValues(form)) !== state.baseline;

    form.dispatchEvent(new CustomEvent('form-dirty-change', {
        detail: { isDirty: state.isDirty },
    }));
}

function trackForm(form) {
    if (trackedFormStates.has(form)) {
        return;
    }

    const state = {
        baseline: JSON.stringify(trackedFormValues(form)),
        isDirty: false,
        isSubmitting: false,
    };

    trackedFormStates.set(form, state);
    form.addEventListener('input', () => dispatchFormDirtyState(form, state));
    form.addEventListener('change', () => dispatchFormDirtyState(form, state));
    form.addEventListener('reset', () => {
        state.isSubmitting = false;
    });
    form.addEventListener('submit', (event) => {
        if (!state.isDirty || state.isSubmitting) {
            event.preventDefault();

            return;
        }

        if (!form.hasAttribute('wire:submit')) {
            state.isSubmitting = true;
        }
    });
}

function trackedFormsWithin(root = document) {
    const forms = [];

    if (root instanceof HTMLFormElement && root.hasAttribute('data-track-changes')) {
        forms.push(root);
    }

    if (root instanceof Element || root instanceof Document) {
        forms.push(...root.querySelectorAll('form[data-track-changes]'));
    }

    return forms;
}

function resetTrackedForms(root = document) {
    trackedFormsWithin(root).forEach((form) => {
        trackForm(form);

        const state = trackedFormStates.get(form);
        state.baseline = JSON.stringify(trackedFormValues(form));
        state.isSubmitting = false;
        dispatchFormDirtyState(form, state);
    });
}

function dirtyTrackedForms(root = document) {
    return trackedFormsWithin(root).filter((form) => {
        const state = trackedFormStates.get(form);

        return state?.isDirty && !state.isSubmitting;
    });
}

function unsavedChangesMessage(forms) {
    return forms[0]?.dataset.unsavedWarning
        ?? document.body.dataset.unsavedWarning
        ?? '';
}

function allowLeavingForms(forms) {
    return forms.length === 0 || window.confirm(unsavedChangesMessage(forms));
}

window.resetTrackedForms = resetTrackedForms;
window.requestTrackedModalClose = (dialog) => {
    const dirtyForms = dirtyTrackedForms(dialog);

    if (allowLeavingForms(dirtyForms)) {
        dialog.close();
    }
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const closeTrigger = event.target.closest('[data-flux-modal-close]');
    const dialog = closeTrigger?.closest('dialog');

    if (!dialog || allowLeavingForms(dirtyTrackedForms(dialog))) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
}, true);

document.addEventListener('livewire:navigate', (event) => {
    if (!allowLeavingForms(dirtyTrackedForms())) {
        event.preventDefault();
    }
});

window.addEventListener('beforeunload', (event) => {
    if (dirtyTrackedForms().length === 0) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
});

document.addEventListener('livewire:init', () => {
    window.Livewire.interceptMessage(({ message, onSuccess, onFinish }) => {
        let shouldResetForms = false;

        onSuccess(({ payload }) => {
            shouldResetForms = payload.effects.dispatches?.some(({ name }) =>
                ['form-saved', 'form-reset'].includes(name)
            ) ?? false;
        });

        onFinish(() => {
            // Livewire restores disabled controls after dispatching component events.
            if (shouldResetForms) {
                resetTrackedForms(message.component.el);
            }
        });
    });
});

const initializeTrackedForms = () => {
    trackedFormsWithin().forEach(trackForm);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTrackedForms, { once: true });
} else {
    initializeTrackedForms();
}

const trackedFormObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
            if (mutation.target instanceof HTMLDialogElement && mutation.target.open) {
                requestAnimationFrame(() => resetTrackedForms(mutation.target));
            }

            return;
        }

        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                trackedFormsWithin(node).forEach(trackForm);
            }
        });
    });
});

trackedFormObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['open'],
    childList: true,
    subtree: true,
});

export function clearForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.reset();

    Array.from(form.elements).forEach((field) => {
        if (field instanceof HTMLInputElement) {
            if (['button', 'hidden', 'reset', 'submit'].includes(field.type)) {
                return;
            }

            if (['checkbox', 'radio'].includes(field.type)) {
                field.checked = false;
            } else {
                field.value = '';
            }
        } else if (field instanceof HTMLTextAreaElement) {
            field.value = '';
        } else if (field instanceof HTMLSelectElement) {
            field.selectedIndex = -1;
        } else {
            return;
        }

        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    form.querySelectorAll('[data-flux-error]').forEach((error) => {
        error.hidden = true;
    });

    form.querySelectorAll('[data-invalid]').forEach((field) => {
        field.removeAttribute('aria-invalid');
        delete field.dataset.invalid;
    });
}

/**
 * Shift a Y-m-d date by a number of days; returns '' for an empty or invalid date.
 */
export function addDays(isoDate, days) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate ?? '');

    if (!match) {
        return '';
    }

    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]) + days));

    return date.toISOString().slice(0, 10);
}

window.initializeProjectCustomerSelect = (element) => {
    const select = $(element);
    const root = document.querySelector('[data-project-form-root]');

    if (!root || select.hasClass('select2-hidden-accessible')) {
        return;
    }

    select.select2({
        width: '100%',
        dropdownParent: $(element.closest('dialog')),
        placeholder: element.dataset.placeholder,
        allowClear: true,
        tags: true,
        ajax: {
            url: element.dataset.customerSearchUrl,
            dataType: 'json',
            delay: 250,
            headers: { Accept: 'application/json' },
            data: (params) => ({ q: params.term ?? '' }),
            processResults: (data) => ({
                results: data.results.map((result) => ({
                    ...result,
                    // Select2 4.0.13 calls its AJAX normalizer unbound.
                    _resultId: `select2-${element.id}-result-${result.id}`,
                })),
            }),
            cache: true,
        },
        language: {
            noResults: () => element.dataset.noResultsLabel,
            errorLoading: () => element.dataset.searchError,
        },
        createTag: (params) => {
            const name = params.term.trim();

            if (!name || Array.from(element.options).some((option) =>
                option.text.trim().toLocaleLowerCase() === name.toLocaleLowerCase()
            )) {
                return null;
            }

            return {
                id: `new-customer:${name}`,
                text: `${element.dataset.createLabel}: ${name}`,
                newTag: true,
                customerName: name,
            };
        },
    }).on('select2:select', async (event) => {
        const customerOption = event.params.data;
        const projectForm = window.Alpine.$data(root);

        if (!customerOption.newTag) {
            projectForm.form.customer_name = customerOption.text;
            projectForm.form.customerCreateError = '';
            element.dispatchEvent(new Event('change', { bubbles: true }));

            return;
        }

        const form = element.closest('form');
        const temporaryValue = String(customerOption.id);
        const csrfToken = form.querySelector('input[name="_token"]').value;

        projectForm.form.customerCreateError = '';
        projectForm.form.customerCreating = true;

        try {
            const response = await fetch(element.dataset.customerStoreUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ name: customerOption.customerName }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.errors?.name?.[0] ?? result.message ?? element.dataset.createError);
            }

            element.querySelector(`option[value="${CSS.escape(temporaryValue)}"]`)?.remove();

            const option = new Option(result.name, String(result.id), true, true);
            element.add(option);
            projectForm.form.customer_name = result.name;
            select.val(String(result.id));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) {
            element.querySelector(`option[value="${CSS.escape(temporaryValue)}"]`)?.remove();
            select.val(null).trigger('change.select2');
            element.dispatchEvent(new Event('change', { bubbles: true }));
            projectForm.form.customer_name = '';
            projectForm.form.customerCreateError = error.message || element.dataset.createError;
        } finally {
            projectForm.form.customerCreating = false;
        }
    });
};

const initializeProjectCustomerSelects = (root = document) => {
    if (root instanceof Element && root.matches('[data-project-customer-select]')) {
        window.initializeProjectCustomerSelect(root);
    }

    root.querySelectorAll('[data-project-customer-select]').forEach((element) => {
        window.initializeProjectCustomerSelect(element);
    });
};

const projectCustomerSelectObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                initializeProjectCustomerSelects(node);
            }
        });
    });
});

projectCustomerSelectObserver.observe(document.documentElement, {
    childList: true,
    subtree: true,
});

initializeProjectCustomerSelects();

window.clearForm = clearForm;
window.addDays = addDays;

document.addEventListener('alpine:init', () => {
    // State and handler behind <x-list.confirm-modal>. It lives here because it is the same for
    // every list: the modal only reads confirmation.*, and x-list.row-actions fills it. The list
    // passes its own local state in, so the three views keep declaring their forms and URLs here
    // instead of each repeating this block.
    window.Alpine.data('listConfirmation', (local = {}) => ({
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },

        confirmAction(action) {
            this.confirmation = {
                action: action.action,
                method: action.method,
                title: action.confirmTitle,
                text: action.confirmText,
                label: action.confirmLabel,
                danger: action.danger,
            };
        },

        ...local,
    }));

    window.Alpine.data('listSearch', (initialSearch, initialTotal, resultsTemplate) => ({
        currentSearch: initialSearch,
        totalResults: initialTotal,
        requestController: null,

        get hasSearch() {
            return this.currentSearch !== '';
        },

        init() {
            this.handlePopstate = () => {
                const url = new URL(window.location.href);

                void this.refreshList(url, false).catch(() => window.location.reload());
            };
            window.addEventListener('popstate', this.handlePopstate);
        },

        destroy() {
            window.removeEventListener('popstate', this.handlePopstate);
            this.requestController?.abort();
        },

        searchInput(event) {
            if (event.target.name !== 'search') {
                return;
            }

            const query = event.target.value.trim();

            if (query.length > 3
                || (query.length === 0 && (this.currentSearch !== '' || this.requestController))) {
                this.search(query);

                return;
            }

            if (this.requestController && query !== this.currentSearch) {
                this.requestController.abort();
            }
        },

        submitSearch(event) {
            const form = event.target.closest('[data-list-search]');
            const query = form.elements.search.value.trim();

            // A term too short to fetch is left to the native form submit, so the search the
            // server accepts is also the search the browser gets without this script.
            if ((query.length > 0 && query.length <= 3)
                || (query.length === 0 && this.currentSearch === '')) {
                return;
            }

            event.preventDefault();

            this.search(query);
        },

        handleClick(event) {
            const clearLink = event.target.closest('[data-list-search-clear]');

            if (clearLink) {
                event.preventDefault();
                void this.refreshList(new URL(clearLink.href))
                    .catch(() => window.location.assign(clearLink.href));

                return;
            }

            const paginationLink = event.target.closest('nav[data-test$="-pagination"] a');

            if (paginationLink) {
                event.preventDefault();
                void this.refreshList(new URL(paginationLink.href))
                    .catch(() => window.location.assign(paginationLink.href));
            }
        },

        search(query) {
            const form = this.$root.querySelector('[data-list-search]');
            const url = new URL(form.action, window.location.href);

            if (query === '') {
                url.searchParams.delete('search');
            } else {
                url.searchParams.set('search', query);
            }

            void this.refreshList(url).catch(() => window.location.assign(url.href));
        },

        async refreshList(url, updateHistory = true) {
            this.requestController?.abort();

            const controller = new AbortController();
            this.requestController = controller;

            try {
                const response = await fetch(url, {
                    headers: { 'X-List-Fragment': 'true' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`List refresh failed with HTTP ${response.status}.`);
                }

                const template = document.createElement('template');
                template.innerHTML = await response.text();

                const results = template.content.querySelector('[data-list-results]');

                if (!results) {
                    throw new Error('The list response did not contain the expected results fragment.');
                }

                this.currentSearch = url.searchParams.get('search')?.trim() ?? '';

                if (updateHistory) {
                    window.history.pushState({}, '', url);
                }

                // The morph re-initialises the root's x-data, so the live region is filled after it
                // rather than before: a value set beforehand belongs to the discarded instance.
                const totalResults = results.dataset.totalResults
                    ? parseInt(results.dataset.totalResults, 10)
                    : results.querySelectorAll('tbody tr').length;
                this.totalResults = totalResults;
                const announcement = (resultsTemplate ?? '').replace(
                    ':count',
                    String(totalResults),
                );

                const root = this.$root;

                window.Alpine.morph(root, results.outerHTML);

                const region = root.querySelector('[data-list-results-announcement]');

                if (region) {
                    region.textContent = announcement;
                }

                // Update visible result count
                const countDisplay = root.querySelector('[data-count-display]');
                const countTemplate = root.dataset.countTemplate ?? '';
                if (countDisplay && countTemplate) {
                    countDisplay.textContent = countTemplate.replace(':count', String(totalResults));
                }
            } catch (error) {
                if (!controller.signal.aborted) {
                    throw error;
                }
            } finally {
                if (this.requestController === controller) {
                    this.requestController = null;
                }
            }
        },
    }));
});
