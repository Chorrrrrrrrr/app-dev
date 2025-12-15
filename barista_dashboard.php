<?php
session_start();
include 'db.php'; // Include your database connection

// === 1. Authorization Check ===
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// === 2. Handle POST Requests (Add to Cart, Process Payment, Remove) ===
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Add to Cart ---
    if (isset($_POST['add_to_cart'])) {
        $product_id = $_POST['product_id'];
        $product_name = $_POST['product_name'];
        $price = $_POST['price'];

        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1; 

        if ($quantity <= 0) {
            $quantity = 1;
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product_name,
                'price' => $price,
                'quantity' => $quantity
            ];
        }
    }

    // --- Process Payment ---
    if (isset($_POST['process_payment'])) {
        $total_amount = filter_var($_POST['total_amount'], FILTER_VALIDATE_FLOAT);
        $amount_received = filter_var($_POST['amount_received'], FILTER_VALIDATE_FLOAT);
        
        if ($total_amount === false || $total_amount <= 0 || $amount_received === false || $amount_received < $total_amount) {
            $_SESSION['message_error'] = "Invalid payment data or amount received is less than total.";
            header("location: barista_dashboard.php");
            exit;
        }
        
        $change_amount = $amount_received - $total_amount;

        // Get Barista User ID
        $username = $_SESSION['username'];
        $stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt_user, "s", $username);
        mysqli_stmt_execute($stmt_user);
        $user_id_result = mysqli_stmt_get_result($stmt_user);
        $user_id_row = mysqli_fetch_assoc($user_id_result);
        $user_id = $user_id_row['id'];
        mysqli_stmt_close($stmt_user);

        // 1. Insert Transaction
        $sql_transaction = "INSERT INTO transactions (user_id, total_amount, amount_paid) VALUES (?, ?, ?)";
        $stmt_transaction = mysqli_prepare($conn, $sql_transaction);
        mysqli_stmt_bind_param($stmt_transaction, "idd", $user_id, $total_amount, $amount_received);

        if (mysqli_stmt_execute($stmt_transaction)) {
            $transaction_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_transaction);

            // 2. Insert Order Items
            $success_items = true;
            $sql_item = "INSERT INTO order_items (transaction_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $stmt_item = mysqli_prepare($conn, $sql_item);

            foreach ($_SESSION['cart'] as $product_id => $item) {
                $product_id_safe = (int)$product_id;
                $quantity_safe = (int)$item['quantity'];
                $price_safe = filter_var($item['price'], FILTER_VALIDATE_FLOAT);

                if ($price_safe === false) {
                    $success_items = false;
                    break;
                }

                mysqli_stmt_bind_param($stmt_item, "iiid", $transaction_id, $product_id_safe, $quantity_safe, $price_safe);
                
                if (!mysqli_stmt_execute($stmt_item)) {
                    $success_items = false;
                    break;
                }
            }
            mysqli_stmt_close($stmt_item);

            // 3. Finalize
            if ($success_items) {
                $_SESSION['last_transaction'] = [
                    'id' => $transaction_id,
                    'total' => $total_amount,
                    'received' => $amount_received,
                    'change' => $change_amount,
                    'items' => $_SESSION['cart']
                ];
                unset($_SESSION['cart']);
                $_SESSION['message_success'] = "Transaction completed successfully! Change: ₱ " . number_format($change_amount, 2);
                header("location: barista_dashboard.php");
            } else {
                // Rollback: Delete the transaction if items failed to save
                mysqli_query($conn, "DELETE FROM transactions WHERE id = $transaction_id");
                $_SESSION['message_error'] = "Transaction error: Failed to save order items.";
                header("location: barista_dashboard.php");
            }

        } else {
            $_SESSION['message_error'] = "Error processing payment: " . mysqli_error($conn);
            header("location: barista_dashboard.php");
        }
        exit;
    }

    // --- Remove from Cart ---
    if (isset($_POST['remove_from_cart'])) {
        $product_id = $_POST['product_id'];
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }
}

// === DYNAMIC FETCH: Get subcategories from DB ===
$drinks_subcategories_list = [];
$result_subcats = mysqli_query($conn, "SELECT name FROM drinks_subcategories ORDER BY sort_order ASC, name ASC");
if ($result_subcats) {
    while ($row = mysqli_fetch_assoc($result_subcats)) {
        // Store names in uppercase to match product grouping convention
        $drinks_subcategories_list[] = strtoupper($row['name']); 
    }
}

// === 3. Fetch Data Grouped by Category & Calculate Total (MODIFIED FOR SUBCATEGORIES) ===
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
            // CRITICAL: Check if the product's subcategory exists in the dynamically fetched list
            if (array_key_exists($subcat, $drinks_subcategories)) {
                $drinks_subcategories[$subcat][] = $row;
            }
        } elseif ($row['category'] == 'snack') {
            $snacks_list[] = $row;
        }
    }
}

$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Hossana Cafe POS Dashboard</title>
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES (White & Brown)
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8F4EF; /* Very light tan background */
            color: #4E342E; /* Dark espresso brown text */
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h1, h2, h3, h4 { /* Added h1 here for the menu titles */
            color: #4E342E; 
            border-bottom: 2px solid #E0E0E0;
            padding-bottom: 5px;
            margin-top: 20px;
        }
        h1 { /* Making main menu titles look distinct */
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        a {
            color: #8B4513; 
            text-decoration: none;
            font-weight: bold;
            padding: 5px;
        }

        a:hover {
            color: #5D4037;
            text-decoration: underline;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        
        /* === NEW BUTTON AND FILTER STYLES (Copied from Customer Dashboard) === */
        .menu-filters {
            margin: 20px 0 30px 0;
            text-align: left; 
        }

        .filter-btn {
            background-color: #D7CCC8; /* Light Brown/Tan */
            color: #4E342E;
            border: none;
            padding: 12px 25px;
            margin-right: 10px; 
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
        /* =================================================================== */
        /* --- NEW SUBCATEGORY STYLES --- */
        .sub-filter-container {
            margin: 10px 0 20px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 10px;
        }
        .sub-filter-container .filter-btn {
            padding: 8px 15px; /* Smaller button for subcategories */
            font-size: 0.9em;
            margin: 0;
        }


        /* ==================================
           2. PRODUCT IMAGE & CARD STYLES
           ================================== */

        .product-image {
            width: 100px; 
            height: 100px; 
            border-radius: 8px; 
            margin: 0 auto 10px auto; 
            display: block; 
            object-fit: cover; 
            border: 1px solid #ccc;
        }
        
        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 40px; 
        }
    
        .product-card {
            background: #FDFDFD;
            border: 1px solid #E0E0E0;
            padding: 15px 10px; 
            max-width: 200px; 
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
            font-size: 1.1em;
            color: #4E342E;
            border-bottom: none;
            padding-bottom: 0;
        }

        .product-card p {
            font-size: 1.3em;
            color: #8B4513; 
            font-weight: bold;
            margin: 5px 0 15px 0;
        }
        
        .product-card input[type="submit"] {
            width: 100%;
            padding: 10px 5px;
            background-color: #66BB6A; /* Green button for Add to Cart */
        }
        .product-card input[type="submit"]:hover {
            background-color: #4CAF50;
        }
        
        /* Input Styles */
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

        input[type="submit"]:hover, button:hover {
            background-color: #5D4037; 
        }

        input[name="amount_received"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 150px;
            text-align: right;
            margin-left: 10px;
        }

        input[value="Clear Cart"] {
            background-color: #D32F2F !important; 
        }
        input[value="Clear Cart"]:hover {
            background-color: #C62828 !important;
        }

        /* ==================================
           3. MESSAGES & TABLES
           ================================== */

        .message-success {
            background-color: #E8F5E9; 
            color: #4CAF50; 
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #C8E6C9;
        }

        .message-error {
            background-color: #FFEBEE;
            color: #E57373;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #FFCDD2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #E0E0E0;
        }

        table th {
            background-color: #F5F5F5;
            color: #4E342E;
            font-weight: bold;
        }
        /* Style for subcategory headers */
        .sub-category-header {
            color: #8B4513;
            border-bottom: 1px solid #E0E0E0;
            margin-top: 15px;
            padding-bottom: 5px;
            font-size: 1.1em;
            font-weight: bold;
        }
        
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!  </h2>
    <p>
        <a href="barista_OOH.php">Online Order History</a> | 
        <a href="barista_TH.php">POS Transaction History</a> | 
        <a href="petty_cash.php">Petty Cash</a> | 
        <a href="logout.php">Logout</a>
    </p>

    <?php if (isset($_SESSION['message_success'])): ?>
        <p class="message-success"><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['message_error'])): ?>
        <p class="message-error"><?php echo $_SESSION['message_error']; unset($_SESSION['message_error']); ?></p>
    <?php endif; ?>

    <div class="clearfix">
        <div style="float:left; width: 58%;">

            <div class="menu-filters">
                <button class="filter-btn active" id="btnDrinks" onclick="showMainCategory('drinks')">☕ Drinks Menu</button>
                <button class="filter-btn" id="btnSnacks" onclick="showMainCategory('snacks')">🍩 Snacks & Pastries</button>
            </div>
            
            <div id="drinks-section">
                <h1>☕ Drinks Menu</h1>
                
                <div class="sub-filter-container">
                    <?php 
                    $first_subcat = true;
                    // Check if there are any subcategories fetched
                    if (!empty($drinks_subcategories_list)):
                        foreach ($drinks_subcategories_list as $subcat): 
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
                        echo "<p style='padding: 5px;'>No subcategories defined. Please inform the Admin.</p>";
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
                <h1>🍩 Snacks & Pastries</h1>
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

        </div>

        <div style="float:right; width: 40%;">
            <h3>Current POS Cart</h3>
            <?php if (empty($_SESSION['cart'])): ?>
                <p>Cart is empty. Add items to begin a transaction.</p>
            <?php else: ?>
                <table border="1">
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    foreach ($_SESSION['cart'] as $item_id => $item) {
                        $subtotal = $item['price'] * $item['quantity'];
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                        echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
                        echo "<td>₱ " . number_format($subtotal, 2) . "</td>";
                        echo "<td>
                            <form action='' method='post' style='display:inline;'>
                                <input type='hidden' name='product_id' value='" . $item_id . "'>
                                <input type='submit' name='remove_from_cart' value='Remove' style='background-color:#E57373; padding: 5px 8px; font-size: 12px;'>
                            </form>
                        </td>";
                        echo "</tr>";
                    }
                    ?>
                </table>

                <br>
                <h4 style="font-size: 1.5em; text-align: right; border-bottom: none;">Total: ₱ <?php echo number_format($total, 2); ?></h4>

                <?php if ($total > 0): ?>
                <div style="margin-top: 20px; padding: 15px; background-color: #FDFDFD; border: 1px solid #E0E0E0; border-radius: 5px;">
                    <form action="" method="post">
                        <input type="hidden" name="total_amount" value="<?php echo $total; ?>">
                        <label for="amount_received">Amount Received:</label>
                        <input type="number" step="0.01" id="amount_received" name="amount_received" value="<?php echo number_format($total, 2, '.', ''); ?>" min="<?php echo $total; ?>" required><br><br>
                        <input type="submit" name="process_payment" value="Process Payment">
                    </form>
                    <form action="barista_cart_management.php" method="post" style="margin-top: 10px;">
                        <input type="hidden" name="clear_cart" value="1">
                        <input type="submit" value="Clear Cart">
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['last_transaction'])): ?>
            <div class="receipt" style="margin-top: 30px; padding: 20px; background-color: #EFEBE9; border-radius: 5px;">
                <h4 style="border-bottom: 1px solid #D7CCC8;">Last Transaction Complete</h4>
                <p>Transaction ID: **<?php echo htmlspecialchars($_SESSION['last_transaction']['id']); ?>**</p>
                <p>Total Paid: **₱ <?php echo number_format($_SESSION['last_transaction']['total'], 2); ?>**</p>
                <p>Amount Received: **₱ <?php echo number_format($_SESSION['last_transaction']['received'], 2); ?>**</p>
                <p style="font-weight: bold; color: #4CAF50;">Change Due: **₱ <?php echo number_format($_SESSION['last_transaction']['change'], 2); ?>**</p>
                <p><a href="receipt_module_barista.php?type=pos&id=<?php echo $_SESSION['last_transaction']['id']; ?>" target="_blank">View Printable Receipt</a></p>
                <?php unset($_SESSION['last_transaction']); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
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

    // === INITIAL LOAD ===
    window.onload = function() {
        // 1. Show the Drinks category and its first subcategory list by default
        showMainCategory('drinks');
        
        // 2. Clear the unnecessary separator if needed (optional cleanup)
        const hr = document.querySelector('.clearfix + hr');
        if (hr) hr.style.display = 'none';
    };
</script>
</body>
</html>