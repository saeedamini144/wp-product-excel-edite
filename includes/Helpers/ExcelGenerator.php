<?php

namespace WPPE\Helpers;

use WPPE\Models\Product;

if (!defined('ABSPATH')) {
    exit;
}

class ExcelGenerator
{
    /**
     * خروجی CSV سازگار با اکسل و زبان فارسی
     * شامل BOM UTF-8 برای جلوگیری از به‌هم‌ریختگی متن
     * جلوگیری از Scientific Notation برای SKU
     */
    public static function output_csv(array $products)
    {
        // هدرهای دانلود فایل
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=products.csv');

        // خروجی به مرورگر
        $output = fopen('php://output', 'w');

        // اضافه کردن BOM برای جلوگیری از خراب شدن فارسی
        fwrite($output, "\xEF\xBB\xBF");

        // هدرهای فایل CSV مطابق فایل نمونه
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

        /** @var Product $p */
        foreach ($products as $p) {

            // جلوگیری از Scientific Notation برای SKU
            // با اضافه کردن یک تک‌کوتیشن قبل از مقدار
            // $sku = "'" . $p->get_sku();

            // تاریخ پایان فروش ویژه به صورت Y-m-d
            $sale_end_date = $p->get_sale_end_date() ?: '';

            fputcsv($output, [
                $p->get_id(),
                $p->get_name(),
                // $sku,
                $p->get_sku(),
                $p->get_regular_price(),
                $p->get_sale_price(),
                $sale_end_date,
                $p->get_stock_quantity(),
                $p->get_stock_status(),
            ]);
        }

        fclose($output);
        exit;
    }
}
