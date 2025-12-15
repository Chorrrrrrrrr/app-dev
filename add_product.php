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

$error_message = "";
$uploadOk = 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $price = $_POST['price'];
    $category = $_POST['category'];
    // Get subcategory only if category is 'drink'. Otherwise, it's set to null.
    $subcategory = ($category == 'drink' && isset($_POST['subcategory'])) ? trim($_POST['subcategory']) : null; 
    
    $image_path = 'placeholder.png'; // Default placeholder image 

    // --- Image Upload Handling (Logic from previous file) ---
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_name = basename($_FILES["product_image"]["name"]);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Create a unique filename
        $unique_file_name = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $unique_file_name;
        
        // 1. Check file size (5MB limit)
        if ($_FILES["product_image"]["size"] > 5000000) {
            $error_message = "Sorry, your file is too large (max 5MB).";
            $uploadOk = 0;
        }

        // 2. Allow specific file formats
        if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif" ) {
            $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }

        // 3. Move file if checks pass
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                $image_path = $unique_file_name; // Save the unique filename
            } else {
                $error_message = "Sorry, there was an error uploading your file.";
                $uploadOk = 0; 
            }
        }
    }
    
    // --- Database Insertion ---
    if ($uploadOk == 1) { 
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, price, category, subcategory, image_path) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sdsss", $name, $price, $category, $subcategory, $image_path); 
        
        if (mysqli_stmt_execute($stmt)) {
            header("location: admin_dashboard.php?success_product_added=" . urlencode($name));
            exit; 
        } else {
            $error_message = "Error inserting product into database: " . mysqli_error($conn);
            if ($uploadOk == 1 && $image_path != 'placeholder.png') {
                 // Clean up the uploaded file if database insertion failed
                 unlink($target_dir . $image_path);
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product</title>
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
            display: none; /* Initially hide subcategory field */
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
    <h2>Add New Product</h2>
    <?php if (!empty($error_message)): ?>
        <p class="message-error"><?php echo $error_message; ?></p>
    <?php endif; ?>
    <form action="" method="post" enctype="multipart/form-data">
        
        <div class="form-group">
            <label for="name">Product Name:</label>
            <input type="text" name="name" id="name" required>
        </div>
        
        <div class="form-group">
            <label for="price">Price (₱):</label>
            <input type="number" step="0.01" name="price" id="price" required>
        </div>
        
        <div class="form-group">
            <label for="category">Category:</label>
            <select name="category" id="category" onchange="toggleSubcategory()" required>
                <option value="drink">Drink</option>
                <option value="snack">Snack</option>
            </select>
        </div>

        <div class="form-group" id="subcategory-group">
            <label for="subcategory">Subcategory:</label>
            <select name="subcategory" id="subcategory">
                <option value="">-- Select Subcategory --</option>
                <?php foreach ($drinks_subcategories_list as $subcat): ?>
                    <option value="<?php echo htmlspecialchars($subcat); ?>"><?php echo htmlspecialchars($subcat); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($drinks_subcategories_list)): ?>
                <p style="color:red; font-size: 0.9em;">(No subcategories found. Please add them via Admin Dashboard.)</p>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="product_image">Product Image:</label>
            <input type="file" name="product_image" id="product_image" accept=".jpg, .jpeg, .png, .gif">
        </div>
        
        <input type="submit" value="Add Product">
    </form>
    <a href="admin_dashboard.php" class="back-link">Back to Dashboard</a>

    <script>
        function toggleSubcategory() {
            const categorySelect = document.getElementById('category');
            const subcategoryGroup = document.getElementById('subcategory-group');
            const subcategorySelect = document.getElementById('subcategory');
            
            if (categorySelect.value === 'drink') {
                subcategoryGroup.style.display = 'block';
                // Only require subcategory if the list isn't empty (i.e., has more than just the '-- Select --' option)
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