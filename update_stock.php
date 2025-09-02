<?php
// update_stock.php
require 'db.php'; // Adjust based on your setup

$productId = $_POST['productId'];
$quantity = $_POST['quantity'];

// Sanitize inputs
$productId = filter_var($productId, FILTER_SANITIZE_STRING);
$quantity = filter_var($quantity, FILTER_SANITIZE_NUMBER_INT);

// Update stock in the database
$query = "UPDATE products SET current_stock = current_stock - ? WHERE product_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$quantity, $productId]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update stock.']);
}
?>
