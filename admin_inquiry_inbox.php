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

// --- Handle Actions ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);

    if ($id > 0) {
        if ($action == 'read') {
            // Mark as Read/Unread
            $new_status = isset($_GET['mark_unread']) ? 0 : 1;
            $stmt = mysqli_prepare($conn, "UPDATE customer_inquiries SET is_read = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $new_status, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $message = ($new_status == 1) ? "Inquiry marked as read." : "Inquiry marked as unread.";
        } elseif ($action == 'delete') {
            // Delete Inquiry
            $stmt = mysqli_prepare($conn, "DELETE FROM customer_inquiries WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $message = "Inquiry ID $id successfully deleted.";
        }
    }
    // Redirect to clear the query string and display the message
    header("Location: admin_inquiry_inbox.php" . (!empty($message) ? "?msg=" . urlencode($message) : ""));
    exit;
}

// Display messages after redirection
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}


// --- Fetch Inquiries ---
$result_inquiries = mysqli_query($conn, "SELECT id, sender_name, sender_email, message, received_at, is_read FROM customer_inquiries ORDER BY is_read ASC, received_at DESC");

// Fetch count of unread inquiries for the header
$result_unread_count = mysqli_query($conn, "SELECT COUNT(id) AS unread_count FROM customer_inquiries WHERE is_read = 0");
$unread_count = mysqli_fetch_assoc($result_unread_count)['unread_count'];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Inquiry Inbox</title>
    <style>
        /* CSS styles reused from Admin Dashboard */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        h2 { color: #173f5f; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; margin-top: 0; margin-bottom: 25px; }
        .message-success { background-color: #d4edda; color: #155724; padding: 12px; margin: 15px 0; border-radius: 5px; border: 1px solid #c3e6cb; font-weight: bold; }
        .message-error { background-color: #f8d7da; color: #721c24; padding: 12px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb; font-weight: bold; }
        .back-link { margin-bottom: 20px; display: inline-block; color: #20639b; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        table th { background-color: #e6f0fa; color: #173f5f; font-weight: 600; text-transform: uppercase; }
        
        /* Styling for unread rows */
        tr.unread {
            background-color: #fff9e6; /* Light yellow for unread messages */
            font-weight: bold;
        }
        
        /* Apply pointer and hover effect to all clickable cells */
        tr.unread td, tr.read td {
            cursor: pointer;
        }
        tr.read:hover, tr.unread:hover {
            background-color: #e9eff7;
        }
        
        /* Ensure the Actions cell does not inherit the pointer */
        .action-links {
             cursor: default !important;
        }

        /* Detail Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .modal-close {
            float: right;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            margin-left: 10px;
        }
        .modal-body strong {
            display: inline-block;
            width: 80px;
            color: #20639b;
        }
        .modal-body p {
            border: 1px solid #eee;
            padding: 10px;
            background: #f9f9f9;
            white-space: pre-wrap; 
            margin-top: 10px;
        }
        .action-links a {
            margin-right: 15px;
        }
        .delete-link {
            color: #cc0000;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
    <h2>📩 Inquiry Inbox 
        <?php if ($unread_count > 0): ?>
            <span style="color: #cc0000; font-size: 0.9em; margin-left: 15px;">(<?php echo $unread_count; ?> New)</span>
        <?php endif; ?>
    </h2>

    <?php if (!empty($message)): ?>
        <p class="message-success"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if (mysqli_num_rows($result_inquiries) > 0): ?>
        <table id="inquiryTable">
            <thead>
                <tr>
                    <th>From</th>
                    <th>Email</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($inquiry = mysqli_fetch_assoc($result_inquiries)): ?>
                <tr class="<?php echo $inquiry['is_read'] == 0 ? 'unread' : 'read'; ?>" 
                    data-id="<?php echo $inquiry['id']; ?>"
                    data-name="<?php echo htmlspecialchars($inquiry['sender_name']); ?>"
                    data-email="<?php echo htmlspecialchars($inquiry['sender_email']); ?>"
                    data-message="<?php echo htmlspecialchars($inquiry['message']); ?>"
                    data-read="<?php echo $inquiry['is_read']; ?>">
                    
                    <td onclick="openInquiryDetail(this.closest('tr'))"><?php echo htmlspecialchars($inquiry['sender_name']); ?></td>
                    <td onclick="openInquiryDetail(this.closest('tr'))"><?php echo htmlspecialchars($inquiry['sender_email']); ?></td>
                    <td onclick="openInquiryDetail(this.closest('tr'))"><?php echo date('M j, Y h:i A', strtotime($inquiry['received_at'])); ?></td>
                    
                    <td onclick="openInquiryDetail(this.closest('tr'))"><?php echo $inquiry['is_read'] == 0 ? 'NEW' : 'Read'; ?></td>
                    
                    <td onclick="event.stopPropagation()" class="action-links">
                        <?php if ($inquiry['is_read'] == 0): ?>
                             <?php else: ?>
                             <a href="admin_inquiry_inbox.php?action=read&id=<?php echo $inquiry['id']; ?>&mark_unread=1">Mark Unread</a>
                        <?php endif; ?>
                        <a href="admin_inquiry_inbox.php?action=delete&id=<?php echo $inquiry['id']; ?>" class="delete-link" onclick="return confirm('Delete this inquiry? This cannot be undone.');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>The inquiry inbox is currently empty.</p>
    <?php endif; ?>
</div>

<div id="inquiryModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeInquiryDetail()">&times;</span>
        <h3>Inquiry Details</h3>
        <div class="modal-body">
            <p><strong>From:</strong> <span id="modal-name"></span></p>
            <p><strong>Email:</strong> <span id="modal-email"></span></p>
            <p><strong>Date:</strong> <span id="modal-date"></span></p>
            <h4>Message:</h4>
            <p id="modal-message"></p>
        </div>
        <div class="action-links" style="text-align: right; margin-top: 20px;">
            <a href="#" id="modal-mark-read" style="font-weight: bold;">Mark as Read</a>
            <a href="#" id="modal-delete" class="delete-link" style="font-weight: bold;">Delete</a>
        </div>
    </div>
</div>

<script>
    const inquiryModal = document.getElementById('inquiryModal');

    function openInquiryDetail(row) {
        const id = row.getAttribute('data-id');
        const name = row.getAttribute('data-name');
        const email = row.getAttribute('data-email');
        const message = row.getAttribute('data-message');
        const date = row.cells[2].innerText; 
        let isRead = row.getAttribute('data-read');

        // Populate modal content
        document.getElementById('modal-name').innerText = name;
        document.getElementById('modal-email').innerText = email;
        document.getElementById('modal-date').innerText = date;
        document.getElementById('modal-message').innerText = message;

        // Set action links
        document.getElementById('modal-delete').href = `admin_inquiry_inbox.php?action=delete&id=${id}`;

        const markReadLink = document.getElementById('modal-mark-read');
        
        // --- CRITICAL AUTOMATIC READ LOGIC ---
        if (isRead == 0) {
             // 1. Visually and logically mark it as read immediately
             row.classList.remove('unread');
             row.cells[3].innerText = 'Read';
             row.setAttribute('data-read', 1);
             isRead = 1; // Update JS state

             // 2. Send a silent, non-blocking request to update the database status
             fetch(`admin_inquiry_inbox.php?action=read&id=${id}`).catch(err => console.error("Failed to mark read:", err));
        }

        // Set the Mark Read/Unread link based on the updated state
        if (isRead == 1) {
            markReadLink.innerText = "Mark as Unread";
            markReadLink.href = `admin_inquiry_inbox.php?action=read&id=${id}&mark_unread=1`;
        } else {
             // This branch should ideally not be hit if auto-read was successful
            markReadLink.innerText = "Mark as Read";
            markReadLink.href = `admin_inquiry_inbox.php?action=read&id=${id}`;
        }


        // Display modal
        inquiryModal.style.display = 'flex';
        
    }

    function closeInquiryDetail() {
        inquiryModal.style.display = 'none';
        // Refresh the page to update the unread count in the header (if needed)
        window.location.href = 'admin_inquiry_inbox.php';
    }
    
    // Close modal if user clicks outside of it
    window.onclick = function(event) {
        if (event.target === inquiryModal) {
            closeInquiryDetail();
        }
    }
</script>
</body>
</html>