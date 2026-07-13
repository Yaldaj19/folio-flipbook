<?php
/**
 * شورت‌کد [flip_catalog] و رندر viewer (موتور StPageFlip).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FC_Shortcode {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'flip_catalog', array( __CLASS__, 'render' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'no_optimize_js' ), 10, 2 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'no_optimize_css' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_nocache' ) );
	}

	/**
	 * صفحه‌هایی که کاتالوگ دارند نباید در مرورگر کش شوند؛ وگرنه HTMLِ کهنه به فایل JS قدیمی
	 * لینک می‌دهد و کاربر نسخهٔ قبلی (که از صفحهٔ آخر باز می‌کرد) را می‌بیند.
	 */
	public static function maybe_nocache() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'flip_catalog' ) ) {
			nocache_headers();
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
				header( 'X-LiteSpeed-Cache-Control: no-cache' );
			}
		}
	}

	/**
	 * جلوگیری از defer/combine شدن اسکریپت‌های viewer توسط LiteSpeed
	 * (در غیر این صورت تا اولین تعامل کاربر اجرا نمی‌شوند و کاتالوگ بالا نمی‌آید).
	 */
	public static function no_optimize_js( $tag, $handle ) {
		if ( in_array( $handle, array( 'fc-pageflip', 'fc-pdfjs', 'fc-pdfjs-worker', 'flip-catalog' ), true ) ) {
			$tag = str_replace( ' src=', ' data-no-optimize="1" data-no-defer="1" data-cfasync="false" src=', $tag );
		}
		return $tag;
	}

	public static function no_optimize_css( $tag, $handle ) {
		if ( in_array( $handle, array( 'fc-pageflip', 'flip-catalog' ), true ) ) {
			$tag = str_replace( ' href=', ' data-no-optimize="1" href=', $tag );
		}
		return $tag;
	}

	public static function register_assets() {
		wp_register_script( 'fc-pageflip', FC_URL . 'assets/vendor/page-flip.browser.js', array(), '2.0.7', true );
		wp_register_style( 'fc-pageflip', FC_URL . 'assets/vendor/stPageFlip.css', array(), '2.0.7' );
		wp_register_script( 'fc-pdfjs', FC_URL . 'assets/vendor/pdf.min.js', array(), '3.11.174', true );
		// worker به‌صورت اسکریپت معمولی: PDF.js مسیر main-thread را می‌گیرد (کانال Worker این build ناسازگار است و هنگ می‌کند).
		wp_register_script( 'fc-pdfjs-worker', FC_URL . 'assets/vendor/pdf.worker.min.js', array( 'fc-pdfjs' ), '3.11.174', true );
		// نسخه را داخل «نام فایل» می‌گذاریم نه در query string، چون LiteSpeed کوئری‌استرینگ را حذف می‌کند
		// و مرورگر نسخهٔ کهنه را از کش سرو می‌کند. .htaccess داخل assets این نام را به فایل اصلی rewrite می‌کند.
		$css_v = @filemtime( FC_DIR . 'assets/css/flip-catalog.css' ) ?: FC_VERSION;
		$js_v  = @filemtime( FC_DIR . 'assets/js/flip-catalog.js' ) ?: FC_VERSION;
		wp_register_style( 'flip-catalog', FC_URL . 'assets/css/flip-catalog.' . $css_v . '.css', array( 'fc-pageflip' ), null );
		wp_register_script( 'flip-catalog', FC_URL . 'assets/js/flip-catalog.' . $js_v . '.js', array( 'fc-pageflip' ), null, true );
	}

	/**
	 * آدرس تصاویر صفحه‌ها.
	 */
	private static function page_urls( $ids ) {
		$urls  = array();
		$first = null;
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( $id, 'large' );
			if ( ! $url ) {
				$url = wp_get_attachment_image_url( $id, 'full' );
			}
			if ( ! $url ) {
				continue;
			}
			$urls[] = $url;
			if ( null === $first ) {
				$meta = wp_get_attachment_metadata( $id );
				$w    = isset( $meta['sizes']['large']['width'] ) ? (int) $meta['sizes']['large']['width'] : ( isset( $meta['width'] ) ? (int) $meta['width'] : 0 );
				$h    = isset( $meta['sizes']['large']['height'] ) ? (int) $meta['sizes']['large']['height'] : ( isset( $meta['height'] ) ? (int) $meta['height'] : 0 );
				$first = array( 'w' => $w, 'h' => $h );
			}
		}
		return array( $urls, $first );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'flip_catalog' );
		$id   = absint( $atts['id'] );

		// چندزبانگی: به نسخه‌ی همان زبانِ جاری سوییچ کن.
		if ( $id ) {
			$id = (int) apply_filters( 'wpml_object_id', $id, FC_CPT, true );
		}

		if ( ! $id || get_post_type( $id ) !== FC_CPT ) {
			return current_user_can( 'edit_posts' )
				? '<p style="color:#b32d2e">Folio: شناسه‌ی نامعتبر است.</p>'
				: '';
		}

		$source    = get_post_meta( $id, '_fc_source', true ) ?: 'images';
		$pdf_id    = (int) get_post_meta( $id, '_fc_pdf', true );
		$pdf_url   = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
		$thumb_id  = (int) get_post_meta( $id, '_fc_pdf_thumb', true );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';

		$direction = get_post_meta( $id, '_fc_direction', true ) ?: 'rtl';
		$mode      = get_post_meta( $id, '_fc_mode', true ) ?: 'book';
		$intro     = get_post_meta( $id, '_fc_intro', true ) ?: 'settle';
		$bg        = get_post_meta( $id, '_fc_background', true ) ?: 'none';
		$sound     = get_post_meta( $id, '_fc_sound', true ) ?: 'off';

		$urls  = array();
		$count = 0;
		$ratio = 1.414;

		if ( 'pdf' === $source ) {
			if ( ! $pdf_url ) {
				return current_user_can( 'edit_posts' )
					? '<p style="color:#b32d2e">Folio: فایل PDF انتخاب نشده است.</p>'
					: '';
			}
			wp_enqueue_script( 'fc-pdfjs' );
			wp_enqueue_script( 'fc-pdfjs-worker' );
		} else {
			$ids = get_post_meta( $id, '_fc_pages', true );
			$ids = $ids ? array_filter( array_map( 'absint', explode( ',', $ids ) ) ) : array();
			if ( empty( $ids ) ) {
				return current_user_can( 'edit_posts' )
					? '<p style="color:#b32d2e">Folio: هنوز صفحه‌ای انتخاب نشده است.</p>'
					: '';
			}
			list( $urls, $first ) = self::page_urls( $ids );
			if ( empty( $urls ) ) {
				return '';
			}
			$ratio = ( $first && $first['w'] && $first['h'] ) ? round( $first['h'] / $first['w'], 4 ) : 1.414;
			$count = count( $urls );
		}

		wp_enqueue_style( 'flip-catalog' );
		wp_enqueue_script( 'flip-catalog' );

		$uid    = 'fc-cat-' . $id . '-' . wp_rand( 100, 999 );
		$json   = wp_json_encode( array_values( $urls ) );
		$worker = FC_URL . 'assets/vendor/pdf.worker.min.js';

		ob_start();
		?>
		<div class="fc-catalog fc-<?php echo esc_attr( $direction ); ?> fc-bg-<?php echo esc_attr( $bg ); ?>" id="<?php echo esc_attr( $uid ); ?>"
			data-dir="<?php echo esc_attr( $direction ); ?>"
			data-mode="<?php echo esc_attr( $mode ); ?>"
			data-intro="<?php echo esc_attr( $intro ); ?>"
			data-ratio="<?php echo esc_attr( $ratio ); ?>"
			data-count="<?php echo esc_attr( $count ); ?>"
			data-source="<?php echo esc_attr( $source ); ?>"
			data-pdf="<?php echo esc_url( $pdf_url ); ?>"
			data-worker="<?php echo esc_url( $worker ); ?>"
			data-sound="<?php echo esc_attr( $sound ); ?>"
			style="--fc-ratio:<?php echo esc_attr( $ratio ); ?>"
			role="region" aria-roledescription="کاتالوگ ورق‌زن" aria-label="<?php echo esc_attr( get_the_title( $id ) ); ?>">

			<div class="fc-book-wrap">
				<div class="fc-scene">
					<div class="fc-stage"></div>
				</div>
				<?php if ( 'pdf' === $source && $thumb_url ) : ?>
					<div class="fc-poster" aria-hidden="true">
						<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" decoding="async" fetchpriority="high">
					</div>
				<?php endif; ?>
				<div class="fc-loader" aria-hidden="true">
					<span></span>
					<em class="fc-progress"></em>
				</div>
			</div>

			<div class="fc-controls" role="group" aria-label="کنترل‌های کاتالوگ">
				<button type="button" class="fc-btn fc-nav fc-prev" aria-label="صفحه‌ی قبل">
					<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M8.6 5.9 10 4.5l7.5 7.5-7.5 7.5-1.4-1.4L14.7 12z"/></svg>
				</button>
				<span class="fc-counter" aria-live="polite"><?php echo $count ? '۱ / ' . esc_html( $count ) : '…'; ?></span>
				<button type="button" class="fc-btn fc-nav fc-next" aria-label="صفحه‌ی بعد">
					<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M15.4 5.9 14 4.5 6.5 12l7.5 7.5 1.4-1.4L9.3 12z"/></svg>
				</button>
				<span class="fc-spacer"></span>
				<button type="button" class="fc-btn fc-sound" aria-pressed="<?php echo 'on' === $sound ? 'true' : 'false'; ?>" aria-label="پخش صدای ورق">
					<svg class="fc-ic-on" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3 9v6h4l5 5V4L7 9H3zm11-.83A4.5 4.5 0 0 1 16.5 12 4.5 4.5 0 0 1 14 15.83v-2.06a2.5 2.5 0 0 0 0-3.54V8.17zm0-4.94a9 9 0 0 1 0 17.54v-2.06a7 7 0 0 0 0-13.42V3.23z"/></svg>
					<svg class="fc-ic-off" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3 9v6h4l5 5V4L7 9H3zm18.29-1.29L19.88 6.3 17.6 8.58l-2.29-2.3-1.41 1.42 2.29 2.3-2.29 2.29 1.41 1.41 2.29-2.29 2.28 2.29 1.41-1.41-2.28-2.29 2.28-2.29z"/></svg>
				</button>
				<button type="button" class="fc-btn fc-full" aria-label="تمام‌صفحه">
					<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M4 9V4h5v2H6v3H4zm14 0V6h-3V4h5v5h-2zM4 15h2v3h3v2H4v-5zm14 0h2v5h-5v-2h3v-3z"/></svg>
				</button>
			</div>

			<script type="application/json" class="fc-json"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode خروجی امن است. ?></script>
		</div>
		<?php
		return ob_get_clean();
	}
}
