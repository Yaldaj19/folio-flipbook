<?php
/**
 * ثبت نوع محتوای «کاتالوگ ورق‌زن».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FC_CPT_Registrar {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => 'کاتالوگ‌ها',
			'singular_name'      => 'کاتالوگ',
			'menu_name'          => 'Folio کاتالوگ',
			'add_new'            => 'کاتالوگ جدید',
			'add_new_item'       => 'افزودن کاتالوگ جدید',
			'edit_item'          => 'ویرایش کاتالوگ',
			'new_item'           => 'کاتالوگ جدید',
			'view_item'          => 'مشاهده کاتالوگ',
			'search_items'       => 'جستجوی کاتالوگ',
			'not_found'          => 'کاتالوگی یافت نشد',
			'not_found_in_trash' => 'کاتالوگی در زباله‌دان نیست',
			'all_items'          => 'همه‌ی کاتالوگ‌ها',
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,          // بدون صفحه‌ی مستقل در فرانت.
			'publicly_queryable'  => false,          // آدرس تکی ندارد.
			'exclude_from_search' => true,           // در جستجوی سایت نمی‌آید.
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-book-alt',
			'menu_position'       => 26,
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
		);

		register_post_type( FC_CPT, $args );
	}
}
