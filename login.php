<?php
session_start();
require 'dbconn_productProfit.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Check if user exists and verify password
    if ($user) {
        // Check if password is already hashed (starts with $2y$)
        if (substr($user['password'], 0, 4) === '$2y$') {
            // Password is hashed, use password_verify
            $password_correct = password_verify($password, $user['password']);
        } else {
            // Password is not hashed, do direct comparison
            // This is for backward compatibility with existing users
            $password_correct = ($password === $user['password']);
            
            // Optionally update the password to be hashed for future logins
            if ($password_correct) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $dbconn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $user['id']);
                $update_stmt->execute();
                // Password updated to hashed version for future logins
            }
        }
        
        if ($password_correct) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['team_id'] = $user['team_id'];
            
            // Remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + 60*60*24*30; // 30 days
                
                // Store token in database (you would need to create a remember_tokens table)
                // $sql = "INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)";
                // $stmt = $dbconn->prepare($sql);
                // $stmt->bind_param("isi", $user['id'], $token, $expires);
                // $stmt->execute();
                
                setcookie('remember_token', $token, $expires, '/', '', true, true);
            }
            
            // Store user role based on the 'role' column in the database
            if (isset($user['role'])) {
                $_SESSION['role'] = $user['role'];
                
                // Set appropriate flags based on role
                switch($user['role']) {
                    case 'super_admin':
                        $_SESSION['is_super_admin'] = true;
                        $_SESSION['is_admin'] = true; // Superadmin inherits admin privileges
                        $_SESSION['is_operation'] = false;
                        break;
                    case 'admin':
                        $_SESSION['is_super_admin'] = false;
                        $_SESSION['is_admin'] = true;
                        $_SESSION['is_operation'] = false;
                        break;
                    case 'operation':
                        $_SESSION['is_super_admin'] = false;
                        $_SESSION['is_admin'] = false;
                        $_SESSION['is_operation'] = true;
                        break;
                    default: // 'team' or any other role
                        $_SESSION['is_super_admin'] = false;
                        $_SESSION['is_admin'] = false;
                        $_SESSION['is_operation'] = false;
                }
            } else {
                // Legacy fallback using is_admin column
                if (isset($user['is_admin']) && $user['is_admin']) {
                    $_SESSION['role'] = 'admin';
                    $_SESSION['is_admin'] = true;
                    $_SESSION['is_super_admin'] = false;
                    $_SESSION['is_operation'] = false;
                } else {
                    $_SESSION['role'] = 'user';
                    $_SESSION['is_admin'] = false;
                    $_SESSION['is_super_admin'] = false;
                    $_SESSION['is_operation'] = false;
                }
            }
            
            // Redirect based on user role
            if ($_SESSION['is_super_admin']) {
                header("Location:super_dashboard.php");
            } else if ($_SESSION['is_admin']) {
                header("Location:admin_dashboard.php");
            } else if ($_SESSION['is_operation']) {
                header("Location:operations_dashboard.php"); // Create this new dashboard
            } else {
                header("Location:dashboard.php");
            }
            exit;
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password.";
    }
}

// Check for any success messages (e.g., from password reset)
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
if ($success_message) {
    unset($_SESSION['success_message']); // Clear after reading
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Iasme Group Of Company</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
    --primary-color: #3b82f6; /* Professional blue */
    --primary-dark: #2563eb;
    --primary-light: #60a5fa;
    --accent-color: #6366f1; /* Subtle indigo accent */
    --dark-bg: #1e293b; /* Deep slate blue */
    --darker-bg: #0f172a; /* Darker slate blue */
    --card-bg: rgba(30, 41, 59, 0.95);
    --text-color: #f8fafc;
    --text-secondary: #cbd5e1;
    --success-color: #10b981; /* Emerald green */
    --error-color: #ef4444; /* Red */
    --border-color: rgba(148, 163, 184, 0.2);
    --input-bg: rgba(15, 23, 42, 0.7);
    --shadow-color: rgba(15, 23, 42, 0.1);
    --card-shadow: 0 8px 32px rgba(15, 23, 42, 0.2);
    --button-gradient: linear-gradient(135deg, #3b82f6, #6366f1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    background: var(--dark-bg);
    min-height: 100vh;
    display: flex;
    position: relative;
    overflow: hidden;
    color: var(--text-color);
}

.login-wrapper {
    display: flex;
    width: 100%;
    min-height: 100vh;
}

.brand-side {
    display: flex;
    width: 50%;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, var(--darker-bg) 0%, var(--dark-bg) 100%);
    position: relative;
    overflow: hidden;
    flex-direction: column;
}

/* Subtle background elements */
.cyber-grid {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        linear-gradient(transparent 0%, transparent calc(100% - 1px), rgba(148, 163, 184, 0.1) 100%),
        linear-gradient(90deg, transparent 0%, transparent calc(100% - 1px), rgba(148, 163, 184, 0.1) 100%);
    background-size: 40px 40px;
    transform: perspective(500px) rotateX(60deg);
    transform-origin: bottom;
    animation: grid-move 30s linear infinite;
    opacity: 0.1;
}

@keyframes grid-move {
    0% {
        background-position: 0 0;
    }
    100% {
        background-position: 0 40px;
    }
}

.cyber-circles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.cyber-circle {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(148, 163, 184, 0.2);
    opacity: 0.15;
}

.circle-1 {
    width: 300px;
    height: 300px;
    top: 20%;
    left: 10%;
    border-width: 2px;
    animation: pulse 6s ease-in-out infinite alternate;
}

.circle-2 {
    width: 500px;
    height: 500px;
    top: 40%;
    left: 30%;
    animation: pulse 8s ease-in-out infinite alternate-reverse;
}

.circle-3 {
    width: 200px;
    height: 200px;
    bottom: 10%;
    right: 20%;
    border-width: 3px;
    animation: pulse 4s ease-in-out infinite alternate;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 0.1;
    }
    100% {
        transform: scale(1.05);
        opacity: 0.2;
    }
}

.glowing-line {
    position: absolute;
    height: 2px;
    width: 100%;
    background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
    opacity: 0.3;
    animation: scan-line 8s linear infinite;
}

@keyframes scan-line {
    0% {
        top: -5%;
        opacity: 0;
    }
    10% {
        opacity: 0.3;
    }
    90% {
        opacity: 0.3;
    }
    100% {
        top: 105%;
        opacity: 0;
    }
}

.brand-content {
    z-index: 1;
    text-align: center;
    padding: 0 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
}

.brand-logo {
    position: relative;
    width: 280px;
    height: 280px;
    margin-bottom: 40px;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 0 30px rgba(59, 130, 246, 0.3);
    animation: logo-glow 3s ease-in-out infinite alternate;
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes logo-glow {
    0% {
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.2);
    }
    100% {
        box-shadow: 0 0 40px rgba(59, 130, 246, 0.4);
    }
}

.brand-logo::after {
    content: '';
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border-radius: 50%;
    border: 3px solid transparent;
    background: linear-gradient(135deg, var(--primary-color), transparent, var(--accent-color)) border-box;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: rotate 10s linear infinite;
}

@keyframes rotate {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.brand-logo img {
    width: 90%;
    height: 90%;
    object-fit: cover;
    border-radius: 50%;
}

.brand-title {
    font-size: 42px;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--text-color);
    text-transform: uppercase;
    margin-bottom: 20px;
    position: relative;
    display: inline-block;
}

.brand-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 70%;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
}

.brand-subtitle {
    font-size: 22px;
    color: var(--text-secondary);
    max-width: 400px;
    margin: 0 auto;
    line-height: 1.6;
}

.login-side {
    width: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
    position: relative;
}

.particles {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.particle {
    position: absolute;
    width: 2px;
    height: 2px;
    background-color: var(--primary-light);
    border-radius: 50%;
    opacity: 0.15;
}

/* Generate 30 particles with random positions */
.particle:nth-child(1) { top: 10%; left: 20%; animation: float 20s infinite; }
.particle:nth-child(2) { top: 30%; left: 50%; animation: float 15s infinite; }
.particle:nth-child(3) { top: 70%; left: 30%; animation: float 18s infinite; }
.particle:nth-child(4) { top: 40%; left: 80%; animation: float 12s infinite; }
.particle:nth-child(5) { top: 60%; left: 10%; animation: float 14s infinite; }
.particle:nth-child(6) { top: 20%; left: 60%; animation: float 16s infinite; }
.particle:nth-child(7) { top: 80%; left: 40%; animation: float 22s infinite; }
.particle:nth-child(8) { top: 50%; left: 70%; animation: float 19s infinite; }
.particle:nth-child(9) { top: 25%; left: 90%; animation: float 21s infinite; }
.particle:nth-child(10) { top: 85%; left: 15%; animation: float 17s infinite; }
.particle:nth-child(11) { top: 15%; left: 35%; animation: float 23s infinite; }
.particle:nth-child(12) { top: 45%; left: 22%; animation: float 13s infinite; }
.particle:nth-child(13) { top: 75%; left: 65%; animation: float 24s infinite; }
.particle:nth-child(14) { top: 35%; left: 5%; animation: float 25s infinite; }
.particle:nth-child(15) { top: 55%; left: 95%; animation: float 18s infinite; }
.particle:nth-child(16) { top: 90%; left: 25%; animation: float 22s infinite; }
.particle:nth-child(17) { top: 5%; left: 75%; animation: float 19s infinite; }
.particle:nth-child(18) { top: 65%; left: 45%; animation: float 20s infinite; }
.particle:nth-child(19) { top: 95%; left: 55%; animation: float 15s infinite; }
.particle:nth-child(20) { top: 42%; left: 88%; animation: float 21s infinite; }
.particle:nth-child(21) { top: 82%; left: 2%; animation: float 16s infinite; }
.particle:nth-child(22) { top: 12%; left: 48%; animation: float 17s infinite; }
.particle:nth-child(23) { top: 72%; left: 78%; animation: float 24s infinite; }
.particle:nth-child(24) { top: 32%; left: 38%; animation: float 14s infinite; }
.particle:nth-child(25) { top: 92%; left: 68%; animation: float 23s infinite; }
.particle:nth-child(26) { top: 22%; left: 28%; animation: float 13s infinite; }
.particle:nth-child(27) { top: 52%; left: 58%; animation: float 25s infinite; }
.particle:nth-child(28) { top: 62%; left: 18%; animation: float 12s infinite; }
.particle:nth-child(29) { top: 2%; left: 98%; animation: float 20s infinite; }
.particle:nth-child(30) { top: 38%; left: 62%; animation: float 18s infinite; }

@keyframes float {
    0%, 100% {
        transform: translateY(0) translateX(0);
    }
    50% {
        transform: translateY(-10px) translateX(5px);
    }
}

.login-container {
    width: 100%;
    max-width: 450px;
    position: relative;
    z-index: 1;
}

.login-card {
    background-color: var(--card-bg);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 40px;
    box-shadow: var(--card-shadow);
    border: 1px solid var(--border-color);
    animation: card-appear 0.8s ease-out forwards;
    position: relative;
    overflow: hidden;
}

.login-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.05) 0%, transparent 70%);
    opacity: 0.5;
    animation: rotate-gradient 15s linear infinite;
}

@keyframes rotate-gradient {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes card-appear {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-header {
    text-align: center;
    margin-bottom: 36px;
    position: relative;
    z-index: 1;
}

.login-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 12px;
    letter-spacing: 0.5px;
}

.login-header p {
    color: var(--text-secondary);
    font-size: 16px;
}

.form-group {
    margin-bottom: 24px;
    position: relative;
    z-index: 1;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 500;
    font-size: 15px;
    color: var(--text-color);
    letter-spacing: 0.5px;
}

.form-group .input-wrapper {
    position: relative;
}

.form-group .input-wrapper i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    transition: all 0.3s ease;
    font-size: 18px;
}

.form-group input:focus + i,
.form-group input:not(:placeholder-shown) + i {
    color: var(--primary-color);
}

.form-group input {
    width: 100%;
    padding: 16px 16px 16px 50px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
    outline: none;
    background-color: var(--input-bg);
    color: var(--text-color);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.form-group input::placeholder {
    color: rgba(203, 213, 225, 0.6);
}

.form-group input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.form-group input:hover {
    border-color: rgba(59, 130, 246, 0.5);
}

.password-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5px;
    z-index: 2;
}

.password-toggle:hover {
    color: var(--primary-color);
}

.login-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
    z-index: 1;
    position: relative;
}

.remember-me {
    display: flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.remember-me input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: relative;
    height: 22px;
    width: 22px;
    background-color: var(--input-bg);
    border: 2px solid var(--border-color);
    border-radius: 6px;
    margin-right: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
    width: 6px;
    height: 12px;
    border: solid var(--primary-color);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.remember-me input:checked ~ .checkmark {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: var(--primary-color);
}

.remember-me input:checked ~ .checkmark:after {
    display: block;
}

.remember-me:hover .checkmark {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.remember-me-text {
    font-size: 15px;
    color: var(--text-secondary);
}

.forgot-password a {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
}

.forgot-password a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background-color: var(--primary-color);
    transition: width 0.3s ease;
}

.forgot-password a:hover::after {
    width: 100%;
}

.button {
    background: var(--button-gradient);
    color: #fff;
    border: none;
    padding: 16px 20px;
    border-radius: 10px;
    cursor: pointer;
    width: 100%;
    font-size: 16px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 8px 15px rgba(59, 130, 246, 0.2);
    position: relative;
    overflow: hidden;
    z-index: 1;
}

.button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.7s;
    z-index: -1;
}

.button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 20px rgba(59, 130, 246, 0.2);
}

.button:hover::before {
    left: 100%;
}

.button:active {
    transform: scale(0.98);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);
}

.button i {
    margin-right: 10px;
    font-size: 18px;
}

.or-divider {
    display: flex;
    align-items: center;
    margin: 30px 0;
    color: var(--text-secondary);
    position: relative;
    z-index: 1;
}

.or-divider::before,
.or-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border-color), transparent);
}

.or-divider span {
    padding: 0 15px;
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 1px;
}

.social-login {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-bottom: 16px;
    position: relative;
    z-index: 1;
}

.social-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border-radius: 10px;
    background-color: rgba(15, 23, 42, 0.7);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    font-size: 22px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.social-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, rgba(59, 130, 246, 0.2) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.social-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    color: var(--text-color);
}

.social-button:hover::before {
    opacity: 0.5;
}

.social-button.google:hover {
    border-color: #ea4335;
    color: #ea4335;
    background-color: rgba(234, 67, 53, 0.05);
}

.social-button.microsoft:hover {
    border-color: #0078d4;
    color: #0078d4;
    background-color: rgba(0, 120, 212, 0.05);
}

.social-button.apple:hover {
    border-color: #ffffff;
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.05);
}

.register-link {
    text-align: center;
    margin-top: 30px;
    color: var(--text-secondary);
    font-size: 15px;
    position: relative;
    z-index: 1;
}

.register-link a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
}

.register-link a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background-color: var(--primary-color);
    transition: width 0.3s ease;
}

.register-link a:hover::after {
    width: 100%;
}

.message {
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    font-size: 15px;
    line-height: 1.5;
    position: relative;
    z-index: 1;
    backdrop-filter: blur(5px);
    animation: message-appear 0.5s ease-out forwards;
}

@keyframes message-appear {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message i {
    margin-right: 12px;
    font-size: 18px;
    flex-shrink: 0;
}

.error-message {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--error-color);
    border-left: 3px solid var(--error-color);
}

.success-message {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
    border-left: 3px solid var(--success-color);
}

/* Staggered animation for form elements */
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-header, 
.form-group:nth-child(1), 
.form-group:nth-child(2),
.login-options,
.button,
.or-divider,
.social-login,
.register-link {
    opacity: 0;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .login-wrapper {
        flex-direction: column;
    }
    
    .brand-side, .login-side {
        width: 100%;
        height: 50vh;
    }
    
    .brand-side {
        order: 1;
    }
    
    .login-side {
        order: 2;
    }
    
    .login-card {
        max-width: 90%;
        margin: 0 auto;
    }
    
    .brand-logo {
        width: 220px;
        height: 220px;
        margin-bottom: 30px;
    }
}

@media (max-width: 768px) {
    .login-card {
        padding: 30px;
        border-radius: 14px;
    }
    
    .brand-side {
        height: 40vh;
    }
    
    .brand-logo {
        width: 180px;
        height: 180px;
        margin-bottom: 20px;
    }
    
    .brand-title {
        font-size: 36px;
    }
    
    .brand-subtitle {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .login-card {
        padding: 24px;
        border-radius: 12px;
    }
    
    .login-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .forgot-password {
        align-self: flex-end;
    }
    
    .button {
        padding: 14px 16px;
    }
    
    .social-button {
        width: 48px;
        height: 48px;
    }
    
    .brand-side {
        height: 35vh;
    }
    
    .brand-logo {
        width: 150px;
        height: 150px;
    }
    
    .brand-title {
        font-size: 28px;
    }
}

/* Accessibility focus styles */
input:focus, button:focus, a:focus {
    outline: 3px solid rgba(59, 130, 246, 0.2);
    outline-offset: 2px;
}
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Brand side (left) -->
        <div class="brand-side">
            <!-- Futuristic background elements -->
            <div class="cyber-grid"></div>
            <div class="cyber-circles">
                <div class="cyber-circle circle-1"></div>
                <div class="cyber-circle circle-2"></div>
                <div class="cyber-circle circle-3"></div>
            </div>
            <div class="glowing-line"></div>
            
            <div class="brand-content">
                <div class="brand-logo">
                    <img src="logo igocc.png" alt="Iasme Trading Logo">
                </div>
                <h1 class="brand-title">IASME GROUP OF COMPANY</h1>
                <p class="brand-subtitle">Welcome to our all-in-one performance and reporting dashboard.</p>
            </div>
        </div>

        <!-- Login side (right) -->
        <div class="login-side">
            <!-- Particle background -->
            <div class="particles">
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
            </div>
            
            <div class="login-container">
                <div class="login-card">
                    <div class="login-header">
                        <h1>Access Portal</h1>
                        <p>Sign in to your enterprise dashboard</p>
                    </div>

                    <?php if (isset($error_message)): ?>
                        <div class="message error-message" role="alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success_message)): ?>
                        <div class="message success-message" role="status">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" aria-label="Login form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-wrapper">
                                <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username" aria-required="true">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password" aria-required="true">
                                <i class="fas fa-lock"></i>
                                <button type="button" class="password-toggle" aria-label="Show password" tabindex="0">
                                    <i class="fas fa-eye" id="password-toggle-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="login-options">
                            <label class="remember-me">
                                <input type="checkbox" name="remember" id="remember">
                                <span class="checkmark"></span>
                                <span class="remember-me-text">Remember me</span>
                            </label>

                            <div class="forgot-password">
                                <a href="forgot_password.php">Forgot Password?</a>
                            </div>
                        </div>

                        <button type="submit" class="button" aria-label="Sign in">
                            <i class="fas fa-sign-in-alt"></i>
                            Access System
                        </button>
                    </form>

                    <div class="or-divider">
                        <span>OR CONTINUE WITH</span>
                    </div>

                    <div class="social-login">
                        <button type="button" class="social-button google" aria-label="Sign in with Google">
                            <i class="fab fa-google"></i>
                        </button>
                        <button type="button" class="social-button microsoft" aria-label="Sign in with Microsoft">
                            <i class="fab fa-microsoft"></i>
                        </button>
                        <button type="button" class="social-button apple" aria-label="Sign in with Apple">
                            <i class="fab fa-apple"></i>
                        </button>
                    </div>

                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register Now</a>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password show/hide functionality
        const passwordField = document.getElementById('password');
        const passwordToggle = document.querySelector('.password-toggle');
        const passwordToggleIcon = document.getElementById('password-toggle-icon');

        passwordToggle.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordToggleIcon.classList.remove('fa-eye');
                passwordToggleIcon.classList.add('fa-eye-slash');
                passwordToggle.setAttribute('aria-label', 'Hide password');
            } else {
                passwordField.type = 'password';
                passwordToggleIcon.classList.remove('fa-eye-slash');
                passwordToggleIcon.classList.add('fa-eye');
                passwordToggle.setAttribute('aria-label', 'Show password');
            }
        });

        // Enhanced form validation
        const form = document.querySelector('form');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        
        // Dynamic input styling
        usernameInput.addEventListener('input', function() {
            if (this.value.trim().length > 0) {
                this.style.borderColor = 'var(--primary-color)';
            } else {
                this.style.borderColor = '';
            }
        });
        
        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                this.style.borderColor = 'var(--primary-color)';
            } else {
                this.style.borderColor = '';
            }
        });
        
        // Form submission with validation and loading state
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            if (!usernameInput.value.trim()) {
                event.preventDefault();
                usernameInput.style.borderColor = 'var(--error-color)';
                usernameInput.focus();
                isValid = false;
            }
            
            if (!passwordInput.value) {
                event.preventDefault();
                passwordInput.style.borderColor = 'var(--error-color)';
                if (isValid) {
                    passwordInput.focus();
                }
                isValid = false;
            }
            
            if (isValid) {
                // Add loading state to button
                const button = document.querySelector('.button');
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Authenticating...';
                button.disabled = true;
                
                // Reset any error styling
                usernameInput.style.borderColor = '';
                passwordInput.style.borderColor = '';
            }
            
            return isValid;
        });
        
        // Particle animation enhancement
        function createParticles(num) {
            const particles = document.querySelector('.particles');
            for (let i = 0; i < num; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size
                const size = Math.random() * 3 + 1;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                particle.style.top = `${Math.random() * 100}%`;
                particle.style.left = `${Math.random() * 100}%`;
                
                // Random opacity
                particle.style.opacity = Math.random() * 0.5 + 0.1;
                
                // Random animation delay
                particle.style.animationDelay = `${Math.random() * 5}s`;
                
                particles.appendChild(particle);
            }
        }
        
        // Add more particles dynamically
        createParticles(20);
        
        // Focus input field on load
        window.addEventListener('load', function() {
            usernameInput.focus();
        });
        
        // Add futuristic scan animation
        let scanInterval;
        function startScanAnimation() {
            let scanCount = 0;
            scanInterval = setInterval(() => {
                const newLine = document.createElement('div');
                newLine.classList.add('glowing-line');
                document.querySelector('.brand-side').appendChild(newLine);
                
                // Remove old lines to prevent DOM bloat
                setTimeout(() => {
                    newLine.remove();
                }, 8000);
                
                scanCount++;
                if (scanCount > 5) {
                    clearInterval(scanInterval);
                    setTimeout(startScanAnimation, 15000); // Restart after a pause
                }
            }, 3000);
        }
        
        // Start scan animation after initial load
        setTimeout(startScanAnimation, 2000);
        
        // Add cyberpunk typing effect to brand title
        function typeWriter(element, text, speed, callback) {
            let i = 0;
            element.innerHTML = "";
            
            function typing() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(typing, speed);
                } else if (callback) {
                    callback();
                }
            }
            
            typing();
        }
        
        const brandTitle = document.querySelector('.brand-title');
        const originalText = brandTitle.textContent;
        
        // Apply typing effect on page load
        setTimeout(() => {
            typeWriter(brandTitle, originalText, 100);
        }, 500);
        // 1. Staggered animation for form elements
function animateFormElements() {
    const elements = [
        '.login-header',
        '.form-group:nth-child(1)',
        '.form-group:nth-child(2)',
        '.login-options',
        '.button',
        '.or-divider',
        '.social-login',
        '.register-link'
    ];
    
    elements.forEach((selector, index) => {
        const el = document.querySelector(selector);
        el.style.animation = `fade-in-up 0.6s ${index * 0.1}s forwards`;
    });
}

// 2. Typewriter effect for subtitle
function typewriterEffect() {
    const subtitle = document.querySelector('.brand-subtitle');
    const text = subtitle.textContent;
    
    subtitle.textContent = '';
    subtitle.style.display = 'inline-block';
    
    const cursor = document.createElement('span');
    cursor.classList.add('typing-cursor');
    cursor.textContent = '|';
    cursor.style.animation = 'cursor-blink 1s step-end infinite';
    subtitle.after(cursor);
    
    let i = 0;
    function type() {
        if (i < text.length) {
            subtitle.textContent += text.charAt(i);
            i++;
            setTimeout(type, 50);
        } else {
            setTimeout(() => {
                cursor.remove();
            }, 2000);
        }
    }
    
    setTimeout(type, 1000);
}

// 3. Data stream animation
function createDataStreams() {
    const brandSide = document.querySelector('.brand-side');
    const streamsCount = 5;
    
    for (let i = 0; i < streamsCount; i++) {
        const stream = document.createElement('div');
        stream.classList.add('data-stream');
        
        // Random position and speed
        const left = Math.random() * 100;
        const duration = Math.random() * 5 + 3;
        const delay = Math.random() * 2;
        const size = Math.random() * 1 + 1;
        
        stream.style.left = `${left}%`;
        stream.style.width = `${size}px`;
        stream.style.animation = `data-stream ${duration}s ${delay}s linear infinite`;
        stream.style.opacity = Math.random() * 0.3 + 0.1;
        
        brandSide.appendChild(stream);
    }
}

// 4. Glitch effect for logo
function addGlitchEffect() {
    const logo = document.querySelector('.brand-logo img');
    
    setInterval(() => {
        if (Math.random() > 0.95) {
            logo.style.animation = 'glitch 0.2s linear';
            
            setTimeout(() => {
                logo.style.animation = 'none';
            }, 200);
        }
    }, 2000);
}

// 5. Interactive particle system
function enhanceParticles() {
    const loginSide = document.querySelector('.login-side');
    
    loginSide.addEventListener('mousemove', (e) => {
        const x = e.clientX;
        const y = e.clientY;
        
        const particles = document.querySelectorAll('.particle');
        particles.forEach(particle => {
            // Calculate distance from mouse
            const rect = particle.getBoundingClientRect();
            const particleX = rect.left + rect.width / 2;
            const particleY = rect.top + rect.height / 2;
            
            const distX = particleX - x;
            const distY = particleY - y;
            const distance = Math.sqrt(distX * distX + distY * distY);
            
            // Move particles away from cursor
            if (distance < 100) {
                const angle = Math.atan2(distY, distX);
                const force = (100 - distance) / 10;
                
                const moveX = Math.cos(angle) * force;
                const moveY = Math.sin(angle) * force;
                
                particle.style.transform = `translate(${moveX}px, ${moveY}px)`;
                particle.style.transition = 'transform 0.3s ease-out';
            } else {
                particle.style.transform = 'translate(0, 0)';
            }
        });
    });
}

// 6. Form validation micro-interactions
function enhanceFormValidation() {
    const inputs = document.querySelectorAll('input');
    
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            if (input.value.trim().length > 0) {
                input.classList.add('has-content');
                
                // Add subtle success animation
                input.style.animation = 'none';
                setTimeout(() => {
                    input.style.animation = 'success-pulse 0.5s ease-in-out';
                }, 10);
            } else {
                input.classList.remove('has-content');
            }
        });
    });
}

// 7. Loading button animation enhancement
function enhanceLoadingButton() {
    const form = document.querySelector('form');
    const button = document.querySelector('.button');
    
    form.addEventListener('submit', (e) => {
        if (form.checkValidity()) {
            button.classList.add('loading');
            
            // Add loading dots animation
            const loadingText = document.createElement('span');
            loadingText.textContent = 'Authenticating';
            loadingText.classList.add('loading-text');
            
            const dots = document.createElement('span');
            dots.classList.add('loading-dots');
            loadingText.appendChild(dots);
            
            button.innerHTML = '';
            button.appendChild(loadingText);
            
            // Dots animation
            let dotsCount = 0;
            const dotsInterval = setInterval(() => {
                dots.textContent = '.'.repeat((dotsCount % 3) + 1);
                dotsCount++;
            }, 300);
            
            // Store interval in window to clear it later if needed
            window.dotsInterval = dotsInterval;
        }
    });
}

// Initialize all animations
window.addEventListener('load', function() {
    // Run animations after a short delay to ensure everything is loaded
    setTimeout(() => {
        animateFormElements();
        typewriterEffect();
        createDataStreams();
        addGlitchEffect();
        enhanceParticles();
        enhanceFormValidation();
        enhanceLoadingButton();
        
        // Add additional class for page-loaded animations
        document.body.classList.add('page-loaded');
    }, 300);
});
    </script>
</body>
</html>