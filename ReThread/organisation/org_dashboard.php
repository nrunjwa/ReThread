<?php
require_once '../session.php';
require_once '../dbconn.php';

// Protect page
if (!isset($_SESSION['org_id']) || ($_SESSION['role'] ?? '') !== 'organization') {
    header("Location: org_login.php");
    exit;
}

$org_id = $_SESSION['org_id'];
$message = "";

// Handle Deactivate/Activate Toggle
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['toggle_status'])) {
    $newStatus = $_POST['current_status'] == 1 ? 0 : 1;
    $stmt = $conn->prepare("UPDATE organizations SET is_active = ? WHERE org_id = ?");
    $stmt->bind_param("is", $newStatus, $org_id);
    $stmt->execute();
    $stmt->close();
    header("Location: org_dashboard.php");
    exit;
}

// Handle Mark as Picked Up
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mark_picked_up'])) {
    $donation_id = $_POST['donation_id'];
    
    // Update Donation status
    $stmt = $conn->prepare("UPDATE donations SET status = 'Completed' WHERE donation_id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $stmt->close();

    // Update Pickup status
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Completed', completed_at = NOW() WHERE donation_id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: org_dashboard.php");
    exit;
}

// Fetch Organization Details
$stmt = $conn->prepare("SELECT org_name, capacity_kg, occupied_kg, accepted_materials, is_active FROM organizations WHERE org_id = ?");
$stmt->bind_param("s", $org_id);
$stmt->execute();
$org = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch Stats
$statsQuery = $conn->prepare("
    SELECT 
        COUNT(donation_id) AS total_donations,
        SUM(CASE WHEN status IN ('Completed', 'Processing', 'Recycled') THEN weight_kg ELSE 0 END) AS verified_received,
        SUM(CASE WHEN status = 'Completed' THEN weight_kg ELSE 0 END) AS total_reused,
        SUM(CASE WHEN status = 'Recycled' THEN weight_kg ELSE 0 END) AS total_recycled
    FROM donations 
    WHERE org_id = ?
");
$statsQuery->bind_param("s", $org_id);
$statsQuery->execute();
$stats = $statsQuery->get_result()->fetch_assoc();
$statsQuery->close();

// Fetch Donations List
$donationsQuery = $conn->prepare("
    SELECT d.donation_id, d.material, d.condition, d.weight_kg, d.status, 
           u.donor_name, p.pickup_type
    FROM donations d
    LEFT JOIN donors u ON d.donor_id = u.donor_id
    LEFT JOIN pickups p ON d.donation_id = p.donation_id
    WHERE d.org_id = ?
    ORDER BY d.created_at DESC
");
$donationsQuery->bind_param("s", $org_id);
$donationsQuery->execute();
$donations = $donationsQuery->get_result();

// Format materials
$cleanMaterials = str_replace(['[', ']', '"'], '', $org['accepted_materials']);

// Calculate capacity percentage
$percentUsed = $org['capacity_kg'] > 0 ? ($org['occupied_kg'] / $org['capacity_kg']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Organisation Dashboard</title>
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
    .main-content { flex: 1; padding: 40px; width: calc(100vw - 260px); overflow-y: auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .header h1 { font-family: 'Newsreader', serif; font-size: 1.8rem; font-weight: 600; }
    .header-right { display: flex; align-items: center; gap: 16px; font-size: 0.9rem; color: var(--text-muted); }
    .btn-signout { border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 500; background: #fff; }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
    .stat-title { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;}
    .stat-value { font-family: 'Newsreader', serif; font-size: 2.2rem; font-weight: 700; color: var(--text-dark); }
    .stat-value span { font-size: 1.2rem; font-family: 'Inter', sans-serif; font-weight: 500; color: var(--text-muted); }

    /* Capacity Panel */
    .capacity-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .capacity-info { flex: 1; max-width: 600px; }
    .capacity-header { display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 500; margin-bottom: 8px; }
    .progress-bg { width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
    .progress-fill { height: 100%; background: var(--primary-green); border-radius: 4px; }
    .capacity-sub { font-size: 0.85rem; color: var(--text-muted); }
    .capacity-actions { display: flex; align-items: center; gap: 16px; margin-left: 20px; }
    .btn-toggle { background: #fff; border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; font-size: 0.9rem; }
    
    /* Donations Panel */
    .donations-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; }
    .donations-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .donations-header h2 { font-family: 'Newsreader', serif; font-size: 1.4rem; }
    
    /* Toggle Group */
    .toggle-group { background: #f1f3f5; border-radius: 8px; display: flex; padding: 4px; }
    .toggle-btn { background: transparent; border: none; padding: 6px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); cursor: pointer; transition: 0.2s;}
    .toggle-btn.active { background: #fff; color: var(--text-dark); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

    /* Donation Item */
    .donation-item { border: 1px solid var(--border-color); border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 12px; }
    .donation-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .donation-details { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; color: var(--text-muted); flex-wrap: wrap;}
    .donation-id { color: var(--primary-green); font-weight: 600; font-size: 1rem; text-decoration: underline; }
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .badge-scheduled { background: #e0e7ff; color: #3730a3; }
    .badge-completed { background: #d1f2e0; color: #176043; }
    .badge-processing { background: #fff3bf; color: #8a6d1c; }
    
    .btn-action { background: #fff; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 0.85rem; cursor: pointer; color: var(--text-dark); align-self: flex-start; }
    .btn-action:hover { background: #f8f9fa; }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="org_dashboard.php" class="nav-item active">Dashboard</a>
	<a href="org_settings.php" class="nav-item">Account Settings</a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    
    <!-- Header -->
    <div class="header">
      <h1><?php echo htmlspecialchars($org['org_name']); ?></h1>
      <div class="header-right">
        <span><?php echo htmlspecialchars($org['org_name']); ?> Staff</span>
        <a href="../logout.php" class="btn-signout">Sign out</a>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-title">Assigned donations <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></div>
        <div class="stat-value"><?php echo $stats['total_donations'] ?? 0; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Verified received <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="stat-value"><?php echo number_format($stats['verified_received'] ?? 0, 1); ?> <span>kg</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Reused <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.38 3.46L16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg></div>
        <div class="stat-value"><?php echo number_format($stats['total_reused'] ?? 0, 1); ?> <span>kg</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Recycled <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 19H4.815a1.83 1.83 0 0 1-1.57-.881 1.785 1.785 0 0 1-.004-1.784L7.196 9.5"/><path d="M11 19h8.203a1.83 1.83 0 0 0 1.556-.89 1.784 1.784 0 0 0 0-1.775l-1.226-2.12"/><path d="m14 16-3 3 3 3"/><path d="M8.293 13.596L7.196 9.5 3.1 10.598"/><path d="m9.344 5.811 1.093-1.892A1.83 1.83 0 0 1 11.985 3a1.784 1.784 0 0 1 1.546.888l3.943 6.843"/><path d="m13.378 9.633 4.096 1.098 1.097-4.096"/></svg></div>
        <div class="stat-value"><?php echo number_format($stats['total_recycled'] ?? 0, 1); ?> <span>kg</span></div>
      </div>
    </div>

    <!-- Capacity Utilisation -->
    <div class="capacity-panel">
      <div class="capacity-info">
        <div class="capacity-header">
          <span>Capacity utilisation</span>
          <span><?php echo number_format($org['occupied_kg'], 0); ?> / <?php echo number_format($org['capacity_kg'], 0); ?> kg</span>
        </div>
        <div class="progress-bg">
          <div class="progress-fill" style="width: <?php echo min($percentUsed, 100); ?>%;"></div>
        </div>
        <div class="capacity-sub">
          Accepts <?php echo htmlspecialchars($cleanMaterials); ?> · Wearable, Lightly worn, Damaged
        </div>
      </div>
      
      <div class="capacity-actions">
        <span style="font-size: 0.9rem;"><?php echo $org['is_active'] ? 'Accepting donations' : 'Not accepting'; ?></span>
        <form method="POST" style="margin:0;">
          <input type="hidden" name="current_status" value="<?php echo $org['is_active']; ?>">
          <button type="submit" name="toggle_status" class="btn-toggle">
            <?php echo $org['is_active'] ? 'Deactivate' : 'Activate'; ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Donations List -->
    <div class="donations-panel">
      <div class="donations-header">
        <h2>Donations</h2>
        <div class="toggle-group">
          <!-- Added ID and onclick events -->
          <button type="button" class="toggle-btn active" id="btn-active" onclick="filterDonations('active')">Active</button>
          <button type="button" class="toggle-btn" id="btn-all" onclick="filterDonations('all')">All</button>
        </div>
      </div>

      <div id="donations-container">
        <?php if($donations->num_rows > 0): ?>
          <?php while($row = $donations->fetch_assoc()): 
            $statusClass = 'badge-scheduled';
            if($row['status'] == 'Completed') $statusClass = 'badge-completed';
            if($row['status'] == 'Processing') $statusClass = 'badge-processing';
          ?>
            <!-- Added data-status attribute to allow JS filtering -->
            <div class="donation-item" data-status="<?php echo htmlspecialchars($row['status']); ?>">
              <div class="donation-top">
                <div class="donation-details">
                  <span class="donation-id">D-<?php echo $row['donation_id']; ?></span>
                  <span><?php echo htmlspecialchars($row['material']); ?> · <?php echo htmlspecialchars($row['condition']); ?> · est. <?php echo htmlspecialchars($row['weight_kg']); ?> kg</span>
                  <span style="color: #adb5bd;">by</span>
                  <span><?php echo htmlspecialchars($row['donor_name'] ?? 'Unknown'); ?></span>
                  <span style="color: #adb5bd;">·</span>
                  <span><?php echo htmlspecialchars($row['pickup_type'] ?? 'Collection'); ?></span>
                </div>
                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
              </div>
              
              <?php if($row['status'] == 'Scheduled'): ?>
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="donation_id" value="<?php echo $row['donation_id']; ?>">
                  <button type="submit" name="mark_picked_up" class="btn-action">Mark picked up</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p style="color: var(--text-muted); font-size: 0.9rem;">No donations assigned yet.</p>
        <?php endif; ?>
      </div>
      
      <!-- Fallback if Active list is empty -->
      <p id="no-active-msg" style="display:none; color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 20px 0;">No active donations right now. Click 'All' to see history.</p>

    </div>

  </div>

  <script>
    function filterDonations(type) {
        const items = document.querySelectorAll('.donation-item');
        const btnActive = document.getElementById('btn-active');
        const btnAll = document.getElementById('btn-all');
        const noActiveMsg = document.getElementById('no-active-msg');
        
        let visibleCount = 0;

        // Update Button Styles
        if (type === 'active') {
            btnActive.classList.add('active');
            btnAll.classList.remove('active');
        } else {
            btnAll.classList.add('active');
            btnActive.classList.remove('active');
        }

        // Filter Items
        items.forEach(item => {
            const status = item.getAttribute('data-status');
            
            // If "All" is clicked, show everything
            if (type === 'all') {
                item.style.display = 'flex';
                visibleCount++;
            } 
            // If "Active" is clicked, only show Scheduled or Processing
            else {
                if (status === 'Scheduled' || status === 'Processing') {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            }
        });

        // Show a message if Active tab has no items
        if (type === 'active' && visibleCount === 0) {
            noActiveMsg.style.display = 'block';
        } else {
            noActiveMsg.style.display = 'none';
        }
    }

    // Run on page load to default to "Active" view
    window.addEventListener('DOMContentLoaded', () => {
        filterDonations('active');
    });
  </script>
</body>
</html>