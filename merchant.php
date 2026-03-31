<?php
session_set_cookie_params(0);
session_start();

// Google Sheets No-Login Security Check
if (!isset($_SESSION['merchant_id'])) {
    header("Location: index.php");
    exit;
}

require 'db.php'; // This should now be your Google API setup file
$merchant_id = $_SESSION['merchant_id'];
$merchant_name = $_SESSION['merchant_name'] ?? 'Unknown Merchant';
$message = '';

// --- GOOGLE SHEETS HELPER LOGIC ---
$range = 'Sheet1!A:AG'; // Our full data range
$all_data = getAllMerchants($service, $spreadsheetId, $range);

$rowIndex = null;
$currentRow = [];
$reqs = [];

// Find the merchant in the sheet
foreach ($all_data as $index => $row) {
    if (isset($row[0]) && strtoupper($row[0]) === $merchant_id) {
        $rowIndex = $index + 1; // Google Sheets is 1-indexed
        $currentRow = array_pad($row, 33, ''); // Ensure the array has 33 columns
        
        // Translate the flat sheet row back into our associative array for the UI
        $reqs = [
            'tbo_pay_scrn' => $currentRow[2], 'tbo_pay_status' => $currentRow[3], 'tbo_pay_reason' => $currentRow[4],
            'tbo_return_scrn' => $currentRow[5], 'tbo_return_status' => $currentRow[6], 'tbo_return_reason' => $currentRow[7],
            'otc_pay_scrn' => $currentRow[8], 'otc_pay_status' => $currentRow[9], 'otc_pay_reason' => $currentRow[10],
            'otc_return_scrn' => $currentRow[11], 'otc_return_status' => $currentRow[12], 'otc_return_reason' => $currentRow[13],
            'otc_admin1_scrn' => $currentRow[14], 'otc_admin1_status' => $currentRow[15], 'otc_admin1_reason' => $currentRow[16],
            'otc_admin2_scrn' => $currentRow[17], 'otc_admin2_status' => $currentRow[18], 'otc_admin2_reason' => $currentRow[19],
            'postback_url' => $currentRow[20], 'postback_status' => $currentRow[21], 'postback_reason' => $currentRow[22],
            'return_url' => $currentRow[23], 'return_url_status' => $currentRow[24], 'return_url_reason' => $currentRow[25],
            'website_url' => $currentRow[26], 'website_status' => $currentRow[27], 'website_reason' => $currentRow[28],
            'rsa_status' => $currentRow[29], 'rsa_reason' => $currentRow[30],
            'idempotency_status' => $currentRow[31], 'idempotency_reason' => $currentRow[32]
        ];
        break;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reqs'])) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    function handleUpload($input_name, $upload_dir) {
        if (!empty($_FILES[$input_name]['name'])) {
            $filename = time() . '_' . basename($_FILES[$input_name]['name']);
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target)) return $target;
        }
        return null;
    }

    // Process Files & Inputs
    $tbo_pay = handleUpload('tbo_pay_scrn', $upload_dir);
    $tbo_ret = handleUpload('tbo_return_scrn', $upload_dir);
    $otc_pay = handleUpload('otc_pay_scrn', $upload_dir);
    $otc_ret = handleUpload('otc_return_scrn', $upload_dir);
    $otc_admin1 = handleUpload('otc_admin1_scrn', $upload_dir);
    $otc_admin2 = handleUpload('otc_admin2_scrn', $upload_dir);
    
    $pb_url = $_POST['postback_url'] ?? null;
    $ret_url = $_POST['return_url'] ?? null;
    $web_url = $_POST['website_url'] ?? null;

    // Prepare data to write to Google Sheets
    // If new merchant, start a fresh array. If existing, use their current row data.
    $updateData = $rowIndex ? $currentRow : array_pad([$merchant_id, $merchant_name], 33, '');

    $made_changes = false;

    // Map inputs to exact Google Sheet Columns. Set to pending, wipe reason.
    if ($tbo_pay) { $updateData[2] = $tbo_pay; $updateData[3] = 'pending'; $updateData[4] = ''; $made_changes = true; }
    if ($tbo_ret) { $updateData[5] = $tbo_ret; $updateData[6] = 'pending'; $updateData[7] = ''; $made_changes = true; }
    if ($otc_pay) { $updateData[8] = $otc_pay; $updateData[9] = 'pending'; $updateData[10] = ''; $made_changes = true; }
    if ($otc_ret) { $updateData[11] = $otc_ret; $updateData[12] = 'pending'; $updateData[13] = ''; $made_changes = true; }
    if ($otc_admin1) { $updateData[14] = $otc_admin1; $updateData[15] = 'pending'; $updateData[16] = ''; $made_changes = true; }
    if ($otc_admin2) { $updateData[17] = $otc_admin2; $updateData[18] = 'pending'; $updateData[19] = ''; $made_changes = true; }
    if ($pb_url) { $updateData[20] = $pb_url; $updateData[21] = 'pending'; $updateData[22] = ''; $made_changes = true; }
    if ($ret_url) { $updateData[23] = $ret_url; $updateData[24] = 'pending'; $updateData[25] = ''; $made_changes = true; }
    if ($web_url) { $updateData[26] = $web_url; $updateData[27] = 'pending'; $updateData[28] = ''; $made_changes = true; }

    if ($made_changes) {
        $body = new Google_Service_Sheets_ValueRange(['values' => [$updateData]]);
        $params = ['valueInputOption' => 'USER_ENTERED'];

        if ($rowIndex) {
            // Update existing row
            $updateRange = "Sheet1!A{$rowIndex}:AG{$rowIndex}";
            $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
            $message = "<div class='alert alert-success'>Requirements updated successfully!</div>";
        } else {
            // Append new merchant row
            $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
            $message = "<div class='alert alert-success'>Requirements submitted successfully!</div>";
        }
        
        // Refresh the page so the UI updates immediately with the new Google Sheet data
        header("Refresh:0");
        exit;
    }
}

// UI Helper Functions
function getStatus($reqs, $key) { return $reqs[$key] ?? 'not_submitted'; }
function needsAction($status) { return ($status === 'not_submitted' || $status === 'rejected' || empty($status)); }

function renderBadge($status) {
    if (empty($status) || $status === 'not_submitted') return "<span class='badge badge-secondary'>Not Submitted</span>";
    if ($status === 'pending') return "<span class='badge badge-warning'>Pending Review</span>";
    if ($status === 'approved') return "<span class='badge badge-success'>Approved</span>";
    if ($status === 'rejected') return "<span class='badge badge-danger'>Rejected</span>";
    return "<span class='badge badge-secondary'>Unknown</span>";
}

function renderReason($status, $reason) {
    if ($status === 'rejected' && !empty($reason)) {
        return "<span style='color: #dc3545; font-size: 0.85rem; font-weight: bold;'>⚠️ " . htmlspecialchars($reason) . "</span>";
    }
    return "<span style='color: #999;'>-</span>";
}

// Map all statuses for the UI loops
$statuses = [
    'tbo_pay' => getStatus($reqs, 'tbo_pay_status'), 'tbo_ret' => getStatus($reqs, 'tbo_return_status'),
    'otc_pay' => getStatus($reqs, 'otc_pay_status'), 'otc_ret' => getStatus($reqs, 'otc_return_status'),
    'otc_admin1' => getStatus($reqs, 'otc_admin1_status'), 'otc_admin2' => getStatus($reqs, 'otc_admin2_status'),
    'postback' => getStatus($reqs, 'postback_status'), 'return' => getStatus($reqs, 'return_url_status'),
    'website' => getStatus($reqs, 'website_status'), 'rsa' => getStatus($reqs, 'rsa_status'),
    'idem' => getStatus($reqs, 'idempotency_status')
];

$merchant_actionable = ['tbo_pay', 'tbo_ret', 'otc_pay', 'otc_ret', 'otc_admin1', 'otc_admin2', 'postback', 'return', 'website'];
$needs_submission = false;
foreach ($merchant_actionable as $key) { if (needsAction($statuses[$key])) { $needs_submission = true; break; } }

$all_approved = true;
foreach ($statuses as $key => $status) { if ($status !== 'approved') { $all_approved = false; break; } }
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
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .msg-box { text-align: center; padding: 40px 20px; border-radius: 8px; border: 1px solid transparent; }
        .msg-approved { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .msg-pending { background-color: #cce5ff; color: #004085; border-color: #b8daff; }
        .msg-box h3 { margin: 0 0 10px 0; font-size: 1.5rem; }
        .msg-box p { margin: 0; font-size: 1.05rem; line-height: 1.5; }
    </style>
</head>
<body>

<div class="top-bar">
    <div>
        <h2 style="margin: 0;">Merchant Dashboard</h2>
        <span style="font-size: 0.9rem; color: #666;">Welcome, <strong><?= htmlspecialchars($merchant_id) ?></strong> (<?= htmlspecialchars($merchant_name) ?>)</span>
    </div>
    
    <a href="logout.php" style="background-color: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem;">
        Log Out
    </a>
</div>

<?= $message ?>

<div class="container">
    <div class="card">
        <div class="card-header">Submission Status</div>
        <table>
            <tr>
                <th width="40%">Requirement</th>
                <th width="25%">Status</th>
                <th width="35%">Remarks</th>
            </tr>
            <tr>
                <td>TBO: Pay.aspx</td>
                <td><?= renderBadge($statuses['tbo_pay']) ?></td>
                <td><?= renderReason($statuses['tbo_pay'], $reqs['tbo_pay_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>TBO: Return URL</td>
                <td><?= renderBadge($statuses['tbo_ret']) ?></td>
                <td><?= renderReason($statuses['tbo_ret'], $reqs['tbo_return_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>OTC: Pay.aspx</td>
                <td><?= renderBadge($statuses['otc_pay']) ?></td>
                <td><?= renderReason($statuses['otc_pay'], $reqs['otc_pay_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>OTC: Return URL</td>
                <td><?= renderBadge($statuses['otc_ret']) ?></td>
                <td><?= renderReason($statuses['otc_ret'], $reqs['otc_return_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>OTC: Admin Pending</td>
                <td><?= renderBadge($statuses['otc_admin1']) ?></td>
                <td><?= renderReason($statuses['otc_admin1'], $reqs['otc_admin1_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>OTC: Admin Validated</td>
                <td><?= renderBadge($statuses['otc_admin2']) ?></td>
                <td><?= renderReason($statuses['otc_admin2'], $reqs['otc_admin2_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Postback URL</td>
                <td><?= renderBadge($statuses['postback']) ?></td>
                <td><?= renderReason($statuses['postback'], $reqs['postback_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Return URL</td>
                <td><?= renderBadge($statuses['return']) ?></td>
                <td><?= renderReason($statuses['return'], $reqs['return_url_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Website URL</td>
                <td><?= renderBadge($statuses['website']) ?></td>
                <td><?= renderReason($statuses['website'], $reqs['website_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>RSA-SHA256 Check</td>
                <td><?= renderBadge($statuses['rsa']) ?></td>
                <td><?= renderReason($statuses['rsa'], $reqs['rsa_reason'] ?? '') ?></td>
            </tr>
            <tr>
                <td>Idempotency Check</td>
                <td><?= renderBadge($statuses['idem']) ?></td>
                <td><?= renderReason($statuses['idem'], $reqs['idempotency_reason'] ?? '') ?></td>
            </tr>
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
                    <small>Screenshot the Dragonpay Pay.aspx payment method selection page.</small>
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
                    <small>Screenshot the Dragonpay Pay.aspx payment method selection page.</small>
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
                    <small>Capture a screenshot showing the transaction ID and status.</small>
                    <input type="file" name="otc_admin1_scrn" accept="image/png, image/jpeg">
                </div>
                <?php endif; ?>

                <?php if (needsAction($statuses['otc_admin2'])): ?>
                <div class="form-group">
                    <label>4. OTC: Admin Orders (Validated)</label>
                    <small>Screenshot the Admin > Orders page again, showing the updated status.</small>
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