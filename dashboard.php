<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/jhcsc_seis/connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | JHCSC SDO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <style>
        :root {
            /* COLORS */
            --dbui-bg-primary: #ecefec;
            --dbui-sidebar-bg: #ecefec;
            --dbui-text-main: #1f2937;
            --dbui-text-light: #ffffff;
            --dbui-text-muted: #000000;
            --dbui-hover-bg: #f0f4f0;
            --dbui-hover-submenu: #f0f4f0;
            --dbui-active-link: #e8efe8;
            --dbui-border: #e2e8e2;
            --dbui-menu-text: #000000;

            /* SIZING */
            --dbui-sidebar-width: 230px;
            --dbui-sidebar-collapsed-width: 80px;
            --dbui-header-height: 48px;
            --dbui-radius: 8px;
            --dbui-icon-size: 18px; 
            /* Sidebar hover inset from left/right edge (adjust to 5px or 10px as you like) */
            --dbui-sidebar-hover-gap: 5px;
            /* Icon distance from left edge of menu pill (matched to submenu feel) */
            --dbui-sidebar-link-padding-x: 10px;
            /* Space between menu icon and menu text (e.g., Dashboard) */
            --dbui-sidebar-icon-text-gap: 5px;
            /* Sidebar header left/right inner spacing (logo/title block position) */
            --dbui-sidebar-header-padding-x: 10px;
            /* Space between logo and title text in header */
            --dbui-sidebar-logo-title-gap: 5px;

            /* --- ADJUST CONTENT SPACING (change these anytime) --- */
            --dbui-body-padding-top: 1px;
            /* Main content left breathing space (current value already reduced by 40) */
            --dbui-content-padding-left: 160px;
            /* Main content right breathing space (current value already reduced by 40) */
            --dbui-content-padding-right: 160px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            font-weight: 400;
        }

        body {
            background-color: var(--dbui-bg-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--dbui-sidebar-width);
            background-color: var(--dbui-sidebar-bg);
            color: var(--dbui-text-light);
            height: 100vh;
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border-right: 1px solid var(--dbui-border);
        }

        .sidebar.collapsed { width: var(--dbui-sidebar-collapsed-width); }

        .sidebar-header {
            padding: 0 var(--dbui-sidebar-header-padding-x);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--dbui-header-height); 
            border-bottom: none;
            margin-bottom: 6px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: var(--dbui-sidebar-logo-title-gap);
            cursor: pointer;
        }

        .logo-box {
    min-width: 40px; /* Slightly wider for a professional logo feel */
    height: 40px;
    background: transparent; /* Remove the dark background so the emblem stands out */
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain; /* Ensures the emblem isn't stretched */
}

        .header-text { white-space: nowrap; }
        .header-text strong { display: block; font-size: 14px; color: var(--dbui-text-main); line-height: 1; }
        .header-text p { font-size: 10px; letter-spacing: 0.5px; text-transform: uppercase; color: var(--dbui-text-muted); margin-top: 4px; }

        /* NAVIGATION */
        .menu-label {
            font-size: 12px;
            font-weight: 400;
            color: var(--dbui-text-muted);
            padding: 0 calc(var(--dbui-sidebar-hover-gap) + var(--dbui-sidebar-link-padding-x));
            margin-top: 2px;
            margin-bottom: 6px;
            text-transform: none;
            letter-spacing: normal;
        }

        .nav-menu { flex: 1; padding: 5px; margin-top: 4px; }
        .nav-item { list-style: none; margin-bottom: 4px; }

        .nav-link {
            display: flex;
            align-items: center;
            /* 5px left icon gap inside blue hover/active pill */
            padding: 10px var(--dbui-sidebar-link-padding-x);
            /* Creates the visual gap so hover/active bg does not touch sidebar border */
            margin: 0 var(--dbui-sidebar-hover-gap);
            color: var(--dbui-menu-text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            border-radius: var(--dbui-radius);
            transition: all 0.2s;
            white-space: nowrap;
            cursor: pointer;
        }

        .nav-link svg { 
            width: var(--dbui-icon-size) !important;
            height: var(--dbui-icon-size) !important;
            margin-right: var(--dbui-sidebar-icon-text-gap); 
            stroke-width: 1.5;
            color: var(--dbui-menu-text);
        }

        .nav-link:hover { background-color: var(--dbui-hover-bg); color: var(--dbui-text-main); }
        .nav-link.active { 
            background-color: var(--dbui-active-link); 
            color: var(--dbui-text-main) !important; 
            font-weight: 500;
            box-shadow: none; 
        }

        /* SUBMENU */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding-left: 30px;
        }

        .submenu.open { max-height: 200px; margin-bottom: 10px; }

        .submenu-link {
            display: block;
            padding: 8px 10px;
            /* Match hover inset with top-level menu */
            margin: 0 var(--dbui-sidebar-hover-gap);
            color: var(--dbui-text-main);
            text-decoration: none;
            font-size: 12px;
            font-weight: 400;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .submenu-link:hover { background-color: var(--dbui-hover-submenu); color: var(--dbui-text-main); }
        .chevron-icon { transition: transform 0.3s; }
        .submenu-link.active {
            background-color: var(--dbui-active-link);
            color: var(--dbui-text-main) !important;
            font-weight: 500;
            box-shadow: none;
        }

        /* COLLAPSE STATES */
        .sidebar.collapsed .header-text,
        .sidebar.collapsed .menu-label,
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .chevron-icon,
        .sidebar.collapsed .submenu { display: none; }
        
        .sidebar.collapsed .header-left { justify-content: center; width: 100%; }
        .sidebar.collapsed .nav-link { color: var(--dbui-menu-text); }
        .sidebar.collapsed .nav-link svg {
            margin-right: 0;
            width: var(--dbui-icon-size) !important;
            height: var(--dbui-icon-size) !important;
            color: var(--dbui-menu-text);
        }
        .sidebar.collapsed .nav-menu { padding: 0 20px; }

        /* MAIN CONTENT */
        /*.main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; height: 100vh;}

        .top-header {
            height: var(--header-height);
            background: #ecefec;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 30px;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;

            /* mao ni ang gipono 
            z-index: 1000;
            width: 100%;
            flex-shrink: 0;
        }*/

        /*.dashboard-body { padding: var(--body-padding-top) var(--body-padding-side); }*/
        /* This is the parent of the header and the body */
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden; /* Change this to hidden to prevent the container itself from jumping */
    position: relative;
}

/* This is your header */
.top-header {
    height: var(--dbui-header-height);
    background: var(--dbui-sidebar-bg); /* Use a solid color */
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 55px 0 40px;
    border-bottom: 1px solid var(--dbui-border);
    
    /* The Fix */
    position: relative; /* Change from sticky to relative */
    z-index: 100;
    flex-shrink: 0; /* CRITICAL: Prevents jumping during scroll */
}

/* This is the new scrolling area */
.dashboard-body {
    flex: 1; /* Takes up all remaining space */
    overflow-y: auto; /* The scroll happens ONLY here now */
    /* Adjust left/right white space using the variables in :root */
    padding: var(--dbui-body-padding-top) var(--dbui-content-padding-right) var(--dbui-body-padding-top) var(--dbui-content-padding-left);
    background-color: var(--dbui-bg-primary);
}
        .dashboard-body h2 { font-size: 24px; color: var(--dbui-text-main); }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="header-left" id="logoTrigger">
                <div class="logo-box">
                    <img src="images/jh_emblem.png" alt="JHCSC Logo" class="logo-img">
                </div>
                <div class="header-text">
                    <strong>JHCSC Dumingag</strong>
                    <p>SDO Inventory</p>
                </div>
            </div>
        </div>

        <ul class="nav-menu">
            <li class="menu-label">Menu</li>
            <!-- DASHBOARD -->
            <li class="nav-item">
                <a href="#" class="nav-link active" onclick="loadModule('modules/dashboard/index.php', this); return false;">
                    <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
                </a>
            </li>
            
            <!-- INVENTORY -->
            <li class="nav-item">
                <div class="nav-link" id="inventoryToggle">
                    <i data-lucide="archive"></i>
                    <span>Inventory</span>
                    <i data-lucide="chevron-down" class="chevron-icon" style="margin-left:auto; width:14px;"></i>
                </div>
                <div class="submenu" id="inventoryMenu">
                    <a href="#" class="submenu-link" onclick="loadModule('modules/equipments/equipment.php', this); return false;">Equipment</a>
                    <a href="#" class="submenu-link" onclick="loadModule('modules/equipments/maintenance_list.php', this); return false;">Maintenance List</a>
                </div>
            </li>
            
            <!-- TRANSACTIONS -->
            <li class="nav-item">
                <div class="nav-link" id="transactionToggle">
                    <i data-lucide="repeat"></i>
                    <span>Transactions</span>
                    <i data-lucide="chevron-down" class="chevron-icon" style="margin-left:auto; width:14px;"></i>
                </div>
                <div class="submenu" id="transactionMenu">
                    <a href="#" class="submenu-link" onclick="loadModule('modules/transactions/borrow.php', this); return false;">Borrow</a>
                    <a href="#" class="submenu-link" onclick="loadModule('modules/transactions/overdue_list.php', this); return false;">Overdue List</a>
                    <a href="#" class="submenu-link" onclick="loadModule('modules/transactions/transaction_log.php', this); return false;">Transaction Log</a>
                </div>
            </li>

            <!-- ANALYTICS -->
            <li class="nav-item">
                <a href="#" class="nav-link" onclick="loadModule('modules/analytics/index.php', this); return false;">
                    <i data-lucide="bar-chart-3"></i><span>Analytics</span>
                </a>
            </li>

            <!-- SETTINGS -->
            <li class="nav-item">
                <a href="#" class="nav-link" onclick="loadModule('modules/settings/index.php', this); return false;">
                    <i data-lucide="settings"></i><span>Settings</span>
                </a>
            </li>
        </ul>

        <div class="logout-box" style="padding: 20px; border-top: 1px solid var(--dbui-border);">
            <a href="logout.php" class="nav-link" style="color: #ff4d4d;"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="header-titles">
                <h1 id="top-title" style="font-size: 14px; font-weight: 400; color: var(--dbui-text-main);">Dashboard</h1>
                <p id="top-desc" style="font-size: 12px; font-weight: 400; color: var(--dbui-text-muted); margin-top: 4px;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Today is <?php echo date('F j, Y'); ?></p>
            </div>
        </div>

        <div class="dashboard-body">
            <!-- Initial content (Dashboard Index) -->
            <?php include 'modules/dashboard/index.php'; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();

        /**
         * AJAX Module Loader
         * Loads content into .dashboard-body without refreshing the page
         */
        function loadModule(url, element = null) {
            const body = document.querySelector('.dashboard-body');
            
            // 1. Update Active States
            if (element) {
                document.querySelectorAll('.nav-link, .submenu-link').forEach(el => el.classList.remove('active'));
                element.classList.add('active');
                
                // Update Top Header Dynamic Titles
                const moduleName = element.innerText.trim();
                document.getElementById('top-title').innerText = moduleName;
                
                const topDesc = document.getElementById('top-desc');
                if (moduleName.toLowerCase() === 'dashboard') {
                    topDesc.style.display = 'block';
                } else {
                    topDesc.style.display = 'none';
                }
            }

            // 2. Fetch Content
            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(html => {
                    body.innerHTML = html;
                    
                    // MUST execute scripts manually since innerHTML doesn't evaluate them
                    const scripts = body.querySelectorAll("script");
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement("script");
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        const scriptText = document.createTextNode(oldScript.innerHTML);
                        newScript.appendChild(scriptText);
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });

                    // Re-initialize icons for the new content
                    lucide.createIcons();
                })
                .catch(error => {
                    console.error('Error loading module:', error);
                    body.innerHTML = `<div style="padding:20px; color:red;">Failed to load module: ${url}</div>`;
                });
        }

        // REUSABLE DROPDOWN FUNCTION
        const setupDropdown = (toggleId, menuId) => {
            const toggle = document.getElementById(toggleId);
            const menu = document.getElementById(menuId);
            const icon = toggle.querySelector('.chevron-icon');
            const syncChevron = () => {
                icon.style.transform = menu.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
            };

            // Ensure default state is always correct on load
            syncChevron();

            toggle.addEventListener('click', () => {
                menu.classList.toggle('open');
                syncChevron();
            });
        };

        // Initialize Dropdowns
        setupDropdown('inventoryToggle', 'inventoryMenu');
        setupDropdown('transactionToggle', 'transactionMenu');

        // SIDEBAR COLLAPSE LOGIC
        const sidebar = document.getElementById('sidebar');
        const logoTrigger = document.getElementById('logoTrigger');

        const toggleAction = () => {
            sidebar.classList.toggle('collapsed');
            if(sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.submenu').forEach(m => m.classList.remove('open'));
                document.querySelectorAll('.chevron-icon').forEach(icon => {
                    icon.style.transform = 'rotate(0deg)';
                });
            }
            lucide.createIcons();
        };

        // Logo toggles collapse/expand in both states
        logoTrigger.addEventListener('click', () => {
            toggleAction();
        });

    </script>
</body>
</html>