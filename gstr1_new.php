<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

// [Previous code remains the same until line 102]

        // Calculate tax (assuming GST is SGST + CGST, or IGST for inter-state)
        $tax_rate = $sale['gst_percentage'] / 100; // Convert percentage to decimal
        $total_amount = $sale['price_per_piece'] * $sale['quantity'];
        // Calculate taxable value (pre-tax amount)
        $taxable_value = $total_amount / (1 + $tax_rate);
        // Calculate tax amount based on taxable value
        $tax_amount = $taxable_value * $tax_rate;
        
        $grouped_sales[$key]['ids'][] = $sale['id'];
        $grouped_sales[$key]['products'][] = [
            'product_id' => $sale['product_id'],
            'product_name' => $sale['product_name'],
            'quantity' => $sale['quantity'],
            'price_per_piece' => $sale['price_per_piece'],
            'tax_rate' => $sale['gst_percentage'],
           
            'taxable_value' => $taxable_value,
            'tax_amount' => $tax_amount,
            'total_price' => $sale['total_price']
        ];

// [Rest of the file remains the same]
