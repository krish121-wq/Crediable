<?php
require_once 'config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized";
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo "Invalid cheque id";
    exit;
}

try {
    $stmt = $conn->prepare("SELECT bt.id, bt.amount, bt.transaction_date, bt.description, bt.cheque_number, bt.payee_name,
        ba.bank_name, ba.account_number, ba.ifsc_code, ba.branch_name, ba.account_holder_name
        FROM bank_transactions bt
        JOIN bank_accounts ba ON bt.bank_account_id = ba.id
        WHERE bt.id = ? AND bt.created_by = ? AND bt.transaction_type = 'debit' AND bt.cheque_number IS NOT NULL");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0) {
        echo "Cheque not found or access denied.";
        exit;
    }
    $row = $res->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit;
}

if (!function_exists('numberToWords')) {
    function numberToWords($num){
        $ones = array('','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen');
        $tens = array('','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety');
        if($num==0) return 'Zero';
        if($num<20) return $ones[$num];
        if($num<100) return $tens[floor($num/10)] . ($num%10? ' '.$ones[$num%10]:'');
        if($num<1000) return $ones[floor($num/100)] . ' Hundred' . ($num%100? ' and '.numberToWords($num%100):'');
        if($num<100000) return numberToWords(floor($num/1000)) . ' Thousand' . ($num%1000? ' '.numberToWords($num%1000):'');
        if($num<10000000) return numberToWords(floor($num/100000)) . ' Lakh' . ($num%100000? ' '.numberToWords($num%100000):'');
        return numberToWords(floor($num/10000000)) . ' Crore' . ($num%10000000? ' '.numberToWords($num%10000000):'');
    }
}

?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Print Cheque</title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;padding:12px;background:#fff}
    .cheque{border:1px solid #000;max-width:860px;height:340px;margin:0 auto;padding:18px;position:relative;background:#fff}

    .bank-name{font-size:18px;font-weight:800}
    .date-box{position:absolute;right:18px;top:18px;
        padding:6px 10px;width:170px;text-align:center;font-size:13px}
    .date-box .label{font-size:11px;color: #ffffff}

    .pay-row{margin-top:28px;display:flex;align-items:center}
    .pay-label{font-weight:800;margin-right:10px;
        font-size:16px;
        margin-top:36px;
    }
    .pay-line{flex:1;
        padding-bottom:6px;
        margin-top:36px;
        margin-left: 65px;
        padding-left:6px;font-size:16px}

    .amount-row{margin-top:18px;display:flex;align-items:flex-start}
    .rupees-label{min-width:90px;font-weight:700}
    .amount-words{flex:1;
        margin-left: 65px;
        padding-bottom:6px;padding-left:6px;font-size:14px}
    .amount-box{width:200px;
        margin-left:12px;padding:8px;
        font-weight:700;
        font-size:16px;text-align:center}
    .amount-box .symbol{font-weight:700;margin-right:6px}

    
    .acc-row{left:18px;bottom:24px;
       padding:6px 10px;width:320px;font-size:13px}
    .acc-row .label{font-weight:700;
        margin-left: 65px;
        margin-right:8px
    }

    /* signature area on right bottom */
    .sign-area{position:absolute;right:18px;bottom:20px;text-align:center;font-size:13px}

    @media print{ body{margin:0} .cheque{border:none} }
    
    @media print {
    .no-print {
        display: none;
    }
}
</style>
</head>
<body>
    
<div class="cheque">
    <div class="bank-name"></div>
    <div class="date-box">
        <div class="label">DATE</div>
        <div style="font-weight:700;margin-top:6px"><?php echo date('d m Y', strtotime($row['transaction_date'])); ?></div>
    </div>

    <div class="pay-row">
        <div class="pay-label"></div>
        <div class="pay-line"><?php echo htmlspecialchars($row['payee_name']); ?></div>
    </div>

    <div class="amount-row">
        <div class="rupees-label"></div>
        <div class="amount-words"><?php
            // amount in words (reuse numberToWords)
            $parts = explode('.', number_format($row['amount'],2,'.',''));
            $int = (int)$parts[0];
            $dec = (int)$parts[1];
            echo numberToWords($int) . ' Rupees' . ($dec? ' and '.numberToWords($dec).' Paise':'' );
        ?></div>
        <div class="amount-box">
            <span class="symbol"></span>
            <span class="num"><?php echo number_format($row['amount'],2); ?></span>
        </div>
    </div>

    <div class="acc-row"><span class="label"></span><?php echo htmlspecialchars($row['account_number']); ?></div>

    <div class="sign-area">
       
    </div>
</div>

<div class="no-print" style="margin-bottom:10px; display:flex; gap:10px;">
    
    <!-- BACK BUTTON -->
    <button onclick="goBack()" style="
        background:#571F5A;
        color:#fff;
        border:none;
        padding:8px 16px;
        border-radius:6px;
        cursor:pointer;
        font-size:14px;
    ">
        ← Back
    </button>

    <!-- PRINT BUTTON -->
    <button onclick="printCheque()" style="
        background:#16A34A;
        color:#fff;
        border:none;
        padding:8px 16px;
        border-radius:6px;
        cursor:pointer;
        font-size:14px;
    ">
        🖨 Print
    </button>

</div>
<script>
function goBack() {
    window.close(); 
    window.history.back(); // fallback
}
</script>
<script>window.onload=function(){window.print();};</script>

<script>
function goBack() {
    window.close();
    window.history.back();
}

function printCheque() {
    window.print();
}
</script>
</body>
</html>
