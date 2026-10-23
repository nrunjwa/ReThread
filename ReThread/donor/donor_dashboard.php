<?php
require_once '../session.php';
require_once '../dbconn.php';

// Protect page
if (!isset($_SESSION['donor_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    header("Location: donor_login.php");
    exit;
}

$donor_id = $_SESSION['donor_id'];

// Fetch donor details
$stmt = $conn->prepare("SELECT donor_email, donor_address FROM donors WHERE donor_id = ?");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$donorData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch Stats for the cards
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total_donations, 
        SUM(weight_kg) as total_weight,
        SUM(CASE WHEN status = 'Completed' THEN weight_kg ELSE 0 END) as reused_weight,
        SUM(CASE WHEN status = 'Recycled' THEN weight_kg ELSE 0 END) as recycled_weight
    FROM donations 
    WHERE donor_id = $donor_id
");
$stats = $statsQuery->fetch_assoc();

$totalDonations = $stats['total_donations'] ?? 0;
$totalWeight = $stats['total_weight'] ?? 0;
$reusedWeight = $stats['reused_weight'] ?? 0;
$recycledWeight = $stats['recycled_weight'] ?? 0;

// Fetch History
$historyQuery = $conn->query("
    SELECT d.donation_id, d.material, d.weight_kg, d.status, d.created_at, o.org_name 
    FROM donations d
    LEFT JOIN organizations o ON d.org_id = o.org_id
    WHERE d.donor_id = $donor_id
    ORDER BY d.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Donor Dashboard</title>
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

    .main-content { flex: 1; padding: 40px; width: calc(100vw - 260px); }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .header h1 { font-family: 'Newsreader', serif; font-size: 2rem; font-weight: 600; }
    .header-right { display: flex; align-items: center; gap: 16px; font-size: 0.9rem; color: var(--text-muted); }
    .btn-signout { border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 500; }

    /* Stat Cards Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; }
    .stat-title { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 12px; display: flex; justify-content: space-between; }
    .stat-value { font-family: 'Newsreader', serif; font-size: 2.5rem; font-weight: 700; }
    .stat-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 8px; }

    /* Table Panel */
    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
    .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .panel h2 { font-family: 'Newsreader', serif; font-size: 1.4rem; }
    .btn-primary { background: var(--primary-green); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .data-table th { text-align: left; padding: 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 500; }
    .data-table td { padding: 16px 12px; border-bottom: 1px solid var(--border-color); }
    
    .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .status-completed { background: #d1f2e0; color: #176043; }
    .status-recycled { background: #cbeef3; color: #19806a; }
    .status-processing { background: #fff3bf; color: #8a6d1c; }
    .status-scheduled { background: #e0e7ff; color: #3730a3; }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="donor_dashboard.php" class="nav-item active">My Donations</a>
    <a href="new_donation.php" class="nav-item">New Donation</a>    
	<a href="collection_points.php" class="nav-item">Find Collection Points</a>
	<a href="donor_settings.php" class="nav-item">Account Settings</a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <!-- HEADER -->
    <div class="header">
      <h1>Hi, <?php echo htmlspecialchars($_SESSION['donor_name']); ?></h1>
      <div class="header-right">
        <span><?php echo htmlspecialchars($donorData['donor_email'] ?? ''); ?></span>
        <a href="../logout.php" class="btn-signout">Log out</a>
      </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-title">Total Donations <span>📦</span></div>
        <div class="stat-value"><?php echo $totalDonations; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Total Weight <span>⚖️</span></div>
        <div class="stat-value"><?php echo number_format($totalWeight, 1); ?> <span style="font-size:1.2rem">kg</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Verified Reuse <span>👕</span></div>
        <div class="stat-value"><?php echo number_format($reusedWeight, 1); ?> <span style="font-size:1.2rem">kg</span></div>
        <div class="stat-sub">Confirmed by organization</div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Verified Recycling <span>♻️</span></div>
        <div class="stat-value"><?php echo number_format($recycledWeight, 1); ?> <span style="font-size:1.2rem">kg</span></div>
      </div>
    </div>

    <!-- DONATION HISTORY TABLE -->
    <div class="panel">
      <div class="panel-header">
        <h2>Donation history</h2>
      </div>
      
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Material</th>
            <th>Weight</th>
            <th>Collection Point</th>
            <th>Status</th>
            <th>Outcome</th>
          </tr>
        </thead>
        <tbody>
          <?php if($historyQuery->num_rows > 0): ?>
            <?php while($row = $historyQuery->fetch_assoc()): 
                // Determine Outcome text based on status
                $outcome = "Pending";
                if($row['status'] == 'Completed') $outcome = "♻️ Reused";
                if($row['status'] == 'Recycled') $outcome = "♻️ Recycled";
            ?>
              <tr>
                <td style="color:var(--primary-green); font-weight:600;">D-<?php echo $row['donation_id']; ?></td>
                <td><?php echo htmlspecialchars($row['material']); ?></td>
                <td><?php echo htmlspecialchars($row['weight_kg']); ?> kg</td>
                <td><?php echo htmlspecialchars($row['org_name'] ?? 'Unassigned'); ?></td>
                <td>
                  <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                    <?php echo htmlspecialchars($row['status']); ?>
                  </span>
                </td>
                <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $outcome; ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">You haven't made any donations yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</body>
</html>