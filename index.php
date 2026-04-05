<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JHCSC SDO Inventory System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/all.min.css">
   
    <style>
        :root {
            --primary-blue: #151515;
            --text-dark: #1a1a1a;
            --text-light: #ffffff;
            /* FONT SIZES */
            --font-size-base: 16px;   /* Standard reading size */
            --font-size-ui: 14px;     /* Button/Nav size */
            /* WEIGHTS */
            --weight-reg: 400;
            --weight-bold: 700;
            /* LAYOUT */
            --border-radius: 8px;
            --side-padding: 5%;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff;
            color: var(--text-dark);
            line-height: 1.6;
            font-size: var(--font-size-base); /* Uniform 16px base */
            font-weight: var(--weight-reg);   /* Uniform 400 weight */
        }

        /* NAVIGATION */
        nav {
            position: fixed;
            top: 0; left: 0; width: 100%;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 20px var(--side-padding);
            z-index: 1000;
            transition: all 0.3s ease;
            background-color: transparent;
        }

        nav.scrolled {
            background-color: #ffffff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 12px var(--side-padding);
        }

        nav.scrolled .btn-staff {
            background-color: var(--primary-blue);
            color: white;
        }

        nav.scrolled a, nav.scrolled .campus-label { color: var(--text-dark); }

        .campus-label {
            color: var(--text-light);
            font-size: 16px; /* Standalone brand size */
            font-weight: var(--weight-bold);
            text-transform: uppercase;
        }

        .nav-mid a {
            text-decoration: none;
            color: var(--text-light);
            margin: 0 15px;
            font-size: var(--font-size-ui); /* 14px for UI */
            font-weight: var(--weight-reg); /* 400 Weight */
        }

        .btn-staff {
            background-color: var(--primary-blue);
            color: var(--text-light);
            padding: 10px 22px;
            border-radius: var(--border-radius);
            font-size: var(--font-size-ui); /* 14px for UI */
            font-weight: var(--weight-reg); /* 400 Weight */
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        /* HERO SECTION */
        .hero {
            height: 100vh;
            width: 100%;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('your-image.jpg');
            background-size: cover;
            background-position: center;
            display: flex; align-items: center;
            padding: 0 var(--side-padding);
        }

        .hero-content { max-width: 800px; color: var(--text-light); }

        .college-tag {
            font-size: var(--font-size-ui); /* 14px for UI */
            font-weight: var(--weight-bold);
            display: flex; align-items: center; gap: 15px;
            text-transform: uppercase; margin-bottom: 10px;
        }

        .college-tag::before { content: ""; width: 60px; height: 2px; background: var(--text-light); }

        .hero-content h1 {
            font-size: 4.5rem;
            line-height: 1.1;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-weight: var(--weight-bold);
        }

        .hero-content .hero-description {
    font-size: var(--font-size-base) !important;
    font-weight: 400 !important;
    font-style: normal !important;
    -webkit-font-smoothing: antialiased; /* Makes Inter look cleaner */
    display: block;
    margin-bottom: 35px !important;
}

        .btn-public {
            background-color: transparent;
            color: var(--text-light);
            border: 1px solid var(--text-light);
            padding: 12px 28px;
            border-radius: var(--border-radius);
            font-size: var(--font-size-ui); /* 14px for UI */
            font-weight: var(--weight-reg); /* 400 Weight */
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 10px;
            transition: all 0.3s;
        }

        .btn-public:hover {
            background-color: var(--text-light);
            color: var(--text-dark);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .hero-content h1 { font-size: 2.8rem; }
            .nav-mid { display: none; }
        }

        .section-padding {
            padding: 120px var(--side-padding);
            min-height: 100vh;
        }

        .section-padding h2 {
            font-size: 2rem;
            margin-bottom: 20px;
            font-weight: var(--weight-bold);
            color: var(--primary-blue);
        }
        
        .section-padding p {
            font-size: var(--font-size-base); /* 16px */
            max-width: 750px;
        }
    </style>
</head>
<body>

    <nav id="navbar">
        <div class="nav-left"><span class="campus-label">JHCSC DUMINGAG CAMPUS</span></div>
        <div class="nav-mid">
            <a href="#home">Home</a>
            <a href="#loan">Loan</a>
            <a href="#maintenance">Maintenance</a>
            <a href="#reports">Reports</a>
        </div>
        <div class="nav-right">
            <a href="javascript:void(0)" class="btn-staff" onclick="toggleModal()">Staff Portal</a>
        </div>
    </nav>

    <section class="hero" id="home">
        <div class="hero-content">
            <p class="college-tag">JH CERILLES STATE COLLEGE</p>
            <h1>Sports Equipment<br>Inventory System</h1>
            <p class="hero-description">
                Institutional excellence in athletic resource management. A centralized ecosystem for inventory accountability, equipment maintenance, and campus-wide logistics for Dumingag Campus.
            </p>
            <a href="#catalog" class="btn-public">Public Catalog <i class="fa-solid fa-layer-group"></i></a>
        </div>
    </section>

    <section class="section-padding" id="loan">
        <h2>Equipment Loan</h2>
        <p>This section handles tracking and active management of athletic gear distribution.</p>
    </section>

    <section class="section-padding" id="maintenance" style="background:#f9f9f9;">
        <h2>Maintenance</h2>
        <p>Manage repair cycles and equipment safety inspections here.</p>
    </section>

    <section class="section-padding" id="reports">
        <h2>Reports</h2>
        <p>Generate data-driven analytics and inventory summaries for campus logistics.</p>
    </section>

    <?php include 'modals/login.php'; ?>

    <script>
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        });

        function toggleModal() {
            const modal = document.getElementById('authModal');
            if(modal) modal.classList.add('active');
        }

        function closeModal(e) {
            const modal = document.getElementById('authModal');
            if(modal) modal.classList.remove('active');
        }
    </script>
</body>
</html>