<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

if (!isset($_SESSION['online_cart'])) {
    $_SESSION['online_cart'] = [];
}

// --- PHP LOGIC TO HANDLE QUANTITY INPUT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if ($quantity <= 0) {
        $quantity = 1;
    }

    if (isset($_SESSION['online_cart'][$product_id])) {
        $_SESSION['online_cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['online_cart'][$product_id] = [
            'name' => $product_name,
            'price' => $price,
            'quantity' => $quantity
        ];
    }
    header("location: customer_dashboard.php");
    exit;
}
// --- END OF PHP LOGIC ---


// === DYNAMIC FETCH: Get subcategories from DB ===
$drinks_subcategories_list = [];
// CRITICAL: Fetching names dynamically from the table the Admin manages
$result_subcats = mysqli_query($conn, "SELECT name FROM drinks_subcategories ORDER BY sort_order ASC, name ASC");
if ($result_subcats) {
    while ($row = mysqli_fetch_assoc($result_subcats)) {
        // Storing names in uppercase to match product grouping convention
        $drinks_subcategories_list[] = strtoupper($row['name']); 
    }
}

// === FETCH PRODUCTS GROUPED BY SUBCATEGORY ===
// Initialize dynamic array keys based on the fetched list
$drinks_subcategories = array_fill_keys($drinks_subcategories_list, []);
$snacks_list = [];

// Fetch ALL products needed for the menu view
$sql_products = "SELECT id, name, price, image_path, category, subcategory FROM products ORDER BY category, name ASC";
$result_products = mysqli_query($conn, $sql_products);

if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        if ($row['category'] == 'drink') {
            $subcat = strtoupper($row['subcategory']);
            // CRITICAL: Checking existence in the dynamically generated list
            if (array_key_exists($subcat, $drinks_subcategories)) {
                $drinks_subcategories[$subcat][] = $row;
            }
        } elseif ($row['category'] == 'snack') {
            $snacks_list[] = $row;
        }
    }
}


// Calculate Total Cart
$total_cart = 0;
foreach ($_SESSION['online_cart'] as $item) {
    $total_cart += $item['price'] * $item['quantity'];
}

$user_id_sql = "SELECT id FROM users WHERE username='{$_SESSION['username']}'";
$user_id_result = mysqli_query($conn, $user_id_sql);
$user_id_row = mysqli_fetch_assoc($user_id_result);
$user_id = $user_id_row['id'];

// Get username for the modal
$current_username = htmlspecialchars($_SESSION['username']); 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================================== */
        /* === CORE LAYOUT STYLES: WHITE CONTAINER ON LIGHT BODY === */
        /* ==================================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #4E342E; 
            margin: 0;
            padding: 20px; 
            
            background-color: #F8F4EF; 
            background-image: none;
            background-blend-mode: normal; 
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); 
            min-height: auto;
        }

        /* === HEADER STYLES: DARK ON WHITE === */
        h2, h3 {
            color: #4E342E; 
            text-shadow: none; 
            border-bottom: 2px solid #E0E0E0; 
            padding-bottom: 5px;
            margin-top: 20px;
        }

        p a { 
            color: #8B4513; 
            text-decoration: none;
            font-weight: bold;
            padding: 5px;
            text-shadow: none;
        }

        p a:hover {
            color: #5D4037;
            text-decoration: underline;
        }
        /* ============================================= */

        /* === NEW BUTTON AND FILTER STYLES === */
        .menu-filters {
            margin: 20px 0 30px 0;
            text-align: center;
        }

        .filter-btn {
            background-color: #D7CCC8; /* Light Brown/Tan */
            color: #4E342E;
            border: none;
            padding: 12px 25px;
            margin: 0 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .filter-btn:hover {
            background-color: #BCAAA4;
        }

        .filter-btn.active {
            background-color: #8B4513; /* Dark Brown Active */
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        /* ==================================== */
        /* --- NEW SUBCATEGORY STYLES (Copied from Barista) --- */
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
            padding: 8px 15px; /* Smaller button for subcategories */
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
        /* -------------------------------------------------- */


        .online-cart-section {
            background-color: #fcfcfc; 
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin: 20px 0; 
        }

        .online-cart-section h4 {
            border-bottom: none;
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
            border-bottom: none;
            padding-bottom: 0;
            font-weight: bold;
        }
        
        .product-card p {
            font-size: 1.3em;
            color: #8B4513; 
            font-weight: bold;
            margin: 5px 0 15px 0;
        }

        .quantity-input {
            width: 50px; 
            padding: 5px; 
            margin: 5px auto 10px auto; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            text-align: center;
            display: block; 
        }
        
        .product-card input[type="submit"] {
            width: 100%;
            padding: 10px 5px;
            background-color: #66BB6A; /* Green button for Add to Cart */
        }
        .product-card input[type="submit"]:hover {
            background-color: #4CAF50;
        }

        input[type="submit"], button {
            background-color: #8B4513; 
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .online-cart-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .online-cart-section table th, .online-cart-section table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .online-cart-section table th {
            background-color: #f2f2f2;
        }
        
        /* === MODAL CSS FOR CENTERING === */
        #welcomeModal {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4); 
            
            display: flex;
            align-items: center; 
            justify-content: center; 
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .modal-content h3 {
            color: #8B4513;
            border-bottom: 2px solid #E0E0E0;
            padding-bottom: 10px;
            margin-top: 0;
            text-shadow: none;
        }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 5px;
            right: 15px;
            cursor: pointer;
        }

        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div id="welcomeModal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <img src="hosana.png" alt="Hosana Cafe Logo" style="max-width: 50%; margin-bottom: 10px;">
        <h3>Welcome!</h3>
        <p style="font-size: 1.2em;">Hello **<?php echo $current_username; ?>**! </p>
        <p>Enjoy your ordering experience at Hosana Cafe.</p>
        <button onclick="closeModal()" style="margin-top: 15px;">Continue</button>
    </div>
</div>
<div class="container">
    
    <h2>Welcome, <?php echo $current_username; ?>!</h2>
    <p>
        <a href="update_customer_account.php">Update Account</a> | 
        <a href="customer_order_history.php">Order History</a> | 
        <a href="logout_customer.php">Logout</a>
    </p>

    <div class="online-cart-section">
        <h3>Your Online Cart</h3>
        <table border="1">
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
            <?php
            if (empty($_SESSION['online_cart'])) {
                echo "<tr><td colspan='4'>Your cart is empty.</td></tr>";
            } else {
                foreach ($_SESSION['online_cart'] as $item) {
                    $subtotal = $item['price'] * $item['quantity'];
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                    echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
                    echo "<td>₱ " . number_format($subtotal, 2) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </table>

        <br>
        <h4>Cart Total: ₱ <?php echo number_format($total_cart, 2); ?></h4>

        <?php if ($total_cart > 0): ?>
            <form action="place_order.php" method="post">
                <input type="hidden" name="total_amount" value="<?php echo $total_cart; ?>">
                <input type="submit" name="place_order" value="Proceed to Checkout">
            </form>
        <?php endif; ?>
    </div>
    
    <hr style="border-top: 1px solid #E0E0E0; margin: 30px 0;">
    
    <div class="menu-filters">
        <button class="filter-btn active" id="btnDrinks" onclick="showMainCategory('drinks')">☕ Drinks Menu</button>
        <button class="filter-btn" id="btnSnacks" onclick="showMainCategory('snacks')">🍩 Snacks & Pastries</button>
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
                            
                            <form action="" method="post">
                                <label for="qty-<?php echo $row['id']; ?>" style="font-size: 0.9em; display: block; margin-bottom: 5px;">Quantity:</label>
                                <input type="number" 
                                        id="qty-<?php echo $row['id']; ?>" 
                                        name="quantity" 
                                        value="1" 
                                        min="1" 
                                        class="quantity-input" 
                                        required>
                                <br>
                                
                                <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['name']); ?>">
                                <input type="hidden" name="price" value="<?php echo $row['price']; ?>">
                                
                                <input type="submit" name="add_to_cart" value="Add to Cart">
                            </form>
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
                    
                    <form action="" method="post">
                        <label for="qty-<?php echo $row['id']; ?>" style="font-size: 0.9em; display: block; margin-bottom: 5px;">Quantity:</label>
                        <input type="number" 
                                id="qty-<?php echo $row['id']; ?>" 
                                name="quantity" 
                                value="1" 
                                min="1" 
                                class="quantity-input" 
                                required>
                        <br>
                        
                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['name']); ?>">
                        <input type="hidden" name="price" value="<?php echo $row['price']; ?>">
                        
                        <input type="submit" name="add_to_cart" value="Add to Cart">
                    </form>
                </div>
            <?php endforeach;
            } else {
                echo "<p>No snacks are currently available.</p>";
            }
            ?>
        </div>
    </div>


    <br style="clear:both;">
    <br style="clear:both;">
</div>

<script>
    const modal = document.getElementById('welcomeModal');
    
    // --- Modal Functions ---
    function closeModal() {
        modal.style.display = 'none';
        localStorage.removeItem('showWelcomeAlert'); 
    }

    // --- Filtering Logic ---
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

    // === INITIAL LOAD & MODAL DISPLAY ===
    window.onload = function() {
        // 1. Show the Drinks category and its first subcategory list by default
        showMainCategory('drinks');
    };
</script>
</body>
</html>