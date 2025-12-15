<?php
session_start();
include 'db.php';

// === DYNAMIC FETCH: Get subcategories from DB ===
$drinks_subcategories_list = [];
$result_subcats = mysqli_query($conn, "SELECT name FROM drinks_subcategories ORDER BY sort_order ASC, name ASC");
if ($result_subcats) {
    while ($row = mysqli_fetch_assoc($result_subcats)) {
        $drinks_subcategories_list[] = $row['name'];
    }
}

// Check if a directory for product images exists, if not, create it
$target_dir = "product_images/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true); 
}

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
$error_message = "";
$product_data = null;
$uploadOk = 1;

if ($product_id > 0) {
    // === Fetch existing product data ===
    $stmt_fetch = mysqli_prepare($conn, "SELECT id, name, price, category, subcategory, image_path FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt_fetch, "i", $product_id);
    mysqli_stmt_execute($stmt_fetch);
    $result_fetch = mysqli_stmt_get_result($stmt_fetch);
    $product_data = mysqli_fetch_assoc($result_fetch);
    mysqli_stmt_close($stmt_fetch);

    if (!$product_data) {
        header("location: admin_dashboard.php?error=Product not found.");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = trim($_POST['name']);
        $price = $_POST['price'];
        $category = $_POST['category'];
        // Get subcategory only if category is 'drink'. Otherwise, it's null.
        $subcategory = ($category == 'drink' && isset($_POST['subcategory'])) ? trim($_POST['subcategory']) : null; 
        $current_image_path = $product_data['image_path']; 

        // --- Image Upload Handling (Logic from previous file) ---
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $file_name = basename($_FILES["product_image"]["name"]);
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_file_name = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $unique_file_name;
            
            // Validation checks (size and format) here...
            if ($_FILES["product_image"]["size"] > 5000000) {
                $error_message = "File is too large (max 5MB).";
                $uploadOk = 0;
            }
            if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif" ) {
                $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
                $uploadOk = 0;
            }

            if ($uploadOk == 1) {
                if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                    // Delete old image if it wasn't the default placeholder
                    if ($current_image_path != 'placeholder.png' && file_exists($target_dir . $current_image_path)) {
                        unlink($target_dir . $current_image_path);
                    }
                    $current_image_path = $unique_file_name; // Use new path
                } else {
                    $error_message = "Error uploading your file.";
                    $uploadOk = 0; 
                }
            }
        }
        
        // --- Database Update ---
        if ($uploadOk == 1) { 
            $stmt_update = mysqli_prepare($conn, "UPDATE products SET name=?, price=?, category=?, subcategory=?, image_path=? WHERE id=?");
            mysqli_stmt_bind_param($stmt_update, "sdsssi", $name, $price, $category, $subcategory, $current_image_path, $product_id); 
            
            if (mysqli_stmt_execute($stmt_update)) {
                header("location: admin_dashboard.php?success_product_updated=" . urlencode($name));
                exit; 
            } else {
                $error_message = "Error updating product: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_update);
        }
        
        // Reload data after failed update attempt to show form with current (failed) inputs
        $product_data['name'] = $name;
        $product_data['price'] = $price;
        $product_data['category'] = $category;
        $product_data['subcategory'] = $subcategory;
        $product_data['image_path'] = $current_image_path;
        
    }
} else {
    header("location: admin_dashboard.php?error=Invalid product ID.");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Shared Styles for Admin Forms */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            width: 90%;
            max-width: 500px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #173f5f;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        form input[type="text"], 
        form input[type="number"], 
        form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        form input[type="submit"] {
            background-color: #20639b;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        form input[type="submit"]:hover {
            background-color: #173f5f;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        #subcategory-group {
            display: none; /* Initial display handled by JS */
        }
        .current-image {
            margin-bottom: 15px;
            text-align: center;
        }
        .current-image img {
            max-width: 150px;
            height: auto;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .back-link {
             color: #20639b;
             text-decoration: none;
             margin-top: 10px;
             display: inline-block;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Product: <?php echo htmlspecialchars($product_data['name']); ?></h2>
    <?php if (!empty($error_message)): ?>
        <p class="message-error"><?php echo $error_message; ?></p>
    <?php endif; ?>
    
    <div class="current-image">
        <p>Current Image:</p>
        <img src="<?php echo $target_dir . htmlspecialchars($product_data['image_path']); ?>" alt="Product Image">
    </div>

    <form action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

        <div class="form-group">
            <label for="name">Product Name:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product_data['name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="price">Price (₱):</label>
            <input type="number" step="0.01" name="price" id="price" value="<?php echo htmlspecialchars($product_data['price']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="category">Category:</label>
            <select name="category" id="category" onchange="toggleSubcategory()" required>
                <option value="drink" <?php if ($product_data['category'] == 'drink') echo 'selected'; ?>>Drink</option>
                <option value="snack" <?php if ($product_data['category'] == 'snack') echo 'selected'; ?>>Snack</option>
            </select>
        </div>

        <div class="form-group" id="subcategory-group" style="<?php echo ($product_data['category'] == 'drink') ? 'display: block;' : 'display: none;'; ?>">
            <label for="subcategory">Subcategory:</label>
            <select name="subcategory" id="subcategory">
                <option value="">-- Select Subcategory --</option>
                <?php foreach ($drinks_subcategories_list as $subcat): ?>
                    <option value="<?php echo htmlspecialchars($subcat); ?>" 
                            <?php if ($product_data['subcategory'] == $subcat) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($subcat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($drinks_subcategories_list)): ?>
                <p style="color:red; font-size: 0.9em;">(No subcategories found. Please add them via Admin Dashboard.)</p>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="product_image">Change Image (Leave blank to keep current):</label>
            <input type="file" name="product_image" id="product_image" accept=".jpg, .jpeg, .png, .gif">
        </div>
        
        <input type="submit" value="Update Product">
    </form>
    <a href="admin_dashboard.php" class="back-link">Back to Dashboard</a>

    <script>
        function toggleSubcategory() {
            const categorySelect = document.getElementById('category');
            const subcategoryGroup = document.getElementById('subcategory-group');
            const subcategorySelect = document.getElementById('subcategory');
            
            if (categorySelect.value === 'drink') {
                subcategoryGroup.style.display = 'block';
                // Only require subcategory if the list isn't empty
                if (subcategorySelect.options.length > 1) { 
                    subcategorySelect.setAttribute('required', 'required');
                }
            } else {
                subcategoryGroup.style.display = 'none';
                subcategorySelect.removeAttribute('required');
            }
        }
        
        // Initial call to set the correct state on load
        toggleSubcategory();
    </script>
</div>
</body>
</html>