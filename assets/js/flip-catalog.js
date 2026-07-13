/* =========================================================
   Folio – 3D Flipbook Catalog
   StPageFlip + PDF.js + auto-fit + حالت کتاب (جلد تکی)
   ========================================================= */
(function () {
	'use strict';

	// نشانهٔ نسخه — برای تشخیص اینکه مرورگر کد جدید را اجرا می‌کند یا نسخهٔ کهنهٔ کش‌شده را.
	console.log('%cFOLIO viewer build 2026-07-13 ✓ (true-RTL + first-page + fullscreen-rebuild)', 'color:#FFCC00;background:#111;padding:3px 8px;border-radius:4px;font-weight:bold');

	var FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	function fa(n) { return String(n).replace(/\d/g, function (d) { return FA[+d]; }); }

	var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- صدای ورق (WebAudio) ---------- */
	var AC = window.AudioContext || window.webkitAudioContext;
	var actx = null;
	function ensureAudio() {
		if (!actx && AC) { try { actx = new AC(); } catch (e) { actx = null; } }
		if (actx && actx.state === 'suspended') { actx.resume(); }
	}
	function playFlip() {
		if (!actx) { return; }
		var dur = 0.5, rate = actx.sampleRate, frames = Math.floor(rate * dur);
		var buf = actx.createBuffer(1, frames, rate), d = buf.getChannelData(0);
		for (var i = 0; i < frames; i++) {
			var t = i / frames;
			var env = Math.pow(1 - t, 1.6);
			d[i] = (Math.random() * 2 - 1) * env * (0.55 + 0.45 * Math.sin(t * Math.PI)) * 0.9;
		}
		var src = actx.createBufferSource(); src.buffer = buf;
		var bp = actx.createBiquadFilter(); bp.type = 'bandpass';
		bp.frequency.setValueAtTime(850, actx.currentTime);
		bp.frequency.exponentialRampToValueAtTime(2600, actx.currentTime + dur);
		bp.Q.value = 0.55;
		var g = actx.createGain();
		g.gain.setValueAtTime(0.0001, actx.currentTime);
		g.gain.exponentialRampToValueAtTime(0.32, actx.currentTime + 0.05);
		g.gain.exponentialRampToValueAtTime(0.0001, actx.currentTime + dur);
		src.connect(bp); bp.connect(g); g.connect(actx.destination);
		src.start();
	}

	/* ---------- رندر PDF (پیش‌رونده + مقیاس تطبیقی = بدون هنگ) ---------- */
	function pdfTargetWidth(numPages) {
		var dpr = Math.min(2, window.devicePixelRatio || 1);
		// مبنا = عرض صفحه‌نمایش (نه فقط پنجره) تا در حالت تمام‌صفحه هم تصویر تیز بماند.
		var base = Math.max(window.innerWidth || 0, (window.screen && window.screen.width) || 0, 1280);
		var w = Math.min(2200, Math.round(base * dpr));
		// هرچه صفحات بیشتر، برای حافظه کمی محافظه‌کارتر — ولی همچنان تیز.
		if (numPages > 80) { w = Math.min(w, 1100); }
		else if (numPages > 40) { w = Math.min(w, 1400); }
		else if (numPages > 20) { w = Math.min(w, 1600); }
		else { w = Math.min(w, 1900); }
		return w;
	}

	function renderPdf(url, worker, onProgress) {
		return new Promise(function (resolve, reject) {
			if (typeof pdfjsLib === 'undefined') { reject('pdfjs missing'); return; }
			pdfjsLib.GlobalWorkerOptions.workerSrc = worker;
			pdfjsLib.getDocument({ url: url, disableAutoFetch: false }).promise.then(function (pdf) {
				var n = pdf.numPages, images = [], ratio = 1.414, i = 1;
				var targetW = pdfTargetWidth(n);
				function next() {
					if (i > n) { resolve({ images: images, ratio: ratio }); return; }
					pdf.getPage(i).then(function (page) {
						var v1 = page.getViewport({ scale: 1 });
						var vp = page.getViewport({ scale: targetW / v1.width });
						var canvas = document.createElement('canvas');
						canvas.width = vp.width; canvas.height = vp.height;
						var ctx = canvas.getContext('2d');
						ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, vp.width, vp.height);
						page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
							images.push(canvas.toDataURL('image/jpeg', 0.92));
							if (i === 1) { ratio = vp.height / vp.width; }
							if (page.cleanup) { page.cleanup(); }
							canvas.width = 0; canvas.height = 0; // آزادسازی حافظه
							onProgress(i, n);
							i++;
							setTimeout(next, 0); // مهلت به main thread → صفحه هنگ نمی‌کند
						}).catch(reject);
					}).catch(reject);
				}
				next();
			}).catch(reject);
		});
	}

	/* ---------- کلاس اصلی ---------- */
	function Catalog(root) {
		this.root = root;
		this.stage = root.querySelector('.fc-stage');
		this.scene = root.querySelector('.fc-scene');
		this.wrap = root.querySelector('.fc-book-wrap');
		this.loader = root.querySelector('.fc-loader');
		this.progressEl = root.querySelector('.fc-progress');
		this.counterEl = root.querySelector('.fc-counter');
		this.prevBtn = root.querySelector('.fc-prev');
		this.nextBtn = root.querySelector('.fc-next');
		this.soundBtn = root.querySelector('.fc-sound');
		this.fullBtn = root.querySelector('.fc-full');

		try { this.urls = JSON.parse(root.querySelector('.fc-json').textContent) || []; }
		catch (e) { this.urls = []; }

		this.dir = root.dataset.dir === 'ltr' ? 'ltr' : 'rtl';
		this.rtl = this.dir === 'rtl';

		var m = root.dataset.mode || 'book';
		this.mode = (m === 'single') ? 'single' : 'book'; // legacy auto → book

		var iv = root.dataset.intro || 'settle';
		var map = { tilt: 'settle', zoom: 'rise', flip3d: 'unfold', drop: 'settle', flip: 'unfold' };
		this.intro = map[iv] || iv;

		this.ratio = parseFloat(root.dataset.ratio) || 1.414;
		this.source = root.dataset.source || 'images';
		this.pdf = root.dataset.pdf || '';
		this.worker = root.dataset.worker || '';
		this.soundOn = root.dataset.sound === 'on';
		this.count = this.urls.length;
	}

	/* اندازه‌ی خودکار: کتاب هیچ‌وقت از ارتفاع صفحه بیرون نمی‌زند */
	Catalog.prototype.fitStage = function () {
		var isFull = document.fullscreenElement === this.root || document.webkitFullscreenElement === this.root;
		var vh = window.innerHeight || 800;
		var reserve = isFull ? 150 : 200;          // جای کنترل‌ها + حاشیه
		var maxH = Math.max(260, vh - reserve);
		if (!isFull) { maxH = Math.min(maxH, 660); }

		/*
		 * دوصفحه‌ای (spread) روی دسکتاپ، جلد با showCover تکی می‌ماند.
		 * page-flip وقتی blockWidth ≥ ۲×minWidth (=۵۲۰) باشد landscape/دوصفحه می‌شود؛
		 * پس عرض را ~۲×عرضِ یک صفحه می‌دهیم. موبایلِ باریک (< ۵۲۰) خودکار تک‌صفحه (portrait).
		 */
		var pageW = Math.floor(maxH / this.ratio);  // عرضِ یک صفحه که در ارتفاع جا شود
		var avail = (this.wrap.clientWidth || 900) - 40;
		var w = Math.max(240, Math.min(avail, pageW * 2, 1400));
		this.stageW = w;                            // برای تشخیصِ تغییرِ محسوسِ اندازه (rebuild)
		this.scene.style.maxWidth = w + 'px';
	};

	/*
	 * RTL واقعی بدونِ خرابیِ جلد:
	 * StPageFlip فقط چپ‌به‌راست رندر می‌کند. برای فارسی آرایه را برعکس می‌کنیم تا
	 * ورق‌زدن راست‌به‌چپ حس شود — اما نقطهٔ شروع را روی «جلد» (آخرین ایندکسِ آرایهٔ برعکس)
	 * می‌گذاریم تا کتاب از صفحهٔ اول باز شود، نه از آخر. برای انگلیسی همه‌چیز طبیعی می‌ماند.
	 */
	Catalog.prototype.orderedImages = function () {
		return this.rtl ? this.urls.slice().reverse() : this.urls.slice();
	};
	// ایندکسِ موتور که «جلد/صفحهٔ اولِ کاتالوگ» را نشان می‌دهد.
	Catalog.prototype.coverIndex = function (len) {
		return this.rtl ? (len - 1) : 0;
	};

	Catalog.prototype.start = function () {
		var self = this;
		if (this.source === 'pdf' && this.pdf) {
			this.loader.classList.add('fc-loading');
			renderPdf(this.pdf, this.worker, function (i, n) {
				if (self.progressEl) { self.progressEl.textContent = 'آماده‌سازی صفحه‌ها ' + fa(i) + '/' + fa(n); }
			}).then(function (res) {
				self.urls = res.images;
				self.count = res.images.length;
				self.ratio = res.ratio || self.ratio;
				self.root.style.setProperty('--fc-ratio', self.ratio);
				self.build();
			}).catch(function (err) {
				if (self.progressEl) { self.progressEl.textContent = 'خطا در خواندن PDF'; }
				console.error('Folio PDF:', err);
			});
		} else {
			this.build();
		}
	};

	/*
	 * ساختِ موتورِ StPageFlip روی اندازه‌ی جاری، با شروع از صفحه‌ی دلخواه.
	 * از این متد هم بار اول (build) و هم برای بازسازی (rebuild) استفاده می‌شود.
	 *
	 * ترتیب طبیعی (جلد = صفحه‌ی اول) تا showCover بتواند جلد را «تنها» ایزوله کند.
	 * StPageFlip فقط اولین صفحه‌ی آرایه را به‌عنوان جلدِ تنها می‌گذارد؛ پس برعکس نمی‌کنیم.
	 */
	Catalog.prototype.mount = function (images, startIndex) {
		var self = this;
		this.engineCount = images.length;

		this.fitStage();

		var baseW = 500, baseH = Math.round(baseW * this.ratio);
		var pf = new St.PageFlip(this.stage, {
			width: baseW, height: baseH, size: 'stretch',
			// minWidth کوچک تا page-flip روی موبایل هم دوصفحه (landscape) بماند، مثل دسکتاپ.
			// آستانه‌ی portrait = ۲×minWidth = ۲۸۰؛ عرضِ موبایلِ ~۳۲۰ ازش بیشتر است → دوصفحه.
			minWidth: 140, maxWidth: 1700,
			minHeight: 140, maxHeight: 2600,
			drawShadow: true, maxShadowOpacity: 0.7, flippingTime: 850,
			usePortrait: true,          // اجازه‌ی تک‌صفحه در موبایل
			showCover: true,            // جلدِ تنها → باز شدن دوصفحه‌ای
			autoSize: true, mobileScrollSupport: true,
			showPageCorners: true, swipeDistance: 30,
			useMouseEvents: true, clickEventForward: true, disableFlipByClick: false
		});
		this.pf = pf;

		pf.on('flip', function (e) { self.updateCounter(e.data); });
		pf.on('changeState', function (e) {
			if (e.data === 'read') { self.reveal(); }
			// صدای ورق دقیقاً هنگام شروعِ چرخش صفحه (باز شدن/ورق‌زدن)
			if (e.data === 'flipping' && self.soundOn) { self.playSound(); }
			// توقف انیمیشن‌های محیطی هنگام drag تا با رندر کاغذ رقابت نکنند
			self.wrap.classList.toggle('fc-dragging', e.data === 'user_fold' || e.data === 'fold_corner');
		});

		pf.loadFromImages(images);

		// صفحه‌ی شروع را دقیق تنظیم کن (بار اول = ۰ یعنی جلد؛ در rebuild = صفحه‌ی جاری).
		var target = Math.min(Math.max(parseInt(startIndex, 10) || 0, 0), images.length - 1);
		if (pf.getCurrentPageIndex() !== target && pf.turnToPage) { pf.turnToPage(target); }

		this.decorate();
	};

	Catalog.prototype.build = function () {
		if (!this.count || typeof St === 'undefined' || !St.PageFlip) { return; }

		var imgs = this.orderedImages();
		var cover = this.coverIndex(imgs.length);
		this.mount(imgs, cover);
		this.bind();

		var self = this;
		setTimeout(function () {
			// اول روی جلد بنشین، بعد نمایان کن — تا در RTL فلاشِ صفحهٔ آخر دیده نشود.
			var c = self.coverIndex(self.engineCount);
			if (self.pf.getCurrentPageIndex() !== c && self.pf.turnToPage) { self.pf.turnToPage(c); }
			self.reveal();
			self.updateCounter(self.pf.getCurrentPageIndex());
		}, 450);
		this.updateCounter(cover);
	};

	/*
	 * بازسازیِ موتور در اندازه‌ی تازه، با حفظِ صفحه‌ی جاری.
	 * چرا rebuild و نه pf.update()؟ StPageFlip هنگام update() بومِ canvas را resize نمی‌کند
	 * (فقط چیدمانِ صفحه‌ها را بزرگ می‌کند)، پس در تمام‌صفحه/تغییرِ اندازه سمتِ راست و پایینِ
	 * کتاب بیرونِ بوم می‌افتد و «برش» می‌خورد. تنها راهِ مطمئن، ساختِ دوباره‌ی موتور است.
	 */
	Catalog.prototype.rebuild = function () {
		if (!this.pf) { this.fitStage(); return; }
		if (!this.urls || !this.urls.length) { return; }
		var idx = this.pf.getCurrentPageIndex ? this.pf.getCurrentPageIndex() : 0;
		// نکته: StPageFlip.destroy() خودِ المانِ .fc-stage را هم از DOM حذف می‌کند،
		// پس بعد از آن یک .fc-stage تازه می‌سازیم و موتور را رویش سوار می‌کنیم.
		try { this.pf.destroy(); } catch (e) {}
		if (this.stage && this.stage.parentNode) { this.stage.parentNode.removeChild(this.stage); }
		var stage = document.createElement('div');
		stage.className = 'fc-stage';
		this.scene.appendChild(stage);
		this.stage = stage;
		this.mount(this.orderedImages(), idx);
		this.updateCounter(this.pf.getCurrentPageIndex());
	};

	/* المان‌های تزئینی: سایه‌ی زمین + برق گوشه‌ی کاغذ */
	Catalog.prototype.decorate = function () {
		var floor = document.createElement('div');
		floor.className = 'fc-floor';
		floor.setAttribute('aria-hidden', 'true');
		this.stage.appendChild(floor);

		if (!REDUCED) {
			var glint = document.createElement('div');
			glint.className = 'fc-glint';
			glint.setAttribute('aria-hidden', 'true');
			this.stage.appendChild(glint);
		}
	};

	Catalog.prototype.reveal = function () {
		if (this.root.classList.contains('fc-ready')) { return; }
		this.root.classList.add('fc-ready');
		if (!this.intro || this.intro === 'none' || REDUCED) { return; }

		var scene = this.scene, intro = this.intro;
		scene.style.willChange = 'transform, opacity';
		scene.classList.add('fc-in-' + intro);

		if (intro === 'sweep') {
			var shine = document.createElement('div');
			shine.className = 'fc-shine';
			shine.setAttribute('aria-hidden', 'true');
			scene.appendChild(shine);
			shine.addEventListener('animationend', function () { shine.remove(); }, { once: true });
		}

		// بعد از پایان انیمیشن، هر transform سه‌بعدی حذف می‌شود تا drag دقیق بماند
		scene.addEventListener('animationend', function h(e) {
			if (e.target !== scene) { return; }
			scene.classList.remove('fc-in-' + intro);
			scene.style.transform = 'none';
			scene.style.willChange = 'auto';
			scene.removeEventListener('animationend', h);
		});
	};

	/*
	 * شماره‌ی صفحه‌ی کاتالوگ از روی ایندکسِ موتور.
	 * RTL: آرایه برعکس است، پس ایندکس ۰ = آخرین صفحه؛ شماره = count - li.
	 * LTR: ترتیب طبیعی؛ شماره = li + 1.
	 */
	Catalog.prototype.toCatalog = function (li) {
		var n = this.rtl ? (this.count - li) : (li + 1);
		return Math.min(Math.max(n, 1), this.count);
	};

	Catalog.prototype.updateCounter = function (li) {
		if (typeof li !== 'number' || li < 0) { li = 0; }
		var n = this.toCatalog(li);
		this.counterEl.textContent = fa(n) + ' / ' + fa(this.count);
		var last = (this.engineCount || this.count) - 1;
		// «اول کاتالوگ» (جلد) و «آخر کاتالوگ» بسته به جهت، دو سرِ متفاوتِ آرایه‌اند.
		var atCover = this.rtl ? (li >= last) : (li <= 0);
		var atBack  = this.rtl ? (li <= 0) : (li >= last);
		if (this.prevBtn) { this.prevBtn.disabled = atCover; }   // prev = بازگشت به‌سمتِ جلد
		if (this.nextBtn) { this.nextBtn.disabled = atBack; }    // next = پیش‌روی در کاتالوگ
	};

	// پیش‌رویِ کاتالوگ: RTL با flipPrev (چون آرایه برعکس است) → حسِ راست‌به‌چپ. LTR با flipNext.
	Catalog.prototype.forward  = function () { if (this.pf) { this.rtl ? this.pf.flipPrev() : this.pf.flipNext(); } };
	Catalog.prototype.backward = function () { if (this.pf) { this.rtl ? this.pf.flipNext() : this.pf.flipPrev(); } };

	// پخش صدا با محافظ ضدتکرار (چند رویداد پشت‌سرهم = یک صدا)
	Catalog.prototype.playSound = function () {
		var t = Date.now();
		if (this._lastSound && (t - this._lastSound) < 220) { return; }
		this._lastSound = t;
		ensureAudio();
		playFlip();
	};

	Catalog.prototype.toggleSound = function () {
		this.soundOn = !this.soundOn;
		this.soundBtn.setAttribute('aria-pressed', this.soundOn ? 'true' : 'false');
		if (this.soundOn) { this.playSound(); }
	};

	Catalog.prototype.refresh = function () {
		var prev = this.stageW || 0;
		this.fitStage();                          // stageW را به‌روز می‌کند
		// فقط وقتی اندازه‌ی هدف محسوس عوض شده موتور را بازبساز — چون update() بومِ canvas را
		// resize نمی‌کند و باعثِ برشِ کتاب می‌شود. تغییرِ ناچیز → کاری لازم نیست.
		if (Math.abs((this.stageW || 0) - prev) > 12) { this.rebuild(); }
	};

	Catalog.prototype.toggleFull = function () {
		var el = this.root;
		if (!document.fullscreenElement && !document.webkitFullscreenElement) {
			(el.requestFullscreen || el.webkitRequestFullscreen || function () {}).call(el);
		} else {
			(document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
		}
		// بازسازی توسطِ رویدادِ fullscreenchange (بعد از نشستِ چیدمان) انجام می‌شود.
	};

	Catalog.prototype.bind = function () {
		var self = this;
		this.nextBtn.addEventListener('click', function () { self.forward(); });
		this.prevBtn.addEventListener('click', function () { self.backward(); });
		this.fullBtn.addEventListener('click', function () { self.toggleFull(); });
		if (this.soundBtn) { this.soundBtn.addEventListener('click', function () { self.toggleSound(); }); }

		this.root.setAttribute('tabindex', '0');
		this.root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { self.rtl ? self.backward() : self.forward(); }
			else if (e.key === 'ArrowLeft') { self.rtl ? self.forward() : self.backward(); }
		});

		var rt;
		window.addEventListener('resize', function () {
			clearTimeout(rt);
			rt = setTimeout(function () { self.refresh(); }, 200);
		});
		// در تمام‌صفحه اندازه به‌کل عوض می‌شود؛ بعد از نشستِ چیدمان موتور را بازبساز تا برش نخورد.
		var onFull = function () {
			self.root.classList.toggle('fc-is-full', document.fullscreenElement === self.root || document.webkitFullscreenElement === self.root);
			setTimeout(function () { self.rebuild(); }, 300);
		};
		document.addEventListener('fullscreenchange', onFull);
		document.addEventListener('webkitfullscreenchange', onFull);

		var wake = function () { if (self.soundOn) { ensureAudio(); } window.removeEventListener('pointerdown', wake); };
		window.addEventListener('pointerdown', wake, { once: true });
	};

	/* ---------- راه‌اندازی تنبل ---------- */
	function initRoot(root) {
		if (root.dataset.fcInit) { return; }
		root.dataset.fcInit = '1';
		new Catalog(root).start();
	}
	function boot() {
		var roots = document.querySelectorAll('.fc-catalog');
		if (!roots.length) { return; }
		if (!('IntersectionObserver' in window)) { Array.prototype.forEach.call(roots, initRoot); return; }
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) { if (en.isIntersecting) { initRoot(en.target); io.unobserve(en.target); } });
		}, { rootMargin: '300px' });
		Array.prototype.forEach.call(roots, function (r) { io.observe(r); });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
	else { boot(); }
})();
