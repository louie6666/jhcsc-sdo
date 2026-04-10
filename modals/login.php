<?php
session_start();

// Only process login if this is a POST request with JSON data
$is_login_request = ($_SERVER['REQUEST_METHOD'] === 'POST' && 
                     strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

if ($is_login_request) {
    header('Content-Type: application/json');
    
    include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Username and password are required.'
        ]);
        exit;
    }

    // Query the Users table
    $query = "SELECT user_id, username, password, full_name, role_id, is_active 
              FROM Users 
              WHERE username = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error. Please try again.'
        ]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password.'
        ]);
        exit;
    }

    $user = mysqli_fetch_assoc($result);

    // Check if user is active
    if (!$user['is_active']) {
        echo json_encode([
            'success' => false,
            'message' => 'Your account is inactive. Please contact an administrator.'
        ]);
        exit;
    }

    // Verify password
    $passwordValid = false;

    // Check if password is hashed (starts with $2y$ for bcrypt)
    if (substr($user['password'], 0, 4) === '$2y$' || substr($user['password'], 0, 4) === '$2a$') {
        $passwordValid = password_verify($password, $user['password']);
    } else {
        // Fallback: compare plain text
        $passwordValid = ($password === $user['password']);
    }

    if (!$passwordValid) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password.'
        ]);
        exit;
    }

    // Password is correct - create session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => 'dashboard.php'
    ]);

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}
?>

<style>
    /* MODAL ROOT VARIABLES - Localized for Auth */
    :root {
        --auth-bg: #f3f4f6;
        --auth-btn: #151515;
        --auth-border: #e5e7eb;
    }

    .modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.1);
        display: none; 
        justify-content: center;
        align-items: center;
        z-index: 9999; /* Higher than Nav */
    }

    .modal-overlay::before {
    content: "";
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    backdrop-filter: blur(8px); /* Blur happens here */
    z-index: -1;
}

    .modal-card {
        background: var(--auth-bg) !important;
        width: 90%;
        max-width: 420px;
        padding: 48px 40px 40px 40px;
        border-radius: 8px;
        position: relative;
       box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
    animation: modalEntrance 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    @keyframes modalEntrance {
        from { opacity: 0; transform: scale(0.95) translateY(-10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* THE CLOSE BUTTON LOGIC */
    .close-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        width: 36px;
        height: 36px;
        
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        transition: all 0.2s;
        z-index: 10;
    }

    .close-modal i,
    .close-modal .fallback-x {
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .close-modal:hover {
        color: #000;
        transform: scale(1.1);
    }

    .fallback-x {
        display: none; /* Hidden if FontAwesome works */
        line-height: 1;
    }

    /* If FA fails, show the fallback X */
    .fa-xmark:empty + .fallback-x { display: block; }

    /* FORM STYLING */
    .modal-card h2 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
    .modal-card p.subtext { font-size: 14px; color: #666; margin-bottom: 32px; }

    .form-group { margin-bottom: 24px; }
    .form-group label { display: block; font-size: 12px; font-weight: 400; margin-bottom: 8px; }
    .form-group input {
        width: 100%;
        padding: 12px 16px;
        font-size: 14px;
        border: 1px solid var(--auth-border);
        border-radius: 8px;
        outline: none;
    }

    .form-group input:focus { border-color: #6b5a2e; box-shadow: 0 0 0 1.5px #6b5a2e; }

    .forgot-link { display: inline-block; font-size: 12px; font-weight: 400; color: #1a1a1a; text-decoration: none; margin-bottom: 32px; }

    .btn-login {
        width: 100%;
        padding: 14px 0;
        background: var(--auth-btn);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-login:hover {
        background-color: #1e1e1e;
    }

    .modal-overlay.active { display: flex; }
</style>

<div class="modal-overlay" id="authModal" onclick="closeModal(event)">
    <div class="modal-card" onclick="event.stopPropagation()">
        
        <button type="button" class="close-modal" onclick="closeModal(event)" aria-label="Close Modal">
            <i class="fa-solid fa-xmark"></i>
            <span class="fallback-x">&times;</span> </button>

        <h2>Welcome back</h2>
        <p class="subtext">Welcome back! Please enter your details.</p>
        
        <form id="loginForm" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            
            <a href="#" class="forgot-link">Forgot password</a>
            
            <button type="submit" class="btn-login">Login</button>
            <div id="loginError" style="color: #ef4444; font-size: 12px; margin-top: 12px; display: none;"></div>
        </form>
    </div>
</div>

<script>
// Handle login form submission
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const username = document.querySelector('input[name="username"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const errorDiv = document.getElementById('loginError');
    const btn = document.querySelector('.btn-login');
    
    // Reset error
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    
    // Disable button
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Logging in...';
    
    // Send login request
    fetch('modals/login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            username: username,
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Login successful - redirect to dashboard
            window.location.href = 'dashboard.php';
        } else {
            // Show error
            errorDiv.textContent = data.message || 'Login failed. Please try again.';
            errorDiv.style.display = 'block';
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(error => {
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.style.display = 'block';
        btn.disabled = false;
        btn.textContent = originalText;
        console.error('Login error:', error);
    });
});
</script>