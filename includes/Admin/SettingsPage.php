<?php

namespace WPPE\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    const MENU_SLUG = 'wppe-product-exporter';

    public static function init()
    {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu()
    {
        add_menu_page(
            'Product Excel Exporter',
            'Product Excel Exporter',
            'manage_woocommerce',
            self::MENU_SLUG,
            [self::class, 'render_page'],
            'dashicons-media-spreadsheet',
            56
        );
    }

    public static function render_page()
    {
        ?>
        <div class="wrap">
            <h1>مدیریت اکسل محصولات</h1>

            <!-- فرم خروجی (Export) -->
            <h2>خروجی CSV محصولات</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wppe_export_products', 'wppe_nonce'); ?>
                <input type="hidden" name="action" value="wppe_export_products">

                <table class="form-table">
                    <tr>
                        <th><label>انتخاب دسته‌بندی</label></th>
                        <td>
                            <select name="product_category">
                                <option value="0">همه دسته‌بندی‌ها</option>
                                <?php
                                $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                                foreach ($cats as $cat) {
                                    echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">
                        دانلود خروجی CSV
                    </button>
                </p>
            </form>

            <hr>

            <!-- فرم ایمپورت (Import) -->
            <h2>ایمپورت CSV و بروزرسانی محصولات</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('wppe_import_products', 'wppe_import_nonce'); ?>
                <input type="hidden" name="action" value="wppe_import_products">

                <table class="form-table">
                    <tr>
                        <th><label>فایل CSV خروجی ویرایش‌شده</label></th>
                        <td>
                            <input type="file" name="wppe_import_file" accept=".csv">
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-secondary">
                        ایمپورت و بروزرسانی محصولات
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
}
