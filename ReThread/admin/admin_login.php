<?php
require_once '../session.php';
require_once '../dbconn.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
                // Query admin record by email FROM THE 'admin' TABLE
        $stmt = $conn->prepare("SELECT admin_id, admin_name, admin_email, admin_password, is_active FROM admin WHERE admin_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Check if account is active
            if ((int)$user['is_active'] !== 1) {
                $message = "Your admin account has been deactivated.";
            }

            elseif ($password === $user['admin_password']) {
                $_SESSION['admin_id']   = $user['admin_id'];
                $_SESSION['admin_name'] = $user['admin_name'];
                $_SESSION['role']       = 'admin';

                header("Location: admin_dashboard.php");
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
  <title>Admin Sign In</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
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
      display: inline-flex; align-items: center; gap: 8px;
      text-decoration: none; margin-bottom: 24px;
    }
    .brand-logo span {
      font-family: 'Newsreader', serif; font-size: 1.6rem;
      font-weight: 700; color: var(--primary-green);
    }
    .main-heading {
      font-family: 'Newsreader', serif; font-size: 2.7rem;
      font-weight: 700; margin-bottom: 8px; text-align: center;
    }
    .sub-heading {
      color: var(--text-muted); font-size: 1.05rem;
      margin-bottom: 36px; text-align: center;
    }
    .auth-card {
      background: var(--card-bg); border: 1px solid var(--border-color);
      border-radius: 20px; width: 100%; max-width: 420px;
      padding: 32px 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    }
    .role-icon {
      width: 48px; height: 48px; background-color: #dbe7f0;
      border-radius: 12px; display: flex; align-items: center;
      justify-content: center; color: #1d4d7a; margin-bottom: 18px;
    }
    .card-title {
      font-family: 'Newsreader', serif; font-size: 1.8rem;
      font-weight: 600; margin-bottom: 6px;
    }
    .card-description {
      font-size: 0.92rem; color: var(--text-muted);
      margin-bottom: 24px; line-height: 1.4;
    }
    .form-group { margin-bottom: 14px; }
    .input-field {
      width: 100%; padding: 12px 16px;
      border: 1px solid var(--border-color); border-radius: 10px;
      font-size: 0.95rem; outline: none; font-family: inherit;
      background-color: #fff;
    }
    .input-field:focus { border-color: var(--primary-green); }
    .btn-submit {
      width: 100%; background-color: var(--primary-green);
      color: #fff; border: none; border-radius: 10px;
      padding: 14px; font-size: 0.95rem; font-weight: 600;
      cursor: pointer; margin-bottom: 12px;
    }
    .btn-submit:hover { background-color: #163d2c; }
    .error-msg {
      color: #c92a2a; font-size: 0.88rem; margin-bottom: 12px;
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

  <h1 class="main-heading">Admin Portal.</h1>
  <p class="sub-heading">Manage donors, recipients and donations.</p>

  <div class="auth-card">
    <div class="role-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </div>

    <h2 class="card-title">Administrator</h2>
    <p class="card-description">Sign in to manage the ReThread platform.</p>

    <?php if (!empty($message)): ?>
      <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form action="admin_login.php" method="POST">
      <div class="form-group">
        <input type="email" name="email" class="input-field" placeholder="admin@demo.rethread" required>
      </div>
      <div class="form-group">
        <input type="password" name="password" class="input-field" placeholder="Password" required>
      </div>
      <button type="submit" class="btn-submit">Sign in as Admin</button>
    </form>
  </div>
</body>
</html>