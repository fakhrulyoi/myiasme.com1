<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Get username from session or database
$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    $sql_username = "SELECT username FROM users WHERE id = ?";
    $stmt_username = $dbconn->prepare($sql_username);
    $stmt_username->bind_param("i", $user_id);
    $stmt_username->execute();
    $username_result = $stmt_username->get_result();
    $username_data = $username_result->fetch_assoc();
    $username = $username_data['username'] ?? 'User';
}

$successMessage = '';
$errorMessage = '';
$showSuccessAnimation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $fb_page = trim($_POST['fb_page'] ?? '');
    $bm_acc = trim($_POST['bm_acc'] ?? '');

    // Validation
    $errors = [];
    if (empty($sku)) $errors[] = "SKU is required";
    if (empty($product_name)) $errors[] = "Product name is required";
    if (empty($domain)) $errors[] = "Domain is required";
    if (empty($fb_page)) $errors[] = "FB Page is required";
    if (empty($bm_acc)) $errors[] = "Business Manager is required";

    // Validate domain format
    if (!empty($domain) && !filter_var($domain, FILTER_VALIDATE_URL) && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}/', $domain)) {
        $errors[] = "Please enter a valid domain URL or domain name";
    }

    if (empty($errors)) {
        // Begin transaction
        $dbconn->begin_transaction();

        try {
            // 1. Insert into project_status table with team_id
            $stmt1 = $dbconn->prepare("INSERT INTO project_status (sku, product_name, domain, fb_page, bm_acc, team_id) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt1) {
                throw new Exception("Failed to prepare project_status statement: " . $dbconn->error);
            }
            
            $stmt1->bind_param("sssssi", $sku, $product_name, $domain, $fb_page, $bm_acc, $team_id);
            
            if (!$stmt1->execute()) {
                throw new Exception("Failed to insert project: " . $stmt1->error);
            }
            
            $project_id = $dbconn->insert_id;
            $stmt1->close();

            // 2. Check if domain already exists in domain_status
            $check_domain = $dbconn->prepare("SELECT id FROM domain_status WHERE domain_name = ?");
            $check_domain->bind_param("s", $domain);
            $check_domain->execute();
            $domain_exists = $check_domain->get_result()->num_rows > 0;
            $check_domain->close();

            // 3. Insert into domain_status table only if domain doesn't exist
            if (!$domain_exists) {
                $stmt2 = $dbconn->prepare("INSERT INTO domain_status (domain_name, status) VALUES (?, 'OFF')");
                if (!$stmt2) {
                    throw new Exception("Failed to prepare domain_status statement: " . $dbconn->error);
                }
                
                $stmt2->bind_param("s", $domain);
                
                if (!$stmt2->execute()) {
                    throw new Exception("Failed to insert domain status: " . $stmt2->error);
                }
                
                $stmt2->close();
            }

            // Commit transaction
            $dbconn->commit();
            
            $successMessage = "Project added successfully!";
            $showSuccessAnimation = true;
            
            // Clear form data on success
            $sku = $product_name = $domain = $fb_page = $bm_acc = '';

        } catch (Exception $e) {
            // Rollback transaction on error
            $dbconn->rollback();
            $errorMessage = $e->getMessage();
        }
    } else {
        $errorMessage = implode("<br>", $errors);
    }
}

// Include the navigation component
include 'navigation.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    * {
        box-sizing: border-box;
    }

    .page-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 2rem 1rem;
        font-family: 'Inter', sans-serif;
    }

    .form-wrapper {
        max-width: 900px;
        margin: 0 auto;
        perspective: 1000px;
    }

    .form-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 
            0 25px 50px rgba(0, 0, 0, 0.1),
            0 0 0 1px rgba(255, 255, 255, 0.2);
        transform-style: preserve-3d;
        transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }

    .form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
        background-size: 200% 100%;
        animation: gradientShift 3s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    .form-container:hover {
        transform: translateY(-5px) rotateX(2deg);
        box-shadow: 
            0 35px 70px rgba(0, 0, 0, 0.15),
            0 0 0 1px rgba(255, 255, 255, 0.3);
    }

    .form-header {
        text-align: center;
        margin-bottom: 2.5rem;
        position: relative;
    }

    .form-header::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 2px;
    }

    .form-header h2 {
        color: #1a202c;
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-header i {
        background: linear-gradient(135deg, #667eea, #764ba2);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-size: 2.2rem;
        animation: iconPulse 2s ease-in-out infinite alternate;
    }

    @keyframes iconPulse {
        from { transform: scale(1); }
        to { transform: scale(1.1); }
    }

    .form-grid {
        display: grid;
        gap: 1.8rem;
        margin-bottom: 2rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .form-group label {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: color 0.3s ease;
    }

    .form-group label i {
        color: #667eea;
        font-size: 1.1rem;
    }

    .required {
        color: #e53e3e;
        font-weight: 700;
    }

    .input-wrapper {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .input-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
        transition: left 0.5s ease;
    }

    .input-wrapper:focus-within::before {
        left: 100%;
    }

    .form-group input,
    .form-group textarea {
        padding: 1rem 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        font-family: inherit;
        background: #ffffff;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        outline: none;
        position: relative;
        z-index: 1;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        border-color: #667eea;
        box-shadow: 
            0 0 0 3px rgba(102, 126, 234, 0.1),
            0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }

    .form-group input.error {
        border-color: #e53e3e;
        box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .form-help {
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: #718096;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }

    .form-group:focus-within .form-help {
        opacity: 1;
        transform: translateY(0);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2.5rem;
        padding-top: 2rem;
        border-top: 1px solid #e2e8f0;
    }

    .btn {
        padding: 1rem 2.5rem;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
        overflow: hidden;
        min-width: 160px;
        justify-content: center;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s ease;
    }

    .btn:hover::before {
        left: 100%;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:active {
        transform: translateY(-1px);
    }

    .btn-secondary {
        background: #ffffff;
        color: #4a5568;
        border: 2px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-secondary:hover {
        background: #f7fafc;
        border-color: #cbd5e0;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-loading {
        position: relative;
        color: transparent;
    }

    .btn-loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-left: -10px;
        margin-top: -10px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-right-color: transparent;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .alert {
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border-radius: 12px;
        font-weight: 500;
        border: none;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideDown 0.5s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: linear-gradient(135deg, #48bb78, #38a169);
        color: white;
        box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
    }

    .alert-danger {
        background: linear-gradient(135deg, #e53e3e, #c53030);
        color: white;
        box-shadow: 0 4px 15px rgba(229, 62, 62, 0.3);
    }

    .alert i {
        font-size: 1.25rem;
    }

    /* Success Animation */
    .success-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .success-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .success-animation {
        background: white;
        border-radius: 20px;
        padding: 3rem;
        text-align: center;
        transform: scale(0.5);
        transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    .success-overlay.show .success-animation {
        transform: scale(1);
    }

    .success-checkmark {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #48bb78, #38a169);
        margin: 0 auto 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .success-checkmark::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
        transform: scale(0);
        animation: ripple 0.6s ease-out 0.2s forwards;
    }

    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }

    .success-checkmark i {
        color: white;
        font-size: 3rem;
        animation: checkmarkPop 0.4s ease-out 0.1s both;
    }

    @keyframes checkmarkPop {
        0% {
            transform: scale(0);
            opacity: 0;
        }
        70% {
            transform: scale(1.2);
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .success-text {
        color: #2d3748;
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .success-message {
        color: #718096;
        margin-bottom: 2rem;
    }

    .success-btn {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .success-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    /* Particle Animation */
    .particles {
        position: absolute;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
    }

    .particle {
        position: absolute;
        background: #667eea;
        border-radius: 50%;
        opacity: 0.7;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-container {
            margin: 1rem;
            padding: 1.5rem;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }

        .form-header h2 {
            font-size: 1.5rem;
        }
    }
</style>

<div class="page-container">
    <div class="particles">
        <div class="particle" style="left: 10%; top: 20%; width: 4px; height: 4px; animation-delay: 0s;"></div>
        <div class="particle" style="left: 20%; top: 80%; width: 6px; height: 6px; animation-delay: 0.5s;"></div>
        <div class="particle" style="left: 60%; top: 30%; width: 5px; height: 5px; animation-delay: 1s;"></div>
        <div class="particle" style="left: 80%; top: 70%; width: 3px; height: 3px; animation-delay: 1.5s;"></div>
        <div class="particle" style="left: 90%; top: 10%; width: 4px; height: 4px; animation-delay: 2s;"></div>
    </div>

    <div class="form-wrapper">
        <div class="form-container">
            <div class="form-header">
                <h2>
                    <i class="fas fa-plus-circle"></i>
                    Add New Project
                </h2>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $errorMessage ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="projectForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sku">
                            <i class="fas fa-barcode"></i>
                            SKU <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="sku" 
                                   name="sku" 
                                   value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>"
                                   required
                                   placeholder="e.g., PRD-001">
                        </div>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Enter a unique product SKU identifier
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="product_name">
                            <i class="fas fa-box"></i>
                            Product Name <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="product_name" 
                                   name="product_name" 
                                   value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>"
                                   required
                                   placeholder="Enter product name">
                        </div>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Descriptive name for your product
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="domain">
                            <i class="fas fa-globe"></i>
                            Domain <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="url" 
                                   id="domain" 
                                   name="domain" 
                                   value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>"
                                   required
                                   placeholder="https://example.com or example.com">
                        </div>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Full domain URL or domain name for this project
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="fb_page">
                            <i class="fab fa-facebook"></i>
                            Facebook Page <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="fb_page" 
                                   name="fb_page" 
                                   value="<?= htmlspecialchars($_POST['fb_page'] ?? '') ?>"
                                   required
                                   placeholder="Your Facebook page name">
                        </div>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Facebook page associated with this project
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bm_acc">
                            <i class="fas fa-user-tie"></i>
                            Business Manager Account <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="bm_acc" 
                                   name="bm_acc" 
                                   value="<?= htmlspecialchars($_POST['bm_acc'] ?? '') ?>"
                                   required
                                   placeholder="Business Manager account name">
                        </div>
                        <div class="form-help">
                            <i class="fas fa-info-circle"></i>
                            Facebook Business Manager account for this project
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        <span>Add Project</span>
                    </button>
                    <a href="domain.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Animation Overlay -->
<div id="successOverlay" class="success-overlay <?= $showSuccessAnimation ? 'show' : '' ?>">
    <div class="success-animation">
        <div class="success-checkmark">
            <i class="fas fa-check"></i>
        </div>
        <div class="success-text">Project Added Successfully!</div>
        <div class="success-message">Your project has been created and is ready to use.</div>
        <button class="success-btn" onclick="redirectToDomain()">
            <i class="fas fa-arrow-right"></i>
            Go to Domains
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('projectForm');
    const submitBtn = document.getElementById('submitBtn');
    const successOverlay = document.getElementById('successOverlay');
    
    // Create floating particles
    function createParticle() {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.width = (Math.random() * 5 + 2) + 'px';
        particle.style.height = particle.style.width;
        particle.style.animationDelay = Math.random() * 3 + 's';
        document.querySelector('.particles').appendChild(particle);
        
        setTimeout(() => {
            particle.remove();
        }, 3000);
    }
    
    // Create particles periodically
    setInterval(createParticle, 2000);
    
    // Form submission with enhanced animations
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('input[required]');
        let isValid = true;
        
        // Validate required fields with animation
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
                
                // Remove error class after animation
                setTimeout(() => {
                    field.classList.remove('error');
                }, 2000);
            } else {
                field.classList.remove('error');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            
            // Show error notification
            showNotification('Please fill in all required fields.', 'error');
            return;
        }
        
        // Validate domain format
        const domainField = document.getElementById('domain');
        const domainValue = domainField.value.trim();
        if (domainValue) {
            const urlPattern = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
            if (!urlPattern.test(domainValue)) {
                e.preventDefault();
                domainField.classList.add('error');
                showNotification('Please enter a valid domain URL.', 'error');
                return;
            }
        }
        
        // Show loading animation
        submitBtn.disabled = true;
        submitBtn.classList.add('btn-loading');
        
        // Create ripple effect on submit
        createRippleEffect(submitBtn, e);
    });
    
    // Remove error class on input
    const inputs = form.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
        });
        
        // Add focus animation
        input.addEventListener('focus', function() {
            this.parentNode.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentNode.style.transform = 'scale(1)';
        });
    });
    
    // Auto-format domain with animation
    const domainInput = document.getElementById('domain');
    domainInput.addEventListener('blur', function() {
        let value = this.value.trim();
        if (value && !value.startsWith('http://') && !value.startsWith('https://')) {
            if (value.includes('.') && !value.includes(' ')) {
                this.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    this.value = 'https://' + value;
                    this.style.transform = 'scale(1)';
                }, 200);
            }
        }
    });
    
    // Show success overlay if needed
    <?php if ($showSuccessAnimation): ?>
    setTimeout(() => {
        successOverlay.classList.add('show');
        
        // Auto-redirect after 5 seconds
        setTimeout(() => {
            redirectToDomain();
        }, 5000);
    }, 100);
    <?php endif; ?>
    
    // Create ripple effect function
    function createRippleEffect(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            background-color: rgba(255, 255, 255, 0.7);
            left: ${x}px;
            top: ${y}px;
            width: ${size}px;
            height: ${size}px;
        `;
        
        element.style.position = 'relative';
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }
    
    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'error' ? 'linear-gradient(135deg, #e53e3e, #c53030)' : 'linear-gradient(135deg, #667eea, #764ba2)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transform: translateX(300px);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(300px)';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }
});

// Redirect function for success animation
function redirectToDomain() {
    const overlay = document.getElementById('successOverlay');
    overlay.style.opacity = '0';
    setTimeout(() => {
        window.location.href = 'domain.php';
    }, 300);
}

// Add CSS for ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>