<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    if (isset($_SESSION['loggedin'])) {
        header("location: barista_dashboard.php");
    } else {
        header("location: login.php");
    }
    exit;
}

$error = $success = "";
$edit_product = null; 

// --- Product Adding Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $price = floatval($_POST['price']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $stock = intval($_POST['stock']); // New Stock variable

    if ($price <= 0) {
        $error = "Price must be a positive number.";
    } elseif ($stock < 0) {
        $error = "Stock cannot be a negative number.";
    } else {
        $sql = "INSERT INTO products (name, price, description, stock) VALUES ('$name', '$price', '$description', '$stock')";
        if (mysqli_query($conn, $sql)) {
            $success = "Product '$name' added successfully.";
        } else {
            $error = "Error adding product: " . mysqli_error($conn);
        }
    }
}

// --- Product Updating Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
    $id = intval($_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $price = floatval($_POST['price']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $stock = intval($_POST['stock']); // New Stock variable

    if ($price <= 0) {
        $error = "Price must be a positive number.";
    } elseif ($stock < 0) {
        $error = "Stock cannot be a negative number.";
    } else {
        $sql = "UPDATE products SET name='$name', price='$price', description='$description', stock='$stock' WHERE id=$id";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Product ID $id updated successfully.";
        } else {
            $error = "Error updating product: " . mysqli_error($conn);
        }
    }
}

// --- Product Deletion Logic (Kept for completeness) ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $sql = "DELETE FROM products WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        $success = "Product ID $id deleted successfully.";
        header("location: product_management.php");
        exit;
    } else {
        $error = "Error deleting product: " . mysqli_error($conn);
    }
}

// --- Edit Form Data Fetch ---
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $sql_edit = "SELECT * FROM products WHERE id=$edit_id";
    $result_edit = mysqli_query($conn, $sql_edit);
    $edit_product = mysqli_fetch_assoc($result_edit);
}

// --- Fetch All Products for Display ---
$sql_products = "SELECT * FROM products ORDER BY id DESC";
$result_products = mysqli_query($conn, $sql_products);
$products = [];
while ($row = mysqli_fetch_assoc($result_products)) {
    $products[] = $row;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Product Management</h2>
    <p>Welcome, <?php echo $_SESSION['username']; ?>! | <a href="admin_dashboard.php">Back to Dashboard</a> | <a href="logout.php">Logout</a></p>
    
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="message-success"><?php echo $success; ?></p>
    <?php endif; ?>

    <h3><?php echo $edit_product ? 'Edit Product: ' . htmlspecialchars($edit_product['name']) : 'Add New Product'; ?></h3>
    <form action="product_management.php" method="post">
        <?php if ($edit_product): ?>
            <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
        <?php endif; ?>

        Product Name: <input type="text" name="name" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required><br><br>
        Price (in PHP): <input type="number" step="0.01" name="price" value="<?php echo $edit_product ? htmlspecialchars($edit_product['price']) : ''; ?>" required><br><br>
        Description (e.g., Category): <input type="text" name="description" value="<?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?>"><br><br>
        
        **Stock Quantity:** <input type="number" step="1" name="stock" value="<?php echo $edit_product ? htmlspecialchars($edit_product['stock']) : '0'; ?>" required><br><br>
        
        <?php if ($edit_product): ?>
            <input type="submit" name="update_product" value="Update Product">
            <a href="product_management.php" class="button-cancel">Cancel Edit</a>
        <?php else: ?>
            <input type="submit" name="add_product" value="Add Product">
        <?php endif; ?>
    </form>

    <hr>

    <h3>Current Menu</h3>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['id']; ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>₱ <?php echo number_format($product['price'], 2); ?></td>
                    <td style="color: <?php echo $product['stock'] < 5 ? 'red' : 'green'; ?>; font-weight: bold;">
                        <?php echo $product['stock']; ?>
                    </td>
                    <td><?php echo htmlspecialchars($product['description']); ?></td>
                    <td>
                        <a href="product_management.php?edit_id=<?php echo $product['id']; ?>">Edit</a> | 
                        <a href="product_management.php?delete_id=<?php echo $product['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">No products found.</td></tr>
        <?php endif; ?>
    </table>

</div>
</body>
</html>