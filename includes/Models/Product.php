<?php

namespace WPPE\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Product
{
    private int $id;
    private string $name;
    private string $sku;
    private string $regular_price;
    private string $sale_price;
    private ?string $sale_end_date;
    private ?int $stock_quantity;
    private string $stock_status;

    public function __construct(
        int $id,
        string $name,
        string $sku,
        string $regular_price,
        string $sale_price,
        ?string $sale_end_date,
        ?int $stock_quantity,
        string $stock_status
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->sku = $sku;
        $this->regular_price = $regular_price;
        $this->sale_price = $sale_price;
        $this->sale_end_date = $sale_end_date;
        $this->stock_quantity = $stock_quantity;
        $this->stock_status = $stock_status;
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_sku(): string
    {
        return $this->sku;
    }

    public function get_regular_price(): string
    {
        return $this->regular_price;
    }

    public function get_sale_price(): string
    {
        return $this->sale_price;
    }

    public function get_sale_end_date(): ?string
    {
        return $this->sale_end_date;
    }

    public function get_stock_quantity(): ?int
    {
        return $this->stock_quantity;
    }

    public function get_stock_status(): string
    {
        return $this->stock_status;
    }
}
