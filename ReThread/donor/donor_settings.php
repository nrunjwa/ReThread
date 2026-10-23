<?php
require_once '../session.php';
require_once '../dbconn.php';

// Protect page - Ensure only logged-in donors can access this
if (!isset($_SESSION['donor_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    header("Location: donor_login.php");
    exit;
}

$donor_id = $_SESSION['donor_id'];
$message = "";
$messageType = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($address)) {
        $message = "Name, Email, and Address are required fields.";
        $messageType = "error";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } else {
        // Check if email is already used by ANOTHER donor
        $checkEmail = $conn->prepare("SELECT donor_id FROM donors WHERE donor_email = ? AND donor_id != ?");
        $checkEmail->bind_param("si", $email, $donor_id);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $message = "This email is already in use by another account.";
            $messageType = "error";
        } else {
            $checkEmail->close();

            // Update the database
            if (!empty($new_password)) {
                // Update with a new password
                $stmt = $conn->prepare("UPDATE donors SET donor_name=?, donor_email=?, donor_phone=?, donor_address=?, donor_password=? WHERE donor_id=?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $address, $new_password, $donor_id);
            } else {
                // Update without changing the password
                $stmt = $conn->prepare("UPDATE donors SET donor_name=?, donor_email=?, donor_phone=?, donor_address=? WHERE donor_id=?");
                $stmt->bind_param("ssssi", $name, $email, $phone, $address, $donor_id);
            }

            if ($stmt->execute()) {
                // Update session name so the dashboard greeting changes immediately
                $_SESSION['donor_name'] = $name;
                
                $message = "Your settings have been saved successfully.";
                $messageType = "success";
            } else {
                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// FETCH CURRENT DATA TO PRE-FILL THE FORM
$stmt = $conn->prepare("SELECT donor_name, donor_email, donor_phone, donor_address FROM donors WHERE donor_id = ?");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$donorData = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Account Settings – ReThread</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-cream: #faf8f2; --sidebar-bg: #153b2c; --sidebar-text: #e0e7e3;
      --primary-green: #1d4d38; --text-dark: #1a2721; --text-muted: #57655d;
      --border-color: #e1e5e2; --card-bg: #ffffff;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg-cream); display: flex; min-height: 100vh; color: var(--text-dark); }
    
    /* Sidebar */
    .sidebar { width: 260px; background: var(--sidebar-bg); color: white; padding: 24px; display: flex; flex-direction: column; }
    .brand-logo { font-family: 'Newsreader', serif; font-size: 1.6rem; font-weight: 700; color: white; text-decoration: none; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
    .nav-item { padding: 12px 16px; border-radius: 8px; color: var(--sidebar-text); text-decoration: none; font-size: 0.95rem; margin-bottom: 8px; transition: 0.2s; }
    .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.1); color: white; }

    /* Main Content */
    .main-content { flex: 1; padding: 40px; width: calc(100vw - 260px); }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .header h1 { font-family: 'Newsreader', serif; font-size: 2rem; font-weight: 600; }
    .header-right { display: flex; align-items: center; gap: 16px; font-size: 0.9rem; color: var(--text-muted); }
    .btn-signout { border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 500; }

    /* Panel & Form */
    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; max-width: 700px; }
    
    .form-group { margin-bottom: 20px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.9rem; font-weight: 500; margin-bottom: 8px; color: var(--text-dark); }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; transition: border 0.2s; background: #fff; }
    .form-control:focus { border-color: var(--primary-green); }
    
    .section-divider { border-top: 1px solid var(--border-color); margin: 30px 0; padding-top: 24px; }
    .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-dark); }
    
    .btn-submit { background: var(--primary-green); color: white; border: none; padding: 14px 24px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
    .btn-submit:hover { background: #143a28; }
    
    .alert { padding: 12px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; }
    .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffc9c9; }
    .alert-success { background: #d1f2e0; color: #176043; border: 1px solid #b5ebc9; }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="donor_dashboard.php" class="nav-item">My Donations</a>
    <a href="new_donation.php" class="nav-item">New Donation</a>    
    <a href="collection_points.php" class="nav-item">Find Collection Points</a>
    <a href="donor_settings.php" class="nav-item active">Account Settings</a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="header">
      <h1>Account Settings</h1>
      <div class="header-right">
        <span><?php echo htmlspecialchars($donorData['donor_email'] ?? ''); ?></span>
        <a href="../logout.php" class="btn-signout">Log out</a>
      </div>
    </div>

    <div class="panel">
      <?php if($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <form method="POST" action="donor_settings.php">
        
        <!-- Pre-filled Profile Info -->
        <div class="form-row">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($donorData['donor_name'] ?? ''); ?>" required>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($donorData['donor_phone'] ?? ''); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($donorData['donor_email'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Default Pickup Address / Postcode</label>
          <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($donorData['donor_address'] ?? ''); ?>" required>
        </div>

        <!-- Password Change Section -->
        <div class="section-divider">
          <div class="section-title">Change Password</div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">Leave these fields blank if you do not want to change your password.</p>
          
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" placeholder="Enter new password">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
            </div>
          </div>
        </div>

        <button type="submit" class="btn-submit">Save Changes</button>

      </form>
    </div>

  </div>
</body>
</html>