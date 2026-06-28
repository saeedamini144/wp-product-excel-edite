<?php

namespace WPPE\Helpers;

use WPPE\Models\Product;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!defined('ABSPATH')) {
    exit;
}

class ExcelGenerator
{
    public static function output_csv(array $products)
    {
        // تبدیل خروجی از CSV به XLSX
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        /*
        |--------------------------------------------------------------------------
        | هدرها – دقیقاً مثل نسخه CSV قبلی
        |--------------------------------------------------------------------------
        */
        $sheet->fromArray([
            [
                'آیدی',
                'نام محصول',
                'شناسه / SKU',
                'قیمت عادی',
                'قیمت فروش ویژه',
                'تاریخ پایان فروش ویژه',
                'موجودی انبار',
                'وضعیت موجودی'
            ]
        ]);

        $rowIndex = 2;

        foreach ($products as $p) {

            $wc_product = wc_get_product($p->get_id());

            /*
            |--------------------------------------------------------------------------
            | محصولات ساده – بدون تغییر
            |--------------------------------------------------------------------------
            */
            if ($wc_product->is_type('simple')) {

                $sale_end_date = $p->get_sale_end_date() ?: '';

                $sheet->fromArray([
                    [
                        $p->get_id(),
                        $p->get_name(),
                        $p->get_sku(),
                        $p->get_regular_price(),
                        $p->get_sale_price(),
                        $sale_end_date,
                        $p->get_stock_quantity(),
                        $p->get_stock_status(),
                    ]
                ], null, "A{$rowIndex}");

                $rowIndex++;
            }

            /*
            |--------------------------------------------------------------------------
            | محصولات متغیر – بدون تغییر در ساختار
            |--------------------------------------------------------------------------
            */
            if ($wc_product->is_type('variable')) {

                $variations = $wc_product->get_children();

                foreach ($variations as $variation_id) {

                    $variation = wc_get_product($variation_id);
                    if (!$variation) continue;

                    /*
                    |--------------------------------------------------------------------------
                    | استخراج ویژگی‌ها – فقط مقدار ویژگی‌ها
                    |--------------------------------------------------------------------------
                    */
                    $attr_parts = [];
                    $raw_attributes = $variation->get_attributes();

                    foreach ($raw_attributes as $taxonomy => $slug_value) {

                        $terms = wc_get_product_terms(
                            $variation_id,
                            $taxonomy,
                            ['fields' => 'names']
                        );

                        if (!empty($terms)) {
                            $value_label = $terms[0];
                        } else {
                            $value_label = urldecode($slug_value);
                        }

                        $attr_parts[] = $value_label;
                    }

                    $variation_name = $p->get_name() . ' - ' . implode(', ', $attr_parts);

                    $date_to = $variation->get_date_on_sale_to();
                    $sale_end_date = $date_to ? $date_to->date('Y-m-d') : '';

                    $sheet->fromArray([
                        [
                            $variation_id,
                            $variation_name,
                            $variation->get_sku(),
                            $variation->get_regular_price(),
                            $variation->get_sale_price(),
                            $sale_end_date,
                            $variation->get_stock_quantity(),
                            $variation->get_stock_status(),
                        ]
                    ], null, "A{$rowIndex}");

                    $rowIndex++;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | خروجی XLSX واقعی
        |--------------------------------------------------------------------------
        */
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="products.xlsx"');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
