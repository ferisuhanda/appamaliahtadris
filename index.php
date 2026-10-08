<?php
require 'config/database.php';

if (isset($_POST['login'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (password_verify($p, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama'] = $row['nama_lengkap'];
            header("Location: dashboard.php");
            exit;
        }
    }
    $error = "Username atau password salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login - App Amaliah Tadris</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <img src="assets/images/logo-sm.png" class="logo-xs" alt="Logo">
    <h2>App Amaliah Tadris</h2>
    <p class="subtitle">Silakan Login</p>
    <?php if(!empty($error)) echo "<div class='alert'>$error</div>"; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
</div>
</body>
</html>