<?php
session_start();
require 'db.php';

// Mock Login (In reality, use a proper login system)
$_SESSION['user_id'] = 'DEVOPS01';
$_SESSION['role'] = 'devops';
$message = '';
$last_edited_id = null; 

// Define all requirement fields dynamically
$req_fields = [
    ['key' => 'tbo_pay', 'label' => 'Test Bank Online: Pay.aspx', 'val_col' => 'tbo_pay_scrn', 'stat_col' => 'tbo_pay_status', 'reason_col' => 'tbo_pay_reason', 'type' => 'file'],
    ['key' => 'tbo_ret', 'label' => 'Test Bank Online: Return URL', 'val_col' => 'tbo_return_scrn', 'stat_col' => 'tbo_return_status', 'reason_col' => 'tbo_return_reason', 'type' => 'file'],
    ['key' => 'otc_pay', 'label' => 'Test Bank Over the Counter: Pay.aspx', 'val_col' => 'otc_pay_scrn', 'stat_col' => 'otc_pay_status', 'reason_col' => 'otc_pay_reason', 'type' => 'file'],
    ['key' => 'otc_ret', 'label' => 'Test Bank Over the Counter: Return URL', 'val_col' => 'otc_return_scrn', 'stat_col' => 'otc_return_status', 'reason_col' => 'otc_return_reason', 'type' => 'file'],
    ['key' => 'otc_admin1', 'label' => 'Test Bank Over the Counter: Admin Pending', 'val_col' => 'otc_admin1_scrn', 'stat_col' => 'otc_admin1_status', 'reason_col' => 'otc_admin1_reason', 'type' => 'file'],
    ['key' => 'otc_admin2', 'label' => 'Test Bank Over the Counter: Admin Validated', 'val_col' => 'otc_admin2_scrn', 'stat_col' => 'otc_admin2_status', 'reason_col' => 'otc_admin2_reason', 'type' => 'file'],
    ['key' => 'postback', 'label' => 'Postback URL', 'val_col' => 'postback_url', 'stat_col' => 'postback_status', 'reason_col' => 'postback_reason', 'type' => 'text'],
    ['key' => 'return', 'label' => 'Return URL', 'val_col' => 'return_url', 'stat_col' => 'return_url_status', 'reason_col' => 'return_url_reason', 'type' => 'text'],
    ['key' => 'website', 'label' => 'Website URL', 'val_col' => 'website_url', 'stat_col' => 'website_status', 'reason_col' => 'website_reason', 'type' => 'text'],
    ['key' => 'rsa', 'label' => 'RSA-SHA256 Check', 'val_col' => null, 'stat_col' => 'rsa_status', 'reason_col' => 'rsa_reason', 'type' => 'devops'],
    ['key' => 'idem', 'label' => 'Idempotency Check', 'val_col' => null, 'stat_col' => 'idempotency_status', 'reason_col' => 'idempotency_reason', 'type' => 'devops'],
];

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $req_id = $_POST['req_id'];
    $last_edited_id = $req_id; 
    
    // 1. Fetch the CURRENT record so we know the file paths before potentially deleting them
    $stmt = $pdo->prepare("SELECT * FROM requirements WHERE id = ?");
    $stmt->execute([$req_id]);
    $current_record = $stmt->fetch(PDO::FETCH_ASSOC);

    $set_clauses = [];
    $params = [];
    
    foreach ($req_fields as $field) {
        $stat_col = $field['stat_col'];
        $val_col = $field['val_col'];
        $reason_col = $field['reason_col'];

        if (isset($_POST[$stat_col])) {
            $new_status = $_POST[$stat_col];
            
            // Add status to update query
            $set_clauses[] = "$stat_col = ?";
            $params[] = $new_status;

            if ($new_status === 'rejected') {
                // Save the reason if rejected
                $reason = trim($_POST[$reason_col] ?? '');
                $set_clauses[] = "$reason_col = ?";
                $params[] = $reason;

                // Delete the file if it's a file type
                if ($field['type'] === 'file' && !empty($current_record[$val_col])) {
                    $file_path = $current_record[$val_col];
                    if (file_exists($file_path)) { unlink($file_path); }
                    $set_clauses[] = "$val_col = NULL";
                }
            } else {
                // If status is changed to pending/approved, wipe the reason clean
                $set_clauses[] = "$reason_col = NULL";
            }
        }
    }
    
    if (!empty($set_clauses)) {
        $params[] = $req_id; 
        $updateQuery = "UPDATE requirements SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $pdo->prepare($updateQuery)->execute($params);
        $message = "<div class='alert alert-success'>Statuses updated successfully for Merchant ID: " . htmlspecialchars($_POST['merchant_id']) . ". Any rejected files have been permanently deleted.</div>";
    }
}

// Handle Email Endorsement
if (isset($_POST['send_email'])) {
    $last_edited_id = $_POST['req_id']; 
    $to = "arvin.retuya@dragonpay.com";
    $subject = "Production Account Endorsement - Merchant ID: " . $_POST['merchant_id'];
    $email_body = "All technical requirements have been approved. Please endorse for a production account.";
    // mail($to, $subject, $email_body);
    $message = "<div class='alert alert-primary'>Endorsement email successfully sent to Accounts & MerchantOps!</div>";
}

// Fetch All Submissions safely
$submissions = [];
try {
    $stmt = $pdo->query("SELECT r.*, u.username FROM requirements r JOIN users u ON r.merchant_id = u.merchant_id ORDER BY r.id DESC");
    if ($stmt) $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// UI Helpers
function renderBadge($status) {
    $status = $status ?? 'not_submitted';
    $class = 'badge-secondary'; $text = 'Not Submitted';
    if ($status === 'pending') { $class = 'badge-warning'; $text = 'Pending'; }
    elseif ($status === 'approved') { $class = 'badge-success'; $text = 'Approved'; }
    elseif ($status === 'rejected') { $class = 'badge-danger'; $text = 'Rejected'; }
    return "<span class='badge $class'>$text</span>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevOps Monitoring Dashboard</title>
    <style>
        :root { --primary: #b30000; --bg: #f4f7f6; --text: #333; --border: #e1e4e8; --success: #28a745; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; margin: 0; }
        .container { max-width: 1400px; margin: 0 auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
        
        details.card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--border); margin-bottom: 15px; overflow: hidden; }
        details.card summary { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; font-size: 1.15rem; font-weight: bold; color: var(--primary); cursor: pointer; user-select: none; list-style: none; transition: background-color 0.2s; }
        details.card summary::-webkit-details-marker { display: none; }
        details.card summary:hover { background-color: #f8f9fa; }
        details.card[open] summary { border-bottom: 1px solid var(--border); background-color: #f8f9fa; }
        
        .card-content { padding: 20px; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .header-icon { font-size: 0.9rem; color: #888; transition: transform 0.3s ease; }
        details.card[open] .header-icon { transform: rotate(180deg); }

        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.95rem; }
        th, td { padding: 12px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
        th { background-color: #f8f9fa; font-weight: 600; color: #555; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; color: white; display: inline-block; text-align: center; min-width: 80px; }
        .badge-success { background-color: var(--success); }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-secondary { background-color: #6c757d; }
        
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: bold; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: #b64545; }
        .btn-success { background-color: var(--success); color: white; width: 100%; padding: 12px; font-size: 1.1rem; margin-top: 15px; }
        .btn-success:hover { background-color: #218838; }
        
        select { padding: 6px; border-radius: 4px; border: 1px solid #ccc; font-size: 0.9rem; width: 100%; }
        .file-link { color: var(--primary); text-decoration: none; font-weight: bold; }
        .file-link:hover { text-decoration: underline; }
        .text-muted { color: #888; font-style: italic; font-size: 0.9rem; }
        .text-danger { color: #dc3545; font-style: italic; font-size: 0.9rem; font-weight: bold; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-primary { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <h2>DevOps Monitoring Dashboard</h2>
        <span>Logged in as <strong>DevOps Admin</strong></span>
    </div>

    <?= $message ?>

    <?php if (empty($submissions)): ?>
        <div class="card" style="text-align: center; padding: 50px; background: white; border-radius: 8px; border: 1px solid var(--border);">
            <h3>No Merchant Submissions Yet</h3>
            <p class="text-muted">When merchants submit their technical requirements, they will appear here.</p>
        </div>
    <?php else: ?>

        <?php foreach ($submissions as $index => $sub): 
            $all_approved = true;
            foreach ($req_fields as $field) {
                if (($sub[$field['stat_col']] ?? '') !== 'approved') {
                    $all_approved = false;
                    break;
                }
            }
            $is_open = ($last_edited_id == $sub['id']) || ($last_edited_id === null && $index === 0);
        ?>
            <details class="card" <?= $is_open ? 'open' : '' ?>>
                
                <summary>
                    <div class="header-left">
                        <span class="header-icon">▼</span>
                        <span><?= htmlspecialchars($sub['username']) ?> (<?= htmlspecialchars($sub['merchant_id']) ?>)</span>
                    </div>
                    <div>
                        <?php if ($all_approved): ?>
                            <span class="badge badge-success">READY FOR PROD</span>
                        <?php else: ?>
                            <span class="badge badge-warning">PENDING REVIEW</span>
                        <?php endif; ?>
                    </div>
                </summary>
                
                <div class="card-content">
                    <form method="POST">
                        <input type="hidden" name="req_id" value="<?= $sub['id'] ?>">
                        <input type="hidden" name="merchant_id" value="<?= htmlspecialchars($sub['merchant_id']) ?>">
                        
                        <table>
                            <tr>
                                <th width="25%">Requirement</th>
                                <th width="35%">Submitted Data / File</th>
                                <th width="15%">Current Status</th>
                                <th width="25%">Review Action</th>
                            </tr>
                            
                            <?php foreach ($req_fields as $field): 
                                $status = $sub[$field['stat_col']] ?? 'not_submitted';
                                $data = $field['val_col'] ? ($sub[$field['val_col']] ?? null) : null;
                            ?>
                            <tr>
                                <td><strong><?= $field['label'] ?></strong></td>
                                
                                <td>
                                    <?php if ($field['type'] === 'file'): ?>
                                        <?php if (!empty($data)): ?>
                                            <a href="<?= htmlspecialchars($data) ?>" target="_blank" class="file-link">🔍 View Image</a>
                                        <?php elseif ($status === 'rejected'): ?>
                                            <span class="text-danger">File deleted. Awaiting resubmission.</span>
                                        <?php else: ?>
                                            <span class="text-muted">No file uploaded</span>
                                        <?php endif; ?>
                                    
                                    <?php elseif ($field['type'] === 'text'): ?>
                                        <?php if (!empty($data)): ?>
                                            <a href="<?= htmlspecialchars($data) ?>" target="_blank" class="file-link"><?= htmlspecialchars($data) ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    
                                    <?php elseif ($field['type'] === 'devops'): ?>
                                        <span class="text-muted">Tested internally by DevOps</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td><?= renderBadge($status) ?></td>
                                
                                <td>
                                    <select name="<?= $field['stat_col'] ?>" onchange="toggleReason(this)">
                                        <option value="pending" <?= $status === 'pending' || $status === 'not_submitted' ? 'selected' : '' ?>>Pending Review</option>
                                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approve</option>
                                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Reject</option>
                                    </select>
                                    
                                    <input type="text" 
                                        name="<?= $field['reason_col'] ?>" 
                                        value="<?= htmlspecialchars($sub[$field['reason_col']] ?? '') ?>" 
                                        placeholder="Reason if rejected..." 
                                        class="reason-input"
                                        style="margin-top: 8px; width: 100%; padding: 6px; box-sizing: border-box; font-size: 0.85rem; border: 1px solid #ccc; border-radius: 4px; display: <?= $status === 'rejected' ? 'block' : 'none' ?>;"
                                        required>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                        </table>
                        
                        <div style="text-align: right;">
                            <button type="submit" name="update_status" class="btn btn-primary">Save All Statuses</button>
                        </div>
                    </form>

                    <?php if ($all_approved): ?>
                        <form method="POST" style="margin-top: 10px;">
                            <input type="hidden" name="req_id" value="<?= $sub['id'] ?>">
                            <input type="hidden" name="merchant_id" value="<?= htmlspecialchars($sub['merchant_id']) ?>">
                            <button type="submit" name="send_email" class="btn btn-success">
                                ✉️ Send Production Account Endorsement Email
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<script>
function toggleReason(selectElement) {
    // Find the input field that comes immediately after this specific dropdown
    const reasonInput = selectElement.nextElementSibling;
    
    // If the status is changed to rejected, show the text box
    if (selectElement.value === 'rejected') {
        reasonInput.style.display = 'block';
        reasonInput.required = true; // Force them to type a reason
    } else {
        // Otherwise, hide it and clear any text they might have typed
        reasonInput.style.display = 'none';
        reasonInput.value = ''; 
        reasonInput.required = false;
    }
}
</script>


</body>
</html>