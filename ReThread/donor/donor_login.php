<?php
require_once '../session.php';
require_once '../dbconn.php'; 

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        // Query donor record by email
        $stmt = $conn->prepare("SELECT donor_id, donor_name, donor_email, donor_password, is_active FROM donors WHERE donor_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Check if account is active
            if ((int)$user['is_active'] !== 1) {
                $message = "Your account is deactivated. Please contact support.";
            } 
            // Compare password
            elseif ($password === $user['donor_password']) {
                // Set session data
                $_SESSION['donor_id']   = $user['donor_id'];
                $_SESSION['donor_name'] = $user['donor_name'];
                $_SESSION['role']       = 'donor';

                header("Location: donor_dashboard.php");
                exit;
            } else {
                $message = "Invalid email or password.";
            }
        } else {
            $message = "Invalid email or password.";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Donor Sign In – ReThread</title>
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
      --accent-badge-bg: #cbeef3;
      --accent-badge-text: #19806a;
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
      position: relative; /* Added for absolute positioning */
    }
    
    /* NEW: Back Button Styles */
    .back-btn {
      position: absolute;
      top: 24px;
      left: 24px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--text-dark);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      padding: 10px 16px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background-color: var(--card-bg);
      transition: background-color 0.2s;
      z-index: 10;
    }
    .back-btn:hover {
      background-color: #f7f9f7;
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
    .main-heading {
      font-family: 'Newsreader', serif;
      font-size: 2.7rem;
      font-weight: 700;
      margin-bottom: 8px;
      text-align: center;
    }
    .sub-heading {
      color: var(--text-muted);
      font-size: 1.05rem;
      margin-bottom: 36px;
      text-align: center;
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
    .extra-link-wrap {
      text-align: right;
      margin-bottom: 20px;
    }
    .extra-link {
      color: #276249;
      font-size: 0.85rem;
      text-decoration: underline;
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
      font-size: 0.88rem;
      margin-bottom: 12px;
    }
  </style>
</head>
<body>

  <!-- NEW: Back to Home Button -->
  <a href="../index.html" class="back-btn">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> back
  </a>

  <a href="../index.html" class="brand-logo">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#25a16d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
      <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
    </svg>
    <span>ReThread</span>
  </a>

  <h1 class="main-heading">Give Clothes a Next Life.</h1>
  <p class="sub-heading">Choose how you'd like to sign in.</p>

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

    <form action="donor_login.php" method="POST">
      <div class="form-group">
        <input type="email" name="email" class="input-field" placeholder="donor@demo.rethread" required>
      </div>

      <div class="form-group">
        <input type="password" name="password" class="input-field" placeholder="Password" required>
      </div>

      <div class="extra-link-wrap">
        <a href="donor_forgot_password.php" class="extra-link">Forgot Password?</a>
      </div>

      <button type="submit" class="btn-submit">Sign in as Donor</button>
      <a href="donor_register.php" class="btn-outline">Register new donor account</a>
    </form>
  </div>

</body>
</html>