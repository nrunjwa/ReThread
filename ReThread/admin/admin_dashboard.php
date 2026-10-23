<?php
require_once '../session.php';
require_once '../dbconn.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// --- 1. FETCH STATS ---
$donorCount = $conn->query("SELECT COUNT(*) AS c FROM donors")->fetch_assoc()['c'] ?? 0;
$totalDonations = $conn->query("SELECT COUNT(*) AS c FROM donations")->fetch_assoc()['c'] ?? 0;
$totalWeight = $conn->query("SELECT SUM(weight_kg) AS total FROM donations")->fetch_assoc()['total'] ?? 0;
$pendingWeight = $conn->query("SELECT SUM(weight_kg) AS total FROM donations WHERE status IN ('Scheduled', 'Processing')")->fetch_assoc()['total'] ?? 0;

// --- 2. FETCH CHART 1: Materials ---
$materialData = []; $materialLabels = [];
$chartQuery = $conn->query("SELECT material, SUM(weight_kg) as total FROM donations GROUP BY material");
while($row = $chartQuery->fetch_assoc()) {
    $materialLabels[] = $row['material']; $materialData[] = $row['total'];
}

// --- 3. FETCH CHART 2: Actions Breakdown ---
$actionQuery = $conn->query("SELECT status, SUM(weight_kg) as weight FROM donations GROUP BY status");
$actionReuse = 0; $actionRecycle = 0; $actionPending = 0;
while($row = $actionQuery->fetch_assoc()) {
    if ($row['status'] == 'Completed') $actionReuse += $row['weight'];
    elseif ($row['status'] == 'Recycled') $actionRecycle += $row['weight'];
    else $actionPending += $row['weight'];
}

// --- 4. FETCH ORGANIZATIONS CAPACITY ---
$orgQuery = $conn->query("SELECT org_name, capacity_kg, occupied_kg, is_active FROM organizations LIMIT 4");

// --- 5. FETCH FILTERS & RECENT DONATIONS ---
$filterOrg = $_GET['filter_org'] ?? '';
$filterMaterial = $_GET['filter_material'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';

$sql = "SELECT d.donation_id, u.donor_name, o.org_name, d.material, d.weight_kg, d.status, d.created_at 
        FROM donations d
        LEFT JOIN donors u ON d.donor_id = u.donor_id
        LEFT JOIN organizations o ON d.org_id = o.org_id
        WHERE 1=1";

if ($filterOrg) { $sql .= " AND d.org_id = '".$conn->real_escape_string($filterOrg)."'"; }
if ($filterMaterial) { $sql .= " AND d.material = '".$conn->real_escape_string($filterMaterial)."'"; }
if ($filterStatus) { $sql .= " AND d.status = '".$conn->real_escape_string($filterStatus)."'"; }
$sql .= " ORDER BY d.created_at DESC";
$recentDonations = $conn->query($sql);

// Dropdown Options
$orgOptions = $conn->query("SELECT org_id, org_name FROM organizations");
$materialOptions = $conn->query("SELECT DISTINCT material FROM donations WHERE material IS NOT NULL");
$statusOptions = ['Scheduled', 'Processing', 'Completed', 'Recycled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg-cream: #faf8f2; --sidebar-bg: #153b2c; --sidebar-text: #e0e7e3;
      --primary-green: #1d4d38; --accent-green: #2e8b57;
      --text-dark: #1a2721; --text-muted: #57655d;
      --border-color: #e1e5e2; --card-bg: #ffffff;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg-cream); display: flex; min-height: 100vh; color: var(--text-dark); }
    
    .sidebar { width: 260px; flex-shrink: 0; background: var(--sidebar-bg); color: white; padding: 24px; display: flex; flex-direction: column; }
    .brand-logo { font-family: 'Newsreader', serif; font-size: 1.6rem; font-weight: 700; color: white; text-decoration: none; margin-bottom: 40px; display: flex; align-items: center; gap: 8px; }
    .nav-item { padding: 12px 16px; border-radius: 8px; color: var(--sidebar-text); text-decoration: none; font-size: 0.95rem; margin-bottom: 8px; transition: 0.2s; }
    .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.1); color: white; }
    
    /* Fixed Main Content Area */
    .main-content { flex: 1; padding: 40px; width: calc(100vw - 260px); overflow-x: hidden; }
    
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .header h1 { font-family: 'Newsreader', serif; font-size: 2rem; font-weight: 600; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
    .stat-title { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; display: flex; justify-content: space-between; }
    .stat-value { font-family: 'Newsreader', serif; font-size: 2rem; font-weight: 700; }
    .stat-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }

    /* Grid layout that adjusts to screen size */
    .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; margin-bottom: 24px; width: 100%; }

    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; width: 100%; }
    .panel h2 { font-family: 'Newsreader', serif; font-size: 1.3rem; margin-bottom: 20px; }
    
    .org-item { margin-bottom: 16px; }
    .org-header { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500; align-items: center;}
    .progress-bar { height: 8px; background: #e1e5e2; border-radius: 4px; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--primary-green); border-radius: 4px; }
    .progress-fill.danger { background: #c92a2a; }
    .badge-active { background: #f4f7f5; border: 1px solid var(--border-color); padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; color: var(--text-muted); }

    .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .data-table th { text-align: left; padding: 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 500; }
    .data-table td { padding: 14px 12px; border-bottom: 1px solid var(--border-color); }
    
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .status-completed { background: #d1f2e0; color: #176043; }
    .status-recycled { background: #cbeef3; color: #19806a; }
    .status-processing { background: #fff3bf; color: #8a6d1c; }
    .status-scheduled { background: #e0e7ff; color: #3730a3; }

    .filters { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-select { padding: 10px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.9rem; color: var(--text-dark); background: white; width: 100%; max-width: 250px; outline: none; cursor: pointer; }
    
    /* Fixed height for chart containers to avoid stretching */
    .chart-container { position: relative; height: 300px; width: 100%; }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="admin_dashboard.php" class="nav-item active">Dashboard</a>
    <a href="manage_donors.php" class="nav-item">Manage Donors</a>
    <a href="manage_organizations.php" class="nav-item">Manage Organisations</a>
    <a href="../logout.php" class="nav-item" style="margin-top: auto;">Logout</a>
  </div>

  <div class="main-content">
    <div class="header">
      <h1>Admin dashboard</h1>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-title">Total donations <span>📦</span></div>
        <div class="stat-value"><?php echo $totalDonations; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Weight collected <span>⚖️</span></div>
        <div class="stat-value"><?php echo number_format($totalWeight, 1); ?> <span style="font-size:1rem">kg</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Total Donors <span>👤</span></div>
        <div class="stat-value"><?php echo $donorCount; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Pending outcome <span>⏳</span></div>
        <div class="stat-value"><?php echo number_format($pendingWeight, 1); ?> <span style="font-size:1rem">kg</span></div>
        <div class="stat-sub">Not yet verified</div>
      </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="grid-2">
      <div class="panel">
        <h2>Donations by material (kg)</h2>
        <div class="chart-container">
            <canvas id="materialChart"></canvas>
        </div>
      </div>
      
      <div class="panel">
        <h2>Donation Actions Breakdown (kg)</h2>
        <div class="chart-container">
            <canvas id="actionChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Organisation & Recent Activity -->
    <div class="grid-2">
      <div class="panel">
        <h2>Organisations & capacity</h2>
        <?php while($org = $orgQuery->fetch_assoc()): 
            $capacity = $org['capacity_kg'] > 0 ? $org['capacity_kg'] : 1;
            $percentage = ($org['occupied_kg'] / $capacity) * 100;
            $isFull = $percentage > 85 ? 'danger' : '';
        ?>
            <div class="org-item">
              <div class="org-header">
                  <span><?php echo htmlspecialchars($org['org_name']); ?></span> 
                  <span style="display:flex; align-items:center; gap:8px;">
                      <?php echo htmlspecialchars($org['occupied_kg']); ?>/<?php echo htmlspecialchars($org['capacity_kg']); ?> kg
                      <span class="badge-active">Active</span>
                  </span>
              </div>
              <div class="progress-bar">
                  <div class="progress-fill <?php echo $isFull; ?>" style="width: <?php echo $percentage; ?>%;"></div>
              </div>
            </div>
        <?php endwhile; ?>
      </div>

      <div class="panel">
        <h2>Recent activity</h2>
        <table class="data-table" style="margin-top: -10px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Donation</th>
                    <th>Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $activityQuery = $conn->query("
                    SELECT d.donation_id, d.weight_kg, d.status, d.created_at, u.donor_name 
                    FROM donations d LEFT JOIN donors u ON d.donor_id = u.donor_id 
                    ORDER BY d.created_at DESC LIMIT 5
                ");
                while($act = $activityQuery->fetch_assoc()): 
                ?>
                <tr>
                    <td style="color:var(--text-muted); font-size: 0.85rem;"><?php echo date("m/d/y", strtotime($act['created_at'])); ?></td>
                    <td style="font-weight:600; color:var(--primary-green);">D-<?php echo $act['donation_id']; ?></td>
                    <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($act['donor_name']); ?> (<?php echo $act['weight_kg']; ?>kg)</td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($act['status']); ?>">
                            <?php echo htmlspecialchars($act['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
      </div>
    </div>

    <div class="panel" style="margin-top: 24px;">
      <h2>All donations</h2>
      
      <form method="GET" action="admin_dashboard.php" class="filters" id="filterForm">
        <select name="filter_org" class="filter-select" onchange="document.getElementById('filterForm').submit()">
            <option value="">All organisations</option>
            <?php while($org = $orgOptions->fetch_assoc()): ?>
                <option value="<?php echo $org['org_id']; ?>" <?php echo ($filterOrg == $org['org_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($org['org_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <select name="filter_material" class="filter-select" onchange="document.getElementById('filterForm').submit()">
            <option value="">All materials</option>
            <?php while($mat = $materialOptions->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($mat['material']); ?>" <?php echo ($filterMaterial == $mat['material']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($mat['material']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <select name="filter_status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
            <option value="">All statuses</option>
            <?php foreach($statusOptions as $status): ?>
                <option value="<?php echo $status; ?>" <?php echo ($filterStatus == $status) ? 'selected' : ''; ?>>
                    <?php echo $status; ?>
                </option>
            <?php endforeach; ?>
        </select>
      </form>

      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Donor</th>
            <th>Organisation</th>
            <th>Material</th>
            <th>Kg</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if($recentDonations->num_rows > 0): ?>
            <?php while($row = $recentDonations->fetch_assoc()): ?>
              <tr>
                <td style="color: var(--primary-green); font-weight:600;">D-<?php echo $row['donation_id']; ?></td>
                <td><?php echo htmlspecialchars($row['donor_name'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($row['org_name'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($row['material']); ?></td>
                <td><?php echo htmlspecialchars($row['weight_kg']); ?></td>
                <td>
                  <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                    <?php echo htmlspecialchars($row['status']); ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No donations found matching your filters.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <script>
    // Chart 1
    new Chart(document.getElementById('materialChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($materialLabels); ?>,
        datasets: [{ data: <?php echo json_encode($materialData); ?>, backgroundColor: '#1d4d38', borderRadius: 4, barThickness: 40 }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
    });

    // Chart 2
    new Chart(document.getElementById('actionChart').getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Reuse (Completed)', 'Recycling', 'Pending'],
        datasets: [{
          data: [<?php echo $actionReuse; ?>, <?php echo $actionRecycle; ?>, <?php echo $actionPending; ?>],
          backgroundColor: ['#1d4d38', '#2e8b57', '#eab308'],
          borderWidth: 0, hoverOffset: 4
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, font: {size: 12} } } }
      }
    });
  </script>
</body>
</html>