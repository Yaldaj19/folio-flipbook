/* مدیریت انتخاب و مرتب‌سازی صفحات کاتالوگ در پیشخوان */
(function ($) {
	'use strict';

	function collectIds() {
		var ids = [];
		$('#fc-pages-grid .fc-page-item').each(function () {
			ids.push($(this).data('id'));
		});
		$('#fc-pages-input').val(ids.join(','));
		renumber();
	}

	function renumber() {
		$('#fc-pages-grid .fc-page-item').each(function (i) {
			$(this).find('.fc-num').text(i + 1);
		});
	}

	function itemHtml(id, url) {
		return (
			'<div class="fc-page-item" data-id="' + id + '">' +
			'<img src="' + url + '" alt="">' +
			'<button type="button" class="fc-remove" title="حذف">&times;</button>' +
			'<span class="fc-num"></span>' +
			'</div>'
		);
	}

	function toggleSource() {
		var v = $('input[name="fc_source"]:checked').val() || 'images';
		$('#fc-src-images').toggle(v === 'images');
		$('#fc-src-pdf').toggle(v === 'pdf');
	}

	$(function () {
		var frame, pdfFrame;

		toggleSource();
		$('input[name="fc_source"]').on('change', toggleSource);

		// انتخاب فایل PDF.
		$('#fc-pick-pdf').on('click', function (e) {
			e.preventDefault();
			if (pdfFrame) { pdfFrame.open(); return; }
			pdfFrame = wp.media({
				title: 'انتخاب فایل PDF',
				button: { text: 'استفاده از این PDF' },
				library: { type: 'application/pdf' },
				multiple: false,
			});
			pdfFrame.on('select', function () {
				var a = pdfFrame.state().get('selection').first().toJSON();
				$('#fc-pdf-input').val(a.id);
				$('#fc-pdf-name').text(a.filename || a.url);
				$('#fc-remove-pdf').show();
			});
			pdfFrame.open();
		});

		$('#fc-remove-pdf').on('click', function () {
			$('#fc-pdf-input').val('');
			$('#fc-pdf-name').text('');
			$(this).hide();
		});

		// انتخاب تصویر پیش‌نمایش PDF.
		var thumbFrame;
		$('#fc-pick-thumb').on('click', function (e) {
			e.preventDefault();
			if (thumbFrame) { thumbFrame.open(); return; }
			thumbFrame = wp.media({
				title: 'انتخاب تصویر پیش‌نمایش (جلد PDF)',
				button: { text: 'استفاده از این تصویر' },
				library: { type: 'image' },
				multiple: false,
			});
			thumbFrame.on('select', function () {
				var a = thumbFrame.state().get('selection').first().toJSON();
				var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
				$('#fc-thumb-input').val(a.id);
				$('#fc-thumb-preview').show().find('img').attr('src', url);
				$('#fc-remove-thumb').show();
			});
			thumbFrame.open();
		});

		$('#fc-remove-thumb').on('click', function () {
			$('#fc-thumb-input').val('');
			$('#fc-thumb-preview').hide().find('img').attr('src', '');
			$(this).hide();
		});

		$('#fc-add-pages').on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'انتخاب صفحات کاتالوگ',
				button: { text: 'افزودن به کاتالوگ' },
				library: { type: 'image' },
				multiple: true,
			});

			frame.on('select', function () {
				var selection = frame.state().get('selection');
				var existing = {};
				$('#fc-pages-grid .fc-page-item').each(function () {
					existing[$(this).data('id')] = true;
				});

				selection.each(function (att) {
					var a = att.toJSON();
					if (existing[a.id]) {
						return;
					}
					var url =
						a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
					$('#fc-pages-grid').append(itemHtml(a.id, url));
				});

				collectIds();
			});

			frame.open();
		});

		// حذف تک صفحه.
		$('#fc-pages-grid').on('click', '.fc-remove', function () {
			$(this).closest('.fc-page-item').remove();
			collectIds();
		});

		// مرتب‌سازی با کشیدن.
		$('#fc-pages-grid').sortable({
			items: '.fc-page-item',
			placeholder: 'fc-page-placeholder',
			tolerance: 'pointer',
			update: collectIds,
		});

		// کپی شورت‌کد.
		$(document).on('click', '.fc-copy', function () {
			var text = $(this).data('copy');
			var $btn = $(this);
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text).then(function () {
					var old = $btn.text();
					$btn.text('✓ کپی شد');
					setTimeout(function () {
						$btn.text(old);
					}, 1500);
				});
			} else {
				window.prompt('کپی کنید:', text);
			}
		});
	});
})(jQuery);
