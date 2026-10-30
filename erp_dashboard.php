<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Growbiller Dashboard</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            width: 200px;
            background: #e0e0e0;
            height: 100vh;
            position: fixed;
            padding: 10px;
            border-right: 1px solid #ccc;
        }
        .sidebar a {
            display: block;
            padding: 5px;
            text-decoration: none;
            color: #000;
            font-size: 14px;
        }
        .sidebar a:hover {
            background: #d0d0d0;
        }
        .content {
            margin-left: 220px;
            padding: 20px;
        }
        .header {
            background: #e0e0e0;
            padding: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-buttons a {
            margin-left: 10px;
            text-decoration: none;
            color: #000;
            font-weight: bold;
        }
        .main-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
        }
        .right-sidebar {
            position: fixed;
            right: 250px;
            width: 200px;
            background: #f0f0f0;
            padding: 10px;
            border-left: 1px solid #ccc;
        }
        .right-sidebar button {
            width: 100%;
            padding: 5px;
            margin-bottom: 5px;
            background: #d3d3d3;
            border: 1px solid #ccc;
            cursor: pointer;
        }
        .right-sidebar button:hover {
            background: #c0c0c0;
        }
        .ad-banner {
            position: fixed;
            right: 10px;
            width: 200px;
            background: #87ceeb;
            padding: 10px;
            color: #000;
            text-align: center;
        }
        .bottom-bar {
            position: fixed;
            bottom: 0;
            width: 100%;
            background: #e0e0e0;
            padding: 10px;
            border-top: 1px solid #ccc;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <?php include 'erp_header.php'; ?>
    <div class="sidebar">
        <a href="#">Digital Entry</a>
        <a href="#">Retailio Services</a>
        <a href="#">ERP to ERP Order</a>
        <input type="text" placeholder="Search" style="width: 100%; margin-top: 20px;">
        <a href="http://growbillermart.com">growbillermart.com</a>
    </div>
    <div class="content">
        <div class="header">
            <div class="header-buttons">
                <a href="#">Dashboard</a>
                <a href="#">Search</a>
            </div>
            <div>Update Dashboard >> Not updated</div>
        </div>
        <div class="main-content">
            <!-- Main content would go here -->
        </div>
    </div>
    <div class="right-sidebar">
        <button>Sale</button>
        <button>Purch. Challan</button>
        <button>S/R Expiry</button>
        <button>P/R Expiry</button>
        <button>Receipt</button>
        <button>Payment</button>
        <button>Cash & Bank Book</button>
        <button>Ledger A/c</button>
        <button>Outstanding</button>
        <button>Stock Status</button>
        <button>Stock & Sales Analysis</button>
        <button>Re-Order</button>
        <button>Sales Book</button>
        <button>Dispatch Summary</button>
        <button>Bill Tagging</button>
        <button>Daily Analysis (MIS)</button>
        <button>Todays Gross Profit</button>
        <button>Exit</button>
    </div>
    <div class="ad-banner">
        <p>Go Cashless with Growbiller Wallet</p>
        <img src="laptop_ad.png" alt="Woman with Laptop" style="width: 100%;">
        <p>Simplify Payments, Enhance Efficiency!</p>
        <img src="pdf_icon.png" alt="PDF Icon">
        <img src="whatsapp_icon.png" alt="WhatsApp Icon">
    </div>
    <div class="bottom-bar">
        <div>
            <p>Growbiller ERP 9+ Gold Edition</p>
            <p>Trial Version – Vouchers entered for 2 days, limit up to 7 days</p>
            <p>Support: 8910666666, 7899329299</p>
            <p>Bagmari, Kolkata 7000067 (India)</p>
        </div>
        <div>
            <p>Date: 7 Jul, 2025</p>
            <p>Day: Monday</p>
            <p>Time: 12:15 PM</p>
        </div>
    </div>
</body>
</html>