<?php

namespace WPPE;

if (!defined('ABSPATH')) {
    exit;
}

class Init
{
    public static function run()
    {
        self::load_dependencies();
        Admin\SettingsPage::init();
        Admin\ExportHandler::init();
        Admin\ImportHandler::init();
    }

    private static function load_dependencies()
    {
        require_once __DIR__ . '/Admin/SettingsPage.php';
        require_once __DIR__ . '/Admin/ExportHandler.php';
        require_once __DIR__ . '/Admin/ImportHandler.php';

        require_once __DIR__ . '/Helpers/ProductQuery.php';
        require_once __DIR__ . '/Helpers/ExcelGenerator.php';

        require_once __DIR__ . '/Models/Product.php';
        require_once __DIR__ . '/vendor/autoload.php';

    }
}
