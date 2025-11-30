<?php
session_start();
require 'dbconn_productProfit.php';

$error_message = '';
$success_message = '';
$teams = [];

// Fetch all teams for the dropdown
$teams_sql = "SELECT team_id, team_name, team_description FROM teams ORDER BY team_name";
$teams_result = $dbconn->query($teams_sql);

if ($teams_result) {
    while ($team = $teams_result->fetch_assoc()) {
        $teams[] = $team;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    
    // Basic validation
    if (empty($username)) {
        $error_message = "Username is required.";
    } elseif (empty($password)) {
        $error_message = "Password is required.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif ($team_id <= 0) {
        $error_message = "Please select a team.";
    } else {
        // Check if username already exists
        $check_sql = "SELECT username FROM users WHERE username = ?";
        $check_stmt = $dbconn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Username already exists. Please choose another one.";
        } else {
            // Hash the password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // FIXED: Changed the SQL query to prevent the unique key constraint error
            // We're not setting an explicit role and is_admin columns since they might have default values
            // or we're using NULL where appropriate
            $insert_sql = "INSERT INTO users (username, password, team_id) VALUES (?, ?, ?)";
            $insert_stmt = $dbconn->prepare($insert_sql);
            $insert_stmt->bind_param("ssi", $username, $hashed_password, $team_id);
            
            try {
                if ($insert_stmt->execute()) {
                    $success_message = "Registration successful! You can now log in.";
                    
                    // Redirect to login page after successful registration
                    $_SESSION['success_message'] = $success_message;
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Registration failed: " . $dbconn->error;
                }
            } catch (Exception $e) {
                $error_message = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Iasme Group Of Company</title>
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

.register-wrapper {
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

.register-side {
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

.register-container {
    width: 100%;
    max-width: 500px;
    position: relative;
    z-index: 1;
}

.register-card {
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

.register-card::before {
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

.register-header {
    text-align: center;
    margin-bottom: 36px;
    position: relative;
    z-index: 1;
}

.register-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 12px;
    letter-spacing: 0.5px;
}

.register-header p {
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

.form-group input,
.form-group select {
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

.form-group input::placeholder,
.form-group select::placeholder {
    color: rgba(203, 213, 225, 0.6);
}

.form-group select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23cbd5e1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    background-size: 16px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.form-group input:hover,
.form-group select:hover {
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

.password-strength {
    height: 5px;
    margin-top: 8px;
    border-radius: 5px;
    transition: all 0.3s ease;
    background-color: var(--error-color);
}

.strength-weak {
    background-color: var(--error-color);
    width: 30%;
}

.strength-medium {
    background-color: #f59e0b;
    width: 60%;
}

.strength-strong {
    background-color: var(--success-color);
    width: 100%;
}

.password-feedback {
    font-size: 12px;
    margin-top: 5px;
    color: var(--text-secondary);
    transition: all 0.3s ease;
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
    margin-top: 20px;
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

.terms-privacy {
    text-align: center;
    margin-top: 30px;
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.5;
    position: relative;
    z-index: 1;
}

.terms-privacy a {
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.terms-privacy a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background-color: var(--primary-color);
    transition: width 0.3s ease;
}

.terms-privacy a:hover::after {
    width: 100%;
}

.login-link {
    text-align: center;
    margin-top: 30px;
    color: var(--text-secondary);
    font-size: 15px;
    position: relative;
    z-index: 1;
}

.login-link a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
}

.login-link a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background-color: var(--primary-color);
    transition: width 0.3s ease;
}

.login-link a:hover::after {
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

.register-header, 
.form-group,
.button,
.terms-privacy,
.login-link {
    opacity: 0;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .register-wrapper {
        flex-direction: column;
    }
    
    .brand-side, .register-side {
        width: 100%;
        height: 50vh;
    }
    
    .brand-side {
        order: 1;
    }
    
    .register-side {
        order: 2;
    }
    
    .register-card {
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
    .register-card {
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
    .register-card {
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
input:focus, button:focus, a:focus, select:focus {
    outline: 3px solid rgba(59, 130, 246, 0.2);
    outline-offset: 2px;
}
    </style>
</head>
<body>
    <div class="register-wrapper">
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
                    <img src="lOGO-IASME-TRADING-5.png" alt="Iasme Trading Logo">
                </div>
                <h1 class="brand-title">IASME GROUP OF COMPANY</h1>
                <p class="brand-subtitle">Join our team and access our performance and reporting dashboard.</p>
            </div>
        </div>

        <!-- Register side (right) -->
        <div class="register-side">
            <!-- Particle background -->
            <div class="particles">
                <?php for ($i = 1; $i <= 30; $i++): ?>
                <div class="particle"></div>
                <?php endfor; ?>
            </div>
            
            <div class="register-container">
                <div class="register-card">
                    <div class="register-header">
                        <h1>Create Account</h1>
                        <p>Register to access our enterprise dashboard</p>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="message error-message" role="alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="message success-message" role="status">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" aria-label="Registration form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-wrapper">
                                <input type="text" id="username" name="username" placeholder="Create a username" required autocomplete="username" aria-required="true" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="password" name="password" placeholder="Create a strong password" required aria-required="true">
                                <i class="fas fa-lock"></i>
                                <button type="button" class="password-toggle" aria-label="Show password" tabindex="0">
                                    <i class="fas fa-eye" id="password-toggle-icon"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="password-strength"></div>
                            <div class="password-feedback" id="password-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required aria-required="true">
                                <i class="fas fa-lock"></i>
                                <button type="button" class="password-toggle" aria-label="Show password" tabindex="0">
                                    <i class="fas fa-eye" id="confirm-password-toggle-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="team_id">Select Team</label>
                            <div class="input-wrapper">
                                <select id="team_id" name="team_id" required aria-required="true">
                                    <option value="" disabled selected>Choose your team</option>
                                    <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['team_id']; ?>" <?php echo (isset($_POST['team_id']) && $_POST['team_id'] == $team['team_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['team_name']); ?> - <?php echo htmlspecialchars($team['team_description']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-users"></i>
                            </div>
                        </div>

                        <button type="submit" class="button" aria-label="Create Account">
                            <i class="fas fa-user-plus"></i>
                            Create Account
                        </button>

                        <div class="terms-privacy">
                            By creating an account, you agree to our 
                            <a href="#" tabindex="0">Terms of Service</a> and 
                            <a href="#" tabindex="0">Privacy Policy</a>
                        </div>

                        <div class="login-link">
                            Already have an account? <a href="index.php">Sign In</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password show/hide functionality
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const passwordField = this.parentElement.querySelector('input');
                const toggleIcon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordField.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                    this.setAttribute('aria-label', 'Show password');
                }
            });
        });

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('password-strength');
        const feedbackElement = document.getElementById('password-feedback');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let feedback = '';
            
            // Length check
            if (password.length >= 8) {
                strength += 1;
            } else {
                feedback = 'Password should be at least 8 characters long';
            }
            
            // Complexity checks
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^A-Za-z0-9]/)) strength += 1;
            
            // Update UI based on strength
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                strengthBar.style.width = '0';
                feedbackElement.textContent = '';
            } else if (strength < 2) {
                strengthBar.className = 'password-strength strength-weak';
                feedbackElement.textContent = feedback || 'Weak password';
                feedbackElement.style.color = 'var(--error-color)';
            } else if (strength < 4) {
                strengthBar.className = 'password-strength strength-medium';
                feedbackElement.textContent = 'Medium strength password';
                feedbackElement.style.color = '#f59e0b';
            } else {
                strengthBar.className = 'password-strength strength-strong';
                feedbackElement.textContent = 'Strong password';
                feedbackElement.style.color = 'var(--success-color)';
            }
        });
        
        // Confirm password validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && this.value !== passwordInput.value) {
                this.style.borderColor = 'var(--error-color)';
            } else if (this.value) {
                this.style.borderColor = 'var(--success-color)';
            } else {
                this.style.borderColor = '';
            }
        });

        // Form validation
        const form = document.querySelector('form');
        const usernameInput = document.getElementById('username');
        const teamSelect = document.getElementById('team_id');
        
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Username validation
            if (!usernameInput.value.trim()) {
                event.preventDefault();
                usernameInput.style.borderColor = 'var(--error-color)';
                isValid = false;
            }
            
            // Password validation
            if (!passwordInput.value) {
                event.preventDefault();
                passwordInput.style.borderColor = 'var(--error-color)';
                isValid = false;
            }
            
            // Confirm password validation
            if (!confirmPasswordInput.value) {
                event.preventDefault();
                confirmPasswordInput.style.borderColor = 'var(--error-color)';
                isValid = false;
            } else if (confirmPasswordInput.value !== passwordInput.value) {
                event.preventDefault();
                confirmPasswordInput.style.borderColor = 'var(--error-color)';
                alert('Passwords do not match!');
                isValid = false;
            }
            
            // Team selection validation
            if (!teamSelect.value) {
                event.preventDefault();
                teamSelect.style.borderColor = 'var(--error-color)';
                isValid = false;
            }
            
            if (isValid) {
                // Add loading state to button
                const button = document.querySelector('.button');
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Creating Account...';
                button.disabled = true;
            }
            
            return isValid;
        });
        
        // Enhanced animations
        function animateFormElements() {
            const elements = [
                '.register-header',
                '.form-group:nth-child(1)',
                '.form-group:nth-child(2)',
                '.form-group:nth-child(3)',
                '.form-group:nth-child(4)',
                '.button',
                '.terms-privacy',
                '.login-link'
            ];
            
            elements.forEach((selector, index) => {
                const el = document.querySelector(selector);
                if (el) {
                    el.style.animation = `fade-in-up 0.6s ${index * 0.1}s forwards`;
                }
            });
        }
        
        // Run animations after page load
        window.addEventListener('load', function() {
            setTimeout(animateFormElements, 300);
            
            // Focus first input
            usernameInput.focus();
            
            // Add particle animation enhancement
            createParticles(20);
        });
        
        // Create additional particles
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
    </script>
</body>
</html>