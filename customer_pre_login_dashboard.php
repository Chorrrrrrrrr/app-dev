<?php
session_start();
include 'db.php'; 

// Check if user is already logged in, redirect them if so.
if (isset($_SESSION['loggedin']) && $_SESSION['role'] == 'customer') {
    header("location: customer_dashboard.php");
    exit;
}

// Check if the request is for the menu view (using URL parameter for persistent state)
$show_full_menu = isset($_GET['show_menu']) && $_GET['show_menu'] === 'true';
$message = "";
$error = "";


// --- DYNAMIC FETCH: Get subcategories from DB (Must be present for menu view) ---
$drinks_subcategories_list = [];
$result_subcats = mysqli_query($conn, "SELECT name FROM drinks_subcategories ORDER BY sort_order ASC, name ASC");
if ($result_subcats) {
    while ($row = mysqli_fetch_assoc($result_subcats)) {
        $drinks_subcategories_list[] = strtoupper($row['name']); 
    }
}

// === Initialize array keys based on dynamic list ===
$drinks_subcategories = array_fill_keys($drinks_subcategories_list, []);
$snacks_list = [];

if ($show_full_menu) {
    // Fetch ALL products needed for the menu view, including subcategory
    $sql_products = "SELECT id, name, price, image_path, category, subcategory FROM products ORDER BY category, name ASC";
    $result_products = mysqli_query($conn, $sql_products);

    if ($result_products) {
        while ($row = mysqli_fetch_assoc($result_products)) {
            if ($row['category'] == 'drink') {
                $subcat = strtoupper($row['subcategory']);
                if (array_key_exists($subcat, $drinks_subcategories)) {
                    $drinks_subcategories[$subcat][] = $row;
                }
            } elseif ($row['category'] == 'snack') {
                $snacks_list[] = $row;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Hosana Cafe Menu</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* ========================================================================================= */
        /* === PART 1: DARK THEME BASE STYLES (LANDING PAGE - The GIF View) === */
        /* ========================================================================================= */
        html, body {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0; 
            min-height: 100vh;
        }
        
        /* 1. DARK THEME BACKGROUND (DEFAULT) */
        body {
            color: white; 
            background-image: url('uploads/bg.gif'); 
            background-size: cover; 
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed; 
            background-color: rgba(0, 0, 0, 0.7); 
            background-blend-mode: overlay; 
            transition: background-color 0.5s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: transparent; 
            padding: 0 30px 30px 30px; 
            box-shadow: none;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background 0.5s;
        }
        
        /* === TOP NAVIGATION BAR (Shared) === */
        .top-nav {
            padding: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative; 
            z-index: 10;
            width: 100%;
        }

        /* CRITICAL FIX: Center the logo on the dark theme page */
        .top-logo {
            position: absolute;
            left: 50%;
            top: 20px; 
            transform: translateX(-50%);
            flex-grow: 0; 
            z-index: 11;
        }
        
        /* Dark Theme Specific Element Colors */
        .top-logo img {
            max-width: 150px;
            height: auto;
            /* White Color & Brightness */
            filter: invert(1) brightness(0.8); 
            /* Faded/Transparent Effect & Round Shape */
            opacity: 0.8; 
            border-radius: 50%; 
            border: 3px solid rgba(255, 255, 255, 0.5); 
        }
        .header-links {
            z-index: 12; 
        }
        .header-links a { 
            color: white; 
            background-color: #8B4513; 
            padding: 8px 15px; 
            text-decoration: none;
            border-radius: 5px;
            margin-left: 10px;
        }
        
        /* Fix the empty spacer element that was confusing the flex layout: */
        .top-nav > div:first-child:not(.top-logo):not(.header-links) {
            visibility: hidden; 
            width: 1px; 
        }


        /* CENTRAL SLOGAN/LANDING AREA */
        #central-slogan {
            flex-grow: 1; 
            display: flex;
            flex-direction: column;
            justify-content: center; 
            align-items: center;
            text-align: center;
            z-index: 5;
            padding-bottom: 100px; 
            transition: opacity 0.5s;
        }
        #central-slogan h1 {
            font-size: 4.5em; 
            color: white;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.9);
            margin: 0;
            text-transform: uppercase;
        }
        #central-slogan p {
            font-size: 1.8em; 
            color: #ccc;
            margin-top: 15px;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.8);
            max-width: 600px;
        }
        
        /* Button Container Styling */
        .landing-buttons {
            margin-top: 50px; 
            display: flex;
            gap: 20px; 
            justify-content: center;
        }

        /* General Button Styling */
        .landing-buttons a, 
        .landing-buttons button { 
            display: inline-block;
            padding: 15px 30px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            text-decoration: none;
            font-size: 1.2em;
            transition: background-color 0.2s, opacity 0.2s;
            border: none;
            cursor: pointer;
        }
        
        /* Specific Button Colors */
        .landing-buttons a:first-child { /* VIEW FULL MENU */
            background-color: #5D4037; 
        }
        .landing-buttons a:first-child:hover {
            background-color: #4E342E;
        }
        .landing-buttons .contact-btn {
             background-color: #8B4513; /* CONTACT US */
        }
        .landing-buttons .contact-btn:hover {
             background-color: #793D0F;
        }
        
        /* ========================================================================================= */
        /* === PART 2: LIGHT THEME OVERRIDE (MENU ACTIVE) === */
        /* ========================================================================================= */
        
        body.menu-active {
            background-image: none !important; 
            background-color: #F8F4EF !important; 
            color: #4E342E !important; 
            padding: 0 !important;
        }
        
        body.menu-active .container {
             max-width: none !important; 
             margin: 0 auto; 
             width: 100%;
             background: white !important; 
             box-shadow: none !important; 
             padding: 0 0 30px 0 !important; 
        }
        
        body.menu-active .top-nav,
        #menu-content-wrapper {
            padding-left: 30px;
            padding-right: 30px;
            box-sizing: border-box; 
        }
        
        body.menu-active .top-nav {
            background: white; 
            padding-top: 15px;
            padding-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 50;
            margin-bottom: 30px; 
            
            justify-content: flex-end; 
            width: 100%;
            margin-left: 0;
            margin-right: 0;
        }
        
        /* Menu Active Logo Centering */
        body.menu-active .top-nav .top-logo {
            position: absolute;
            left: 50%;
            top: 50%; 
            transform: translate(-50%, -50%);
            z-index: 51; 
            flex-grow: 0; 
            text-align: center; 
        }

        body.menu-active .top-nav .top-logo img {
            max-width: 130px !important; 
            filter: none; 
            opacity: 1; 
            border-radius: 0; /* Remove roundness in light theme */
            border: none;
        }
        
        /* Links to the right */
        body.menu-active .header-links {
            text-align: right; 
            display: flex;
            justify-content: flex-end;
        }
        
        /* Placeholder for Left Side (Order 1) */
        body.menu-active .top-nav > div:first-child:not(.top-logo):not(.header-links) {
             flex-basis: 1px; 
             flex-grow: 1; 
        }

        body.menu-active .header-links a {
            color: #4E342E !important; 
            background: transparent;
            border: 1px solid #E0E0E0;
            padding: 9px 14px;
            margin-left: 5px !important; 
            margin-right: 0 !important;
        }
        
        /* ---------------------------------------------------- */
        /* --- MESSAGE STYLES (Kept for potential general errors) --- */
        .message-container {
            padding: 10px 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            font-weight: bold;
            text-shadow: none;
            display: inline-block;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6fb;
            font-weight: bold;
            text-shadow: none;
            display: inline-block;
        }
        /* ---------------------------------------------------- */
        
        #menu-content-wrapper {
            padding-top: 0; 
        }
        
        #menu-content-wrapper h2, 
        #menu-content-wrapper h3 {
            color: #4E342E; 
            text-shadow: none; 
            border-bottom: 2px solid #E0E0E0; 
            padding-bottom: 5px;
            margin-top: 20px;
        }
        
        #menu-content-wrapper h2 {
            text-align: center;
        }
        
        /* Subcategory filter container */
        .sub-filter-container {
            margin: 10px 0 20px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        .sub-filter-container .filter-btn {
            padding: 8px 15px; 
            font-size: 0.9em;
            margin: 0;
        }
        .sub-category-header {
            color: #8B4513;
            border-bottom: 1px solid #E0E0E0;
            margin-top: 15px;
            padding-bottom: 5px;
            font-size: 1.1em;
            font-weight: bold;
        }
        
        .menu-filters {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .filter-btn {
            background-color: #D7CCC8; 
            color: #4E342E;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .filter-btn:hover {
            background-color: #BCAAA4;
        }

        .filter-btn.active {
            background-color: #8B4513; 
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 40px; 
        }

        .product-card {
            background: white; 
            border: 1px solid #E0E0E0;
            width: 100%; 
            padding:30px 10px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            border-color: #8B4513; 
        }

        .product-card h4 {
            margin: 0 0 5px 0;
            font-size: 1.25em; 
            color: #4E342E;
            font-weight: bold; 
            padding-bottom: 0;
        }
        
        .product-card p {
            font-size: 1.3em;
            color: #8B4513; 
            font-weight: bold;
            margin: 5px 0 15px 0;
        }

        .product-image {
            width: 120px; 
            height: 120px;
            object-fit: cover; 
            border-radius: 8px; 
            margin: 0 auto 10px auto; 
            display: block; 
            border: 2px solid #E0E0E0;
        }
        
        .guest-action {
            background-color: #D7CCC8;
            color: #4E342E;
            border: none;
            padding: 10px 5px;
            border-radius: 5px;
            width: 100%;
            font-size: 14px;
            font-weight: bold;
            cursor: default;
            transition: background-color 0.2s;
        }
        .guest-action:hover {
            background-color: #BCAAA4;
        }
    </style>
</head>
<body class="<?php echo $show_full_menu ? 'menu-active' : ''; ?>">

<div class="container">
    
    <div class="top-nav">
        <div style="flex-basis: 1px; flex-grow: 1;"></div> 

        <div class="top-logo">
            <a href="customer_pre_login_dashboard.php">
                <img src="hosana.png" alt="Hosana Cafe Logo">
            </a>
        </div>
        
        <div class="header-links">
            <a href="customer_login.php">LOGIN</a>
            <a href="customer_register.php">REGISTER</a>
        </div>
    </div>
    
    <div id="central-slogan" style="display: <?php echo $show_full_menu ? 'none' : 'flex'; ?>;">
        <h1>HOSANNA CAFE</h1>
        <p>Specialty Coffee, Made Affordable.</p>
        
        <div class="landing-buttons">
            <a href="customer_pre_login_dashboard.php?show_menu=true">
                VIEW FULL MENU
            </a>
            <a href="https://www.facebook.com/profile.php?id=61554159952061" target="_blank" class="contact-btn">
                CONTACT US
            </a>
        </div>
    </div>
    
    <div id="menu-content-wrapper" style="display: <?php echo $show_full_menu ? 'block' : 'none'; ?>;">
        
        <?php 
        // No inquiry messages to display here
        ?>
        
        <h2 style="text-align: center;">OUR MENU</h2>

        <div class="menu-nav-bar">
            <div class="menu-filters">
                <button class="filter-btn active" id="btnDrinks" onclick="showMainCategory('drinks')">☕ Drinks Menu</button>
                <button class="filter-btn" id="btnSnacks" onclick="showMainCategory('snacks')">🍩 Snacks & Pastries</button>
            </div>
        </div>
        
        <div id="drinks-section">
            <h3>☕ Drinks Menu</h3>
            
            <div class="sub-filter-container">
                <?php 
                $first_subcat = true;
                if (!empty($drinks_subcategories_list)):
                    foreach ($drinks_subcategories_list as $subcat): 
                        // Sanitize name for use as a JavaScript/HTML slug ID
                        $subcat_slug = strtolower(str_replace(' ', '-', $subcat)); 
                    ?>
                        <button class="filter-btn sub-btn <?php echo $first_subcat ? 'active' : ''; ?>" 
                                id="sub-<?php echo $subcat_slug; ?>" 
                                onclick="showSubCategory('<?php echo $subcat_slug; ?>')">
                            <?php echo htmlspecialchars($subcat); ?>
                        </button>
                    <?php 
                        $first_subcat = false;
                    endforeach; 
                else:
                    echo "<p style='padding: 5px; color: #8B4513;'>No subcategories defined.</p>";
                endif;
                ?>
            </div>
            
            <?php 
            $first_subcat = true;
            foreach ($drinks_subcategories as $subcat_name => $products): 
                $subcat_id = strtolower(str_replace(' ', '-', $subcat_name));
            ?>
                <div class="sub-category-list" id="list-<?php echo $subcat_id; ?>" style="display: <?php echo $first_subcat ? 'block' : 'none'; ?>;">
                    <div class="sub-category-header"><?php echo htmlspecialchars($subcat_name); ?></div>
                    <div class="product-list">
                        <?php 
                        if (!empty($products)) {
                            foreach($products as $row):
                                $image_file = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'placeholder.png';
                            ?>
                            <div class="product-card">
                                <img src="./product_images/<?php echo $image_file; ?>" 
                                    alt="<?php echo htmlspecialchars($row['name']); ?>" 
                                    class="product-image">

                                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                                <p>₱ <?php echo number_format($row['price'], 2); ?></p>
                                
                                <button class="guest-action">Login to Order</button>
                            </div>
                        <?php endforeach; 
                        } else {
                            echo "<p style='color: #8B4513;'>No " . htmlspecialchars($subcat_name) . " drinks are currently available.</p>";
                        }
                        ?>
                    </div>
                </div>
            <?php 
                $first_subcat = false;
            endforeach; 
            ?>
        </div>
        
        <div id="snacks-section" style="display: none;">
            <h3>🍩 Snacks & Pastries</h3>
            <div class="product-list">
                <?php 
                if (!empty($snacks_list)) {
                    foreach($snacks_list as $row):
                        $image_file = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'placeholder.png';
                    ?>
                    <div class="product-card">
                        <img src="./product_images/<?php echo $image_file; ?>" 
                            alt="<?php echo htmlspecialchars($row['name']); ?>" 
                            class="product-image">

                        <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                        <p>₱ <?php echo number_format($row['price'], 2); ?></p>
                        
                        <button class="guest-action">Login to Order</button>
                    </div>
                <?php endforeach;
                } else {
                    echo "<p style='color: #8B4513;'>No snacks are currently available.</p>";
                }
                ?>
            </div>
        </div>
    </div> </div>

<script>
    // --- Filtering Logic (No changes needed) ---
    function showMainCategory(category) {
        const drinksSection = document.getElementById('drinks-section');
        const snacksSection = document.getElementById('snacks-section');
        const btnDrinks = document.getElementById('btnDrinks');
        const btnSnacks = document.getElementById('btnSnacks');

        if (category === 'drinks') {
            drinksSection.style.display = 'block';
            snacksSection.style.display = 'none';
            btnDrinks.classList.add('active');
            btnSnacks.classList.remove('active');
            
            // Show the first subcategory list by default when drinks is selected
            const firstSubcatButton = document.querySelector('#drinks-section .sub-btn');
            if (firstSubcatButton) {
                const subcatId = firstSubcatButton.id.replace('sub-', '');
                showSubCategory(subcatId); 
            }
        } else if (category === 'snacks') {
            drinksSection.style.display = 'none';
            snacksSection.style.display = 'block';
            btnDrinks.classList.remove('active');
            btnSnacks.classList.add('active');
        }
    }

    function showSubCategory(subcat_id) {
        // Hide all subcategory lists
        document.querySelectorAll('.sub-category-list').forEach(list => {
            list.style.display = 'none';
        });

        // Show the selected subcategory list
        const selectedList = document.getElementById('list-' + subcat_id);
        if (selectedList) {
            selectedList.style.display = 'block';
        }

        // Update active class for subcategory buttons
        document.querySelectorAll('.sub-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const selectedButton = document.getElementById('sub-' + subcat_id);
        if (selectedButton) {
            selectedButton.classList.add('active');
        }
    }

    // === INITIAL LOAD Check ===
    window.onload = function() {
        const menuWrapper = document.getElementById('menu-content-wrapper');
        if (menuWrapper.style.display !== 'none') {
            showMainCategory('drinks');
        }
    
        // No inquiry message check needed here anymore
    };
</script>
</body>
</html>