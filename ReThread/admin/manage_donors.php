<?php
require_once '../session.php';
require_once '../dbconn.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Handle Activate/Deactivate
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $conn->query("UPDATE donors SET is_active = NOT is_active WHERE donor_id = $id");
    header("Location: manage_donors.php");
    exit;
}

// Handle Search
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM donors";
if ($search) {
    $safeSearch = $conn->real_escape_string($search);
    $query .= " WHERE donor_name LIKE '%$safeSearch%' OR donor_email LIKE '%$safeSearch%'";
}
$query .= " ORDER BY donor_id DESC";
$donors = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Donors</title>
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
    
    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; }
    
    .search-bar { padding: 10px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.9rem; width: 300px; outline: none; margin-bottom: 20px; }
    
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .data-table th { text-align: left; padding: 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-weight: 500; }
    .data-table td { padding: 14px 12px; border-bottom: 1px solid var(--border-color); }
    
    .status-active { color: #176043; font-weight: 600; }
    .status-inactive { color: #c92a2a; font-weight: 600; }
    
    .btn { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; cursor: pointer; border: none; }
    .btn-deactivate { background: #ffe3e3; color: #c92a2a; }
    .btn-activate { background: #d1f2e0; color: #176043; }
    .btn-view { background: #e0e7ff; color: #3730a3; margin-right: 8px; }
    
    /* Modal Styles */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-content { background: white; width: 100%; max-width: 600px; border-radius: 12px; padding: 30px; position: relative; max-height: 90vh; overflow-y: auto; }
    .close-btn { position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); background: none; border: none; }
    .close-btn:hover { color: #c92a2a; }
    
    .modal-header { margin-bottom: 20px; }
    .modal-header h2 { font-family: 'Newsreader', serif; font-size: 1.8rem; color: var(--primary-green); }
    .modal-details { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.9rem; margin-bottom: 24px; background: #f4f7f5; padding: 16px; border-radius: 8px; }
    
    .modal-history h3 { font-family: 'Newsreader', serif; font-size: 1.2rem; margin-bottom: 12px; }
  </style>
</head>
<body>
  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="admin_dashboard.php" class="nav-item">Dashboard</a>
    <a href="manage_donors.php" class="nav-item active">Manage Donors</a>
    <a href="manage_organisations.php" class="nav-item">Manage Organisations</a>
    <a href="../logout.php" class="nav-item" style="margin-top: auto;">Logout</a>
  </div>

  <div class="main-content">
    <div class="header">
      <h1>Manage Donors</h1>
    </div>
    
    <div class="panel">
      <form method="GET" action="manage_donors.php">
        <input type="text" name="search" class="search-bar" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-activate" style="background:var(--primary-green); color:white;">Search</button>
        <?php if($search): ?>
            <a href="manage_donors.php" class="btn" style="color:var(--text-muted);">Clear</a>
        <?php endif; ?>
      </form>
      <br>

      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if($donors->num_rows > 0): ?>
            <?php while($row = $donors->fetch_assoc()): ?>
            <tr>
              <td><?php echo $row['donor_id']; ?></td>
              <td style="font-weight:500;"><?php echo htmlspecialchars($row['donor_name']); ?></td>
              <td><?php echo htmlspecialchars($row['donor_email']); ?></td>
              <td><?php echo htmlspecialchars($row['donor_phone']); ?></td>
              <td class="<?php echo $row['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
              </td>
              <td>
                <button class="btn btn-view" onclick="viewDonor(<?php echo $row['donor_id']; ?>)">View Profile</button>

                <a href="?toggle_id=<?php echo $row['donor_id']; ?>" class="btn <?php echo $row['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?>">
                  <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No donors found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- THE POPUP MODAL -->
  <div class="modal-overlay" id="donorModal">
    <div class="modal-content">
      <button class="close-btn" onclick="closeModal()">&times;</button>
      <div class="modal-header">
        <h2 id="modalName">Donor Name</h2>
        <span id="modalStatus" class="status-active">Active</span>
      </div>
      
      <div class="modal-details">
        <div><strong>Email:</strong> <br><span id="modalEmail"></span></div>
        <div><strong>Phone:</strong> <br><span id="modalPhone"></span></div>
        <div style="grid-column: span 2;"><strong>Address:</strong> <br><span id="modalAddress"></span></div>
      </div>

      <div class="modal-history">
        <h3>Donation History</h3>
        <table class="data-table" id="historyTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Material</th>
                    <th>Kg</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="historyBody">
            </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    const donorsData = <?php 
        $donors->data_seek(0); // Reset pointer
        $jsArray = [];
        while($d = $donors->fetch_assoc()) { $jsArray[] = $d; }
        echo json_encode($jsArray); 
    ?>;

    function viewDonor(id) {
        // Find the donor in our data
        const donor = donorsData.find(d => d.donor_id == id);
        if(!donor) return;

        // Populate the modal fields
        document.getElementById('modalName').innerText = donor.donor_name;
        document.getElementById('modalEmail').innerText = donor.donor_email;
        document.getElementById('modalPhone').innerText = donor.donor_phone;
        document.getElementById('modalAddress').innerText = donor.donor_address;
        
        const statusEl = document.getElementById('modalStatus');
        statusEl.innerText = donor.is_active == 1 ? 'Active' : 'Inactive';
        statusEl.className = donor.is_active == 1 ? 'status-active' : 'status-inactive';

        // Fetch their donation history
        const historyBody = document.getElementById('historyBody');
        historyBody.innerHTML = '<tr><td colspan="4">Loading history...</td></tr>';
        
        fetch('get_donor_history.php?id=' + id)
            .then(response => response.text())
            .then(html => {
                historyBody.innerHTML = html;
            });

        // Show the modal
        document.getElementById('donorModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('donorModal').style.display = 'none';
    }

    // Close modal if clicking outside the box
    window.onclick = function(event) {
        const modal = document.getElementById('donorModal');
        if (event.target == modal) {
            closeModal();
        }
    }
  </script>
</body>
</html>