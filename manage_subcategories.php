<?php
session_start();
include 'db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$message = "";
$error = "";
$edit_id = 0;
$edit_name = "";
$edit_sort_order = 0; 

// --- CRUD Logic ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    // --- CREATE/UPDATE (SAVE) ---
    if ($action == 'add' || $action == 'update') {
        $name = trim($_POST['name']);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $id = intval($_POST['id'] ?? 0);

        if (empty($name)) {
            $error = "Subcategory name cannot be empty.";
        } else {
            if ($action == 'add') {
                $stmt = mysqli_prepare($conn, "INSERT INTO drinks_subcategories (name, sort_order) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "si", $name, $sort_order);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Subcategory '$name' added successfully.";
                } else {
                    if (mysqli_errno($conn) == 1062) {
                         $error = "Error adding subcategory: Name already exists.";
                    } else {
                         $error = "Error adding subcategory: " . mysqli_error($conn);
                    }
                }
            } elseif ($action == 'update' && $id > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE drinks_subcategories SET name = ?, sort_order = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sii", $name, $sort_order, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Subcategory ID $id updated successfully.";
                } else {
                    if (mysqli_errno($conn) == 1062) {
                         $error = "Error updating subcategory: Name might already exist.";
                    } else {
                         $error = "Error updating subcategory: " . mysqli_error($conn);
                    }
                }
            }
            if (isset($stmt)) mysqli_stmt_close($stmt);
        }
    }
    
    // --- DELETE ---
    elseif ($action == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Get the name of the subcategory to be deleted
            $subcat_name_result = mysqli_query($conn, "SELECT name FROM drinks_subcategories WHERE id = $id");
            $subcat_name = mysqli_fetch_assoc($subcat_name_result)['name'] ?? null;

            if ($subcat_name) {
                // Set products using this subcategory to NULL before deleting the category name
                mysqli_query($conn, "UPDATE products SET subcategory = NULL WHERE subcategory = '$subcat_name'");
            }
            
            $stmt = mysqli_prepare($conn, "DELETE FROM drinks_subcategories WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Subcategory ID $id deleted successfully.";
            } else {
                $error = "Error deleting subcategory: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- Fetch for Edit ---
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = mysqli_prepare($conn, "SELECT id, name, sort_order FROM drinks_subcategories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_data = mysqli_fetch_assoc($result);
    if ($edit_data) {
        $edit_name = htmlspecialchars($edit_data['name']);
        $edit_sort_order = $edit_data['sort_order'];
    } else {
        $edit_id = 0;
        $error = "Subcategory for editing not found.";
    }
    mysqli_stmt_close($stmt);
}

// --- Read (Fetch All) ---
$result_subcategories = mysqli_query($conn, "SELECT id, name, sort_order FROM drinks_subcategories ORDER BY sort_order ASC, name ASC");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Drink Subcategories</title>
    <style>
        /* CSS styles from your Admin Dashboard for consistency */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        h2 { color: #173f5f; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; margin-top: 0; margin-bottom: 25px; }
        .message-success { background-color: #d4edda; color: #155724; padding: 12px; margin: 15px 0; border-radius: 5px; border: 1px solid #c3e6cb; font-weight: bold; }
        .message-error { background-color: #f8d7da; color: #721c24; padding: 12px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb; font-weight: bold; }
        form { margin-bottom: 30px; padding: 20px; border: 1px solid #ccc; border-radius: 5px; background: #f9f9f9; }
        form input[type="text"], form input[type="number"] { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; margin-bottom: 10px; }
        form label { display: block; margin-top: 10px; font-weight: bold; }
        form input[type="submit"] { background-color: #20639b; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; margin-top: 15px; }
        form input[type="submit"]:hover { background-color: #173f5f; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 10px; text-align: left; border: 1px solid #e0e0e0; }
        table th { background-color: #e6f0fa; color: #173f5f; }
        table a { color: #20639b; text-decoration: none; margin-right: 10px; }
        table a:hover { text-decoration: underline; }
        .delete-form { display: inline-block; }
        .delete-form input[type="submit"] { background: #cc0000; padding: 5px 10px; font-size: 0.9em; margin: 0; }
        .delete-form input[type="submit"]:hover { background: #a00000; }
        .back-link { margin-top: 20px; display: inline-block; color: #20639b; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>☕ Manage Drink Subcategories</h2>

    <?php if (!empty($message)): ?>
        <p class="message-success"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form action="manage_subcategories.php" method="post">
        <h3><?php echo $edit_id > 0 ? "Edit Subcategory (ID: $edit_id)" : "Add New Subcategory"; ?></h3>
        
        <input type="hidden" name="action" value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">
        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">

        <label for="name">Subcategory Name (e.g., ESPRESSO BASED):</label>
        <input type="text" name="name" value="<?php echo $edit_name; ?>" required>
        
        <label for="sort_order">Sort Order (Lower number appears first):</label>
        <input type="number" name="sort_order" value="<?php echo $edit_id > 0 ? $edit_sort_order : 0; ?>">

        <input type="submit" value="<?php echo $edit_id > 0 ? 'Update Subcategory' : 'Add Subcategory'; ?>">
        
        <?php if ($edit_id > 0): ?>
            <a href="manage_subcategories.php" style="margin-left: 10px;">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <h3>Current Subcategories</h3>
    <?php if (mysqli_num_rows($result_subcategories) > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
            <?php while($row = mysqli_fetch_assoc($result_subcategories)): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo $row['sort_order']; ?></td>
                <td>
                    <a href="manage_subcategories.php?edit_id=<?php echo $row['id']; ?>">Edit</a>
                    
                    <form action="manage_subcategories.php" method="post" class="delete-form" onsubmit="return confirm('WARNING: This will set all products currently assigned to this subcategory to UNASSIGNED (NULL). Are you sure?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <input type="submit" value="Delete">
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No drink subcategories have been added yet. </p>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

</body>
</html>