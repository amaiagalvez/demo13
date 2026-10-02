import $ from 'jquery';
import select2 from 'select2';

window.$ = window.jQuery = $;
select2(window, $);

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
        language: {
            noResults: () => element.dataset.noResultsLabel,
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
            projectForm.form.customerCreateError = '';

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
            select.val(String(result.id));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) {
            element.querySelector(`option[value="${CSS.escape(temporaryValue)}"]`)?.remove();
            select.val(null).trigger('change.select2');
            element.dispatchEvent(new Event('change', { bubbles: true }));
            projectForm.form.customerCreateError = error.message || element.dataset.createError;
        } finally {
            projectForm.form.customerCreating = false;
        }
    });
};

const initializeProjectCustomerSelects = () => {
    document.querySelectorAll('[data-project-customer-select]').forEach((element) => {
        window.initializeProjectCustomerSelect(element);
    });
};

const projectCustomerSelectObserver = new MutationObserver(initializeProjectCustomerSelects);

projectCustomerSelectObserver.observe(document.documentElement, {
    childList: true,
    subtree: true,
});

initializeProjectCustomerSelects();

window.clearForm = clearForm;
window.addDays = addDays;
