<?php
require_once '../session.php';
require_once '../dbconn.php';

// Protect page
if (!isset($_SESSION['donor_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    header("Location: donor_login.php");
    exit;
}

$donor_id = $_SESSION['donor_id'];
$message = "";
$messageType = "";

// Fetch donor address to pre-fill the form
$donorStmt = $conn->prepare("SELECT donor_address FROM donors WHERE donor_id = ?");
$donorStmt->bind_param("i", $donor_id);
$donorStmt->execute();
$donorData = $donorStmt->get_result()->fetch_assoc();
$donorStmt->close();
$defaultAddress = $donorData['donor_address'] ?? '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $material = trim($_POST['material']);
    $condition = trim($_POST['condition']);
    $weight = (float)$_POST['weight_kg'];
    $org_id = trim($_POST['org_id']);
    $pickup_type = $_POST['pickup_type'] ?? 'Collection';
    $address = trim($_POST['address']);
    
    if ($weight <= 0) {
        $message = "Please enter a valid weight greater than 0.";
        $messageType = "error";
    } else {
        // Check capacity
        $checkStmt = $conn->prepare("SELECT capacity_kg, occupied_kg FROM organizations WHERE org_id = ?");
        $checkStmt->bind_param("s", $org_id);
        $checkStmt->execute();
        $orgData = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        $freeSpace = $orgData['capacity_kg'] - $orgData['occupied_kg'];

        if ($weight > $freeSpace) {
            $message = "This collection point only has " . number_format($freeSpace, 1) . " kg of free space left.";
            $messageType = "error";
        } else {
            // Insert Donation
            $stmt = $conn->prepare("INSERT INTO donations (donor_id, org_id, material, `condition`, weight_kg, status) VALUES (?, ?, ?, ?, ?, 'Scheduled')");
            $stmt->bind_param("isssd", $donor_id, $org_id, $material, $condition, $weight);
            
            if ($stmt->execute()) {
                $donation_id = $stmt->insert_id; 

                // Insert Pickup
                $pickupStmt = $conn->prepare("INSERT INTO pickups (donation_id, schedule_at, address, status, pickup_type) VALUES (?, NOW(), ?, 'Pending', ?)");
                $pickupStmt->bind_param("iss", $donation_id, $address, $pickup_type);
                $pickupStmt->execute();
                $pickupStmt->close();

                // Update Organization Capacity
                $updateStmt = $conn->prepare("UPDATE organizations SET occupied_kg = occupied_kg + ? WHERE org_id = ?");
                $updateStmt->bind_param("ds", $weight, $org_id);
                $updateStmt->execute();
                $updateStmt->close();

                header("Location: donor_dashboard.php");
                exit;
            } else {
                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch active organizations (Include capacity data for the right-side panel)
$orgs = $conn->query("SELECT org_id, org_name, org_address, capacity_kg, occupied_kg, accepted_materials FROM organizations WHERE is_active = 1 ORDER BY org_name ASC");
$orgList = [];
while($row = $orgs->fetch_assoc()) {
    $row['clean_materials'] = str_replace(['[', ']', '"'], '', $row['accepted_materials']);
    $orgList[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Donation – ReThread</title>
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

    /* Main Layout */
    .main-content { flex: 1; padding: 40px; width: calc(100vw - 260px); }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .header h1 { font-family: 'Newsreader', serif; font-size: 2rem; font-weight: 600; }
    .header-right { display: flex; align-items: center; gap: 16px; font-size: 0.9rem; color: var(--text-muted); }
    .btn-signout { border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 500; }

    /* Grid Layout for Form and Panel */
    .donation-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start; }
    
    /* Form Panel */
    .panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; }
    
    .form-row { display: flex; gap: 16px; margin-bottom: 20px; }
    .form-group { flex: 1; }
    
    .form-label { display: block; font-size: 0.9rem; font-weight: 500; margin-bottom: 8px; color: var(--text-dark); }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; transition: border 0.2s; background: #fff; }
    .form-control:focus { border-color: var(--primary-green); }
    
    /* Radio Buttons */
    .radio-group { display: flex; gap: 16px; margin-bottom: 20px; }
    .radio-label { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; flex: 1; font-size: 0.95rem; }
    .radio-label input[type="radio"] { accent-color: var(--primary-green); }
    
    .btn-submit { background: var(--primary-green); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; width: auto; }
    .btn-submit:hover { background: #143a28; }
    
    /* Right Info Panel */
    .info-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; }
    .info-panel h2 { font-family: 'Newsreader', serif; font-size: 1.3rem; margin-bottom: 12px; }
    .info-text { font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; }
    .info-detail { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); font-size: 0.9rem; }

    .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
    .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffc9c9; }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="#" class="brand-logo">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/></svg>
      ReThread
    </a>
    <a href="donor_dashboard.php" class="nav-item">My Donations</a>
    <a href="new_donation.php" class="nav-item active">New Donation</a>
    <a href="collection_points.php" class="nav-item">Find Collection Points</a>
	<a href="donor_settings.php" class="nav-item">Account Settings</a>
  </div>

  <div class="main-content">
    <div class="header">
      <h1>New donation</h1>
      <div class="header-right">
        <span><?php echo htmlspecialchars($_SESSION['donor_name'] ?? 'Donor'); ?></span>
        <a href="../logout.php" class="btn-signout">Sign out</a>
      </div>
    </div>

    <?php if($message): ?>
      <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="donation-layout">
      
      <!-- LEFT SIDE: FORM -->
      <div class="panel">
        <form method="POST" action="new_donation.php">
          
          <!-- Top Row: Material, Condition, Weight -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Material</label>
              <select name="material" id="materialSelect" class="form-control" required onchange="filterOrganizations()">
                <option value="" disabled selected>Select...</option>
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
            </div>

            <div class="form-group">
              <label class="form-label">Condition</label>
              <select name="condition" class="form-control" required>
                <option value="Wearable">Wearable</option>
                <option value="Lightly Worn">Lightly Worn</option>
                <option value="Damaged">Damaged</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Estimated weight (kg)</label>
              <input type="number" step="0.1" name="weight_kg" class="form-control" placeholder="e.g. 3.5" required>
            </div>
          </div>

          <!-- Collection Point -->
          <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label">Collection point</label>
            <select name="org_id" id="orgSelect" class="form-control" required onchange="updateInfoPanel()">
              <option value="" disabled selected>Select...</option>
            </select>
          </div>

          <!-- Pickup Preference -->
          <div class="form-group">
            <label class="form-label">Pickup preference</label>
            <div class="radio-group">
              <label class="radio-label">
                <input type="radio" name="pickup_type" value="Collection" checked onchange="toggleAddress()"> 
                Collect from my address
              </label>
              <label class="radio-label">
                <input type="radio" name="pickup_type" value="Drop-off" onchange="toggleAddress()"> 
                I'll drop it off
              </label>
            </div>
          </div>

          <!-- Pickup Address -->
          <div class="form-group" style="margin-bottom: 24px;" id="addressGroup">
            <label class="form-label">Pickup address</label>
            <input type="text" name="address" id="addressInput" class="form-control" value="<?php echo htmlspecialchars($defaultAddress); ?>" placeholder="Enter your address" required>
          </div>

          <button type="submit" class="btn-submit">Submit donation</button>
        </form>
      </div>

      <!-- RIGHT SIDE: INFO PANEL -->
      <div class="info-panel">
        <h2>Collection point</h2>
        <div class="info-text" id="infoText">Pick a collection point to see what it accepts.</div>
        
        <!-- Hidden details that show up when an org is selected -->
        <div class="info-detail" id="infoDetails" style="display: none;">
          <strong>Address:</strong> <span id="infoAddress"></span><br><br>
          <strong>Accepts:</strong> <span id="infoMaterials"></span><br><br>
          <strong>Capacity:</strong> 
          <div style="background: #e9ecef; height: 6px; border-radius: 3px; margin-top: 8px; overflow: hidden;">
            <div id="infoCapacityBar" style="height: 100%; background: #2b8a59; width: 0%;"></div>
          </div>
          <span id="infoCapacityText" style="font-size: 0.8rem;"></span>
        </div>
      </div>

    </div>
  </div>

  <script>
    // Pass PHP data to JS
    const allOrgs = <?php echo json_encode($orgList); ?>;
    const orgSelect = document.getElementById('orgSelect');
    const urlParams = new URLSearchParams(window.location.search);
    const preselectedOrgId = urlParams.get('org_id');

    // Filter Organizations by Material
    function filterOrganizations() {
        const selectedMaterial = document.getElementById('materialSelect').value;
        orgSelect.innerHTML = '<option value="" disabled selected>Select...</option>';
        
        if (!selectedMaterial) {
            updateInfoPanel();
            return;
        }

        allOrgs.forEach(org => {
            if (org.accepted_materials.includes(selectedMaterial)) {
                const option = document.createElement('option');
                option.value = org.org_id;
                option.text = org.org_name;
                if (preselectedOrgId && org.org_id === preselectedOrgId) {
                    option.selected = true;
                }
                orgSelect.appendChild(option);
            }
        });
        updateInfoPanel();
    }

    // Update Right Panel based on selected Org
    function updateInfoPanel() {
        const selectedId = orgSelect.value;
        const org = allOrgs.find(o => o.org_id === selectedId);
        
        const infoText = document.getElementById('infoText');
        const infoDetails = document.getElementById('infoDetails');

        if (!org) {
            infoText.style.display = 'block';
            infoText.innerText = "Pick a collection point to see what it accepts.";
            infoDetails.style.display = 'none';
            return;
        }

        // Populate panel
        infoText.style.display = 'none';
        infoDetails.style.display = 'block';
        
        document.getElementById('infoAddress').innerText = org.org_address;
        document.getElementById('infoMaterials').innerText = org.clean_materials;
        
        // Calculate Capacity Bar
        const capacity = parseFloat(org.capacity_kg);
        const occupied = parseFloat(org.occupied_kg);
        const percent = capacity > 0 ? (occupied / capacity) * 100 : 0;
        
        const bar = document.getElementById('infoCapacityBar');
        bar.style.width = Math.min(percent, 100) + '%';
        bar.style.background = percent > 80 ? '#e03131' : '#2b8a59';
        
        document.getElementById('infoCapacityText').innerText = `${occupied} / ${capacity} kg used`;
    }

    // Toggle Address Field based on Pickup Preference
    function toggleAddress() {
        const pickupType = document.querySelector('input[name="pickup_type"]:checked').value;
        const addressInput = document.getElementById('addressInput');
        
        if (pickupType === 'Drop-off') {
            addressInput.value = "Drop-off at collection point";
            addressInput.readOnly = true;
            addressInput.style.backgroundColor = "#f1f3f5";
        } else {
            addressInput.value = "<?php echo addslashes($defaultAddress); ?>";
            addressInput.readOnly = false;
            addressInput.style.backgroundColor = "#fff";
        }
    }

    // Run on page load to handle pre-selected orgs from "Donate Here" button
    window.addEventListener('DOMContentLoaded', () => {
        if (preselectedOrgId) {
            const org = allOrgs.find(o => o.org_id === preselectedOrgId);
            if (org) {
                // Auto-select material if it exists in the list
                const firstMaterial = org.clean_materials.split(',')[0].trim();
                const matSelect = document.getElementById('materialSelect');
                for(let i=0; i<matSelect.options.length; i++) {
                    if(matSelect.options[i].value === firstMaterial) {
                        matSelect.selectedIndex = i;
                        break;
                    }
                }
                filterOrganizations();
            }
        }
        toggleAddress();
    });
  </script>
</body>
</html>