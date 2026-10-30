<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
require_once 'whatsapp_helper.php';
include('layout.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "WhatsApp Message Logs";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';

if ($success_message) {
    unset($_SESSION['success_message']);
}

$whatsapp = new WhatsAppHelper();
$logs = $whatsapp->getMessageLogs(100, 0);
?>

<style>
    :root {
        --primary: #571F5A;
        --primary-light: #7A2E80;
        --success: #22c55e;
        --danger: #ef4444;
        --warning: #f59e0b;
        --text-dark: #1A202C;
        --text-light: #ffffff;
        --text-muted: #6B7280;
        --bg-main: #F4F5F7;
        --card-bg: #ffffff;
        --border: #E5E7EB;
        --radius: 8px;
        --shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--bg-main);
        color: var(--text-dark);
        font-size: 14.2px;
        line-height: 1.5;
    }

    .content {
        margin-top: 65px;
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 65px);
        transition: margin-left 0.3s ease;
    }

    .content-full {
        margin-left: 0;
    }

    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: var(--text-light);
        padding: 18px 24px;
        border-radius: var(--radius);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
    }

    .page-title h4 {
        font-size: 1.55rem;
        font-weight: 700;
        margin: 0;
    }

    .page-title h6 {
        font-size: 0.92rem;
        opacity: 0.85;
        margin-top: 4px;
    }

    .table-responsive {
        background: var(--card-bg);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow-x: auto;
    }

    .table th {
        background: var(--primary);
        color: white;
        padding: 11px 10px;
        font-size: 12.8px;
        font-weight: 600;
        text-transform: uppercase;
        text-align: left;
    }

    .table td {
        padding: 13px 12px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        font-size: 14.2px;
    }

    .table tr:hover {
        background: #f9fafb;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .status-success {
        background: #dcfce7;
        color: #166534;
    }

    .status-failed {
        background: #fef2f2;
        color: #991b1b;
    }

    .message-preview {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-custom {
        background: linear-gradient(135deg, #571F5A, #6E2A73);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.85);
        padding: 12px 26px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15.2px;
        box-shadow: 0 5px 15px rgba(87, 31, 90, 0.35);
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-custom:hover {
        background: linear-gradient(135deg, #6A2C70, #8A3B90);
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(87, 31, 90, 0.45);
        border-color: rgba(255, 255, 255, 1);
    }

    .alert-success, .alert-danger {
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 18px;
        font-weight: 500;
    }

    .alert-success { background: var(--success); color: white; }
    .alert-danger  { background: var(--danger); color: white; }

    @media (max-width: 1024px) {
        .content {
            margin-left: 0;
            padding: 18px;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>WhatsApp Message Logs</h4>
            <h6>View sent message history</h6>
        </div>
        <div class="page-btn-group">
            <a href="customers.php" class="btn btn-custom btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Customers
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead class="thead-light">
                        <tr>
                            <th>Date & Time</th>
                            <th>Recipient</th>
                            <th>Message Type</th>
                            <th>Message Preview</th>
                            <th>Status</th>
                            <th>HTTP Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['recipient_phone']); ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo ucfirst(str_replace('_', ' ', $log['message_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="message-preview" title="<?php echo htmlspecialchars($log['message_data']); ?>">
                                        <?php 
                                        $message_data = json_decode($log['message_data'], true);
                                        if ($message_data && isset($message_data['text']['body'])) {
                                            echo htmlspecialchars(substr($message_data['text']['body'], 0, 50) . '...');
                                        } elseif ($message_data && isset($message_data['template']['name'])) {
                                            echo 'Template: ' . htmlspecialchars($message_data['template']['name']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['http_code'] == 200): ?>
                                        <span class="status-badge status-success">Success</span>
                                    <?php else: ?>
                                        <span class="status-badge status-failed">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $log['http_code'] == 200 ? 'success' : 'danger'; ?>">
                                        <?php echo $log['http_code']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No message logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

<?php
ob_end_flush();
?>
