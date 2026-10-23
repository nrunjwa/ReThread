<?php
require_once '../session.php';
require_once '../dbconn.php';

// Protect page
if (!isset($_SESSION['donor_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    header("Location: donor_login.php");
    exit;
}

// Fetch organizations directly from the table
$query = "SELECT org_id, org_name, org_address, capacity_kg, occupied_kg, accepted_materials, is_active FROM organizations";
$result = $conn->query($query);
$organizations = [];
while($row = $result->fetch_assoc()) {
    $organizations[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Find Collection Points</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-cream: #faf8f2; --sidebar-bg: #153b2c; --sidebar-text: #e0e7e3;
      --primary-green: #1d4d38; --text-dark: #1a2721; --text-muted: #57655d;
      --border-color: #e1e5e2; --card-bg: #ffffff;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg-cream); display: flex; min-height: 100vh; color: var(--text-dark); }
    
    /* Sidebar Styles */
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

    /* Filter Bar */
    .filter-bar { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; display: flex; gap: 16px; margin-bottom: 30px; }
    .filter-bar input, .filter-bar select { flex: 1; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; }
    .filter-bar input:focus, .filter-bar select:focus { border-color: var(--primary-green); }

    /* Cards Grid */
    .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; display: flex; flex-direction: column; gap: 12px; }
    
    .card-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .card-title { font-family: 'Newsreader', serif; font-size: 1.3rem; font-weight: 600; color: var(--text-dark); }
    .card-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }
    
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .badge-open { background: #d1f2e0; color: #176043; }
    .badge-inactive { background: #e9ecef; color: #495057; }

    /* Progress Bar */
    .progress-container { margin-top: 8px; }
    .progress-text { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 6px; }
    .progress-bg { width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 4px; }
    .bg-green { background: #2b8a59; }
    .bg-red { background: #e03131; }

    /* Card Details */
    .card-details { font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-top: 8px; }
    .card-details strong { color: var(--text-dark); font-weight: 500; }

    /* Button */
    .btn-donate { background: var(--primary-green); color: white; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.95rem; margin-top: 16px; transition: 0.2s; }
    .btn-donate:hover { background: #143a28; }
    .btn-donate.disabled { background: #ccc; pointer-events: none; color: #777; }

    /* Empty State */
    .empty-state { grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-muted); display: none; }
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
    <a href="collection_points.php" class="nav-item active">Find Collection Points</a>
	<a href="donor_settings.php" class="nav-item">Account Settings</a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    
    <div class="header">
      <h1>Find collection points</h1>
      <div class="header-right">
        <span><?php echo htmlspecialchars($_SESSION['donor_name'] ?? 'Donor'); ?></span>
        <a href="../logout.php" class="btn-signout">Sign out</a>
      </div>
    </div>
	
	<!-- Filter Bar -->
    <div class="filter-bar">
      <input type="text" id="filter-address" placeholder="Search by postcode or address...">
      
      <select id="filter-material">
        <option value="">Any Material</option>
        <option value="Cotton">Cotton</option>
        <option value="Denim">Denim</option>
        <option value="Polyester">Polyester</option>
        <option value="Wool">Wool</option>
        <option value="Linen">Linen</option>
        <option value="Mixed Textiles">Mixed Textiles</option>
        <option value="Wearable Clothes">Wearable Clothes</option>
        <option value="Shoes">Shoes</option>
        <option value="Bags">Bags</option>
      </select>

      <select id="filter-condition">
        <option value="">Any condition</option>
        <option value="Wearable">Wearable</option>
        <option value="Lightly Worn">Lightly Worn</option>
        <option value="Damaged">Damaged</option>
      </select>
    </div>

    <!-- CARDS GRID -->
    <div class="cards-grid" id="cards-container">
      <?php foreach($organizations as $org): 
          // Use occupied_kg directly from the database
          $capacity = (float)$org['capacity_kg'];
          $occupied = (float)$org['occupied_kg']; 
          $free = $capacity - $occupied;
          
          // Calculate Percentage (cap at 100% for visual safety)
          $percent = $capacity > 0 ? ($occupied / $capacity) * 100 : 0;
          $visualPercent = min($percent, 100);
          
          $barColor = $percent > 80 ? 'bg-red' : 'bg-green';
          $materialsStr = str_replace(['[', ']', '"'], '', $org['accepted_materials']);
          
          $isActive = $org['is_active'] == 1;
          $badgeClass = $isActive ? 'badge-open' : 'badge-inactive';
          $badgeText = $isActive ? 'Open' : 'Inactive';

          // Prepare data for JS filtering (lowercase for case-insensitive matching)
          $dataAddress = strtolower(htmlspecialchars($org['org_address']));
          $dataMaterials = strtolower(htmlspecialchars($materialsStr));
          // Note: Since conditions are hardcoded in your UI, we add them to the data attribute
          $dataConditions = "wearable, lightly worn, damaged"; 
      ?>
      
      <!-- Card includes data attributes for JavaScript filtering -->
      <div class="card" 
           data-address="<?php echo $dataAddress; ?>"
           data-materials="<?php echo $dataMaterials; ?>"
           data-conditions="<?php echo $dataConditions; ?>">
        
        <div class="card-header">
          <div>
            <div class="card-title"><?php echo htmlspecialchars($org['org_name']); ?></div>
            <div class="card-subtitle">Postcode: <?php echo htmlspecialchars($org['org_address']); ?></div>
          </div>
          <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
        </div>

        <!-- Capacity Bar -->
        <div class="progress-container">
          <div class="progress-text">
            <span><?php echo number_format($occupied, 0); ?> / <?php echo number_format($capacity, 0); ?> kg</span>
            <span><?php echo number_format($free, 0); ?> kg free</span>
          </div>
          <div class="progress-bg">
            <div class="progress-fill <?php echo $barColor; ?>" style="width: <?php echo $visualPercent; ?>%;"></div>
          </div>
        </div>

        <!-- Details -->
        <div class="card-details">
          <div><strong>Materials:</strong> <?php echo htmlspecialchars($materialsStr); ?></div>
          <div><strong>Conditions:</strong> Wearable, Lightly worn, Damaged</div>
        </div>

        <!-- Action Button
        <?php //if($isActive && $free > 0): ?>
            <a href="new_donation.php?org_id=<?php //echo $org['org_id']; ?>" class="btn-donate">Donate here</a>
        <?php //else: ?>
            <a href="#" class="btn-donate disabled">Unavailable (Full)</a>
        <?php //endif; ?>-->
      </div>

      <?php endforeach; ?>
      
      <!-- Fallback message if no cards match the filter -->
      <div class="empty-state" id="empty-state">
        <h3>No collection points found</h3>
        <p>Try adjusting your search filters.</p>
      </div>
    </div>

  </div>

  <!-- FILTERING SCRIPT -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Get filter inputs
        const inputAddress = document.getElementById('filter-address');
        const selectMaterial = document.getElementById('filter-material');
        const selectCondition = document.getElementById('filter-condition');
        
        // Get all cards and empty state message
        const cards = document.querySelectorAll('.card');
        const emptyState = document.getElementById('empty-state');

        function filterCards() {
            // Get current values and convert to lowercase for comparison
            const addressValue = inputAddress.value.toLowerCase().trim();
            const materialValue = selectMaterial.value.toLowerCase();
            const conditionValue = selectCondition.value.toLowerCase();

            let visibleCount = 0;

            cards.forEach(card => {
                // Get the data attributes from the card
                const cardAddress = card.getAttribute('data-address');
                const cardMaterials = card.getAttribute('data-materials');
                const cardConditions = card.getAttribute('data-conditions');

                // Check if card matches all active filters
                // If the filter value is empty, we treat it as a "match"
                const matchAddress = addressValue === "" || cardAddress.includes(addressValue);
                const matchMaterial = materialValue === "" || cardMaterials.includes(materialValue);
                const matchCondition = conditionValue === "" || cardConditions.includes(conditionValue);

                if (matchAddress && matchMaterial && matchCondition) {
                    card.style.display = 'flex'; // Show card
                    visibleCount++;
                } else {
                    card.style.display = 'none'; // Hide card
                }
            });

            // Show or hide the "No results" message
            if (visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        // Attach event listeners to trigger filtering on user input
        inputAddress.addEventListener('input', filterCards);
        selectMaterial.addEventListener('change', filterCards);
        selectCondition.addEventListener('change', filterCards);
    });
  </script>
</body>
</html>