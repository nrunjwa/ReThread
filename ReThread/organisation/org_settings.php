<?php
require_once '../session.php';
require_once '../dbconn.php';

// Protect page - Ensure only logged-in organizations can access this
if (!isset($_SESSION['org_id']) || ($_SESSION['role'] ?? '') !== 'organization') {
    header("Location: org_login.php");
    exit;
}

$org_id = $_SESSION['org_id'];
$message = "";
$messageType = "";

// Define the available materials for the checkboxes
$availableMaterials = [
    "Cotton", "Denim", "Polyester", "Wool", "Linen", 
    "Mixed Textiles", "Wearable Clothes", "Shoes", "Bags", "Household Textiles"
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['org_name'] ?? '');
    $email = trim($_POST['org_email'] ?? '');
    $address = trim($_POST['org_address'] ?? '');
    $capacity = (float)($_POST['capacity_kg'] ?? 0);
    
    // Get selected materials array and convert to JSON
    $selectedMaterials = $_POST['materials'] ?? [];
    $materialsJson = json_encode($selectedMaterials);

    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($address) || $capacity <= 0) {
        $message = "Name, Email, Address, and a valid Capacity are required.";
        $messageType = "error";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } else {
        // Check if email is already used by ANOTHER organization
        $checkEmail = $conn->prepare("SELECT org_id FROM organizations WHERE org_email = ? AND org_id != ?");
        $checkEmail->bind_param("ss", $email, $org_id);
        $checkEmail->execute();
        $checkEmail->store_result();

        // Check if new capacity is less than currently occupied space
        $checkCap = $conn->prepare("SELECT occupied_kg FROM organizations WHERE org_id = ?");
        $checkCap->bind_param("s", $org_id);
        $checkCap->execute();
        $capData = $checkCap->get_result()->fetch_assoc();
        $occupied = (float)$capData['occupied_kg'];
        $checkCap->close();

        if ($checkEmail->num_rows > 0) {
            $message = "This email is already in use by another organization.";
            $messageType = "error";
        } elseif ($capacity < $occupied) {
            $message = "Capacity cannot be less than your currently occupied space (" . number_format($occupied, 1) . " kg).";
            $messageType = "error";
        } else {
            $checkEmail->close();

            // Update the database
            if (!empty($new_password)) {
                // Update with a new password
                $stmt = $conn->prepare("UPDATE organizations SET org_name=?, org_email=?, org_address=?, capacity_kg=?, accepted_materials=?, org_password=? WHERE org_id=?");
                $stmt->bind_param("sssdsss", $name, $email, $address, $capacity, $materialsJson, $new_password, $org_id);
            } else {
                // Update without changing the password
                $stmt = $conn->prepare("UPDATE organizations SET org_name=?, org_email=?, org_address=?, capacity_kg=?, accepted_materials=? WHERE org_id=?");
                $stmt->bind_param("sssdss", $name, $email, $address, $capacity, $materialsJson, $org_id);
            }

            if ($stmt->execute()) {
                // Update session name so the dashboard greeting changes immediately
                $_SESSION['org_name'] = $name;
                
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
$stmt = $conn->prepare("SELECT org_name, org_email, org_address, capacity_kg, accepted_materials FROM organizations WHERE org_id = ?");
$stmt->bind_param("s", $org_id);
$stmt->execute();
$orgData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Convert the database JSON string back into a PHP array for checkboxes
$currentMaterials = json_decode($orgData['accepted_materials'] ?? '[]', true);
if (!is_array($currentMaterials)) {
    $currentMaterials = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Organisation Settings</title>
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
    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; max-width: 800px; }
    
    .form-group { margin-bottom: 20px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.9rem; font-weight: 500; margin-bottom: 8px; color: var(--text-dark); }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; transition: border 0.2s; background: #fff; }
    .form-control:focus { border-color: var(--primary-green); }
    
    /* Checkbox Grid */
    .checkbox-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: #fafafa; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); }
    .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer; }
    .checkbox-label input[type="checkbox"] { accent-color: var(--primary-green); width: 16px; height: 16px; }

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
    <a href="org_dashboard.php" class="nav-item">Dashboard</a>
    <a href="org_settings.php" class="nav-item active">Account Settings</a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="header">
      <h1>Organisation Settings</h1>
      <div class="header-right">
        <span><?php echo htmlspecialchars($orgData['org_email'] ?? ''); ?></span>
        <a href="../logout.php" class="btn-signout">Log out</a>
      </div>
    </div>

    <div class="panel">
      <?php if($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <form method="POST" action="org_settings.php">
        
        <!-- Pre-filled Profile Info -->
        <div class="form-row">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Organisation Name</label>
            <input type="text" name="org_name" class="form-control" value="<?php echo htmlspecialchars($orgData['org_name'] ?? ''); ?>" required>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Contact Email</label>
            <input type="email" name="org_email" class="form-control" value="<?php echo htmlspecialchars($orgData['org_email'] ?? ''); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Address / Postcode</label>
            <input type="text" name="org_address" class="form-control" value="<?php echo htmlspecialchars($orgData['org_address'] ?? ''); ?>" required>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Total Capacity (kg)</label>
            <input type="number" step="0.1" name="capacity_kg" class="form-control" value="<?php echo htmlspecialchars($orgData['capacity_kg'] ?? 0); ?>" required min="1">
          </div>
        </div>

        <!-- Accepted Materials Section -->
        <div class="section-divider">
          <div class="section-title">Accepted Materials</div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">Select the materials your organization accepts. Donors will only see your location if they are donating these items.</p>
          
          <div class="checkbox-grid">
            <?php foreach($availableMaterials as $material): ?>
              <label class="checkbox-label">
                <input type="checkbox" name="materials[]" value="<?php echo htmlspecialchars($material); ?>" 
                  <?php echo in_array($material, $currentMaterials) ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($material); ?>
              </label>
            <?php endforeach; ?>
          </div>
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