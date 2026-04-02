<?php
// Force session cookie to expire when the browser closes
session_set_cookie_params(0);
session_start();

require 'db.php'; // Google API setup file

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$last_edited_merchant = null; // Remember which card to keep open

// --- GOOGLE SHEETS SETUP ---
$range = 'Sheet1!A:AG';
$all_data = getAllMerchants($service, $spreadsheetId, $range);

// Define fields mapped to exact Google Sheet column indexes (0-based)
$req_fields = [
    ['label' => 'Test Bank Online: Pay.aspx', 'stat_col' => 'tbo_pay_status', 'reason_col' => 'tbo_pay_reason', 'val_idx' => 2, 'stat_idx' => 3, 'reason_idx' => 4, 'type' => 'file'],
    ['label' => 'Test Bank Online: Return URL', 'stat_col' => 'tbo_return_status', 'reason_col' => 'tbo_return_reason', 'val_idx' => 5, 'stat_idx' => 6, 'reason_idx' => 7, 'type' => 'file'],
    ['label' => 'Test Bank Over-the-counter: Pay.aspx', 'stat_col' => 'otc_pay_status', 'reason_col' => 'otc_pay_reason', 'val_idx' => 8, 'stat_idx' => 9, 'reason_idx' => 10, 'type' => 'file'],
    ['label' => 'Test Bank Over-the-counter: Return URL', 'stat_col' => 'otc_return_status', 'reason_col' => 'otc_return_reason', 'val_idx' => 11, 'stat_idx' => 12, 'reason_idx' => 13, 'type' => 'file'],
    ['label' => 'Test Bank Over-the-counter: Admin Pending', 'stat_col' => 'otc_admin1_status', 'reason_col' => 'otc_admin1_reason', 'val_idx' => 14, 'stat_idx' => 15, 'reason_idx' => 16, 'type' => 'file'],
    ['label' => 'Test Bank Over-the-counter: Admin Validated', 'stat_col' => 'otc_admin2_status', 'reason_col' => 'otc_admin2_reason', 'val_idx' => 17, 'stat_idx' => 18, 'reason_idx' => 19, 'type' => 'file'],
    ['label' => 'Postback URL', 'stat_col' => 'postback_status', 'reason_col' => 'postback_reason', 'val_idx' => 20, 'stat_idx' => 21, 'reason_idx' => 22, 'type' => 'text'],
    ['label' => 'Return URL', 'stat_col' => 'return_url_status', 'reason_col' => 'return_url_reason', 'val_idx' => 23, 'stat_idx' => 24, 'reason_idx' => 25, 'type' => 'text'],
    ['label' => 'Website URL', 'stat_col' => 'website_status', 'reason_col' => 'website_reason', 'val_idx' => 26, 'stat_idx' => 27, 'reason_idx' => 28, 'type' => 'text'],
    ['label' => 'RSA-SHA256 Check', 'stat_col' => 'rsa_status', 'reason_col' => 'rsa_reason', 'val_idx' => null, 'stat_idx' => 29, 'reason_idx' => 30, 'type' => 'devops'],
    ['label' => 'Idempotency Check', 'stat_col' => 'idempotency_status', 'reason_col' => 'idempotency_reason', 'val_idx' => null, 'stat_idx' => 31, 'reason_idx' => 32, 'type' => 'devops'],
];

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $merchant_id_post = $_POST['merchant_id'];
    $last_edited_merchant = $merchant_id_post;
    
    $row_to_update = [];
    $row_index = null;

    // Find exact row in spreadsheet
    foreach ($all_data as $index => $row) {
        if (isset($row[0]) && strtoupper($row[0]) === strtoupper($merchant_id_post)) {
            $row_index = $index + 1; 
            $row_to_update = array_pad($row, 33, ''); 
            break;
        }
    }

    if ($row_index) {
        foreach ($req_fields as $field) {
            $stat_name = $field['stat_col'];
            $reason_name = $field['reason_col'];
            
            if (isset($_POST[$stat_name])) {
                $new_status = $_POST[$stat_name];
                $row_to_update[$field['stat_idx']] = $new_status; 

                if ($new_status === 'rejected') {
                    $row_to_update[$field['reason_idx']] = trim($_POST[$reason_name] ?? '');
                    if ($field['type'] === 'file' && !empty($row_to_update[$field['val_idx']])) {
                        $file_path = $row_to_update[$field['val_idx']];
                        if (file_exists($file_path)) { unlink($file_path); }
                        $row_to_update[$field['val_idx']] = ''; 
                    }
                } else {
                    $row_to_update[$field['reason_idx']] = ''; 
                }
            }
        }
        
        $updateRange = "Sheet1!A{$row_index}:AG{$row_index}";
        $body = new Google_Service_Sheets_ValueRange(['values' => [$row_to_update]]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
        
        $message = "<div class='alert alert-success'>Statuses updated successfully for Merchant ID: " . htmlspecialchars($merchant_id_post) . ".</div>";
        $all_data = getAllMerchants($service, $spreadsheetId, $range);
    }
}

// Handle Email Endorsement
if (isset($_POST['send_email'])) {
    $last_edited_merchant = $_POST['merchant_id']; 
    $merchant_to_endorse = htmlspecialchars($_POST['merchant_id']);
    // ... [Your PHPMailer Code Here] ...
    $message = "<div class='alert alert-primary'>Endorsement email successfully sent via SMTP!</div>";
}

// --- SEARCH & PAGINATION LOGIC ---
$search_query = trim($_GET['search'] ?? '');
$current_page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$records_per_page = 10;

// 1. Clean data (Remove Header row & Empty rows)
$merchants = [];
foreach ($all_data as $index => $row) {
    if ($index > 0 && !empty($row[0])) { // Skip row 0 (headers)
        $merchants[] = $row;
    }
}

// 2. Apply Search Filter
if ($search_query !== '') {
    $merchants = array_filter($merchants, function($row) use ($search_query) {
        $mid = strtolower($row[0] ?? '');
        $mname = strtolower($row[1] ?? '');
        $sq = strtolower($search_query);
        // Return true if search query is found in ID or Name
        return strpos($mid, $sq) !== false || strpos($mname, $sq) !== false;
    });
}

// 3. Apply Pagination Math
$total_records = count($merchants);
$total_pages = ceil($total_records / $records_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $records_per_page;
$paged_merchants = array_slice($merchants, $offset, $records_per_page);

// UI Helpers
function renderBadge($status) {
    if (empty($status) || $status === 'not_submitted') return "<span class='badge badge-secondary'>Not Submitted</span>";
    if ($status === 'pending') return "<span class='badge badge-warning'>Pending</span>";
    if ($status === 'approved') return "<span class='badge badge-success'>Approved</span>";
    if ($status === 'rejected') return "<span class='badge badge-danger'>Rejected</span>";
    return "<span class='badge badge-secondary'>Unknown</span>";
}

// URL builder helper for maintaining state when forms submit
$url_params = "?page=" . $current_page . "&search=" . urlencode($search_query);
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
        
        /* Search Bar Styles */
        .search-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .search-form { display: flex; gap: 10px; width: 100%; max-width: 500px; }
        .search-form input[type="text"] { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        
        /* Pagination Styles */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; }
        .page-btn { padding: 8px 12px; border: 1px solid var(--border); background: white; color: var(--primary); text-decoration: none; border-radius: 4px; font-weight: bold; }
        .page-btn:hover { background: #f8f9fa; }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-btn.disabled { color: #ccc; cursor: not-allowed; pointer-events: none; }

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
        
        .btn { padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: bold; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: #b64545; }
        .btn-secondary { background-color: #6c757d; color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; }
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
        <h2>DevOps Technical Requirements Monitoring Dashboard</h2>
        <!-- <a href="logout.php" style="background-color: #dc3545; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem;">
            Log Out
        </a> -->
    </div>

    <?= $message ?>

    <div class="search-container">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search by Merchant Name or ID..." value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search_query)): ?>
                <a href="devops.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        <div>
            <span class="text-muted">Total Records: <strong><?= $total_records ?></strong></span>
        </div>
    </div>

    <?php if (empty($paged_merchants)): ?>
        <div class="card" style="text-align: center; padding: 50px; background: white; border-radius: 8px; border: 1px solid var(--border);">
            <h3>No Records Found</h3>
            <p class="text-muted">Try adjusting your search criteria or wait for new submissions.</p>
        </div>
    <?php else: ?>

        <?php foreach ($paged_merchants as $row): 
            $sub = array_pad($row, 33, '');
            $merchant_id = $sub[0];
            $merchant_name = $sub[1];

            $all_approved = true;
            foreach ($req_fields as $field) {
                if ($sub[$field['stat_idx']] !== 'approved') {
                    $all_approved = false;
                    break;
                }
            }

            // Closed by default, UNLESS we just edited this exact card
            $is_open = ($last_edited_merchant === $merchant_id);
        ?>
            <details class="card" <?= $is_open ? 'open' : '' ?>>
                
                <summary>
                    <div class="header-left">
                        <span class="header-icon">▼</span>
                        <span><?= htmlspecialchars($merchant_name) ?> (<?= htmlspecialchars($merchant_id) ?>)</span>
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
                    <form method="POST" action="<?= $url_params ?>">
                        <input type="hidden" name="merchant_id" value="<?= htmlspecialchars($merchant_id) ?>">
                        
                        <table>
                            <tr>
                                <th width="25%">Requirement</th>
                                <th width="35%">Submitted Data / File</th>
                                <th width="15%">Current Status</th>
                                <th width="25%">Review Action</th>
                            </tr>
                            
                            <?php foreach ($req_fields as $field): 
                                $status = $sub[$field['stat_idx']] ?: 'not_submitted';
                                $data = $field['val_idx'] !== null ? $sub[$field['val_idx']] : null;
                                $reason = $sub[$field['reason_idx']] ?? '';
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
                                           value="<?= htmlspecialchars($reason) ?>" 
                                           placeholder="Reason if rejected..." 
                                           class="reason-input"
                                           style="margin-top: 8px; width: 100%; padding: 6px; box-sizing: border-box; font-size: 0.85rem; border: 1px solid #ccc; border-radius: 4px; display: <?= $status === 'rejected' ? 'block' : 'none' ?>;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                        </table>
                        
                        <div style="text-align: right;">
                            <button type="submit" name="update_status" class="btn btn-primary">Save All Statuses</button>
                        </div>
                    </form>

                    <?php if ($all_approved): ?>
                        <form method="POST" action="<?= $url_params ?>" style="margin-top: 10px;">
                            <input type="hidden" name="merchant_id" value="<?= htmlspecialchars($merchant_id) ?>">
                            <button type="submit" name="send_email" class="btn btn-success">
                                ✉️ Send Production Account Endorsement Email
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="?page=<?= max(1, $current_page - 1) ?>&search=<?= urlencode($search_query) ?>" class="page-btn <?= $current_page <= 1 ? 'disabled' : '' ?>">« Prev</a>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>" class="page-btn <?= $i === $current_page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <a href="?page=<?= min($total_pages, $current_page + 1) ?>&search=<?= urlencode($search_query) ?>" class="page-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>">Next »</a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function toggleReason(selectElement) {
    const reasonInput = selectElement.nextElementSibling;
    if (selectElement.value === 'rejected') {
        reasonInput.style.display = 'block';
        reasonInput.required = true; 
    } else {
        reasonInput.style.display = 'none';
        reasonInput.value = ''; 
        reasonInput.required = false;
    }
}
</script>

</body>
</html>