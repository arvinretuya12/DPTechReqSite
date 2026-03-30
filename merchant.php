<?php
session_start();
require 'db.php';

// Mock Login (In reality, use a proper login system)
$_SESSION['user_id'] = 'TESTMID';
$_SESSION['role'] = 'merchant';
$merchant_id = $_SESSION['user_id'];
$message = '';

// Helper function to safely get status
function getStatus($reqs, $key) {
    return $reqs[$key] ?? 'not_submitted';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reqs'])) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    function handleUpload($input_name, $upload_dir) {
        if (!empty($_FILES[$input_name]['name'])) {
            $filename = time() . '_' . basename($_FILES[$input_name]['name']);
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target)) {
                return $target;
            }
        }
        return null;
    }

    // Process TBO Files
    $tbo_pay = handleUpload('tbo_pay_scrn', $upload_dir);
    $tbo_ret = handleUpload('tbo_return_scrn', $upload_dir);
    
    // Process OTC Files
    $otc_pay = handleUpload('otc_pay_scrn', $upload_dir);
    $otc_ret = handleUpload('otc_return_scrn', $upload_dir);
    $otc_admin1 = handleUpload('otc_admin1_scrn', $upload_dir);
    $otc_admin2 = handleUpload('otc_admin2_scrn', $upload_dir);

    // Process URLs
    $pb_url = $_POST['postback_url'] ?? null;
    $ret_url = $_POST['return_url'] ?? null;
    $web_url = $_POST['website_url'] ?? null;

    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM requirements WHERE merchant_id = ?");
    $stmt->execute([$merchant_id]);
    
    if ($stmt->rowCount() > 0) {
        $updateFields = [];
        $params = [];
        
        if ($tbo_pay) { $updateFields[] = "tbo_pay_scrn=?, tbo_pay_status='pending'"; $params[] = $tbo_pay; }
        if ($tbo_ret) { $updateFields[] = "tbo_return_scrn=?, tbo_return_status='pending'"; $params[] = $tbo_ret; }
        if ($otc_pay) { $updateFields[] = "otc_pay_scrn=?, otc_pay_status='pending'"; $params[] = $otc_pay; }
        if ($otc_ret) { $updateFields[] = "otc_return_scrn=?, otc_return_status='pending'"; $params[] = $otc_ret; }
        if ($otc_admin1) { $updateFields[] = "otc_admin1_scrn=?, otc_admin1_status='pending'"; $params[] = $otc_admin1; }
        if ($otc_admin2) { $updateFields[] = "otc_admin2_scrn=?, otc_admin2_status='pending'"; $params[] = $otc_admin2; }
        
        if ($pb_url) { $updateFields[] = "postback_url=?, postback_status='pending'"; $params[] = $pb_url; }
        if ($ret_url) { $updateFields[] = "return_url=?, return_url_status='pending'"; $params[] = $ret_url; }
        if ($web_url) { $updateFields[] = "website_url=?, website_status='pending'"; $params[] = $web_url; }

        if (!empty($updateFields)) {
            $updateQuery = "UPDATE requirements SET " . implode(', ', $updateFields) . " WHERE merchant_id = ?";
            $params[] = $merchant_id;
            $pdo->prepare($updateQuery)->execute($params);
            $message = "<div class='alert alert-success'>Requirements updated successfully!</div>";
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO requirements 
            (merchant_id, tbo_pay_scrn, tbo_return_scrn, otc_pay_scrn, otc_return_scrn, otc_admin1_scrn, otc_admin2_scrn, postback_url, return_url, website_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$merchant_id, $tbo_pay, $tbo_ret, $otc_pay, $otc_ret, $otc_admin1, $otc_admin2, $pb_url, $ret_url, $web_url]);
        $message = "<div class='alert alert-success'>Requirements submitted successfully!</div>";
    }
}

// Fetch current statuses
$stmt = $pdo->prepare("SELECT * FROM requirements WHERE merchant_id = ?");
$stmt->execute([$merchant_id]);
$reqs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Map all statuses, including DevOps ones
$statuses = [
    'tbo_pay' => getStatus($reqs, 'tbo_pay_status'),
    'tbo_ret' => getStatus($reqs, 'tbo_return_status'),
    'otc_pay' => getStatus($reqs, 'otc_pay_status'),
    'otc_ret' => getStatus($reqs, 'otc_return_status'),
    'otc_admin1' => getStatus($reqs, 'otc_admin1_status'),
    'otc_admin2' => getStatus($reqs, 'otc_admin2_status'),
    'postback' => getStatus($reqs, 'postback_status'),
    'return' => getStatus($reqs, 'return_url_status'),
    'website' => getStatus($reqs, 'website_status'),
    'rsa' => getStatus($reqs, 'rsa_status'),
    'idem' => getStatus($reqs, 'idempotency_status')
];

// Define which items the merchant actually needs to upload/submit
$merchant_actionable = ['tbo_pay', 'tbo_ret', 'otc_pay', 'otc_ret', 'otc_admin1', 'otc_admin2', 'postback', 'return', 'website'];

function needsAction($status) {
    return ($status === 'not_submitted' || $status === 'rejected');
}

// 1. Check if the merchant needs to submit anything
$needs_submission = false;
foreach ($merchant_actionable as $key) {
    if (needsAction($statuses[$key])) { 
        $needs_submission = true; 
        break; 
    }
}

// 2. Check if ABSOLUTELY EVERYTHING is approved
$all_approved = true;
foreach ($statuses as $key => $status) {
    if ($status !== 'approved') {
        $all_approved = false;
        break;
    }
}

function renderBadge($status) {
    $class = 'badge-secondary'; $text = 'Not Submitted';
    if ($status === 'pending') { $class = 'badge-warning'; $text = 'Pending Review'; }
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
    <title>Merchant Dashboard</title>
    <style>
        :root { --primary: #b30000; --bg: #f4f7f6; --text: #333; --border: #e1e4e8; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; margin: 0; }
        .container { max-width: 1300px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; }
        @media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .card-header { font-size: 1.25rem; font-weight: bold; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.9rem; }
        th, td { padding: 10px; border-bottom: 1px solid var(--border); text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.80rem; font-weight: bold; color: white; display: inline-block; white-space: nowrap; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-secondary { background-color: #6c757d; }
        .form-section-title { margin-top: 25px; margin-bottom: 10px; color: var(--primary); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: 5px;}
        .form-group { margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid var(--primary); }
        .form-group label { font-weight: bold; display: block; margin-bottom: 3px; }
        .form-group small { display: block; margin-bottom: 8px; color: #555; line-height: 1.4; }
        .form-group input[type="file"], .form-group input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: var(--primary); color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; width: 100%; margin-top: 15px; }
        .btn:hover { background-color: #b64545; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        /* New Custom Message Boxes */
        .msg-box { text-align: center; padding: 40px 20px; border-radius: 8px; border: 1px solid transparent; }
        .msg-approved { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .msg-pending { background-color: #cce5ff; color: #004085; border-color: #b8daff; }
        .msg-box h3 { margin: 0 0 10px 0; font-size: 1.5rem; }
        .msg-box p { margin: 0; font-size: 1.05rem; line-height: 1.5; }
    </style>
</head>
<body>

<div class="top-bar">
    <h2>Merchant Dashboard</h2>
    <span>Welcome, <strong><?= htmlspecialchars($merchant_id) ?></strong></span>
</div>

<?= $message ?>

<div class="container">
    <div class="card">
        <div class="card-header">Submission Status</div>
        <table>
            <tr><th>Requirement</th><th>Status</th></tr>
            <tr><td>TBO: Pay.aspx</td><td><?= renderBadge($statuses['tbo_pay']) ?></td></tr>
            <tr><td>TBO: Return URL</td><td><?= renderBadge($statuses['tbo_ret']) ?></td></tr>
            <tr><td>OTC: Pay.aspx</td><td><?= renderBadge($statuses['otc_pay']) ?></td></tr>
            <tr><td>OTC: Return URL</td><td><?= renderBadge($statuses['otc_ret']) ?></td></tr>
            <tr><td>OTC: Admin Pending</td><td><?= renderBadge($statuses['otc_admin1']) ?></td></tr>
            <tr><td>OTC: Admin Validated</td><td><?= renderBadge($statuses['otc_admin2']) ?></td></tr>
            <tr><td>Postback URL</td><td><?= renderBadge($statuses['postback']) ?></td></tr>
            <tr><td>Return URL</td><td><?= renderBadge($statuses['return']) ?></td></tr>
            <tr><td>Website URL</td><td><?= renderBadge($statuses['website']) ?></td></tr>
            <tr><td>RSA-SHA256 Check</td><td><?= renderBadge($statuses['rsa']) ?></td></tr>
            <tr><td>Idempotency Check</td><td><?= renderBadge($statuses['idem']) ?></td></tr>
        </table>
        <p><small><em>* Note: RSA and Idempotency are tested internally by our DevOps team.</em></small></p>
    </div>

    <div class="card">
        <div class="card-header">Action Items</div>

        <?php if ($needs_submission): ?>
            <p>Please provide the missing or rejected requirements below.</p>
            <form method="POST" enctype="multipart/form-data">
                
                <?php if (needsAction($statuses['tbo_pay']) || needsAction($statuses['tbo_ret'])): ?>
                    <div class="form-section-title">Test Bank Online</div>
                <?php endif; ?>

                <?php if (needsAction($statuses['tbo_pay'])): ?>
                <div class="form-group">
                    <label>1. TBO: Pay.aspx</label>
                    <small>Screenshot the Dragonpay Pay.aspx payment method selection page (include the address bar).</small>
                    <input type="file" name="tbo_pay_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['tbo_ret'])): ?>
                <div class="form-group">
                    <label>2. TBO: Return URL</label>
                    <small>Select 'Test Bank Online', proceed with the payment, and screenshot the Return URL page.</small>
                    <input type="file" name="tbo_return_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['otc_pay']) || needsAction($statuses['otc_ret']) || needsAction($statuses['otc_admin1']) || needsAction($statuses['otc_admin2'])): ?>
                    <div class="form-section-title">Test Bank Over the Counter</div>
                <?php endif; ?>

                <?php if (needsAction($statuses['otc_pay'])): ?>
                <div class="form-group">
                    <label>1. OTC: Pay.aspx</label>
                    <small>Screenshot the Dragonpay Pay.aspx payment method selection page (include the address bar).</small>
                    <input type="file" name="otc_pay_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['otc_ret'])): ?>
                <div class="form-group">
                    <label>2. OTC: Return URL</label>
                    <small>Click on 'Send instructions via email', then screenshot the Return URL page.</small>
                    <input type="file" name="otc_return_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['otc_admin1'])): ?>
                <div class="form-group">
                    <label>3. OTC: Admin Orders (Pending)</label>
                    <small>Go to your Admin > Orders page or your database. Capture a screenshot showing the transaction ID and status.</small>
                    <input type="file" name="otc_admin1_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['otc_admin2'])): ?>
                <div class="form-group">
                    <label>4. OTC: Admin Orders (Validated)</label>
                    <small>Follow the emailed instructions, complete 'Step 2. Validation'. Screenshot the Admin > Orders page again, showing the updated status.</small>
                    <input type="file" name="otc_admin2_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['postback']) || needsAction($statuses['return']) || needsAction($statuses['website'])): ?>
                    <div class="form-section-title">Integration URLs</div>
                <?php endif; ?>

                <?php if (needsAction($statuses['postback'])): ?>
                <div class="form-group">
                    <label>Postback URL</label>
                    <input type="text" name="postback_url" placeholder="https://yourdomain.com/postback">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['return'])): ?>
                <div class="form-group">
                    <label>Return URL</label>
                    <input type="text" name="return_url" placeholder="https://yourdomain.com/return">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['website'])): ?>
                <div class="form-group">
                    <label>Website URL</label>
                    <input type="text" name="website_url" placeholder="https://yourdomain.com">
                </div>
                <?php endif; ?>

                <button type="submit" name="submit_reqs" class="btn">Submit Pending Requirements</button>
            </form>

        <?php elseif ($all_approved): ?>
            <div class="msg-box msg-approved">
                <h3>🎉 Congratulations!</h3>
                <p>All requirements have been approved. Kindly wait for your Accounts Manager to reach out for other business requirements needed for Production.</p>
            </div>
            
        <?php else: ?>
            <div class="msg-box msg-pending">
                <h3>✅ All caught up!</h3>
                <p>You have submitted all required files. Please wait for DevOps to review your pending items.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>