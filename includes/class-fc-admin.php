<?php
/**
 * بخش مدیریت: انتخاب صفحات، تنظیمات و راهنمای استفاده.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FC_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . FC_CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_' . FC_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . FC_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * بارگذاری اسکریپت‌های ادمین فقط در صفحه‌ی ویرایش کاتالوگ.
	 */
	public static function assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || FC_CPT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'fc-admin', FC_URL . 'assets/js/fc-admin.js', array( 'jquery', 'jquery-ui-sortable' ), FC_VERSION, true );
		wp_add_inline_style( 'wp-admin', self::admin_css() );
	}

	private static function admin_css() {
		return '
		.fc-pages-grid{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0;min-height:40px}
		.fc-page-item{position:relative;width:90px;height:120px;border:1px solid #dcdcde;border-radius:6px;overflow:hidden;background:#f6f7f7;cursor:move;box-shadow:0 1px 2px rgba(0,0,0,.06)}
		.fc-page-item img{width:100%;height:100%;object-fit:cover;display:block}
		.fc-page-item .fc-remove{position:absolute;top:2px;left:2px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:22px;height:22px;line-height:20px;cursor:pointer;font-size:14px}
		.fc-page-item .fc-num{position:absolute;bottom:0;right:0;background:rgba(0,0,0,.6);color:#fff;font-size:11px;padding:1px 6px;border-radius:6px 0 0 0}
		.fc-page-placeholder{width:90px;height:120px;border:2px dashed #a7aaad;border-radius:6px}
		.fc-help code{background:#f0f0f1;padding:3px 8px;border-radius:4px;font-size:14px;direction:ltr;display:inline-block}
		.fc-help .fc-copy{margin-right:6px}
		.fc-settings-row{margin:10px 0}
		.fc-settings-row label{font-weight:600;display:block;margin-bottom:4px}
		';
	}

	public static function meta_boxes() {
		add_meta_box( 'fc_pages', 'صفحات کاتالوگ', array( __CLASS__, 'box_pages' ), FC_CPT, 'normal', 'high' );
		add_meta_box( 'fc_settings', 'تنظیمات نمایش', array( __CLASS__, 'box_settings' ), FC_CPT, 'side', 'default' );
		add_meta_box( 'fc_help', 'راهنمای استفاده', array( __CLASS__, 'box_help' ), FC_CPT, 'normal', 'default' );
	}

	/**
	 * متاباکس منبع محتوا (تصویر یا PDF).
	 */
	public static function box_pages( $post ) {
		wp_nonce_field( 'fc_save', 'fc_nonce' );
		$ids = get_post_meta( $post->ID, '_fc_pages', true );
		$ids = $ids ? array_filter( array_map( 'absint', explode( ',', $ids ) ) ) : array();

		$source  = get_post_meta( $post->ID, '_fc_source', true ) ?: 'images';
		$pdf_id  = (int) get_post_meta( $post->ID, '_fc_pdf', true );
		$pdf_url = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
		?>
		<div class="fc-settings-row">
			<label>منبع محتوای کاتالوگ</label>
			<label style="font-weight:400;display:inline-block;margin-left:16px">
				<input type="radio" name="fc_source" value="images" <?php checked( $source, 'images' ); ?>> تصاویر صفحه‌ها
			</label>
			<label style="font-weight:400;display:inline-block">
				<input type="radio" name="fc_source" value="pdf" <?php checked( $source, 'pdf' ); ?>> فایل PDF
			</label>
		</div>

		<div id="fc-src-images" class="fc-src-panel">
			<p>تصاویر صفحه‌ها را انتخاب کن؛ با کشیدن ترتیب را عوض کن. اولین تصویر = جلد.</p>
			<button type="button" class="button button-primary" id="fc-add-pages">➕ افزودن / انتخاب صفحات</button>
			<input type="hidden" id="fc-pages-input" name="fc_pages" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
			<div class="fc-pages-grid" id="fc-pages-grid">
				<?php foreach ( $ids as $i => $id ) : ?>
					<?php $thumb = wp_get_attachment_image_url( $id, 'thumbnail' ); ?>
					<?php if ( $thumb ) : ?>
						<div class="fc-page-item" data-id="<?php echo esc_attr( $id ); ?>">
							<img src="<?php echo esc_url( $thumb ); ?>" alt="">
							<button type="button" class="fc-remove" title="حذف">&times;</button>
							<span class="fc-num"><?php echo esc_html( $i + 1 ); ?></span>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<?php
		$thumb_id  = (int) get_post_meta( $post->ID, '_fc_pdf_thumb', true );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
		?>
		<div id="fc-src-pdf" class="fc-src-panel">
			<p>یک فایل PDF انتخاب کن؛ صفحه‌هایش داخل مرورگر به‌صورت کتابچه نمایش داده می‌شود (بدون نیاز به سرور).</p>
			<button type="button" class="button button-primary" id="fc-pick-pdf">📄 انتخاب فایل PDF</button>
			<button type="button" class="button" id="fc-remove-pdf" style="<?php echo $pdf_url ? '' : 'display:none'; ?>">حذف PDF</button>
			<input type="hidden" id="fc-pdf-input" name="fc_pdf" value="<?php echo esc_attr( $pdf_id ); ?>">
			<p id="fc-pdf-name" style="margin-top:8px;font-weight:600;color:#135e96">
				<?php echo $pdf_url ? esc_html( wp_basename( $pdf_url ) ) : ''; ?>
			</p>

			<hr style="margin:16px 0">
			<p><strong>تصویر پیش‌نمایش (جلد PDF)</strong> — تا آماده شدن صفحه‌های PDF، این تصویر نمایش داده می‌شود. بهتر است تصویر جلد کاتالوگ باشد.</p>
			<button type="button" class="button" id="fc-pick-thumb">🖼️ انتخاب تصویر پیش‌نمایش</button>
			<button type="button" class="button" id="fc-remove-thumb" style="<?php echo $thumb_url ? '' : 'display:none'; ?>">حذف تصویر</button>
			<input type="hidden" id="fc-thumb-input" name="fc_pdf_thumb" value="<?php echo esc_attr( $thumb_id ); ?>">
			<div id="fc-thumb-preview" style="margin-top:10px;<?php echo $thumb_url ? '' : 'display:none'; ?>">
				<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="max-width:140px;height:auto;border:1px solid #dcdcde;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.1)">
			</div>
		</div>
		<?php
	}

	/**
	 * متاباکس تنظیمات.
	 */
	public static function box_settings( $post ) {
		$direction = get_post_meta( $post->ID, '_fc_direction', true ) ?: 'rtl';
		$intro     = get_post_meta( $post->ID, '_fc_intro', true ) ?: 'settle';
		$mode      = get_post_meta( $post->ID, '_fc_mode', true ) ?: 'book';
		$bg        = get_post_meta( $post->ID, '_fc_background', true ) ?: 'none';
		$sound     = get_post_meta( $post->ID, '_fc_sound', true ) ?: 'off';
		?>
		<div class="fc-settings-row">
			<label for="fc_direction">جهت ورق‌زدن</label>
			<select name="fc_direction" id="fc_direction" style="width:100%">
				<option value="rtl" <?php selected( $direction, 'rtl' ); ?>>راست به چپ (فارسی)</option>
				<option value="ltr" <?php selected( $direction, 'ltr' ); ?>>چپ به راست (لاتین)</option>
			</select>
		</div>
		<div class="fc-settings-row">
			<label for="fc_mode">حالت نمایش</label>
			<select name="fc_mode" id="fc_mode" style="width:100%">
				<option value="book" <?php selected( $mode, 'book' ); ?>>کتاب (پیش‌فرض) — جلد تکی، بعد باز شدن دوصفحه‌ای</option>
				<option value="single" <?php selected( $mode, 'single' ); ?>>همیشه تک‌صفحه</option>
			</select>
			<p class="description">حالت کتاب: اول جلد تنها نمایش داده می‌شود، با ورق‌زدن مثل کتاب واقعی باز می‌شود. در موبایل خودکار تک‌صفحه می‌شود.</p>
		</div>
		<div class="fc-settings-row">
			<label for="fc_intro">انیمیشن ورود</label>
			<select name="fc_intro" id="fc_intro" style="width:100%">
				<option value="settle" <?php selected( $intro, 'settle' ); ?>>قرار گرفتن روی میز (پیش‌فرض)</option>
				<option value="sweep" <?php selected( $intro, 'sweep' ); ?>>درخشش نور روی جلد</option>
				<option value="unfold" <?php selected( $intro, 'unfold' ); ?>>باز شدن مثل کتاب</option>
				<option value="rise" <?php selected( $intro, 'rise' ); ?>>ظاهر شدن نرم از پایین</option>
				<option value="fade" <?php selected( $intro, 'fade' ); ?>>محو نرم</option>
				<option value="none" <?php selected( $intro, 'none' ); ?>>بدون انیمیشن</option>
			</select>
		</div>
		<div class="fc-settings-row">
			<label for="fc_background">پس‌زمینه</label>
			<select name="fc_background" id="fc_background" style="width:100%">
				<option value="none" <?php selected( $bg, 'none' ); ?>>بدون پس‌زمینه (پیش‌فرض)</option>
				<option value="light" <?php selected( $bg, 'light' ); ?>>روشن ملایم</option>
				<option value="dark" <?php selected( $bg, 'dark' ); ?>>تیره</option>
				<option value="wood" <?php selected( $bg, 'wood' ); ?>>میز چوبی</option>
			</select>
			<p class="description">پیش‌فرض بدون پس‌زمینه است و با رنگ صفحه‌ی سایت ترکیب می‌شود.</p>
		</div>
		<div class="fc-settings-row">
			<label for="fc_sound">صدای ورق</label>
			<select name="fc_sound" id="fc_sound" style="width:100%">
				<option value="off" <?php selected( $sound, 'off' ); ?>>خاموش (پیش‌فرض)</option>
				<option value="on" <?php selected( $sound, 'on' ); ?>>روشن</option>
			</select>
			<p class="description">کاربر هم می‌تواند از روی کاتالوگ صدا را خاموش/روشن کند.</p>
		</div>
		<?php
	}

	/**
	 * متاباکس راهنما.
	 */
	public static function box_help( $post ) {
		$shortcode = '[flip_catalog id="' . (int) $post->ID . '"]';
		?>
		<div class="fc-help">
			<p><strong>چطور این کاتالوگ را در سایت نشان دهم؟</strong></p>
			<ol style="line-height:2">
				<li>یک <b>برگه</b> یا <b>نوشته‌ی</b> جدید بساز (یا هرکدام که از قبل داری را باز کن).</li>
				<li>شورت‌کد زیر را داخل محتوای آن برگه بگذار:</li>
			</ol>
			<p>
				<code id="fc-shortcode"><?php echo esc_html( $shortcode ); ?></code>
				<button type="button" class="button fc-copy" data-copy="<?php echo esc_attr( $shortcode ); ?>">📋 کپی</button>
			</p>
			<p class="description">
				همین شورت‌کد را می‌توانی در هر برگه، نوشته، یا ویجت متنی قرار دهی تا این کاتالوگ همان‌جا نمایش داده شود.
				اگر از بلوک‌ها استفاده می‌کنی، از بلوک «کد کوتاه» (Shortcode) استفاده کن.
			</p>
			<p class="description">
				<b>نکته:</b> اول باید حداقل یک صفحه در بالای همین صفحه انتخاب کرده و کاتالوگ را ذخیره کنی، سپس شورت‌کد کار می‌کند.
			</p>
		</div>
		<?php
	}

	/**
	 * ذخیره‌ی متاها.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['fc_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fc_nonce'] ), 'fc_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// منبع.
		$source_in = isset( $_POST['fc_source'] ) ? sanitize_key( $_POST['fc_source'] ) : 'images';
		$source    = in_array( $source_in, array( 'images', 'pdf' ), true ) ? $source_in : 'images';
		update_post_meta( $post_id, '_fc_source', $source );

		// صفحه‌ها.
		$pages_raw = isset( $_POST['fc_pages'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_pages'] ) ) : '';
		$ids       = $pages_raw ? array_filter( array_map( 'absint', explode( ',', $pages_raw ) ) ) : array();
		update_post_meta( $post_id, '_fc_pages', implode( ',', $ids ) );

		// فایل PDF.
		$pdf = isset( $_POST['fc_pdf'] ) ? absint( $_POST['fc_pdf'] ) : 0;
		update_post_meta( $post_id, '_fc_pdf', $pdf );

		// تصویر پیش‌نمایش PDF.
		$thumb = isset( $_POST['fc_pdf_thumb'] ) ? absint( $_POST['fc_pdf_thumb'] ) : 0;
		update_post_meta( $post_id, '_fc_pdf_thumb', $thumb );

		// جهت.
		$direction = ( isset( $_POST['fc_direction'] ) && 'ltr' === $_POST['fc_direction'] ) ? 'ltr' : 'rtl';
		update_post_meta( $post_id, '_fc_direction', $direction );

		// حالت.
		$mode_in = isset( $_POST['fc_mode'] ) ? sanitize_key( $_POST['fc_mode'] ) : 'book';
		$mode    = in_array( $mode_in, array( 'book', 'single' ), true ) ? $mode_in : 'book';
		update_post_meta( $post_id, '_fc_mode', $mode );

		// انیمیشن ورود.
		$intro_in = isset( $_POST['fc_intro'] ) ? sanitize_key( $_POST['fc_intro'] ) : 'settle';
		$intro    = in_array( $intro_in, array( 'settle', 'sweep', 'unfold', 'rise', 'fade', 'none' ), true ) ? $intro_in : 'settle';
		update_post_meta( $post_id, '_fc_intro', $intro );

		// پس‌زمینه.
		$bg_in = isset( $_POST['fc_background'] ) ? sanitize_key( $_POST['fc_background'] ) : 'none';
		$bg    = in_array( $bg_in, array( 'none', 'light', 'dark', 'wood' ), true ) ? $bg_in : 'none';
		update_post_meta( $post_id, '_fc_background', $bg );

		// صدا.
		$snd = ( isset( $_POST['fc_sound'] ) && 'on' === $_POST['fc_sound'] ) ? 'on' : 'off';
		update_post_meta( $post_id, '_fc_sound', $snd );
	}

	/**
	 * ستون شورت‌کد در لیست کاتالوگ‌ها.
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['fc_shortcode'] = 'شورت‌کد';
				$new['fc_count']     = 'تعداد صفحات';
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'fc_shortcode' === $column ) {
			echo '<code style="direction:ltr;display:inline-block">[flip_catalog id="' . (int) $post_id . '"]</code>';
		}
		if ( 'fc_count' === $column ) {
			$ids = get_post_meta( $post_id, '_fc_pages', true );
			$n   = $ids ? count( array_filter( explode( ',', $ids ) ) ) : 0;
			echo esc_html( $n ) . ' صفحه';
		}
	}
}
