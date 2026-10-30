<?php
/**
 * Fetches company details from the database
 * 
 * @param mysqli $conn Database connection object
 * @return array|false Returns company details as an associative array or false if not found
 */
function getCompanyDetails($conn) {
    $query = "SELECT 
                id,
                company_name,
                company_address,
                company_gst,
                registration_number,
                email,
                phone,
                state
              FROM company_details 
              LIMIT 1";
              
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log("Error fetching company details: " . mysqli_error($conn));
        return false;
    }
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

/**
 * Generates unified next sequential invoice number shared across Sales & Service invoices
 * 
 * @param mysqli $conn
 * @param string $prefix ('INV' for Sales, 'SINV' for Service)
 * @return string (e.g. 'INV-2026-0003' or 'SINV-2026-0003')
 */
function getUnifiedNextInvoiceNumber($conn, $prefix = 'INV') {
    $currentYear = date('Y');
    $max_num = 0;

    // 1. Check Service Invoices for current year
    try {
        $res = $conn->query("SELECT invoice_no FROM service_invoices WHERE invoice_no LIKE '%-$currentYear-%'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $parts = explode('-', $row['invoice_no']);
                $num = (int)end($parts);
                if ($num > $max_num) $max_num = $num;
            }
        }
    } catch (Exception $e) {}

    // 2. Check Sales Invoices for current year (invoice_no column)
    try {
        $res = $conn->query("SELECT invoice_no FROM invoices WHERE invoice_no LIKE '%-$currentYear-%'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $parts = explode('-', $row['invoice_no']);
                $num = (int)end($parts);
                if ($num > $max_num) $max_num = $num;
            }
        }
    } catch (Exception $e) {}

    // 3. Check Max IDs from both tables as safety baseline
    try {
        $resMax1 = $conn->query("SELECT MAX(id) as max_id FROM invoices");
        if ($resMax1 && $r = $resMax1->fetch_assoc()) {
            $num = (int)$r['max_id'];
            if ($num > $max_num) $max_num = $num;
        }
    } catch (Exception $e) {}

    try {
        $resMax2 = $conn->query("SELECT MAX(id) as max_id FROM service_invoices");
        if ($resMax2 && $r = $resMax2->fetch_assoc()) {
            $num = (int)$r['max_id'];
            if ($num > $max_num) $max_num = $num;
        }
    } catch (Exception $e) {}

    $next_num = $max_num + 1;
    return $prefix . "-" . $currentYear . "-" . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}
