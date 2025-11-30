<?php
// manage_users.php
require 'auth.php';
require 'dbconn_productProfit.php';

// Check if user is admin
if (!$is_admin) {
    header("Location: index.php");
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new user
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $team_id = $_POST['team_id'] == 'admin' ? NULL : $_POST['team_id'];
            $is_admin = $_POST['team_id'] == 'admin' ? 1 : 0;
            
            $sql = "INSERT INTO users (username, password, team_id, is_admin) VALUES (?, ?, ?, ?)";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("ssii", $username, $password, $team_id, $is_admin);
            $stmt->execute();
            
        } elseif ($_POST['action'] == 'edit') {
            // Update user
            $user_id = $_POST['user_id'];
            $team_id = $_POST['team_id'] == 'admin' ? NULL : $_POST['team_id'];
            $is_admin = $_POST['team_id'] == 'admin' ? 1 : 0;
            
            $sql = "UPDATE users SET team_id = ?, is_admin = ? WHERE id = ?";
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("iii", $team_id, $is_admin, $user_id);
            $stmt->execute();
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $dbconn->prepare($sql);
                $stmt->bind_param("si", $password, $user_id);
                $stmt->execute();
            }
            
        } elseif ($_POST['action'] == 'delete') {
            // Delete user
            $user_id = $_POST['user_id'];
            
            $sql = "DELETE FROM users WHERE id = ? AND id != ?"; // Prevent deleting self
            $stmt = $dbconn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
            $stmt->execute();
        }
    }
}

// Get all users
$sql = "SELECT u.*, t.team_name FROM users u LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.id";
$result = $dbconn->query($sql);

// Get all teams
$sql = "SELECT * FROM teams ORDER BY id";
$teams = $dbconn->query($sql);
?>

<?php include 'navigation.php'; ?>

<div class="container">
    <h2>Manage Users</h2>
    
    <!-- Add User Form -->
    <div class="add-user-form">
        <h3>Add New User</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="team_id">Team:</label>
                <select id="team_id" name="team_id" required>
                    <option value="admin">Admin (No Team)</option>
                    <?php while ($team = $teams->fetch_assoc()): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo $team['team_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit">Add User</button>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="users-table">
        <h3>Current Users</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Team</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo $user['username']; ?></td>
                        <td><?php echo $user['is_admin'] ? 'Admin' : 'Team Member'; ?></td>
                        <td><?php echo $user['team_name'] ?? 'N/A'; ?></td>
                        <td>
                            <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>', <?php echo $user['team_id'] ?? 'null'; ?>, <?php echo $user['is_admin']; ?>)">Edit</button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit User</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="form-group">
                    <label for="edit_username">Username:</label>
                    <input type="text" id="edit_username" disabled>
                </div>
                <div class="form-group">
                    <label for="edit_password">New Password (leave blank to keep current):</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                <div class="form-group">
                    <label for="edit_team_id">Team:</label>
                    <select id="edit_team_id" name="team_id" required>
                        <option value="admin">Admin (No Team)</option>
                        <?php
                        // Reset the teams result pointer
                        $teams->data_seek(0);
                        while ($team = $teams->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo $team['team_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit">Update User</button>
            </form>
        </div>
    </div>

    <script>
        // Get the modal
        var modal = document.getElementById("editUserModal");
        
        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("close")[0];
        
        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
          modal.style.display = "none";
        }
        
        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
          if (event.target == modal) {
            modal.style.display = "none";
          }
        }
        
        // Open the edit modal with user data
        function openEditModal(id, username, team_id, is_admin) {
            document.getElementById("edit_user_id").value = id;
            document.getElementById("edit_username").value = username;
            
            var teamSelect = document.getElementById("edit_team_id");
            if (is_admin) {
                teamSelect.value = "admin";
            } else {
                teamSelect.value = team_id;
            }
            
            modal.style.display = "block";
        }
    </script>
</div>