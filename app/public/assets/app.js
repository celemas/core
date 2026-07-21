const trigger = document.querySelector('[data-xhr]');
const output = document.querySelector('#xhr-output');

if (trigger instanceof HTMLButtonElement && output instanceof HTMLElement) {
	trigger.addEventListener('click', async () => {
		trigger.disabled = true;
		output.dataset.state = 'loading';
		output.textContent = 'Transmitting…';

		try {
			const response = await fetch(trigger.dataset.xhr ?? '', {
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});
			const body = await response.json();
			output.textContent = JSON.stringify(body, null, 2);
			output.dataset.state = response.ok ? 'ready' : 'error';
		} catch (error) {
			output.textContent = error instanceof Error ? error.message : 'Request failed';
			output.dataset.state = 'error';
		} finally {
			trigger.disabled = false;
		}
	});
}
