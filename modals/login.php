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

    .close-modal:hover {
        color: #000;
        transform: scale(1.1);
    }

    .fallback-x {
        display: none; /* Hidden if FontAwesome works */
        font-size: 24px;
        line-height: 1;
    }

    /* If FA fails, show the fallback X */
    .fa-xmark:empty + .fallback-x { display: block; }

    /* FORM STYLING */
    .modal-card h2 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
    .modal-card p.subtext { font-size: 14px; color: #666; margin-bottom: 32px; }

    .form-group { margin-bottom: 24px; }
    .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; }
    .form-group input {
        width: 100%;
        padding: 12px 16px;
        font-size: 14px;
        border: 1px solid var(--auth-border);
        border-radius: 8px;
        outline: none;
    }

    .form-group input:focus { border-color: #6b5a2e; box-shadow: 0 0 0 1.5px #6b5a2e; }

    .forgot-link { display: inline-block; font-size: 12px; font-weight: 500; color: #1a1a1a; text-decoration: none; margin-bottom: 32px; }

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
        
        <form action="modules/auth_process.php" method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            
            <a href="#" class="forgot-link">Forgot password</a>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
</div>