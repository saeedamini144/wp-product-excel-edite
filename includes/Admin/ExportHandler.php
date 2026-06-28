<?php

namespace WPPE\Admin;

use WPPE\Helpers\ProductQuery;
use WPPE\Helpers\ExcelGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class ExportHandler
{
    public static function init()
    {
        add_action('admin_post_wppe_export_products', [self::class, 'handle']);
    }

    public static function handle()
    {
        if (!isset($_POST['wppe_nonce']) || !wp_verify_nonce($_POST['wppe_nonce'], 'wppe_export_products')) {
            wp_die('درخواست نامعتبر');
        }

        $category_id = isset($_POST['product_category']) ? intval($_POST['product_category']) : 0;

        $products = ProductQuery::get_products($category_id);

        if (empty($products)) {
            wp_die('هیچ محصولی برای دسته‌بندی انتخاب‌شده یافت نشد.');
        }

        ExcelGenerator::output_csv($products);
    }
}
