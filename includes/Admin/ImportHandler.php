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

        foreach ($rows as $index => $row) {

            // رد کردن هدر
            if ($index === 0) {
                continue;
            }

            // ایندکس ستون‌ها مطابق ساختار قبلی
            $product_id      = isset($row[0]) ? intval($row[0]) : 0;
            $product_name    = isset($row[1]) ? $row[1] : '';
            $sku             = isset($row[2]) ? trim($row[2]) : '';
            $regular_price   = isset($row[3]) ? $row[3] : '';
            $sale_price      = isset($row[4]) ? $row[4] : '';
            $sale_end_date   = isset($row[5]) ? $row[5] : '';
            $stock_quantity  = isset($row[6]) ? $row[6] : '';
            $stock_status    = isset($row[7]) ? $row[7] : '';

            /*
            |--------------------------------------------------------------------------
            | پیدا کردن محصول بر اساس SKU یا آیدی
            |--------------------------------------------------------------------------
            */
            $found_id = null;

            if (!empty($sku)) {
                $found_id = wc_get_product_id_by_sku($sku);
            }

            if (!$found_id && $product_id > 0) {
                $found_id = $product_id;
            }

            if (!$found_id) {
                // اگر محصول پیدا نشد، رد کن
                continue;
            }

            $product = wc_get_product($found_id);
            if (!$product) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | قیمت عادی
            |--------------------------------------------------------------------------
            */
            if ($regular_price !== '' && $regular_price !== null) {
                $normalized_regular = self::normalize_price($regular_price);
                if ($normalized_regular !== null) {
                    $product->set_regular_price($normalized_regular);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | قیمت فروش ویژه — منطق کامل و مقاوم
            |--------------------------------------------------------------------------
            */
            if ($sale_price !== '' && $sale_price !== null) {

                $formatted_sale = self::normalize_price($sale_price);

                // اگر مقدار قیمت معتبر نیست، نادیده بگیر
                if ($formatted_sale === null) {
                    // ادامه به بقیه فیلدها
                } else {

                    // اگر محصول یک وارییشن است (SKU معمولاً وارییشن را برمی‌گرداند)
                    if ($product->is_type('variation')) {

                        // ست کردن قیمت فروش ویژه و قیمت جاری برای نمایش فوری
                        $product->set_sale_price($formatted_sale);
                        $product->set_price($formatted_sale);

                        // تنظیم تاریخ شروع = امروز و تاریخ پایان در صورت وجود
                        self::apply_sale_dates_to_product($product, $sale_end_date);

                        $product->save();
                        self::clear_product_cache($product->get_id());

                    } elseif ($product->is_type('simple')) {

                        $product->set_sale_price($formatted_sale);
                        $product->set_price($formatted_sale);

                        self::apply_sale_dates_to_product($product, $sale_end_date);

                        $product->save();
                        self::clear_product_cache($product->get_id());

                    } elseif ($product->is_type('variable')) {

                        // اگر ردیف مربوط به محصول والد متغیر است، قیمت را روی همه وارییشن‌ها اعمال کن
                        $children = $product->get_children();
                        if (!empty($children)) {
                            foreach ($children as $child_id) {
                                $child = wc_get_product($child_id);
                                if (!$child) continue;

                                $child->set_sale_price($formatted_sale);
                                $child->set_price($formatted_sale);

                                self::apply_sale_dates_to_product($child, $sale_end_date);

                                $child->save();
                                self::clear_product_cache($child->get_id());
                            }
                            // پاک‌سازی والد هم
                            $product->save();
                            self::clear_product_cache($product->get_id());
                        } else {
                            // اگر هیچ وارییشنی نبود، روی والد ست کن (نادر)
                            $product->set_sale_price($formatted_sale);
                            $product->set_price($formatted_sale);
                            self::apply_sale_dates_to_product($product, $sale_end_date);
                            $product->save();
                            self::clear_product_cache($product->get_id());
                        }
                    }
                }

            } else {
                // اگر قیمت فروش ویژه خالی است → حذف تخفیف
                $product->set_sale_price('');
                // بازگرداندن price به regular برای نمایش
                $regular = $product->get_regular_price();
                if ($regular !== '') {
                    $product->set_price($regular);
                } else {
                    $product->set_price('');
                }
                $product->set_date_on_sale_from(null);
                $product->set_date_on_sale_to(null);
                $product->save();
                self::clear_product_cache($product->get_id());
            }

            /*
            |--------------------------------------------------------------------------
            | مدیریت موجودی و تعداد موجودی
            |--------------------------------------------------------------------------
            */
            if ($stock_quantity !== '' && $stock_quantity !== null) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(intval($stock_quantity));
            }

            /*
            |--------------------------------------------------------------------------
            | وضعیت موجودی
            |--------------------------------------------------------------------------
            */
            if (!empty($stock_status)) {
                $product->set_stock_status($stock_status);
            }

            /*
            |--------------------------------------------------------------------------
            | ذخیرهٔ نهایی (برای مواردی که قیمت فروش ویژه در بالا ذخیره نشده)
            |--------------------------------------------------------------------------
            */
            // اگر هنوز ذخیره نشده باشد (مثلاً فقط موجودی یا وضعیت تغییر کرده)
            $product->save();
            self::clear_product_cache($product->get_id());
        }

        wp_redirect(admin_url('admin.php?page=wppe-product-exporter&import=success'));
        exit;
    }

    /**
     * نرمال‌سازی مقدار قیمت: حذف کاما، فاصله، ارقام فارسی و تبدیل به فرمت قابل قبول ووکامرس
     * برمی‌گرداند رشته عددی یا null در صورت نامعتبر بودن
     *
     * @param mixed $value
     * @return string|null
     */
    private static function normalize_price($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // تبدیل به رشته و حذف فاصله‌ها
        $s = trim((string) $value);

        // حذف کاما و کامای فارسی و فاصله‌های غیرمعمول
        $s = str_replace([',', '٬', ' '], ['', '', ''], $s);

        // تبدیل ارقام فارسی به لاتین اگر وجود داشته باشد
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $latin   = ['0','1','2','3','4','5','6','7','8','9'];
        $s = str_replace($persian, $latin, $s);

        // اگر رشته خالی شد
        if ($s === '') {
            return null;
        }

        // استفاده از wc_format_decimal برای اطمینان از فرمت اعشاری مناسب
        $formatted = wc_format_decimal($s);

        // اگر نتیجه عددی معتبر نیست، null برگردان
        if ($formatted === '' || $formatted === null) {
            return null;
        }

        return $formatted;
    }

    /**
     * تنظیم تاریخ شروع (امروز) و تاریخ پایان تخفیف برای یک محصول (WC_Product یا variation)
     *
     * @param \WC_Product $product
     * @param string $sale_end_date
     * @return void
     */
    private static function apply_sale_dates_to_product($product, $sale_end_date)
    {
        // تاریخ شروع = امروز
        $today = new WC_DateTime('now');

        if (!empty($sale_end_date)) {
            $sale_end_date = str_replace('/', '-', trim($sale_end_date));
            try {
                $endDate = new \DateTime($sale_end_date);
                // پایان روز
                $wc_end = new WC_DateTime($endDate->format('Y-m-d') . ' 23:59:59');

                $product->set_date_on_sale_from($today);
                $product->set_date_on_sale_to($wc_end);
            } catch (\Exception $e) {
                // اگر تاریخ نامعتبر بود، تاریخ‌ها را پاک کن (قیمت فروش بدون تاریخ خواهد بود)
                $product->set_date_on_sale_from(null);
                $product->set_date_on_sale_to(null);
            }
        } else {
            // اگر تاریخ پایان وارد نشده، تاریخ‌ها را پاک کن (قیمت فروش بدون بازه زمانی)
            $product->set_date_on_sale_from(null);
            $product->set_date_on_sale_to(null);
        }
    }

    /**
     * پاک‌سازی ترانزینت و کش محصول برای منعکس شدن فوری تغییرات
     *
     * @param int $product_id
     * @return void
     */
    private static function clear_product_cache($product_id)
    {
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        // پاک کردن کش وردپرس برای محصول
        wp_cache_delete($product_id, 'products');
    }
}
