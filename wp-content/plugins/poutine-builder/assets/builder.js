/* Poutine Builder — configurateur. Vanilla JS, aucun build. */
(() => {
	const root = document.getElementById('pb-builder');
	if (!root) return;

	const cfg = JSON.parse(root.dataset.config);
	const money = new Intl.NumberFormat(cfg.locale, { style: 'currency', currency: cfg.currency });
	cfg.steps = cfg.steps.filter((s) => s.required || (cfg.ingredients[s.key] || []).length);
	const state = Object.fromEntries(cfg.steps.map((s) => [s.key, []]));
	let qty = 1;

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

	const byId = {};
	Object.values(cfg.ingredients).flat().forEach((i) => (byId[i.id] = i));
	const unitPrice = () => Object.values(state).flat().reduce((sum, id) => sum + (byId[id]?.price || 0), 0);
	const isDone = (s) => (s.required ? state[s.key].length > 0 : true);

	/* ---------- Rendu ---------- */
	const stepsEl = el('div', { class: 'pb__steps' });
	const progressBar = el('div', { class: 'pb__progress-bar' });
	const summaryList = el('ul', { class: 'pb__summary-list' });
	const totalEl = el('strong', { class: 'pb__total' });
	const qtyEl = el('output', { class: 'pb__qty-val' }, '1');
	const addBtn = el('button', { type: 'button', class: 'pb__add', onclick: addToCart }, 'Ajouter au panier');
	const msg = el('p', { class: 'pb__msg', role: 'status', 'aria-live': 'polite' });

	const stepSections = cfg.steps.map((step, idx) => {
		const items = cfg.ingredients[step.key] || [];
		const badge = el('span', { class: 'pb__step-badge' });
		const head = el('header', { class: 'pb__step-head' },
			el('span', { class: 'pb__step-num' }, String(idx + 1)),
			el('div', {},
				el('h3', {}, step.label, step.badge ? el('span', { class: 'pb__badge' }, step.badge) : '', step.required ? '' : el('em', {}, ' (optionnel)')),
				el('p', { class: 'pb__hint' }, step.hint || '')
			),
			badge
		);
		const grid = el('div', { class: 'pb__grid' + (step.absolute ? ' pb__grid--recipes' : ''), role: step.type === 'single' ? 'radiogroup' : 'group', 'aria-label': step.label });
		items.forEach((ing) => grid.append(tile(step, ing)));
		if (!items.length) grid.append(el('p', { class: 'pb__empty' }, 'Rien de disponible pour le moment.'));

		const sec = el('section', { class: 'pb__step', id: 'pb-step-' + step.key }, head, grid);
		sec._badge = badge;
		return sec;
	});
	stepSections.forEach((s) => stepsEl.append(s));

	function tile(step, ing) {
		const input = el('input', {
			type: step.type === 'single' ? 'radio' : 'checkbox',
			name: 'pb_' + step.key,
			value: ing.id,
			onchange: (e) => choose(step, ing.id, e.target.checked),
		});
		const visual = ing.image
			? el('img', { src: ing.image, alt: '', loading: 'lazy' })
			: el('span', { class: 'pb__emoji', 'aria-hidden': 'true' }, ing.emoji || '🍟');
		const price = el('span', { class: 'pb__price' }, step.absolute ? money.format(ing.price) : ing.price > 0 ? '+' + money.format(ing.price) : 'Inclus');
		const label = el('label', { class: 'pb__tile' }, input,
			ing.badge ? el('span', { class: 'pb__badge pb__badge--tile' }, ing.badge) : '',
			visual,
			el('span', { class: 'pb__tile-name' }, ing.name),
			ing.desc && step.absolute ? el('span', { class: 'pb__tagline' }, ing.desc) : '',
			ing.contents ? el('span', { class: 'pb__contents' }, ing.contents) : '',
			price);
		if (ing.desc && !step.absolute) label.title = ing.desc;
		return label;
	}

	/* ---------- Logique ---------- */
	function choose(step, id, checked) {
		msg.textContent = '';
		if (step.type === 'single') {
			state[step.key] = [id];
			// Passe automatiquement à l'étape suivante (confortable sur mobile).
			const next = stepSections[cfg.steps.indexOf(step) + 1];
			if (next && !isDone(cfg.steps[cfg.steps.indexOf(step) + 1])) {
				setTimeout(() => next.scrollIntoView({ behavior: 'smooth', block: 'start' }), 180);
			}
		} else if (checked) {
			if (step.max && state[step.key].length >= step.max) {
				// Limite atteinte : on refuse le choix.
				root.querySelector(`input[name="pb_${step.key}"][value="${id}"]`).checked = false;
				msg.textContent = `${step.label} : ${step.max} maximum.`;
				return;
			}
			state[step.key].push(id);
		} else {
			state[step.key] = state[step.key].filter((x) => x !== id);
		}
		update();
	}

	function update() {
		const required = cfg.steps.filter((s) => s.required);
		const done = required.filter(isDone).length;
		progressBar.style.width = (required.length ? (done / required.length) * 100 : 100) + '%';

		cfg.steps.forEach((s, i) => {
			const ok = state[s.key].length > 0;
			stepSections[i].classList.toggle('is-done', ok);
			stepSections[i]._badge.textContent = s.type === 'multi' && s.max ? `${state[s.key].length}/${s.max}` : ok ? '✓' : '';
		});

		summaryList.replaceChildren(
			...cfg.steps.flatMap((s) => state[s.key].map((id) =>
				el('li', {}, el('span', {}, byId[id].name), el('span', {}, money.format(byId[id].price)))
			))
		);
		if (!summaryList.children.length) summaryList.append(el('li', { class: 'pb__summary-empty' }, 'Commence par choisir une taille 👆'));

		totalEl.textContent = money.format(unitPrice() * qty);
		qtyEl.textContent = String(qty);
		addBtn.disabled = done < required.length;
		addBtn.textContent = done < required.length ? `Encore ${required.length - done} étape${required.length - done > 1 ? 's' : ''}` : 'Ajouter au panier';
	}

	async function addToCart() {
		addBtn.disabled = true;
		msg.textContent = '';
		try {
			const body = new URLSearchParams({
				action: 'pb_add_to_cart',
				nonce: cfg.nonce,
				qty: String(qty),
				selection: JSON.stringify(state),
			});
			const res = await fetch(cfg.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' });
			const json = await res.json();
			if (!json.success) throw new Error(json.data?.message || 'Erreur, réessaie.');
			msg.replaceChildren('Ajoutée au panier ! ',
				el('a', { href: json.data.cartUrl }, 'Voir le panier'), ' · ',
				el('a', { href: json.data.checkoutUrl }, 'Commander'));
		} catch (e) {
			msg.textContent = e.message;
		} finally {
			update();
		}
	}

	const stepper = el('div', { class: 'pb__stepper' },
		el('button', { type: 'button', 'aria-label': 'Moins', onclick: () => { qty = Math.max(1, qty - 1); update(); } }, '−'),
		qtyEl,
		el('button', { type: 'button', 'aria-label': 'Plus', onclick: () => { qty = Math.min(20, qty + 1); update(); } }, '+')
	);

	root.replaceChildren(
		el('div', { class: 'pb__progress' }, progressBar),
		el('div', { class: 'pb__layout' },
			stepsEl,
			el('aside', { class: 'pb__summary' },
				el('h3', {}, 'Ta poutine'),
				summaryList,
				el('div', { class: 'pb__bar' },
					stepper,
					el('div', { class: 'pb__total-wrap' }, 'Total ', totalEl),
					addBtn
				),
				msg
			)
		)
	);
	// Présélection depuis la carte : ?poutine=ID
	const wanted = Number(new URLSearchParams(location.search).get('poutine'));
	const pre = wanted && root.querySelector(`input[name="pb_poutine"][value="${wanted}"]`);
	if (pre) {
		pre.checked = true;
		state.poutine = [wanted];
	}
	update();
	if (pre) setTimeout(() => stepSections[1]?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
})();
