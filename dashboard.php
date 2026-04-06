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
            --bg-primary: #f8fafc;
            --sidebar-bg: #ecefec;
            --text-main: #1a1c1e;
            --text-light: #ffffff;
            --text-muted: black;
            --accent-blue: #8faadc;
            --hover-bg: #8faadc;
            --hover-submenu: #8faadc;
            --active-link: #0c1f3f;
            --border: white;

            /* SIZING */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 60px;
            --radius: 8px;
            --icon-size: 18px; 

            /* --- ADJUST CONTENT SPACING --- */
            --body-padding-top: 1px; 
            --body-padding-side: 1px; 
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            font-weight: 400;
        }

        body {
            background-color: var(--bg-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--text-light);
            height: 100vh;
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border-right: 1px solid var(--border);
        }

        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

        .sidebar-header {
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height); 
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .menu-trigger {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .menu-trigger:hover { color: var(--hover-bg); }

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
        .header-text strong { display: block; font-size: 14px; color: var(--text-muted); line-height: 1; }
        .header-text p { font-size: 10px; letter-spacing: 0.5px; text-transform: uppercase; color: var(--text-muted); margin-top: 4px; }

        /* NAVIGATION */
        .nav-menu { flex: 1; padding: 0 15px; margin-top: 30px; }
        .nav-item { list-style: none; margin-bottom: 4px; }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            border-radius: var(--radius);
            transition: all 0.2s;
            white-space: nowrap;
            cursor: pointer;
        }

        .nav-link svg { 
            width: var(--icon-size) !important;
            height: var(--icon-size) !important;
            margin-right: 12px; 
            stroke-width: 1.5;
        }

        .nav-link:hover { background-color: var(--hover-bg); color: var(--text-light); }
        .nav-link.active { 
            background-color: var(--active-link); 
            color: var(--text-light) !important; 
            box-shadow: inset 3px 0 0 var(--accent-blue); 
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
            padding: 8px 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .submenu-link:hover { background-color: var(--hover-submenu); color: var(--text-light); }
        .chevron-icon { transition: transform 0.3s; }
        .submenu-link.active {
            background-color: var(--active-link);
            color: var(--text-light) !important;
            box-shadow: inset 3px 0 0 var(--accent-blue);
        }

        /* COLLAPSE STATES */
        .sidebar.collapsed .header-text,
        .sidebar.collapsed .menu-trigger,
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .chevron-icon,
        .sidebar.collapsed .submenu { display: none; }
        
        .sidebar.collapsed .header-left { justify-content: center; width: 100%; }
        .sidebar.collapsed .nav-link svg { margin-right: 0; }
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

        .search-container { position: relative; width: 280px; }
        .search-container input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border-radius: var(--radius);
            border: 1px solid #e5e7eb;
            font-size: 14px;
            background: var(--bg-primary);
            outline: none;
        }
        .search-container svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

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
    height: var(--header-height);
    background: var(--sidebar-bg); /* Use a solid color */
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 55px 0 40px;
    border-bottom: 1px solid var(--border);
    
    /* The Fix */
    position: relative; /* Change from sticky to relative */
    z-index: 100;
    flex-shrink: 0; /* CRITICAL: Prevents jumping during scroll */
}

/* This is the new scrolling area */
.dashboard-body {
    flex: 1; /* Takes up all remaining space */
    overflow-y: auto; /* The scroll happens ONLY here now */
    padding: var(--body-padding-top) var(--body-padding-side);
    background-color: var(--bg-primary);
}
        .dashboard-body h2 { font-size: 24px; color: var(--text-main); }
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
            <button class="menu-trigger" id="collapseToggle">
                <i data-lucide="menu" style="width: 20px;"></i>
            </button>
        </div>

        <ul class="nav-menu">
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

        <div class="logout-box" style="padding: 20px; border-top: 1px solid var(--border);">
            <a href="logout.php" class="nav-link" style="color: #ff4d4d;"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="header-titles">
                <h1 id="top-title" style="font-size: 24px; font-weight: 700; color: var(--text-main);">Dashboard</h1>
                <p id="top-desc" style="font-size: 12px; font-weight: 400; color: var(--text-muted); margin-top: 4px;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Today is <?php echo date('F j, Y'); ?></p>
            </div>
            <div class="search-container" id="borrowerSearchContainer" style="position: relative;">
                <i data-lucide="search" style="width: 16px;"></i>
                <input type="text" id="borrowerSearchInput" placeholder="Search borrower ID or name..." autocomplete="off">
                <div id="borrowerSearchDropdown" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; max-height: 300px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);"></div>
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

            toggle.addEventListener('click', () => {
                const isOpen = menu.classList.toggle('open');
                icon.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0deg)';
            });
        };

        // Initialize Dropdowns
        setupDropdown('inventoryToggle', 'inventoryMenu');
        setupDropdown('transactionToggle', 'transactionMenu');

        // SIDEBAR COLLAPSE LOGIC
        const sidebar = document.getElementById('sidebar');
        const collapseBtn = document.getElementById('collapseToggle');
        const logoTrigger = document.getElementById('logoTrigger');

        const toggleAction = () => {
            sidebar.classList.toggle('collapsed');
            if(sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.submenu').forEach(m => m.classList.remove('open'));
            }
            lucide.createIcons();
        };

        collapseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleAction();
        });

        logoTrigger.addEventListener('click', () => {
            if(sidebar.classList.contains('collapsed')) {
                toggleAction();
            }
        });

        // ─── BORROWER SEARCH FUNCTIONALITY ───
        const borrowerSearchInput = document.getElementById('borrowerSearchInput');
        const borrowerSearchDropdown = document.getElementById('borrowerSearchDropdown');
        const borrowerSearchContainer = document.getElementById('borrowerSearchContainer');

        if (borrowerSearchInput) {
            borrowerSearchInput.addEventListener('input', async function(e) {
                const query = this.value.trim().toLowerCase();
                if (!query || query.length < 2) {
                    borrowerSearchDropdown.style.display = 'none';
                    return;
                }

                try {
                    const response = await fetch('modules/transactions/search_borrowers.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ query: query })
                    });

                    const data = await response.json();
                    if (data.success && data.borrowers && data.borrowers.length > 0) {
                        borrowerSearchDropdown.innerHTML = data.borrowers.map(b => 
                            `<div class="search-result-item" data-borrower-id="${b.borrower_id}" data-page="${b.page}" style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                                <span style="font-weight: 600;">${b.full_name} <span style="color: #64748b; font-weight: 400;">${b.department}</span></span>
                                <span style="color: #64748b; font-size: 12px;">${b.id_number}</span>
                            </div>`
                        ).join('');
                        borrowerSearchDropdown.style.display = 'block';

                        // Add click handlers
                        borrowerSearchDropdown.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', function() {
                                const borrowerId = this.dataset.borrowerId;
                                const page = this.dataset.page;
                                borrowerSearchInput.value = '';
                                borrowerSearchDropdown.style.display = 'none';
                                // Navigate to borrow page and highlight
                                loadModule('modules/transactions/borrow.php?page=' + page, null);
                                setTimeout(() => highlightBorrowerRow(borrowerId), 500);
                            });
                        });
                    } else {
                        borrowerSearchDropdown.innerHTML = `<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; cursor: default;">
                            <div>
                                <div style="font-weight: 600;">Borrower not found</div>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Click the "+ New Transaction" button to create a new borrower</div>
                            </div>
                        </div>`;
                        borrowerSearchDropdown.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    borrowerSearchDropdown.style.display = 'none';
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!borrowerSearchContainer.contains(e.target)) {
                    borrowerSearchDropdown.style.display = 'none';
                }
            });

            // Highlight borrower row
            window.highlightBorrowerRow = function(borrowerId) {
                const rows = document.querySelectorAll('.borrow-table tbody tr.main-row');
                rows.forEach(row => {
                    if (row.dataset.borrowerId && parseInt(row.dataset.borrowerId) === parseInt(borrowerId)) {
                        row.style.backgroundColor = '#dfe8f5';
                        row.style.boxShadow = 'inset 0 0 0 2px #8faadc';
                        row.style.transition = 'all 0.3s ease';
                        row.classList.add('highlighted-row');
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Clear highlight on row click or action button click
                        const clearHighlight = () => {
                            row.style.backgroundColor = '';
                            row.style.boxShadow = '';
                            row.classList.remove('highlighted-row');
                            row.removeEventListener('click', clearHighlight);
                            actionButtons.forEach(btn => btn.removeEventListener('click', clearHighlight));
                        };
                        
                        row.addEventListener('click', clearHighlight);
                        const actionButtons = row.querySelectorAll('.btn-action');
                        actionButtons.forEach(btn => btn.addEventListener('click', clearHighlight));
                    }
                });
            };
        }
    </script>
</body>
</html>