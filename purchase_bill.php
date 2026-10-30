<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Growbiller - Purchase Entry</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #c0c0c0;
        }
        
        .title-bar {
            background: #000080;
            color: white;
            padding: 2px 10px;
            font-size: 12px;
            border-bottom: 2px solid #fff;
        }
        .header-strip {
            background: #FFFFFF;
            color: black;
            padding: 5px 10px;
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: bold;
        }
        .form-section {
            background: #f0e68c;
            padding: 10px;
            margin: 5px;
            border: 1px solid #000;
            display: flex;
            flex-wrap: wrap;
        }
        .form-group {
            margin-right: 20px;
        }
        .form-group label {
            margin-right: 5px;
            font-weight: bold;
        }
        .form-group input {
            padding: 2px;
            width: 120px;
            border: 1px solid #000;
            background: #fff;
        }
        .main-table {
            margin: 5px;
            border: 2px solid #90ee90;
        }
        .main-table table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        .main-table th, .main-table td {
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
            font-size: 12px;
        }
        .main-table th {
            background: #90ee90;
        }
        .main-table input {
            width: 100%;
            border: none;
            background: transparent;
            padding: 0;
        }
        .total-summary {
            background: #f0e68c;
            padding: 10px;
            margin: 5px;
            border: 1px solid #000;
            text-align: right;
        }
        .button-group {
            margin: 5px;
            text-align: center;
        }
        button {
            padding: 5px 15px;
            background: #d3d3d3;
            border: 1px solid #ccc;
            cursor: pointer;
        }
        button:hover {
            background: #c0c0c0;
        }
        .toolbar {
            background: #FFFFFF;
            padding: 5px;
            display: flex;
            justify-content: space-between;
            margin: 5px;
            border: 1px solid #000;
        }
        .toolbar img {
            width: 16px;
            height: 16px;
            margin-right: 5px;
        }
        .footer {
            background: #e0e0e0;
            padding: 5px 10px;
            margin: 5px;
            border: 1px solid #000;
            text-align: left;
            font-weight: bold;
        }
    </style>
    <script>
        $(document).ready(function() {
            let currentIndex = 0;

            // Set today's date and time
            const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }).split('/').join('-');
            const time = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            $('#date').val(today);
            $('#time').text(time);

            // Handle Enter key navigation and product row addition
            $(document).on('keydown', 'input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const $inputs = $('input');
                    const $currentInput = $(this);
                    const $currentRow = $currentInput.closest('tr');
                    const $rowInputs = $currentRow.find('input');
                    const inputIndex = $rowInputs.index($currentInput);

                    if (inputIndex < $rowInputs.length - 1) {
                        currentIndex = $inputs.index($rowInputs.eq(inputIndex + 1));
                        $rowInputs.eq(inputIndex + 1).focus();
                    } else if (inputIndex === $rowInputs.length - 1 && $currentInput.attr('name').startsWith('amount')) {
                        // Auto-calculate amount
                        const price = parseFloat($rowInputs.eq(1).val()) || 0;
                        const qty = parseFloat($rowInputs.eq(2).val()) || 0;
                        const dis1 = parseFloat($rowInputs.eq(3).val()) || 0;
                        const dis2 = parseFloat($rowInputs.eq(4).val()) || 0;
                        const amount = qty * price * (1 - ((dis1 + dis2) / 100));
                        $currentInput.val(amount.toFixed(2));

                        // Add new row and focus on new Product
                        if ($('table tbody tr').length < 100) {
                            addNewRow();
                            const $newInputs = $('input');
                            currentIndex = $newInputs.index($newInputs.filter('[name^="product"]').last());
                            $newInputs.eq(currentIndex).focus();
                        }
                    }
                }

                // Ctrl+S shortcut for Save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    saveData();
                }
            });

            // Auto-calculate amount on input
            $('table tbody').on('input', 'input[name^="price"], input[name^="qty"], input[name^="dis"]', function() {
                const $row = $(this).closest('tr');
                const $rowInputs = $row.find('input');
                const price = parseFloat($rowInputs.eq(1).val()) || 0;
                const qty = parseFloat($rowInputs.eq(2).val()) || 0;
                const dis1 = parseFloat($rowInputs.eq(3).val()) || 0;
                const dis2 = parseFloat($rowInputs.eq(4).val()) || 0;
                const amount = qty * price * (1 - ((dis1 + dis2) / 100));
                $rowInputs.eq(5).val(amount.toFixed(2));
            });

            function addNewRow() {
                const newRow = `
                    <tr>
                        <td><input type="text" name="product[]"></td>
                        <td><input type="number" name="price[]" step="0.01"></td>
                        <td><input type="number" name="qty[]"></td>
                        <td><input type="number" name="dis1[]"></td>
                        <td><input type="number" name="dis2[]"></td>
                        <td><input type="number" name="amount[]" readonly></td>
                    </tr>
                `;
                $('table tbody').append(newRow);
            }

            // Focus on name field initially
            $('input').first().focus();
        });

        function saveData() {
            const formData = {
                name: $('[name="name"]').val(),
                date: $('[name="date"]').val(),
                entry_no: $('[name="entry_no"]').val(),
                party_no: $('[name="party_no"]').val(),
                dt: $('[name="dt"]').val(),
                type: $('[name="type"]').val(),
                products: []
            };

            $('table tbody tr').each(function() {
                const $row = $(this);
                const product = $row.find('[name^="product"]').val();
                const price = $row.find('[name^="price"]').val();
                const qty = $row.find('[name^="qty"]').val();
                const dis1 = $row.find('[name^="dis1"]').val();
                const dis2 = $row.find('[name^="dis2"]').val();
                const amount = $row.find('[name^="amount"]').val();
                if (product || price || qty || dis1 || dis2 || amount) {
                    formData.products.push({ product, price, qty, dis1, dis2, amount });
                }
            });

            fetch('save_purchase.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'erp_dashboard.php';
                } else {
                    alert('Error saving data: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    </script>
    <?php include 'config.php'; ?>
</head>
<body>
    <div class="title-bar">
        MARG ERP 9+ | Gold | Ver-03-04-2025 | Lic-TRIAL | AIDI-AI DIGITAL 2025-2026 | USER-praja [S-11]
    </div>
    <div class="header-strip">
        <span>PURCHASE ENTRY</span>
        <span>
            Date: 07-07-2025<br>
            Day: Monday<br>
            Time: <span id="time">18:19:00</span>
        </span>
    </div>
    <div class="form-section">
        <div class="form-group"><label>Name:</label><input type="text" name="name"></div>
        <div class="form-group" style="margin-left: auto;"><label>Date:</label><input type="text" id="date" name="date"></div>
        <div class="form-group"><label>Entry No:</label><input type="text" name="entry_no"></div>
        <div class="form-group"><label>Party No:</label><input type="text" name="party_no"></div>
        <div class="form-group"><label>Dt:</label><input type="text" name="dt"></div>
        <div class="form-group"><label>Type:</label><input type="text" name="type"></div>
    </div>
    <div class="main-table">
        <table>
            <thead>
                <tr>
                    <th>PRODUCT</th>
                    <th>PER PIECE PRICE</th>
                    <th>QTY</th>
                    <th>DIS1%</th>
                    <th>DIS2%</th>
                    <th>AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="text" name="product[]"></td>
                    <td><input type="number" name="price[]" step="0.01"></td>
                    <td><input type="number" name="qty[]"></td>
                    <td><input type="number" name="dis1[]"></td>
                    <td><input type="number" name="dis2[]"></td>
                    <td><input type="number" name="amount[]" readonly></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="total-summary">
        <label>Total Amount:</label><input type="number" id="total_amount" name="total_amount" readonly>
    </div>
    <div class="button-group">
        <button onclick="saveData()">Save</button>
    </div>
    <div class="toolbar">
        <div>
            <img src="tool_icon.png" alt="Tool"><img src="chat_icon.png" alt="Chat"><img src="hourglass_icon.png" alt="Hourglass"><img src="upload_icon.png" alt="Upload"><img src="printer_icon.png" alt="Printer">
        </div>
        <span>HELP</span>
    </div>
    <div class="footer">
        <p>PURCHASE</p>
        <p>Last Bills :</p>
    </div>
    <script>
        // Update total amount
        $('table tbody').on('input', 'input', function() {
            const amounts = $('[name^="amount"]');
            let total = 0;
            amounts.each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('#total_amount').val(total.toFixed(2));
        });
    </script>
</body>
</html>