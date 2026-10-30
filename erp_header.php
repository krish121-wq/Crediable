<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Growbiller Header</title>
    <style>
        .top-menu {
            background: #000080;
            padding: 5px;
            display: flex;
            justify-content: space-around;
            color: white;
            font-family: Arial, sans-serif;
            font-weight: bold;
            position: relative;
        }
        .top-menu a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            cursor: pointer;
        }
        .top-menu a:hover {
            background: #0000a0;
        }
        .dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #e0e0e0;
            border: 1px solid #ccc;
            min-width: 100px;
            z-index: 1;
        }
        .dropdown button {
            width: 100%;
            padding: 5px;
            margin: 2px 0;
            background: #d3d3d3;
            border: 1px solid #ccc;
            cursor: pointer;
            text-align: left;
            border: none;
        }
        .dropdown button:hover {
            background: #c0c0c0;
        }
        .category:hover .dropdown {
            display: block;
        }
        .nested-dropdown {
            display: none;
            position: absolute;
            top: 0;
            left: 100%;
            background: #e0e0e0;
            border: 1px solid #ccc;
            min-width: 100px;
            z-index: 2;
        }
    </style>
    <script>
        function initDropdowns() {
            const transactions = document.querySelector('.category');
            const dropdown = transactions.querySelector('.dropdown');
            const purchaseBtn = dropdown.querySelector('.purchase-btn');
            const nestedDropdown = purchaseBtn.nextElementSibling;

            transactions.addEventListener('mouseover', function() {
                dropdown.style.display = 'block';
            });
            transactions.addEventListener('mouseout', function(e) {
                if (!dropdown.contains(e.relatedTarget)) {
                    dropdown.style.display = 'none';
                }
            });

            purchaseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                nestedDropdown.style.display = (nestedDropdown.style.display === 'block') ? 'none' : 'block';
            });

            const billBtn = nestedDropdown.querySelector('.bill-btn');
            billBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'purchase_bill.php';
            });
        }
        window.onload = initDropdowns;
    </script>
</head>
<body>
    <div class="top-menu">
        <a href="#">Masters</a>
        <div class="category">
            <a href="#" onclick="return false;">Transactions</a>
            <div class="dropdown">
                <button>Sale</button>
                <button class="purchase-btn">Purchase</button>
                <div class="nested-dropdown">
                    <button class="bill-btn">Bill</button>
                    <button>Challan</button>
                    <button>Stock</button>
                </div>
                <button>Return</button>
                <button>Receive</button>
                <button>Issue</button>
                <button>Stock</button>
                <button>Order Management</button>
                <button>Import</button>
                <button>Upload</button>
                <button>Server</button>
            </div>
        </div>
        <a href="#">Accounts</a>
        <a href="#">Digital</a>
        <a href="#">Books</a>
        <a href="#">Final Reports</a>
        <a href="#">GST</a>
        <a href="#">e-Way</a>
        <a href="#">Stocks</a>
        <a href="#">Daily Reports</a>
        <a href="#">Reports</a>
        <a href="#">Hot Keys</a>
        <a href="#">Links</a>
        <a href="#">Exit</a>
    </div>
</body>
</html>