<?php
require_once '../session.php';
require_once '../dbconn.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Handle Activate/Deactivate
if (isset($_GET['toggle_id'])) {
    $id = $conn->real_escape_string($_GET['toggle_id']);
    $conn->query("UPDATE organizations SET is_active = NOT is_active WHERE org_id = '$id'");
    header("Location: manage_organisations.php");
    exit;
}

// Handle Add New Organization
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_org'])) {
    $org_id = $conn->real_escape_string($_POST['org_id']);
    $name = $conn->real_escape_string($_POST['org_name']);
    $address = $conn->real_escape_string($_POST['org_address']);
    $capacity = (float)$_POST['capacity_kg'];
    
    $sql = "INSERT INTO organizations (org_id, org_name, org_address, capacity_kg, occupied_kg, is_active) 
            VALUES ('$org_id', '$name', '$address', '$capacity', 0, 1)";
            
    if ($conn->query($sql)) {
        $message = "<div style='color:#176043; margin-bottom:15px; font-weight:bold;'>Organisation added successfully!</div>";
    } else {
        $message = "<div style='color:#c92a2a; margin-bottom:15px; font-weight:bold;'>Error: " . $conn->error . "</div>";
    }
}

$orgs = $conn->query("SELECT * FROM organizations ORDER BY org_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Organisations</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-cream: #faf8f2; --sidebar-bg: #153b2c; --sidebar-text: #e0e7e3;
      --primary-green: #1d4d38; --text-dark: #1a2721; --text-muted: #57655d;
      --border-color: #e1e5e2; --card-bg: #ffffff;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg-cream); display: flex; min-height: 100vh; color: var(--text-dark); }
    
    .sidebar { width: 260px; background: var(--sidebar-bg); color: white; padding: 24px; display: flex; flex-direction: column; }
    .brand-logo { font-family: 'Newsreader', serif; font-size: 1.6rem; font-weight: 700; color: white; text-decoration: none; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
    .nav-item { padding: 12px 16px; border-radius: 8px; color: var(--sidebar-text); text-decoration: none; font-size: 0.95rem; margin-bottom: 8px; transition: 0.2s; }
    .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.1); color: white; }

    .main-content { flex: 1; padding: 40px; width: calc(100vw - 260px); overflow-x: hidden; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .header h1 { font-family: 'Newsreader', serif; font-size: 2rem; font-weight: 600; }
    
    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; width: 100%; margin-bottom: 24px;}
    
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .data-table th { text-align: left; padding: 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 500; }
    .data-table td { padding: 14px 12px; border-bottom: 1px solid var(--border-color); }
    
    .status-active { color: #176043; font-weight: 600; }
    .status-inactive { color: #c92a2a; font-weight: 600; }
    
    .btn { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; cursor: pointer; border: none; }
    .btn-deactivate { background: #ffe3e3; color: #c92a2a; }
    .btn-activate { background: #d1f2e0; color: #176043; }
    .btn-primary { background: var(--primary-green); color: white; padding: 10px 16px; font-size: 0.95rem; }
    
    /* Add Form Grid */
    .add-form { display: grid; grid-template-columns: 1fr 2fr 2fr 1fr auto; gap: 12px; margin-bottom: 20px; align-items: start; }
    .input-field { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; outline: none; }
    
    .capacity-warning { color: #c92a2a; font-weight: bold; }
  </style>
</head>
<body>
  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="admin_dashboard.php" class="nav-item">Dashboard</a>
    <a href="manage_donors.php" class="nav-item">Manage Donors</a>
    <a href="manage_organisations.php" class="nav-item active">Manage Organisations</a>
    <a href="../logout.php" class="nav-item" style="margin-top: auto;">Logout</a>
  </div>

  <div class="main-content">
    <div class="header">
      <h1>Manage Organisations</h1>
    </div>
    
    <!--<div class="panel">
      <?php echo $message; ?>
      
       ADD NEW ORGANISATION FORM 
      <h3 style="font-family:'Newsreader', serif; font-size: 1.2rem; margin-bottom: 12px; color: var(--primary-green);">Add New Organisation</h3>
      <form method="POST" action="manage_organisations.php" class="add-form">
        <input type="text" name="org_id" class="input-field" placeholder="ID (e.g. ORG011)" required>
        <input type="text" name="org_name" class="input-field" placeholder="Organisation Name" required>
        <input type="text" name="org_address" class="input-field" placeholder="Address" required>
        <input type="number" name="capacity_kg" class="input-field" placeholder="Capacity (kg)" required>
        <button type="submit" name="add_org" class="btn btn-primary">+ Add</button>
      </form>
    </div>-->
    
    <div class="panel">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Organisation</th>
            <th>Address</th>
            <th>Capacity (kg)</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $orgs->fetch_assoc()): 
            // Highlight if capacity is over 90%
            $capacity = $row['capacity_kg'] > 0 ? $row['capacity_kg'] : 1;
            $percentage = ($row['occupied_kg'] / $capacity) * 100;
            $warningClass = $percentage >= 90 ? 'capacity-warning' : '';
          ?>
          <tr>
            <td style="font-weight:600; color:var(--primary-green);"><?php echo $row['org_id']; ?></td>
            <td style="font-weight:500;"><?php echo htmlspecialchars($row['org_name']); ?></td>
            <td><?php echo htmlspecialchars($row['org_address']); ?></td>
            <td class="<?php echo $warningClass; ?>">
                <?php echo htmlspecialchars($row['occupied_kg']); ?> / <?php echo htmlspecialchars($row['capacity_kg']); ?>
                <?php if($warningClass) echo " ⚠️"; ?>
            </td>
            <td class="<?php echo $row['is_active'] ? 'status-active' : 'status-inactive'; ?>">
              <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
            </td>
            <td>
              <a href="?toggle_id=<?php echo $row['org_id']; ?>" class="btn <?php echo $row['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?>">
                <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>