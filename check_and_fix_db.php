<?php
// This script checks and fixes the database structure
require 'dbconn_productProfit.php'; // Changed from dbconn.php to match your existing file

echo "<h1>Database Structure Check and Fix</h1>";

// Check if the teams table exists
$result = $dbconn->query("SHOW TABLES LIKE 'teams'");
if ($result->num_rows == 0) {
    echo "<p>Creating teams table...</p>";
    
    $sql = "CREATE TABLE teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_name VARCHAR(100) NOT NULL,
        team_description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($dbconn->query($sql)) {
        echo "<p>Teams table created successfully.</p>";
        
        // Insert default teams
        $sql = "INSERT INTO teams (team_name, team_description) VALUES 
            ('Team 1', 'First sales team'),
            ('Team 2', 'Second sales team'),
            ('Team 3', 'Third sales team'),
            ('Team 4', 'Fourth sales team')";
        
        if ($dbconn->query($sql)) {
            echo "<p>Default teams added successfully.</p>";
        } else {
            echo "<p>Error adding default teams: " . $dbconn->error . "</p>";
        }
    } else {
        echo "<p>Error creating teams table: " . $dbconn->error . "</p>";
    }
} else {
    echo "<p>Teams table already exists.</p>";
}

// Check users table structure
$result = $dbconn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
if ($result->num_rows == 0) {
    echo "<p>Adding is_admin column to users table...</p>";
    
    $sql = "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0";
    
    if ($dbconn->query($sql)) {
        echo "<p>is_admin column added successfully.</p>";
        
        // Update is_admin based on existing role if available
        $sql = "UPDATE users SET is_admin = 1 WHERE role = 'admin'";
        $dbconn->query($sql);
        echo "<p>Updated is_admin values based on role.</p>";
    } else {
        echo "<p>Error adding is_admin column: " . $dbconn->error . "</p>";
    }
} else {
    echo "<p>is_admin column already exists in users table.</p>";
}

// Check team_id column in users table
$result = $dbconn->query("SHOW COLUMNS FROM users LIKE 'team_id'");
if ($result->num_rows == 0) {
    echo "<p>Adding team_id column to users table...</p>";
    
    $sql = "ALTER TABLE users ADD COLUMN team_id INT NULL";
    
    if ($dbconn->query($sql)) {
        echo "<p>team_id column added successfully.</p>";
        
        // Assign regular users to teams randomly
        $sql = "UPDATE users SET team_id = FLOOR(1 + RAND() * 4) WHERE is_admin = 0 OR is_admin IS NULL";
        $dbconn->query($sql);
        echo "<p>Assigned users to random teams.</p>";
    } else {
        echo "<p>Error adding team_id column: " . $dbconn->error . "</p>";
    }
} else {
    echo "<p>team_id column already exists in users table.</p>";
}

// Check team_id column in products table
$result = $dbconn->query("SHOW COLUMNS FROM products LIKE 'team_id'");
if ($result->num_rows == 0) {
    echo "<p>Adding team_id column to products table...</p>";
    
    $sql = "ALTER TABLE products ADD COLUMN team_id INT NULL";
    
    if ($dbconn->query($sql)) {
        echo "<p>team_id column added successfully.</p>";
        
        // Assign existing products to teams randomly
        $sql = "UPDATE products SET team_id = FLOOR(1 + RAND() * 4)";
        $dbconn->query($sql);
        echo "<p>Assigned products to random teams.</p>";
    } else {
        echo "<p>Error adding team_id column: " . $dbconn->error . "</p>";
    }
} else {
    echo "<p>team_id column already exists in products table.</p>";
}

// Create default admin user if no admin exists
$result = $dbconn->query("SELECT * FROM users WHERE is_admin = 1");
if ($result->num_rows == 0) {
    echo "<p>No admin user found. Creating default admin...</p>";
    
    $username = "admin";
    $password = password_hash("admin123", PASSWORD_DEFAULT);
    $is_admin = 1;
    
    $sql = "INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)";
    $stmt = $dbconn->prepare($sql);
    $stmt->bind_param("ssi", $username, $password, $is_admin);
    
    if ($stmt->execute()) {
        echo "<p>Default admin user created successfully.</p>";
        echo "<p>Username: admin<br>Password: admin123</p>";
        echo "<p><strong>IMPORTANT: Please change this password immediately after logging in!</strong></p>";
    } else {
        echo "<p>Error creating default admin: " . $stmt->error . "</p>";
    }
} else {
    echo "<p>Admin user already exists.</p>";
}

echo "<p>Database structure check and fix completed.</p>";
echo "<p><a href='login.php'>Go to login page</a></p>";
?>