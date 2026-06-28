<?php

namespace WPPE\Admin;

use PhpOffice\PhpSpreadsheet\IOFactory;
use WC_DateTime;

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
            wp_die('فایل XLSX ارسال نشده است.');
        }

        $file = $_FILES['wppe_import_file']['tmp_name'];

        // خواندن فایل XLSX
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $not_found = [];
        $imported_count = 0;

        foreach ($rows as $index => $row) {

            if ($index === 0) continue;

            $product_id      = intval($row[0]);
            $product_name    = $row[1];
            $sku             = trim($row[2]);
            $regular_price   = $row[3];
            $sale_price      = $row[4];
            $sale_end_date   = $row[5];
            $stock_quantity  = $row[6];
            $stock_status    = $row[7];

            /*
            |--------------------------------------------------------------------------
            | پیدا کردن محصول فقط بر اساس SKU
            |--------------------------------------------------------------------------
            */
            if (empty($sku)) {
                $not_found[] = [
                    'row' => $index + 1,
                    'product_name' => $product_name,
                    'sku' => $sku,
                    'reason' => 'SKU empty',
                    'raw' => $row,
                ];
                continue;
            }

            $found_id = wc_get_product_id_by_sku($sku);

            if (!$found_id) {
                $not_found[] = [
                    'row' => $index + 1,
                    'product_name' => $product_name,
                    'sku' => $sku,
                    'reason' => 'SKU not found',
                    'raw' => $row,
                ];
                continue;
            }

            $product = wc_get_product($found_id);
            if (!$product) {
                $not_found[] = [
                    'row' => $index + 1,
                    'product_name' => $product_name,
                    'sku' => $sku,
                    'reason' => 'Product object invalid',
                    'raw' => $row,
                ];
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | قیمت عادی
            |--------------------------------------------------------------------------
            */
            if ($regular_price !== '' && $regular_price !== null) {
                $product->set_regular_price(wc_format_decimal($regular_price));
            }

            /*
            |--------------------------------------------------------------------------
            | قیمت فروش ویژه + تاریخ‌ها
            |--------------------------------------------------------------------------
            */
            if ($sale_price !== '' && $sale_price !== null) {

                $formatted_sale = wc_format_decimal($sale_price);

                $product->set_sale_price($formatted_sale);
                $product->set_price($formatted_sale);

                // تاریخ شروع = امروز
                $today = new WC_DateTime('now');

                if (!empty($sale_end_date)) {
                    $sale_end_date = str_replace('/', '-', trim($sale_end_date));
                    try {
                        $endDate = new \DateTime($sale_end_date);
                        $wc_end = new WC_DateTime($endDate->format('Y-m-d') . ' 23:59:59');

                        $product->set_date_on_sale_from($today);
                        $product->set_date_on_sale_to($wc_end);

                    } catch (\Exception $e) {
                        $product->set_date_on_sale_from(null);
                        $product->set_date_on_sale_to(null);
                    }
                } else {
                    $product->set_date_on_sale_from(null);
                    $product->set_date_on_sale_to(null);
                }

            } else {
                // حذف تخفیف
                $product->set_sale_price('');
                $product->set_price($product->get_regular_price());
                $product->set_date_on_sale_from(null);
                $product->set_date_on_sale_to(null);
            }

            /*
            |--------------------------------------------------------------------------
            | مدیریت موجودی و وضعیت موجودی (منطق جدید)
            |--------------------------------------------------------------------------
            */

            // اگر تعداد موجودی وارد شده باشد
            if ($stock_quantity !== '' && $stock_quantity !== null) {

                $qty = intval($stock_quantity);

                if ($qty > 0) {
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($qty);
                    $product->set_stock_status('instock');

                } elseif ($qty === 0) {
                    $product->set_manage_stock(false);
                    $product->set_stock_quantity(0);
                    $product->set_stock_status('outofstock');
                }
            }

            // اگر وضعیت موجودی در فایل اکسل نوشته شده باشد → اولویت دارد
            if (!empty($stock_status)) {

                if ($stock_status === 'instock') {
                    $product->set_manage_stock(true);

                    if ($product->get_stock_quantity() == 0) {
                        $product->set_stock_quantity(1);
                    }

                    $product->set_stock_status('instock');
                }

                if ($stock_status === 'outofstock') {
                    $product->set_manage_stock(false);
                    $product->set_stock_quantity(0);
                    $product->set_stock_status('outofstock');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | ذخیره نهایی
            |--------------------------------------------------------------------------
            */
            $product->save();
            wc_delete_product_transients($product->get_id());
            wp_cache_delete($product->get_id(), 'products');

            $imported_count++;
        }

        /*
        |--------------------------------------------------------------------------
        | ذخیره لیست محصولات پیدا نشده
        |--------------------------------------------------------------------------
        */
        $existing = get_option('wppe_not_found_products', []);
        $existing[] = [
            'time' => current_time('mysql'),
            'count' => count($not_found),
            'items' => $not_found,
        ];
        update_option('wppe_not_found_products', $existing);

        /*
        |--------------------------------------------------------------------------
        | ریدایرکت با اعلان
        |--------------------------------------------------------------------------
        */
        $redirect = add_query_arg([
            'page' => 'wppe-product-exporter',
            'import' => 'success',
            'imported' => $imported_count,
            'not_found' => count($not_found),
        ], admin_url('admin.php'));

        wp_redirect($redirect);
        exit;
    }
}
