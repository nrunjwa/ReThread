<?php
require_once '../session.php';
require_once '../dbconn.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['org_id']) && ($_SESSION['role'] ?? '') === 'organization') {
    header("Location: org_dashboard.php");
    exit;
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['org_name'] ?? '');
    $address = trim($_POST['org_address'] ?? '');
    $email = trim($_POST['org_email'] ?? '');
    $capacity = (float)($_POST['capacity_kg'] ?? 100.0);
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($name) || empty($address) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "error";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT org_id FROM organizations WHERE org_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "This email is already registered. Please try another.";
            $messageType = "error";
        } else {
            $stmt->close();
            
            // Auto-generate Org ID 
            $result = $conn->query("SELECT MAX(CAST(SUBSTRING(org_id, 4) AS UNSIGNED)) as max_id FROM organizations");
            $row = $result->fetch_assoc();
            $next_id = ($row['max_id'] ?? 0) + 1;
            $new_org_id = 'ORG' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

            // Insert new organization with is_active = 0
            // set default occupied_kg = 0 and accepted_materials = '[]'
            $insertStmt = $conn->prepare("
                INSERT INTO organizations 
                (org_id, org_name, org_address, capacity_kg, occupied_kg, accepted_materials, is_active, org_email, org_password) 
                VALUES (?, ?, ?, ?, 0, '[]', 0, ?, ?)
            ");
            $insertStmt->bind_param("sssdss", $new_org_id, $name, $address, $capacity, $email, $password);
            
            if ($insertStmt->execute()) {
                $message = "Registration successful! Your account is pending admin approval.";
                $messageType = "success";
            } else {
                $message = "Something went wrong. Please try again later.";
                $messageType = "error";
            }
            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Organisation Registration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,500;0,6..72,600;0,6..72,700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-cream: #fbf9f4;
      --primary-green: #1d4d38;
      --text-dark: #1a2721;
      --text-muted: #57655d;
      --border-color: #dbe0dc;
      --card-bg: #ffffff;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-cream);
      color: var(--text-dark);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 40px 16px;
    }
    .brand-logo {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      margin-bottom: 24px;
    }
    .brand-logo span {
      font-family: 'Newsreader', serif;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--primary-green);
    }
    .auth-card {
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 20px;
      width: 100%;
      max-width: 480px;
      padding: 32px 28px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    }
    .role-icon {
      width: 48px;
      height: 48px;
      background-color: #bbf0dd;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #176043;
      margin-bottom: 18px;
    }
    .card-title {
      font-family: 'Newsreader', serif;
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 6px;
    }
    .card-description {
      font-size: 0.92rem;
      color: var(--text-muted);
      margin-bottom: 24px;
      line-height: 1.4;
    }
    .form-group {
      margin-bottom: 14px;
    }
    .input-field {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      font-size: 0.95rem;
      outline: none;
      font-family: inherit;
      background-color: #fff;
    }
    .input-field:focus {
      border-color: var(--primary-green);
    }
    .btn-submit {
      width: 100%;
      background-color: var(--primary-green);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 14px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 10px;
      margin-bottom: 12px;
    }
    .btn-submit:hover {
      background-color: #163d2c;
    }
    .btn-outline {
      display: block;
      width: 100%;
      text-align: center;
      background-color: transparent;
      color: var(--text-dark);
      border: 1px solid var(--border-color);
      border-radius: 10px;
      padding: 12px;
      font-size: 0.95rem;
      font-weight: 500;
      text-decoration: none;
    }
    .btn-outline:hover {
      background-color: #f7f9f7;
    }
    .alert {
      padding: 12px;
      border-radius: 8px;
      font-size: 0.9rem;
      margin-bottom: 16px;
      text-align: center;
    }
    .alert-error { background: #ffe3e3; color: #c92a2a; border: 1px solid #ffc9c9; }
    .alert-success { background: #d1f2e0; color: #176043; border: 1px solid #b5ebc9; }
  </style>
</head>
<body>

  <a href="../index.html" class="brand-logo">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#25a16d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
      <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
    </svg>
    <span>ReThread</span>
  </a>

  <div class="auth-card">
    <div class="role-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>
        <path d="M6 12H4a2 2 0 0 0-2 2v8h4"/>
        <path d="M18 9h2a2 2 0 0 1 2 2v11h-4"/>
        <path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>
      </svg>
    </div>

    <h2 class="card-title">Register Organisation</h2>
    <p class="card-description">Join ReThread to manage donations and pickups.</p>

    <?php if (!empty($message)): ?>
      <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- If registration is successful, hide the form -->
    <?php if ($messageType !== 'success'): ?>
    <form action="org_register.php" method="POST">
      
      <div class="form-group">
        <input type="text" name="org_name" class="input-field" placeholder="Organisation Name" required>
      </div>

      <div class="form-group">
        <input type="text" name="org_address" class="input-field" placeholder="Address / Postcode" required>
      </div>

      <div class="form-group">
        <input type="email" name="org_email" class="input-field" placeholder="org@demo.rethread" required>
      </div>

      <div class="form-group">
        <input type="number" name="capacity_kg" class="input-field" placeholder="Storage Capacity (kg) e.g. 500" required min="10">
      </div>

      <div class="form-group">
        <input type="password" name="password" id="password" class="input-field" placeholder="Password" required>
      </div>

      <div class="form-group">
        <input type="password" name="confirm_password" id="confirm_password" class="input-field" placeholder="Confirm Password" required>
      </div>

      <button type="submit" class="btn-submit">Create Organisation Account</button>
      <a href="org_login.php" class="btn-outline">Back to Login</a>
    </form>
    <?php else: ?>
      <!-- Show this only on success -->
      <a href="org_login.php" class="btn-submit" style="text-align: center; text-decoration: none;">Go to Login</a>
    <?php endif; ?>

  </div>

  <script>
    const form = document.querySelector('form');
    if (form) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        form.addEventListener('submit', function(e) {
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                confirmPassword.focus();
            }
        });
    }
  </script>
</body>
</html>