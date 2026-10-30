<?php
require_once 'config.php';
session_start();

// Set page title
$page_title = "Accounting Management";

// Set default for $is_restricted to ensure sidebar is visible
$is_restricted = isset($is_restricted) ? $is_restricted : true;

// Sample data (replace with actual database queries)
$ledger_entries = [
    ['id' => 1, 'date' => '2025-06-20', 'description' => 'Sales Invoice #1001', 'debit' => 5000, 'credit' => 0, 'account' => 'Accounts Receivable'],
    ['id' => 2, 'date' => '2025-06-21', 'description' => 'Vendor Payment #2001', 'debit' => 0, 'credit' => 2000, 'account' => 'Accounts Payable'],
];

$receivables = [
    ['id' => 1, 'customer' => 'Acme Corp', 'invoice' => 'INV-1001', 'amount' => 5000, 'due_date' => '2025-07-20'],
];

$payables = [
    ['id' => 1, 'vendor' => 'Beta LLC', 'bill' => 'BILL-2001', 'amount' => 2000, 'due_date' => '2025-07-15'],
];


$bank_reconciliations = [
    ['id' => 1, 'date' => '2025-06-22', 'bank_balance' => 10000, 'ledger_balance' => 10000, 'status' => 'Reconciled'],
];

// Include layout
include 'layout.php';
?>

<!-- Accounting Management CSS -->
<style>
    .content {
        margin-left: 250px;
        padding: 20px 30px;
        min-height: calc(100vh - 56px);
        transition: margin-left 0.3s ease;
        animation: fadeIn 0.6s ease-out;
    }

    .content-full {
        margin-left: 0;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dashboard-title {
        color: var(--primary);
        font-weight: 700;
        font-size: 1.75rem;
        margin-bottom: 1rem;
    }

    .card {
        background: var(--card-bg);
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    .table {
        background: var(--card-bg);
    }

    .table th, .table td {
        padding: 0.75rem;
        vertical-align: middle;
    }

    .table thead th {
        background: var(--grey-light);
        color: var(--primary);
        font-weight: 600;
    }

    .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-weight: 700;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: var(--blue-dark);
        transform: scale(1.03);
        box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
    }

    .text-secondary {
        color: var(--text-secondary);
        font-size: 1.1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 20px 15px;
        }
        .dashboard-title {
            font-size: 1.5rem;
        }
        .btn-primary {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
    }
</style>

<!-- Accounting Management Content -->
<div class="content <?php echo $is_restricted ? '' : 'content-full'; ?>">
    <h2 class="dashboard-title">Accounting Management</h2>

    <!-- General Ledger -->
    <div class="card">
        <div class="card-body">
            <div class="section-title">General Ledger</div>
            <?php if (empty($ledger_entries)): ?>
                <p class="text-secondary">No ledger entries found.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Account</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger_entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['date']); ?></td>
                                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                <td><?php echo htmlspecialchars($entry['account']); ?></td>
                                <td><?php echo number_format($entry['debit'], 2); ?></td>
                                <td><?php echo number_format($entry['credit'], 2); ?></td>
                                <td>
                                    <a href="journal_entry.php?id=<?php echo $entry['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="add_journal_entry.php" class="btn btn-primary mt-3">Add Journal Entry</a>
        </div>
    </div>

    <!-- Receivables -->
    <div class="card">
        <div class="card-body">
            <div class="section-title">Accounts Receivable</div>
            <?php if (empty($receivables)): ?>
                <p class="text-secondary">No receivables found.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receivables as $receivable): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($receivable['customer']); ?></td>
                                <td><?php echo htmlspecialchars($receivable['invoice']); ?></td>
                                <td><?php echo number_format($receivable['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($receivable['due_date']); ?></td>
                                <td>
                                    <a href="receive_payment.php?id=<?php echo $receivable['id']; ?>" class="btn btn-primary btn-sm">Receive Payment</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="create_sales_voucher.php" class="btn btn-primary mt-3">Create Sales Voucher</a>
        </div>
    </div>

    <!-- Payables -->
    <div class="card">
        <div class="card-body">
            <div class="section-title">Accounts Payable</div>
            <?php if (empty($payables)): ?>
                <p class="text-secondary">No payables found.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Bill</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payables as $payable): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payable['vendor']); ?></td>
                                <td><?php echo htmlspecialchars($payable['bill']); ?></td>
                                <td><?php echo number_format($payable['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payable['due_date']); ?></td>
                                <td>
                                    <a href="make_payment.php?id=<?php echo $payable['id']; ?>" class="btn btn-primary btn-sm">Make Payment</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="create_payment_voucher.php" class="btn btn-primary mt-3">Create Payment Voucher</a>
        </div>
    </div>

    <!-- Bank Reconciliation -->
    <div class="card">
        <div class="card-body">
            <div class="section-title">Bank Reconciliation</div>
            <?php if (empty($bank_reconciliations)): ?>
                <p class="text-secondary">No reconciliations found.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bank Balance</th>
                            <th>Ledger Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bank_reconciliations as $reconciliation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reconciliation['date']); ?></td>
                                <td><?php echo number_format($reconciliation['bank_balance'], 2); ?></td>
                                <td><?php echo number_format($reconciliation['ledger_balance'], 2); ?></td>
                                <td><?php echo htmlspecialchars($reconciliation['status']); ?></td>
                                <td>
                                    <a href="reconcile_bank.php?id=<?php echo $reconciliation['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="start_reconciliation.php" class="btn btn-primary mt-3">Start Reconciliation</a>
        </div>
    </div>

    <!-- Contra Vouchers -->
    <div class="card">
        <div class="card-body">
            <div class="section-title">Contra Vouchers</div>
            <p class="text-secondary">Manage fund transfers between accounts.</p>
            <a href="create_contra_voucher.php" class="btn btn-primary">Create Contra Voucher</a>
        </div>
    </div>
</div>

<script>
    // Ensure sidebar is visible on page load for desktop
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const content = document.querySelector('.content');
        if (window.innerWidth > 768) {
            sidebar.classList.remove('sidebar-hidden');
            sidebar.classList.add('sidebar-active');
            content.classList.remove('content-full');
        }
    });
</script>

<?php
// Include footer if needed
// include 'footer.php';
?>