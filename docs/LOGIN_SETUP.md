# Login System Setup Guide

## Overview
The login system is now fully functional. Users can log in through the Staff Portal modal on the homepage.

## Files Created/Modified

### New Files:
1. **auth_process.php** - Handles user authentication via AJAX
2. **logout.php** - Destroys session and redirects to homepage
3. **setup_users.php** - Helper script to populate test users

### Modified Files:
1. **index.php** - Already has login modal trigger and modal scripts
2. **modals/login.php** - Updated to use `username` instead of `email` + added AJAX handler
3. **dashboard.php** - Added session protection + displays user's full name in welcome message

---

## Quick Start

### Step 1: Create Test Users
1. Open your browser and navigate to: `http://localhost/jhcsc_seis/setup_users.php`
2. This will create 3 test users with the following credentials:
   - **Admin**: `admin` / `password123`
   - **Staff 1**: `mgarcia` / `password123`
   - **Staff 2**: `jsmith` / `password123`

### Step 2: Test Login
1. Go to `http://localhost/jhcsc_seis/index.php`
2. Click the "Staff Portal" button (top right)
3. Enter username and password
4. Click "Login"
5. You should be redirected to the dashboard

### Step 3: Logout
1. In the dashboard, click the "Logout" option in the sidebar
2. You'll be redirected back to the homepage

---

## Database Requirements

Your `Users` table must have these fields:
```sql
CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_pic VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (role_id) REFERENCES Roles(role_id)
);
```

---

## How It Works

### Login Flow:
1. **User enters credentials** in the modal on `index.php`
2. **JavaScript sends AJAX request** to `modules/auth_process.php`
3. **PHP authenticates** against the Users table
4. **Session created** if successful
5. **Redirects to `dashboard.php`**
6. **Dashboard checks session** at the top and either allows access or redirects to index

### Password Handling:
- Currently supports **both hashed AND plain-text passwords** for flexibility
- Uses `password_verify()` for hashed passwords (bcrypt)
- Falls back to plain-text comparison for non-encrypted passwords
- **For production, hash all passwords** using: `password_hash('password123', PASSWORD_BCRYPT)`

### Session Variables Created:
- `$_SESSION['logged_in']` - Boolean flag
- `$_SESSION['user_id']` - User ID
- `$_SESSION['username']` - Username
- `$_SESSION['full_name']` - User's full name
- `$_SESSION['role_id']` - Role ID (Admin, Staff, Student)

---

## Security Considerations

⚠️ **Before Production:**
1. Delete `setup_users.php` file
2. Hash all passwords using `password_hash()`:
   ```php
   $hashedPassword = password_hash('mypassword', PASSWORD_BCRYPT);
   UPDATE Users SET password = '$hashedPassword' WHERE user_id = 1;
   ```
3. Set database connection to use strong credentials
4. Add CSRF token validation to login form
5. Implement rate limiting on login attempts
6. Use HTTPS for all login transmissions

---

## Troubleshooting

### "Login failed. Please try again."
- Check the username and password in the Users table
- Ensure the user's `is_active` field is set to `TRUE` (1)

### "Can't access dashboard"
- Check if you're logged in (session must exist)
- Clear browser cookies and try logging in again
- Check browser console for JavaScript errors

### "Column doesn't exist" error
- Verify your database table structure matches the requirements
- Run `setup_users.php` if tables aren't created

---

## Next Steps

1. ✅ **Test the login system** with the test credentials
2. Customize the dashboard welcome message as needed
3. Add role-based access control (Admin vs Staff views)
4. Implement password reset functionality
5. Add email verification for new accounts

---

**Last Updated**: April 6, 2026
