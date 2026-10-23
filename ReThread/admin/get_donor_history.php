<?php
require_once '../session.php';
require_once '../dbconn.php';

if (!isset($_SESSION['admin_id'])) {
    exit("Unauthorized");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Fetch history for specific donor
    $query = $conn->query("
        SELECT d.donation_id, d.material, d.weight_kg, d.status, o.org_name 
        FROM donations d
        LEFT JOIN organizations o ON d.org_id = o.org_id
        WHERE d.donor_id = $id
        ORDER BY d.created_at DESC
    ");

    if ($query->num_rows > 0) {
        while($row = $query->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='color:var(--primary-green); font-weight:600;'>D-" . $row['donation_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['material']) . "<br><small style='color:#888;'>" . htmlspecialchars($row['org_name']) . "</small></td>";
            echo "<td>" . htmlspecialchars($row['weight_kg']) . " kg</td>";
            echo "<td><span class='status-badge status-" . strtolower($row['status']) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4' style='color:#888;'>No donations made yet.</td></tr>";
    }
}
?>