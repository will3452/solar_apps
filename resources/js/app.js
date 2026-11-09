const printButton = document.querySelector('[data-print]');
printButton?.addEventListener('click', () => window.print());

const estimateForm = document.querySelector('[data-estimate-form]');
if (estimateForm) {
    const output = estimateForm.querySelector('[data-estimate-preview]');
    const status = estimateForm.querySelector('[data-preview-status]');
    let timer;
    let controller;
    let workIndex = estimateForm.querySelectorAll('.additional-row').length;
    let pending = false;

    const recalculate = async () => {
        if (!estimateForm.checkValidity()) {
            status.textContent = 'Preview not updated. Complete the required fields and check the entered values.';
            return;
        }
        if (output.contains(document.activeElement)) {
            pending = true;
            status.textContent = 'Leave the adjustment table to update the preview.';
            return;
        }
        pending = false;
        controller?.abort();
        controller = new AbortController();
        const activeController = controller;
        const body = new FormData(estimateForm);
        body.delete('_method');
        const openDetails = [...output.querySelectorAll('details')].map((node) => node.open);
        output.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating materials and prices…';
        try {
            const response = await fetch(estimateForm.dataset.previewUrl, {
                method: 'POST', body, signal: activeController.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok) {
                status.textContent = response.status === 401 ? 'Your session has ended. Sign in again to continue.' : Object.values(data.errors ?? {}).flat().slice(0, 3).join(' ') || 'The preview could not be updated. Check the entered values.';
                return;
            }
            output.innerHTML = data.html;
            output.querySelectorAll('details').forEach((node, index) => { if (openDetails[index] !== undefined) node.open = openDetails[index]; });
            status.textContent = 'Preview updated. Save to keep this version.';
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = 'Could not update the preview. Try Recalculate, or save to calculate on the server.';
        } finally {
            if (controller === activeController) output.removeAttribute('aria-busy');
        }
    };
    const schedule = () => { clearTimeout(timer); timer = setTimeout(recalculate, 650); };
    estimateForm.addEventListener('input', schedule);
    estimateForm.addEventListener('change', schedule);
    estimateForm.addEventListener('focusout', () => { if (pending) schedule(); });
    estimateForm.querySelector('[data-recalculate]').addEventListener('click', recalculate);
    estimateForm.querySelector('[data-add-work]').addEventListener('click', () => {
        if (estimateForm.querySelectorAll('[data-additional-rows] .additional-row').length >= 20) return;
        const markup = estimateForm.querySelector('[data-work-template]').innerHTML.replaceAll('__index__', String(workIndex++));
        estimateForm.querySelector('[data-additional-rows]').insertAdjacentHTML('beforeend', markup);
    });
    estimateForm.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-work]')) {
            event.target.closest('.additional-row').remove();
            schedule();
        }
    });
    const hashTarget = document.getElementById(location.hash.slice(1));
    if (hashTarget) {
        if (hashTarget instanceof HTMLDetailsElement) hashTarget.open = true;
        if (location.hash === '#materials') hashTarget.querySelector('details:nth-of-type(1)')?.setAttribute('open', '');
    }
    if (estimateForm.checkValidity()) recalculate();
}
