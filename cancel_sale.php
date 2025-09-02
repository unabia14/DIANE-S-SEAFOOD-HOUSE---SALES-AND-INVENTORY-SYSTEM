<?php
// Include database connection
include('db.php');

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the product ID and quantity from the POST request
    $product_id = isset($_POST['product_name']) ? intval($_POST['product_name']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

    // Prepare the SQL statement to update the stock
    if ($product_id > 0 && $quantity > 0) {
        $stmt = $conn->prepare("UPDATE products SET current_stock = current_stock + ? WHERE product_name = ?");
        $stmt->bind_param("ii", $quantity, $product_id);

        // Execute the statement and check for success
        if ($stmt->execute()) {
            // Success response
            echo json_encode(['success' => true, 'message' => 'Stock updated successfully.']);
        } else {
            // Error response
            echo json_encode(['success' => false, 'message' => 'Failed to update stock.']);
        }

        // Close the statement
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

// Close the database connection
$conn->close();
?>
