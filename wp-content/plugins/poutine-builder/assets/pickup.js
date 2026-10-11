/* Checkout : charge les créneaux de la boutique choisie. */
(() => {
	const select = document.getElementById('pb_slot');
	if (!select || !window.PBPickup) return;

	async function load(store) {
		select.disabled = true;
		select.replaceChildren(new Option('Chargement…', ''));
		try {
			const url = `${PBPickup.ajaxUrl}?action=pb_slots&nonce=${PBPickup.nonce}&store=${encodeURIComponent(store)}`;
			const json = await (await fetch(url, { credentials: 'same-origin' })).json();
			const slots = json.success ? json.data : [];
			select.replaceChildren(new Option(slots.length ? 'Choisis une heure' : 'Aucun créneau disponible', ''));
			const groups = {};
			slots.forEach((s) => {
				groups[s.day] ??= Object.assign(document.createElement('optgroup'), { label: s.day });
				groups[s.day].append(new Option(s.label, s.value));
			});
			Object.values(groups).forEach((g) => select.append(g));
			select.disabled = !slots.length;
		} catch {
			select.replaceChildren(new Option('Erreur de chargement', ''));
		}
	}

	document.addEventListener('change', (e) => {
		if (e.target.name === 'pb_store') load(e.target.value);
	});
})();
