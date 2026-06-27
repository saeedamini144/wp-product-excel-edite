<?php

namespace WPPE\Helpers;

use WPPE\Models\Product;

if (!defined('ABSPATH')) {
    exit;
}

class ProductQuery
{
    /**
     * دریافت محصولات
     * اگر $category_id = 0 → همه دسته‌بندی‌ها
     * اگر > 0 → فقط همان دسته
     */
    public static function get_products(int $category_id): array
    {
        $args = [
            'limit'  => -1,
            'status' => 'publish',
        ];

        // اگر دسته خاص انتخاب شده
        if ($category_id > 0) {
            $term = get_term($category_id, 'product_cat');

            if ($term && !is_wp_error($term)) {
                // wc_get_products برای category از slug استفاده می‌کند
                $args['category'] = [$term->slug];
            }
        }

        $wc_products = wc_get_products($args);
        $products = [];

        foreach ($wc_products as $p) {
            $date_to      = $p->get_date_on_sale_to();
            $sale_end     = $date_to ? $date_to->date('Y-m-d') : '';
            $stock_qty    = $p->get_stock_quantity();
            $stock_status = $p->get_stock_status();

            $products[] = new Product(
                $p->get_id(),
                $p->get_name(),
                $p->get_sku(),
                $p->get_regular_price(),
                $p->get_sale_price(),
                $sale_end,
                $stock_qty,
                $stock_status
            );
        }

        return $products;
    }
}
