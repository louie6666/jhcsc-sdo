<?php
/**
 * User Setup Script - Creates test users in the database
 * Usage: Run this once to populate the Users table with test users
 * Then delete this file or disable it for security
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';

// Check if connection exists
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Sample users to insert
$users = [
    [
        'role_id' => 1,  // Admin
        'full_name' => 'John Administrator',
        'username' => 'admin',
        'password' => 'password123'  // Plain text for testing
    ],
    [
        'role_id' => 2,  // Staff
        'full_name' => 'Maria Garcia',
        'username' => 'mgarcia',
        'password' => 'password123'
    ],
    [
        'role_id' => 2,  // Staff
        'full_name' => 'James Smith',
        'username' => 'jsmith',
        'password' => 'password123'
    ]
];

// First, check if Roles table is populated
$role_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Roles");
$role_count = mysqli_fetch_assoc($role_check);

if ($role_count['count'] == 0) {
    echo "Setting up Roles...<br>";
    mysqli_query($conn, "INSERT INTO Roles (role_id, role_name) VALUES (1, 'Admin')");
    mysqli_query($conn, "INSERT INTO Roles (role_id, role_name) VALUES (2, 'Staff')");
    mysqli_query($conn, "INSERT INTO Roles (role_id, role_name) VALUES (3, 'Student')");
    echo "✓ Roles created<br><br>";
}

// Insert users
echo "Creating test users...<br>";
$success_count = 0;

foreach ($users as $user) {
    $role_id = $user['role_id'];
    $full_name = $user['full_name'];
    $username = $user['username'];
    $password = $user['password'];
    
    // Check if user already exists
    $check = mysqli_query($conn, "SELECT user_id FROM Users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        echo "⚠ User '$username' already exists - skipping<br>";
        continue;
    }
    
    // Insert user
    $query = "INSERT INTO Users (role_id, full_name, username, password, is_active) 
              VALUES ($role_id, '$full_name', '$username', '$password', 1)";
    
    if (mysqli_query($conn, $query)) {
        echo "✓ Created user: $username<br>";
        $success_count++;
    } else {
        echo "✗ Failed to create user $username: " . mysqli_error($conn) . "<br>";
    }
}

echo "<br><hr>";
echo "<h3>Test Login Credentials:</h3>";
echo "<p><strong>Admin Account:</strong><br>";
echo "Username: <code>admin</code><br>";
echo "Password: <code>password123</code></p>";

echo "<p><strong>Staff Accounts:</strong><br>";
echo "Username: <code>mgarcia</code> | Password: <code>password123</code><br>";
echo "Username: <code>jsmith</code> | Password: <code>password123</code></p>";

echo "<hr>";
echo "<p style='color: red;'><strong>⚠ IMPORTANT:</strong> Delete this file (setup_users.php) after testing!</p>";
echo "<p style='color: red;'><strong>⚠ Change the passwords to hashed values before going to production!</strong></p>";
echo "<p>You can use: <code>password_hash('password123', PASSWORD_BCRYPT)</code> to hash passwords</p>";

mysqli_close($conn);
?>
