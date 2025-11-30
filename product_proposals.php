<?php
require 'auth.php';
require 'dbconn_productProfit.php';

// Handle file upload
function handleFileUpload($file) {
    $targetDir = "uploads/proposals/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . basename($file["name"]);
    $targetFile = $targetDir . $fileName;
    
    // Check if image file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'File is too large (max 5MB).'];
    }
    
    // Allow certain file formats
    $imageFileType = strtolower(pathinfo($targetFile,PATHINFO_EXTENSION));
    if(!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
    }
    
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ['success' => true, 'file_path' => $targetFile];
    } else {
        return ['success' => false, 'message' => 'Error uploading file.'];
    }
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_proposal') {
    // Validate required fields
    $required = ['productName', 'category', 'costPrice', 'sellingPrice', 'productDescription', 'tiktokLink'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $error_message = "Please fill in all required fields.";
            break;
        }
    }
    
    if (empty($error_message)) {
        $product_name = trim($_POST['productName']);
        $category = trim($_POST['category']);
        $cost_price = floatval($_POST['costPrice']);
        $selling_price = floatval($_POST['sellingPrice']);
        $product_description = trim($_POST['productDescription']);
        $tiktok_link = trim($_POST['tiktokLink']);
        
        // Validate prices
        if ($selling_price <= $cost_price) {
            $error_message = "Selling price must be greater than cost price.";
        }
        
        // Handle file upload
        $product_image = null;
        if (!empty($_FILES['productImage']['name'])) {
            $uploadResult = handleFileUpload($_FILES['productImage']);
            if (!$uploadResult['success']) {
                $error_message = $uploadResult['message'];
            } else {
                $product_image = $uploadResult['file_path'];
            }
        }
        
        if (empty($error_message)) {
            try {
                $dbconn->begin_transaction();
                
                $sql = "INSERT INTO product_proposals (
                    product_name, 
                    category, 
                    cost_price, 
                    selling_price, 
                    product_description, 
                    tiktok_link, 
                    product_image, 
                    team_id, 
                    user_id,
                    status,
                    proposed_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                
                $stmt = $dbconn->prepare($sql);
                $stmt->bind_param(
                    "ssddsssii",
                    $product_name,
                    $category,
                    $cost_price,
                    $selling_price,
                    $product_description,
                    $tiktok_link,
                    $product_image,
                    $team_id,
                    $user_id
                );
                
                if ($stmt->execute()) {
                    $dbconn->commit();
                    $_SESSION['success_message'] = "Your product proposal has been submitted successfully!";
                    header("Location: user_product_proposed.php");
                    exit();
                } else {
                    $dbconn->rollback();
                    $error_message = "Error submitting proposal: " . $dbconn->error;
                }
            } catch (Exception $e) {
                $dbconn->rollback();
                $error_message = "Error submitting proposal: " . $e->getMessage();
            }
        }
    }
    
    // If there's an error, store the form data in session to repopulate the form
    $_SESSION['form_data'] = $_POST;
    $_SESSION['error_message'] = $error_message;
    header("Location: user_product_proposed.php");
    exit();
}

// Default redirect if accessed directly without a proper POST request
header("Location: user_product_proposed.php");
exit();
?>