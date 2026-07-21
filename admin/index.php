<?php
// admin/index.php
require_once 'auth.php';
require_once '../api/supabase/config.php';

$pdo = getDbConnection();
$update_message = '';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $transfer_id = intval($_POST['transfer_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    if ($transfer_id > 0 && in_array($new_status, ['pending', 'success', 'failed'])) {
        try {
            $stmt = $pdo->prepare("UPDATE money_transfers SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $transfer_id]);
            $update_message = "Status updated successfully to '" . ucfirst($new_status) . "'!";
        } catch (PDOException $e) {
            $update_message = "Error updating status: " . $e->getMessage();
        }
    }
}

// Fetch Stats
$total_volume = 0;
$total_fees = 0;
$total_transfers = 0;
$pending_count = 0;

try {
    $stats = $pdo->query("SELECT 
        SUM(amount) as total_volume, 
        SUM(fee) as total_fees, 
        COUNT(*) as total_transfers,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count 
        FROM money_transfers")->fetch();
        
    $total_volume = floatval($stats['total_volume'] ?? 0);
    $total_fees = floatval($stats['total_fees'] ?? 0);
    $total_transfers = intval($stats['total_transfers'] ?? 0);
    $pending_count = intval($stats['pending_count'] ?? 0);
} catch (PDOException $e) {}

// Fetch Transfers joined with user details
$transfers = [];
try {
    $transfers = $pdo->query("SELECT t.*, u.email, p.full_name, p.phone 
        FROM money_transfers t 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN profiles p ON u.id = p.id 
        ORDER BY t.created_at DESC")->fetchAll();
} catch (PDOException $e) {}

// Fetch Contact Submissions
$submissions = [];
try {
    $submissions = $pdo->query("SELECT * FROM contact_submissions ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {}

// Fetch Customer Reports & Accounts
$customers = [];
$total_customers = 0;
try {
    $customers = $pdo->query("SELECT u.id, u.email, u.created_at, p.full_name, p.phone,
        COUNT(t.id) as total_transfers,
        COALESCE(SUM(t.amount), 0) as total_volume
        FROM users u 
        LEFT JOIN profiles p ON u.id = p.id 
        LEFT JOIN money_transfers t ON u.id = t.user_id 
        GROUP BY u.id, u.email, u.created_at, p.full_name, p.phone 
        ORDER BY u.created_at DESC")->fetchAll();
    $total_customers = count($customers);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMMPayNow - Executive Admin Portal</title>
    <!-- RemixIcons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.5.0/remixicon.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        body {
            background-color: #0b0c10;
            color: #e5e7eb;
            min-height: 100vh;
        }
        
        /* Layout Grid */
        .admin-container {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar Styling */
        .sidebar {
            background-color: #12141c;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
        }
        .logo-box {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        .logo-box i {
            color: #fff;
            font-size: 20px;
        }
        .logo-text {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }
        .nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .nav-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #9ca3af;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .nav-item a:hover, .nav-item.active a {
            color: #fff;
            background-color: rgba(99, 102, 241, 0.15);
        }
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #f87171;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s;
            background-color: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
        }
        .logout-btn:hover {
            background-color: rgba(239, 68, 68, 0.15);
        }

        /* Main Content */
        .main-content {
            padding: 40px;
            overflow-y: auto;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header-title h2 {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }
        .header-title p {
            font-size: 14px;
            color: #6b7280;
        }
        
        /* Banner Alerts */
        .toast-alert {
            background-color: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Metric Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .metric-card {
            background-color: #12141c;
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .metric-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 0.5px;
        }
        .metric-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .metric-card.volume .metric-icon { background-color: rgba(99, 102, 241, 0.1); color: #818cf8; }
        .metric-card.fees .metric-icon { background-color: rgba(52, 211, 153, 0.1); color: #34d399; }
        .metric-card.orders .metric-icon { background-color: rgba(251, 191, 36, 0.1); color: #fbbf24; }
        .metric-card.pending .metric-icon { background-color: rgba(248, 113, 113, 0.1); color: #f87171; }
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }
        
        /* Table Styling */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .section-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }
        .card-table-wrapper {
            background-color: #12141c;
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            margin-bottom: 40px;
        }
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 16px 24px;
            font-size: 13px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }
        th {
            background-color: rgba(255, 255, 255, 0.01);
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        td {
            color: #d1d5db;
        }
        tr:hover td {
            background-color: rgba(255, 255, 255, 0.01);
        }
        
        /* Details Badge and styling */
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .user-name {
            font-weight: 600;
            color: #fff;
        }
        .user-subtext {
            font-size: 11px;
            color: #6b7280;
        }
        .bank-details {
            font-size: 12px;
            line-height: 1.4;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge.pending { background-color: rgba(251, 191, 36, 0.1); color: #fbbf24; }
        .badge.success { background-color: rgba(52, 211, 153, 0.1); color: #34d399; }
        .badge.failed { background-color: rgba(239, 68, 68, 0.1); color: #f87171; }
        
        /* Action form */
        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-select {
            background-color: #1a1c24;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 8px;
            outline: none;
            cursor: pointer;
        }
        .status-btn {
            background-color: #4f46e5;
            border: none;
            color: #fff;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .status-btn:hover {
            background-color: #6366f1;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div>
                <div class="logo">
                    <div class="logo-box">
                        <i class="ri-wallet-3-fill"></i>
                    </div>
                    <span class="logo-text">SMMPayNow</span>
                </div>
                <ul class="nav-links">
                    <li class="nav-item active">
                        <a href="#dashboard">
                            <i class="ri-dashboard-3-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#customers">
                            <i class="ri-user-line"></i>
                            <span>Customers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#transfers">
                            <i class="ri-exchange-funds-line"></i>
                            <span>Transfers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#inquiries">
                            <i class="ri-mail-line"></i>
                            <span>Inquiries</span>
                        </a>
                    </li>
                </ul>
            </div>
            <a href="auth.php?action=logout" class="logout-btn">
                <i class="ri-logout-circle-line"></i>
                <span>Logout Portal</span>
            </a>
        </div>
        
        <!-- Main Panel Content -->
        <div class="main-content">
            <div class="header-bar">
                <div class="header-title">
                    <h2>Admin Dashboard</h2>
                    <p>Manage users, verify payments, and oversee bank settlements</p>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button id="refresh-toggle-btn" onclick="toggleAutoRefresh()" style="background-color: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); color: #818cf8; padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.3s;">
                        <i class="ri-refresh-line"></i>
                        <span id="refresh-btn-text">Auto-Refresh: OFF</span>
                    </button>
                </div>
            </div>
            
            <?php if (!empty($update_message)): ?>
                <div class="toast-alert">
                    <i class="ri-information-line"></i>
                    <span><?php echo $update_message; ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Overview Cards (Dashboard View) -->
            <div id="section-dashboard" class="view-section">
                <div class="metrics-grid" style="grid-template-columns: repeat(5, 1fr);">
                    <div class="metric-card volume">
                        <div class="metric-header">
                            <span class="metric-title">Total Volume</span>
                            <div class="metric-icon"><i class="ri-money-rupee-circle-line"></i></div>
                        </div>
                        <span class="metric-value">₹<?php echo number_format($total_volume, 2); ?></span>
                    </div>
                    <div class="metric-card fees">
                        <div class="metric-header">
                            <span class="metric-title">Total Fees (0.7%)</span>
                            <div class="metric-icon"><i class="ri-percent-line"></i></div>
                        </div>
                        <span class="metric-value">₹<?php echo number_format($total_fees, 2); ?></span>
                    </div>
                    <div class="metric-card orders">
                        <div class="metric-header">
                            <span class="metric-title">Total Transfers</span>
                            <div class="metric-icon"><i class="ri-list-check-3"></i></div>
                        </div>
                        <span class="metric-value"><?php echo $total_transfers; ?></span>
                    </div>
                    <div class="metric-card pending">
                        <div class="metric-header">
                            <span class="metric-title">Pending Orders</span>
                            <div class="metric-icon"><i class="ri-time-line"></i></div>
                        </div>
                        <span class="metric-value"><?php echo $pending_count; ?></span>
                    </div>
                    <div class="metric-card customers" style="background-color: #12141c; border: 1px solid rgba(255, 255, 255, 0.03); border-radius: 20px; padding: 24px;">
                        <div class="metric-header">
                            <span class="metric-title">Customers</span>
                            <div class="metric-icon" style="background-color: rgba(168, 85, 247, 0.1); color: #c084fc;"><i class="ri-user-3-line"></i></div>
                        </div>
                        <span class="metric-value"><?php echo $total_customers; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Money Transfers Section -->
            <div id="section-transfers" class="view-section" style="display: none;">
                <div class="section-header">
                    <h3>Recent Bank Transfers</h3>
                </div>
                <div class="card-table-wrapper">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date / User</th>
                                    <th>Sender Card</th>
                                    <th>Recipient Bank Details</th>
                                    <th>Amount / Fee</th>
                                    <th>Razorpay ID</th>
                                    <th>Status / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transfers)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #9ca3af; padding: 40px 0;">No money transfer requests found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transfers as $t): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars($t['full_name'] ?? 'Guest'); ?></span>
                                                    <span class="user-subtext"><?php echo htmlspecialchars($t['email'] ?? 'No email'); ?></span>
                                                    <span class="user-subtext"><?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars($t['sender_card_holder']); ?></span>
                                                    <span class="user-subtext"><?php echo implode(' ', str_split($t['sender_card_number'], 4)); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="bank-details">
                                                    <strong>Name:</strong> <?php echo htmlspecialchars($t['recipient_name']); ?><br>
                                                    <strong>Bank:</strong> <?php echo htmlspecialchars($t['recipient_bank_name']); ?><br>
                                                    <strong>Acc Number:</strong> <?php echo htmlspecialchars($t['recipient_account_number']); ?><br>
                                                    <strong>IFSC:</strong> <?php echo htmlspecialchars($t['recipient_ifsc']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name" style="color: #34d399;">₹<?php echo number_format($t['transfer_amount'], 2); ?></span>
                                                    <span class="user-subtext">Total: ₹<?php echo number_format($t['amount'], 2); ?></span>
                                                    <span class="user-subtext">Fee: ₹<?php echo number_format($t['fee'], 2); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <code style="background-color: rgba(255, 255, 255, 0.05); padding: 4px 8px; border-radius: 6px; font-size: 11px;">
                                                    <?php echo htmlspecialchars($t['razorpay_payment_id'] ?? 'N/A'); ?>
                                                </code>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                                    <div>
                                                        <span class="badge <?php echo htmlspecialchars($t['status']); ?>">
                                                            <i class="ri-checkbox-blank-circle-fill" style="font-size: 8px;"></i>
                                                            <?php echo ucfirst($t['status']); ?>
                                                        </span>
                                                    </div>
                                                    <form class="status-form" method="POST">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="transfer_id" value="<?php echo $t['id']; ?>">
                                                        <select class="status-select" name="status">
                                                            <option value="pending" <?php echo $t['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="success" <?php echo $t['status'] === 'success' ? 'selected' : ''; ?>>Success</option>
                                                            <option value="failed" <?php echo $t['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                        </select>
                                                        <button type="submit" class="status-btn">Save</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Contact Inquiries Section -->
            <div id="section-inquiries" class="view-section" style="display: none;">
                <div class="section-header">
                    <h3>Contact Form Inquiries</h3>
                </div>
                <div class="card-table-wrapper">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sender</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Date Received</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($submissions)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #9ca3af; padding: 40px 0;">No inquiries received yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($submissions as $s): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars($s['name']); ?></span>
                                                    <span class="user-subtext"><?php echo htmlspecialchars($s['email']); ?></span>
                                                    <span class="user-subtext"><?php echo htmlspecialchars($s['phone'] ?? 'No Phone'); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong style="color: #fff;"><?php echo htmlspecialchars($s['subject'] ?? 'No Subject'); ?></strong>
                                            </td>
                                            <td>
                                                <div style="max-width: 400px; white-space: pre-wrap; font-size: 12px; line-height: 1.5; color: #9ca3af;">
                                                    <?php echo htmlspecialchars($s['message']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="user-subtext"><?php echo date('d M Y, h:i A', strtotime($s['created_at'])); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Registered Customers Section -->
            <div id="section-customers" class="view-section" style="display: none;">
                <div class="section-header">
                    <h3>Registered Customers & User Reports</h3>
                </div>
                <div class="card-table-wrapper">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name / Email</th>
                                    <th>Phone Number</th>
                                    <th>Total Transfers</th>
                                    <th>Total Volume</th>
                                    <th>Registered Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #9ca3af; padding: 40px 0;">No registered customers found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <span class="user-name"><?php echo htmlspecialchars($c['full_name'] ?? 'User #' . $c['id']); ?></span>
                                                    <span class="user-subtext"><?php echo htmlspecialchars($c['email']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="color: #d1d5db; font-size: 13px;"><?php echo htmlspecialchars($c['phone'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge success">
                                                    <i class="ri-list-check-3" style="font-size: 10px;"></i>
                                                    <?php echo intval($c['total_transfers']); ?> Transfers
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="color: #4ade80;">₹<?php echo number_format(floatval($c['total_volume']), 2); ?></strong>
                                            </td>
                                            <td>
                                                <span class="user-subtext"><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- SPA View Switcher & Auto-Refresh Script -->
            <script>
            let autoRefreshTimer = null;

            function toggleAutoRefresh() {
                const btnText = document.getElementById('refresh-btn-text');
                const btn = document.getElementById('refresh-toggle-btn');
                if (autoRefreshTimer) {
                    clearInterval(autoRefreshTimer);
                    autoRefreshTimer = null;
                    btnText.textContent = 'Auto-Refresh: OFF';
                    btn.style.backgroundColor = 'rgba(99, 102, 241, 0.15)';
                    btn.style.color = '#818cf8';
                } else {
                    autoRefreshTimer = setInterval(() => {
                        window.location.reload();
                    }, 15000);
                    btnText.textContent = 'Auto-Refresh: ON (15s)';
                    btn.style.backgroundColor = 'rgba(52, 211, 153, 0.2)';
                    btn.style.color = '#34d399';
                }
            }

            function showSection(sectionId) {
                // Hide all sections
                document.querySelectorAll('.view-section').forEach(sec => sec.style.display = 'none');
                
                // Remove active class from all links
                document.querySelectorAll('.nav-links li').forEach(li => li.classList.remove('active'));
                
                // Show correct section
                const targetSection = document.getElementById('section-' + sectionId);
                if (targetSection) {
                    targetSection.style.display = 'block';
                }
                
                // Find matching link
                const targetLink = document.querySelector(`.nav-links a[href="#${sectionId}"]`);
                if (targetLink) {
                    targetLink.parentElement.classList.add('active');
                }
                
                // Update title
                const headerTitle = document.querySelector('.header-title h2');
                const headerSubtitle = document.querySelector('.header-title p');
                if (sectionId === 'dashboard') {
                    headerTitle.textContent = 'Admin Dashboard';
                    headerSubtitle.textContent = 'Manage users, verify payments, and oversee bank settlements';
                } else if (sectionId === 'customers') {
                    headerTitle.textContent = 'Registered Customers';
                    headerSubtitle.textContent = 'View registered accounts and their complete transaction reports';
                } else if (sectionId === 'transfers') {
                    headerTitle.textContent = 'Bank Transfers';
                    headerSubtitle.textContent = 'Overview and settle user bank transfer requests';
                } else if (sectionId === 'inquiries') {
                    headerTitle.textContent = 'Contact Inquiries';
                    headerSubtitle.textContent = 'Read messages submitted by users through the contact form';
                }
            }

            // Handle clicks
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.addEventListener('click', function(e) {
                    const sectionId = this.getAttribute('href').substring(1);
                    showSection(sectionId);
                });
            });

            // Check url hash on page load
            window.addEventListener('DOMContentLoaded', () => {
                const hash = window.location.hash.substring(1) || 'dashboard';
                showSection(hash);
            });
            </script>
        </div>
    </div>
</body>
</html>
