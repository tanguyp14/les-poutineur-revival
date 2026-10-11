/* Suivi des commandes (personnel) : 3 colonnes, rafraîchissement auto, bip sur nouvelle commande. */
(() => {
	const root = document.getElementById('pb-staff');
	if (!root) return;
	const cfg = JSON.parse(root.dataset.config);
	const LS = {
		get: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
		set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* ignore */ } },
	};

	let store = LS.get('pbs_store');
	if (!cfg.stores.some((s) => s.id === store)) store = cfg.stores[0]?.id;
	let sound = LS.get('pbs_sound') === '1';
	let showDone = false;
	let seen = null; // Set des IDs déjà vus (null = premier chargement, pas de bip).
	let orders = [];
	let offset = 0; // décalage horloge serveur - navigateur.
	let audio;

	const el = (tag, props = {}, ...kids) => {
		const n = document.createElement(tag);
		for (const [k, v] of Object.entries(props)) {
			if (k === 'class') n.className = v;
			else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
			else if (v === true) n.setAttribute(k, '');
			else if (v !== false && v != null) n.setAttribute(k, v);
		}
		kids.flat().forEach((c) => n.append(c));
		return n;
	};

	const COLS = [
		{ key: 'new', label: 'Nouvelles', next: 'preparing', btn: 'Préparer' },
		{ key: 'preparing', label: 'En préparation', next: 'ready', btn: 'Prête' },
		{ key: 'ready', label: 'Prêtes', next: 'collected', btn: 'Retirée' },
	];

	function beep() {
		if (!sound) return;
		try {
			audio ??= new AudioContext();
			[880, 1175, 880].forEach((f, i) => {
				const o = audio.createOscillator();
				const g = audio.createGain();
				o.frequency.value = f;
				o.connect(g).connect(audio.destination);
				const t = audio.currentTime + i * 0.22;
				g.gain.setValueAtTime(0.25, t);
				g.gain.exponentialRampToValueAtTime(0.001, t + 0.2);
				o.start(t);
				o.stop(t + 0.2);
			});
		} catch { /* audio indisponible */ }
	}

	async function call(params, post) {
		const url = post ? cfg.ajaxUrl : `${cfg.ajaxUrl}?${new URLSearchParams({ ...params, nonce: cfg.nonce })}`;
		const res = await fetch(url, {
			method: post ? 'POST' : 'GET',
			body: post ? new URLSearchParams({ ...params, nonce: cfg.nonce }) : undefined,
			credentials: 'same-origin',
			cache: 'no-store',
		});
		const json = await res.json();
		if (!json.success) throw new Error(json.data?.message || 'Erreur');
		return json.data;
	}

	async function refresh() {
		try {
			const data = await call({ action: 'pb_staff_orders', store, done: showDone ? '1' : '' });
			offset = data.now * 1000 - Date.now();
			orders = data.orders;
			const ids = new Set(orders.map((o) => o.id));
			const fresh = seen ? orders.filter((o) => !seen.has(o.id) && o.status === 'new') : [];
			seen = ids;
			if (fresh.length) {
				beep();
				document.title = `(${fresh.length}) Nouvelle commande !`;
				setTimeout(() => (document.title = baseTitle), 8000);
			}
			status.textContent = 'Mis à jour ' + new Date().toLocaleTimeString();
			status.classList.remove('is-error');
			render(new Set(fresh.map((o) => o.id)));
		} catch (e) {
			status.textContent = 'Connexion perdue — nouvelle tentative…';
			status.classList.add('is-error');
		}
	}

	async function setStatus(o, s) {
		try {
			const upd = await call({ action: 'pb_staff_update', order_id: o.id, status: s }, true);
			orders = orders.map((x) => (x.id === upd.id ? upd : x));
			render();
		} catch (e) {
			status.textContent = e.message;
			status.classList.add('is-error');
		}
	}

	function card(o, col, isNew) {
		const late = col && o.status !== 'ready' && o.pickup_ts * 1000 < Date.now() + offset;
		const mins = Math.round((o.pickup_ts * 1000 - (Date.now() + offset)) / 60000);
		const items = o.items.map((it) =>
			el('li', {},
				el('strong', {}, `${it.qty} × ${it.name}`),
				el('ul', {}, Object.entries(it.parts).map(([k, v]) => el('li', {}, el('span', { class: 'pbs-k' }, k + ' : '), v.join(', '))))
			));
		return el('article', { class: 'pbs-card' + (late ? ' is-late' : '') + (isNew ? ' is-fresh' : '') },
			el('header', {},
				el('span', { class: 'pbs-time' }, (o.today ? '' : o.day + ' ') + o.pickup),
				el('span', { class: 'pbs-eta' }, o.status === 'collected' ? 'Retirée' : mins >= 0 ? `dans ${mins} min` : `retard ${-mins} min`),
				el('span', { class: 'pbs-num' }, '#' + o.number)
			),
			el('p', { class: 'pbs-who' }, o.customer || 'Client', ' ',
				o.phone ? el('a', { href: 'tel:' + o.phone.replace(/[^\d+]/g, '') }, o.phone) : ''),
			el('ul', { class: 'pbs-items' }, items),
			o.note ? el('p', { class: 'pbs-note' }, '📝 ' + o.note) : '',
			el('footer', {},
				el('span', { class: 'pbs-pay ' + (o.paid ? 'is-paid' : 'is-due') }, o.paid ? 'Payée' : 'À encaisser ' + o.total),
				o.pos === 'failed' ? el('span', { class: 'pbs-pay is-due' }, 'Caisse : échec') : '',
				col
					? el('span', { class: 'pbs-actions' },
						col.key !== 'new' ? el('button', { type: 'button', class: 'pbs-back', onclick: () => setStatus(o, COLS[COLS.indexOf(col) - 1].key) }, '↩') : '',
						el('button', { type: 'button', class: 'pbs-go', onclick: () => setStatus(o, col.next) }, col.btn))
					: el('button', { type: 'button', class: 'pbs-back', onclick: () => setStatus(o, 'ready') }, '↩ Annuler')
			));
	}

	function render(freshIds = new Set()) {
		board.replaceChildren(...COLS.map((col) => {
			const list = orders.filter((o) => o.status === col.key);
			return el('section', { class: 'pbs-col pbs-col--' + col.key },
				el('h2', {}, col.label, el('span', { class: 'pbs-count' }, String(list.length))),
				list.length ? list.map((o) => card(o, col, freshIds.has(o.id))) : el('p', { class: 'pbs-empty' }, 'Rien ici'));
		}));
		const done = orders.filter((o) => o.status === 'collected');
		if (showDone) {
			board.append(el('section', { class: 'pbs-col pbs-col--done' }, el('h2', {}, 'Retirées (12 h)', el('span', { class: 'pbs-count' }, String(done.length))), done.map((o) => card(o, null))));
		}
	}

	/* ---------- Barre du haut ---------- */
	const baseTitle = document.title;
	const status = el('span', { class: 'pbs-status', role: 'status' }, '…');
	const board = el('div', { class: 'pbs-board' });
	const soundBtn = el('button', { type: 'button', class: 'pbs-tool', onclick: () => {
		sound = !sound;
		LS.set('pbs_sound', sound ? '1' : '0');
		if (sound) { audio ??= new AudioContext(); beep(); } // geste utilisateur requis pour l'audio.
		paintTools();
	} });
	const doneBtn = el('button', { type: 'button', class: 'pbs-tool', onclick: () => { showDone = !showDone; paintTools(); refresh(); } });
	const select = el('select', { 'aria-label': 'Boutique', onchange: (e) => { store = e.target.value; LS.set('pbs_store', store); seen = null; orders = []; render(); refresh(); } },
		cfg.stores.map((s) => el('option', { value: s.id, selected: s.id === store }, s.name)));
	function paintTools() {
		soundBtn.textContent = sound ? '🔔 Son activé' : '🔕 Son coupé';
		soundBtn.classList.toggle('is-on', sound);
		doneBtn.textContent = showDone ? 'Masquer les retirées' : 'Voir les retirées';
	}
	paintTools();

	root.append(el('div', { class: 'pbs-bar' }, select, soundBtn, doneBtn, status), board);
	render();
	refresh();
	setInterval(refresh, cfg.poll);
	setInterval(() => render(), 30000); // met à jour les « dans X min ».
})();
