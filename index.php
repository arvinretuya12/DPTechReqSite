<?php
// Force session cookie to expire when the browser closes
session_set_cookie_params(0);
session_start();

// If they are already in a session, send them straight to the dashboard
if (isset($_SESSION['merchant_id'])) {
    header("Location: merchant.php");
    exit;
}

require 'db.php'; 

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $merchant_id = trim($_POST['merchant_id']);
    
    if (!empty($merchant_id)) {
        $merchant_id_upper = strtoupper($merchant_id);
        
        // UPDATE 1: Fetch Columns A through C so we can reach Column C
        $preferred_range = 'PreferredSheet!A:C'; 
        
        try {
            $response = $service->spreadsheets_values->get($spreadsheetId, $preferred_range);
            $preferred_data = $response->getValues() ?: [];
            
            $is_valid_merchant = false;
            $fetched_merchant_name = '';
            
            // Loop through the list to see if their Merchant ID exists
            foreach ($preferred_data as $row) {
                // Check if the ID in Column A (Index 0) matches
                if (isset($row[0]) && strtoupper(trim($row[0])) === $merchant_id_upper) {
                    $is_valid_merchant = true;
                    
                    // UPDATE 2: Grab the name from Column C (Index 2). 
                    // Fallback to 'Unknown' if the cell is blank.
                    $fetched_merchant_name = isset($row[2]) ? trim($row[2]) : 'Unknown Merchant';
                    break; 
                }
            }
            
            if ($is_valid_merchant) {
                // Log them in using the exact name pulled from Column C
                $_SESSION['merchant_id'] = $merchant_id_upper;
                $_SESSION['merchant_name'] = $fetched_merchant_name;
                header("Location: merchant.php");
                exit;
            } else {
                $error = "No existing Account yet with DP.";
            }

        } catch (Exception $e) {
            $error = "System Error: Could not connect to validation database. Please try again later.";
        }

    } else {
        $error = "Please provide a Merchant ID.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Technical Requirements Portal</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 35px 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 400px; border: 1px solid #e1e4e8; }
        .login-card h2 { margin-top: 0; text-align: center; color: #b30000; font-size: 1.5rem; }
        .login-card p.subtitle { text-align: center; color: #666; font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #333; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1rem; transition: border-color 0.2s; text-transform: uppercase; }
        input:focus { border-color: #b30000; outline: none; }
        .btn { width: 100%; padding: 12px; background-color: #b30000; color: white; border: none; border-radius: 4px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: background-color 0.2s; margin-top: 10px; }
        .btn:hover { background-color: #8a0000; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; font-weight: bold; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Technical Requirements Portal</h2>
        <p class="subtitle">Enter your Merchant ID to access your system integration dashboard.</p>
        
        <?php if($error): ?>
            <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Merchant ID</label>
                <input type="text" name="merchant_id" placeholder="e.g. ACME_TEST" required 
                       value="<?= isset($_POST['merchant_id']) ? htmlspecialchars($_POST['merchant_id']) : '' ?>">
            </div>
            
            <button type="submit" class="btn">Access Dashboard</button>
        </form>
    </div>
</body>
</html>