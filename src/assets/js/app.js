const payloadElement = document.querySelector('[data-page-payload]');
const payload = payloadElement ? JSON.parse(payloadElement.textContent) : null;

const updateQuantityValidation = (input) => {
	const value = input.value.trim();
	const quantity = Number(value);
	let message = '';

	if (value === '') {
		message = 'Jumlah barang wajib diisi.';
	} else if (!Number.isInteger(quantity)) {
		message = 'Jumlah barang harus berupa bilangan bulat.';
	} else if (quantity < 1) {
		message = 'Jumlah barang minimal 1.';
	} else if (quantity > 2147483647) {
		message = 'Jumlah barang melebihi batas maksimum.';
	}

	input.setCustomValidity(message);
	const messageElement = input.closest('td')?.querySelector('[data-quantity-error]');
	if (messageElement) {
		messageElement.textContent = message;
		messageElement.hidden = message === '';
	}
};

document.addEventListener('input', (event) => {
	if (event.target.matches('.item-quantity')) updateQuantityValidation(event.target);
});

document.addEventListener('invalid', (event) => {
	if (event.target.matches('.item-quantity')) updateQuantityValidation(event.target);
}, true);

document.addEventListener('click', (event) => {
	const addItemButton = event.target.closest('#add-item');
	if (!addItemButton) return;

	const row = addItemButton.closest('form')?.querySelector('tbody tr:last-child');
	const quantity = row?.querySelector('.item-quantity');
	if (!quantity) return;

	quantity.setCustomValidity('');
	const messageElement = quantity.closest('td')?.querySelector('[data-quantity-error]');
	if (messageElement) {
		messageElement.textContent = '';
		messageElement.hidden = true;
	}
});

const dashboardShell = document.querySelector('[data-dashboard-shell]');
if (dashboardShell) {
	const sidebar = dashboardShell.querySelector('[data-sidebar]');
	const content = dashboardShell.querySelector('.dashboard-content');
	const mobileOpenButton = dashboardShell.querySelector('[data-sidebar-open]');
	const desktopCollapseButton = dashboardShell.querySelector('[data-sidebar-collapse]');
	const overlay = dashboardShell.querySelector('[data-sidebar-overlay]');

	const closeDrawer = (restoreFocus = false) => {
		sidebar?.classList.remove('is-open');
		if (overlay) overlay.hidden = true;
		if (mobileOpenButton) mobileOpenButton.setAttribute('aria-expanded', 'false');
		if (content) content.inert = false;
		document.body.classList.remove('sidebar-drawer-open');
		if (restoreFocus) mobileOpenButton?.focus();
	};

	mobileOpenButton?.addEventListener('click', () => {
		sidebar?.classList.add('is-open');
		if (overlay) overlay.hidden = false;
		mobileOpenButton.setAttribute('aria-expanded', 'true');
		if (content) content.inert = true;
		document.body.classList.add('sidebar-drawer-open');
		sidebar?.querySelector('[data-sidebar-link]')?.focus();
	});

	overlay?.addEventListener('click', () => closeDrawer(true));
	sidebar?.querySelectorAll('[data-sidebar-link]').forEach((link) => {
		link.addEventListener('click', () => closeDrawer());
	});

	desktopCollapseButton?.addEventListener('click', () => {
		const isCollapsed = dashboardShell.classList.toggle('is-collapsed');
		desktopCollapseButton.setAttribute('aria-expanded', String(!isCollapsed));
		desktopCollapseButton.setAttribute('aria-label', isCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
		desktopCollapseButton.title = isCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar';
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) {
			closeDrawer(true);
		}
	});

	window.addEventListener('resize', () => {
		if (window.matchMedia('(min-width: 1024px)').matches) closeDrawer();
	});
}

window.__DAVINGM__ = {
	payload,
	navigate(url) {
		return fetch(url, {
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
		}).then((response) => {
			if (!response.ok) throw new Error(`Navigation failed: ${response.status}`);
			return response.text();
		}).then((html) => {
			const documentFromResponse = new DOMParser().parseFromString(html, 'text/html');
			const nextMain = documentFromResponse.querySelector('#page-view');
			const currentMain = document.querySelector('#page-view');

			if (!nextMain || !currentMain) {
				window.location.assign(url);
				return;
			}

			currentMain.replaceWith(nextMain);
			document.title = documentFromResponse.title;
			history.pushState({}, '', url);
			window.scrollTo({ top: 0, behavior: 'instant' });
			window.dispatchEvent(new CustomEvent('davingm:navigated', { detail: { url } }));
		}).catch(() => window.location.assign(url));
	},
};

document.addEventListener('click', (event) => {
	const link = event.target.closest('[data-navigate]');
	if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
	event.preventDefault();
	window.__DAVINGM__.navigate(link.dataset.navigate || link.href);
});

document.addEventListener('submit', (event) => {
	const message = event.target.dataset?.confirm;
	if (message && !window.confirm(message)) event.preventDefault();
});

document.addEventListener('input', (event) => {
	const search = event.target;
	if (!search.matches('[data-table-search]')) return;

	const tableBody = search.closest('section')?.querySelector('[data-table-body]');
	if (!tableBody) return;

	const query = search.value.trim().toLocaleLowerCase();
	const rows = [...tableBody.querySelectorAll('[data-table-row]')];
	let visibleRows = 0;

	for (const row of rows) {
		const matches = row.textContent.toLocaleLowerCase().includes(query);
		row.hidden = !matches;
		if (matches) visibleRows++;
	}

	const noResults = tableBody.querySelector('[data-table-no-results]');
	if (noResults) noResults.hidden = query.length === 0 || visibleRows > 0 || rows.length === 0;
});

document.querySelectorAll('[data-reveal]').forEach((element) => {
	element.style.setProperty('--reveal-delay', `${element.dataset.delay || 0}ms`);
});
