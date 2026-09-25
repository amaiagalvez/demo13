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
        field.removeAttribute('data-invalid');
    });
}

window.clearForm = clearForm;
