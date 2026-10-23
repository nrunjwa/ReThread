<?php
require_once '../session.php';
require_once '../dbconn.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['donor_id']) && ($_SESSION['role'] ?? '') === 'donor') {
    header("Location: donor_dashboard.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? ''); 
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($name) || empty($phone) || empty($address) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT donor_id FROM donors WHERE donor_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "This email is already registered. Please log in.";
        } else {
            $stmt->close();
            
            $insertStmt = $conn->prepare("INSERT INTO donors (donor_name, donor_email, donor_password, donor_phone, donor_address, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            
            $insertStmt->bind_param("sssss", $name, $email, $password, $phone, $address);
            
            if ($insertStmt->execute()) {
                // Redirect to login page after successful registration
                header("Location: donor_login.php?registered=1");
                exit;
            } else {
                // If it fails, show the actual database error for debugging
                $message = "Database Error: " . $insertStmt->error;
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donor Registration</title>
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
      max-width: 420px;
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
      margin-top: 8px;
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
    .error-msg {
      color: #c92a2a;
      background: #ffe3e3;
      border: 1px solid #ffc9c9;
      padding: 10px;
      border-radius: 8px;
      font-size: 0.88rem;
      margin-bottom: 16px;
      text-align: center;
    }
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
      <!-- Shirt Icon -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/>
      </svg>
    </div>

    <h2 class="card-title">Donor</h2>
    <p class="card-description">Donate unwanted clothes and track their journey.</p>

    <?php if (!empty($message)): ?>
      <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form action="donor_register.php" method="POST">
      
      <!-- Full Name -->
      <div class="form-group">
        <input type="text" name="name" class="input-field" placeholder="Full name" required>
      </div>
	  
	  <!-- Phone number -->
      <div class="form-group">
        <input type="text" name="phone" class="input-field" placeholder="e.g. 0123456789" required>
      </div>

      <!-- Address / Postcode -->
      <div class="form-group">
        <input type="text" name="address" class="input-field" placeholder="Address" required>
      </div>

      <!-- Email -->
      <div class="form-group">
        <input type="email" name="email" class="input-field" placeholder="donor@demo.rethread" required>
      </div>

      <!-- Password -->
      <div class="form-group">
        <input type="password" name="password" id="password" class="input-field" placeholder="Password" required>
      </div>

      <!-- Password Confirmation -->
      <div class="form-group">
        <input type="password" name="confirm_password" id="confirm_password" class="input-field" placeholder="Confirm Password" required>
      </div>

      <button type="submit" class="btn-submit">Create donor account</button>
      <a href="donor_login.php" class="btn-outline">Back to login</a>
    </form>
  </div>

  <script>
    const form = document.querySelector('form');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    form.addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
            e.preventDefault(); // Stop form submission
            alert('Passwords do not match. Please check and try again.');
            confirmPassword.focus();
        }
    });
  </script>
</body>
</html>