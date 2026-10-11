/* Les Poutineurs : animations GSAP (accueil). Contenu lisible sans JS ; rien ne bouge si "réduire les animations" est actif. */
(() => {
	const g = window.gsap;
	if (!g || !window.ScrollTrigger) return;
	g.registerPlugin(ScrollTrigger);
	const $ = (s, c = document) => [...c.querySelectorAll(s)];

	/* Découpe un titre en mots/lettres (le texte d'origine reste lu par les lecteurs d'écran). */
	function split(el) {
		const text = el.textContent.trim();
		el.setAttribute('aria-label', text);
		el.replaceChildren(
			...text.split(/\s+/).flatMap((word, i, all) => {
				const w = document.createElement('span');
				w.className = 'pbt-w';
				w.setAttribute('aria-hidden', 'true');
				[...word].forEach((ch) => {
					const c = document.createElement('span');
					c.className = 'pbt-c';
					c.textContent = ch;
					w.append(c);
				});
				return i < all.length - 1 ? [w, document.createTextNode(' ')] : [w];
			})
		);
		return $('.pbt-c', el);
	}

	const mm = g.matchMedia();

	mm.add('(prefers-reduced-motion: no-preference)', () => {
		/* --- Hero : lettres qui rebondissent, puis vague continue --- */
		const heroTitle = $('.pbt-hero h1')[0];
		if (heroTitle) {
			const chars = split(heroTitle);
			const tl = g.timeline({ defaults: { ease: 'back.out(1.8)' } });
			tl.from(chars, { yPercent: 120, rotate: () => g.utils.random(-14, 14), opacity: 0, duration: 0.7, stagger: 0.03 })
				.from('.pbt-hero__lead, .pbt-hero__cta', { y: 30, opacity: 0, duration: 0.6, stagger: 0.12, ease: 'power3.out' }, '-=0.3')
				.from('.pbt-hero__img', { scale: 0.5, rotate: -20, opacity: 0, duration: 1.1, ease: 'elastic.out(1, 0.55)' }, 0.1)
				.from('.pbt-ring-wrap', { scale: 0.6, opacity: 0, duration: 0.9, ease: 'power3.out' }, 0.3)
				.add(() => g.to(chars, { y: -7, duration: 0.9, ease: 'sine.inOut', repeat: -1, yoyo: true, stagger: { each: 0.07, repeat: -1, yoyo: true } }));
			g.to('.pbt-ring', { rotation: 360, duration: 28, ease: 'none', repeat: -1, transformOrigin: '50% 50%' });
			g.to('.pbt-ring-wrap', { rotation: 200, ease: 'none', scrollTrigger: { trigger: '.pbt-hero', start: 'top top', end: 'bottom top', scrub: true } });
			g.to('.pbt-hero__img', { yPercent: 12, ease: 'none', scrollTrigger: { trigger: '.pbt-hero', start: 'top top', end: 'bottom top', scrub: true } });
		}

		/* --- Titres de section : lettres qui tombent à l'entrée --- */
		$('section h2').filter((h) => !h.closest('.pbx-card, .pbx-opt, .pbx-store')).forEach((h) => {
			const chars = split(h);
			g.from(chars, { yPercent: 110, rotate: () => g.utils.random(-10, 10), opacity: 0, duration: 0.6, ease: 'back.out(1.7)', stagger: 0.025, scrollTrigger: { trigger: h, start: 'top 88%', once: true } });
		});

		/* --- Bandeaux défilants : vitesse et sens suivent le scroll --- */
		$('.pbt-marquee').forEach((band) => {
			const track = band.querySelector('.pbt-marquee__track');
			const item = track.firstElementChild;
			while (track.scrollWidth < band.clientWidth * 2.2) track.append(item.cloneNode(true));
			[...track.children].forEach((c) => track.append(c.cloneNode(true))); // 2 jeux identiques : boucle sur -50 %.
			const base = band.classList.contains('pbt-marquee--b') ? -1 : 1;
			const tween = g.to(track, { xPercent: -50, ease: 'none', duration: 28, repeat: -1 });
			tween.timeScale(base);
			ScrollTrigger.create({
				onUpdate(self) {
					const boost = g.utils.clamp(0, 7, Math.abs(self.getVelocity()) / 350);
					g.timeline().to(tween, { timeScale: base * self.direction * (1 + boost), duration: 0.15, overwrite: true })
						.to(tween, { timeScale: base * self.direction, duration: 1.1, ease: 'power2.out' });
				},
			});
		});

		/* --- Texte sur une courbe : la vague se déforme et le texte glisse --- */
		const wave = document.getElementById('pbt-wave');
		const waveText = document.getElementById('pbt-wave-text');
		if (wave && waveText) {
			const o = { a: 20, b: 220 };
			const draw = () => wave.setAttribute('d', `M-60,120 C140,${o.a} 340,${o.b} 540,120 S940,${o.a} 1260,120`);
			const st = { trigger: '.pbt-curve', start: 'top bottom', end: 'bottom top', scrub: 0.6 };
			g.to(o, { a: 220, b: 20, ease: 'none', onUpdate: draw, scrollTrigger: st });
			g.fromTo(waveText, { attr: { startOffset: '0%' } }, { attr: { startOffset: '-100%' }, ease: 'none', scrollTrigger: st });
			g.from('.pbt-curve', { opacity: 0, scale: 0.9, duration: 0.8, scrollTrigger: { trigger: '.pbt-curve', start: 'top 90%', once: true } });
		}

		/* --- Étapes --- */
		g.from('.pbt-steps li', { y: 70, rotate: (i) => (i % 2 ? 4 : -4), opacity: 0, duration: 0.8, ease: 'back.out(1.5)', stagger: 0.15, scrollTrigger: { trigger: '.pbt-steps', start: 'top 85%', once: true } });

		/* --- Cartes : entrée en vrac, inclinaison au repos, redressées au survol, photo en parallaxe --- */
		const cards = $('.pbx-card');
		cards.forEach((c, i) => {
			c._tilt = (i % 2 ? 1 : -1) * g.utils.random(0.6, 1.8);
			c.addEventListener('pointerenter', () => g.to(c, { rotate: 0, scale: 1.03, duration: 0.35, ease: 'back.out(2)' }));
			c.addEventListener('pointerleave', () => g.to(c, { rotate: c._tilt, scale: 1, duration: 0.4, ease: 'power2.out' }));
		});
		ScrollTrigger.batch(cards, {
			start: 'top 92%',
			once: true,
			onEnter: (batch) => g.fromTo(batch, { y: 90, opacity: 0, rotate: () => g.utils.random(-8, 8), scale: 0.92 }, { y: 0, opacity: 1, scale: 1, rotate: (i, el) => el._tilt, duration: 0.9, ease: 'back.out(1.4)', stagger: 0.09 }),
		});
		g.set(cards, { opacity: 0 });
		$('.pbx-img').forEach((img) => g.fromTo(img, { yPercent: -7 }, { yPercent: 7, ease: 'none', scrollTrigger: { trigger: img.closest('.pbx-media'), start: 'top bottom', end: 'bottom top', scrub: true } }));
		$('.pbx-sticker').forEach((s) => g.from(s, { scale: 0, rotate: -90, duration: 0.7, ease: 'elastic.out(1, 0.5)', scrollTrigger: { trigger: s, start: 'top 95%', once: true } }));

		/* --- Options, boutiques, FAQ --- */
		['.pbx-opt', '.pbx-store', '.pbt-faq details'].forEach((sel) =>
			ScrollTrigger.batch(sel, { start: 'top 90%', once: true, onEnter: (b) => g.from(b, { y: 50, opacity: 0, duration: 0.7, ease: 'power3.out', stagger: 0.12 }) })
		);
		g.to('.pbx-opt li.is-featured', { rotate: 2, duration: 0.25, repeat: -1, yoyo: true, repeatDelay: 1.6, ease: 'sine.inOut' });
	});
})();
