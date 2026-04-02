<?php
session_start();

// If they are already in a session, send them to the dashboard
if (isset($_SESSION['merchant_id'])) {
    header("Location: merchant.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $merchant_id = trim($_POST['merchant_id']);
    $merchant_name = trim($_POST['merchant_name']);
    
    if (!empty($merchant_id) && !empty($merchant_name)) {
        // Log them in using session variables
        $_SESSION['merchant_id'] = strtoupper($merchant_id);
        $_SESSION['merchant_name'] = $merchant_name;
        header("Location: merchant.php");
        exit;
    } else {
        $error = "Please provide both Merchant ID and Merchant Name.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Merchant Technical Requirements Portal</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; background-color: #b30000; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .btn:hover { background-color: #b64545; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 style="text-align: center; color: #b30000;">Merchant Technical Requirements Portal</h2>
        <p style="text-align: center; color: #666; font-size: 0.9rem;">Enter your details to submit or monitor your requirements.</p>
        
        <?php if(isset($error)) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>

        <form method="POST">
            <div class="form-group">
                <label>Merchant ID</label>
                <input type="text" name="merchant_id" placeholder="e.g. TESTMID" required>
            </div>
            <div class="form-group">
                <label>Merchant Name / Company</label>
                <input type="text" name="merchant_name" placeholder="e.g. Acme Corp" required>
            </div>
            <button type="submit" class="btn">Continue to Dashboard</button>
        </form>
    </div>
</body>
</html>