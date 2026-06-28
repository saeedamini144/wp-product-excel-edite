<?php

namespace WPPE\Helpers;

use WPPE\Models\Product;

if (!defined('ABSPATH')) {
    exit;
}

class ExcelGenerator
{
    public static function output_csv(array $products)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=products.csv');

        $output = fopen('php://output', 'w');

        // BOM برای جلوگیری از خراب شدن فارسی
        fwrite($output, "\xEF\xBB\xBF");

        // هدرهای فایل CSV
        fputcsv($output, [
            'آیدی',
            'نام محصول',
            'شناسه / SKU',
            'قیمت عادی',
            'قیمت فروش ویژه',
            'تاریخ پایان فروش ویژه',
            'موجودی انبار',
            'وضعیت موجودی'
        ]);

        foreach ($products as $p) {

            $wc_product = wc_get_product($p->get_id());

            /*
            |--------------------------------------------------------------------------
            | محصولات ساده
            |--------------------------------------------------------------------------
            */
            if ($wc_product->is_type('simple')) {

                $sale_end_date = $p->get_sale_end_date() ?: '';

                fputcsv($output, [
                    $p->get_id(),
                    $p->get_name(),
                    $p->get_sku(),
                    $p->get_regular_price(),
                    $p->get_sale_price(),
                    $sale_end_date,
                    $p->get_stock_quantity(),
                    $p->get_stock_status(),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | محصولات متغیر
            |--------------------------------------------------------------------------
            */
            if ($wc_product->is_type('variable')) {

                $variations = $wc_product->get_children();

                foreach ($variations as $variation_id) {

                    $variation = wc_get_product($variation_id);
                    if (!$variation) continue;

                    /*
                    |--------------------------------------------------------------------------
                    | استخراج ویژگی‌ها (فقط مقدار ویژگی، بدون لیبل)
                    |--------------------------------------------------------------------------
                    */
                    $attr_parts = [];

                    $raw_attributes = $variation->get_attributes();

                    foreach ($raw_attributes as $taxonomy => $slug_value) {

                        // مقدار ویژگی (label واقعی term)
                        $terms = wc_get_product_terms(
                            $variation_id,
                            $taxonomy,
                            ['fields' => 'names']
                        );

                        if (!empty($terms)) {
                            $value_label = $terms[0]; // مثلاً "مشکی" یا "سایز-36"
                        } else {
                            // اگر term پیدا نشد → decode slug
                            $value_label = urldecode($slug_value);
                        }

                        // فقط مقدار ویژگی در خروجی
                        $attr_parts[] = $value_label;
                    }

                    // نام نهایی Variation
                    $variation_name = $p->get_name() . ' - ' . implode(', ', $attr_parts);

                    /*
                    |--------------------------------------------------------------------------
                    | تاریخ تخفیف
                    |--------------------------------------------------------------------------
                    */
                    $date_to = $variation->get_date_on_sale_to();
                    $sale_end_date = $date_to ? $date_to->date('Y-m-d') : '';

                    /*
                    |--------------------------------------------------------------------------
                    | خروجی نهایی Variation
                    |--------------------------------------------------------------------------
                    */
                    fputcsv($output, [
                        $variation_id,
                        $variation_name,
                        $variation->get_sku(),
                        $variation->get_regular_price(),
                        $variation->get_sale_price(),
                        $sale_end_date,
                        $variation->get_stock_quantity(),
                        $variation->get_stock_status(),
                    ]);
                }
            }
        }

        fclose($output);
        exit;
    }
}
