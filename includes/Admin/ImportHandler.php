<?php

namespace WPPE\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class ImportHandler
{
    public static function init()
    {
        add_action('admin_post_wppe_import_products', [self::class, 'handle_import']);
    }

    public static function handle_import()
    {
        if (!isset($_POST['wppe_import_nonce']) || !wp_verify_nonce($_POST['wppe_import_nonce'], 'wppe_import_products')) {
            wp_die('درخواست نامعتبر');
        }

        if (!isset($_FILES['wppe_import_file']) || empty($_FILES['wppe_import_file']['tmp_name'])) {
            wp_die('فایل CSV ارسال نشده است.');
        }

        $file = $_FILES['wppe_import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        // رد کردن هدر
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {

            $product_id      = intval($row[0]);
            $product_name    = $row[1];
            $sku             = $row[2];
            $regular_price   = $row[3];
            $sale_price      = $row[4];
            $sale_end_date   = $row[5];
            $stock_quantity  = $row[6];
            $stock_status    = $row[7];

            /*
            |--------------------------------------------------------------------------
            | پیدا کردن محصول بر اساس SKU
            |--------------------------------------------------------------------------
            */
            $found_id = wc_get_product_id_by_sku($sku);

            // اگر SKU پیدا نشد، از product_id استفاده کن
            if (!$found_id && $product_id > 0) {
                $found_id = $product_id;
            }

            if (!$found_id) {
                continue; // هیچ محصولی پیدا نشد
            }

            $product = wc_get_product($found_id);
            if (!$product) continue;

            /*
            |--------------------------------------------------------------------------
            | بروزرسانی محصول ساده یا متغیر
            |--------------------------------------------------------------------------
            */

            // قیمت عادی
            if ($regular_price !== '') {
                $product->set_regular_price($regular_price);
            }

            // قیمت فروش ویژه
            if ($sale_price !== '') {
                $product->set_sale_price($sale_price);
            }

            // تاریخ پایان فروش ویژه
            if ($sale_end_date !== '') {
                $sale_end_date = str_replace('/', '-', $sale_end_date);
                $timestamp = strtotime($sale_end_date);
                if ($timestamp) {
                    $product->set_date_on_sale_to($timestamp);
                }
            }

            // موجودی
            if ($stock_quantity !== '') {
                $product->set_stock_quantity(intval($stock_quantity));
            }

            // وضعیت موجودی
            if ($stock_status !== '') {
                $product->set_stock_status($stock_status);
            }

            // ذخیره محصول یا Variation
            $product->save();
        }

        fclose($handle);

        wp_redirect(admin_url('admin.php?page=wppe-product-exporter&import=success'));
        exit;
    }
}
