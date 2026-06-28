<?php
/**
 * Plugin Name: wp product excel edite
 * Plugin URI:  https://websemicolon.com/
 * Description: Export WooCommerce products to Excel with editable fields.
 * Version: 0.1.0
 * Author: Saeed Amini
 * Author URI: https://websemicolon.com/
 * Text Domain: wp-product-excel-edite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * اگر از namespace استفاده می‌کنیم، باید فایل‌ها دقیقاً با همان namespace لود شوند.
 * همچنین مسیر فایل Init باید درست باشد.
 */

if (!file_exists(__DIR__ . '/includes/Init.php')) {
    // جلوگیری از Fatal Error هنگام فعال‌سازی
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>فایل Init.php یافت نشد. لطفاً ساختار پوشه‌ها را بررسی کنید.</p></div>';
    });
    return;
}

require_once __DIR__ . '/includes/Init.php';

// اجرای پلاگین بعد از لود شدن همه چیز
add_action('plugins_loaded', function () {
    // اگر کلاس Init با namespace درست وجود داشت، اجرا کن
    if (class_exists('WPPE\\Init')) {
        WPPE\Init::run();
    } else {
        // اگر کلاس پیدا نشد، به‌جای Fatal Error، نوتیس بده
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>کلاس WPPE\Init یافت نشد. احتمالاً namespace یا مسیر فایل اشتباه است.</p></div>';
        });
    }
});
require_once __DIR__ . '/vendor/autoload.php';
