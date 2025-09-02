<?php
include 'db.php';

// Retrieve form data
$saleDate = $_POST['saleDate'];
$product_id = $_POST['productId']; // Retrieve product ID
$product_name = $_POST['productName'];
$quantity = $_POST['quantity'];
$price = $_POST['price'];
$total_price = $_POST['total'];
$customer_type = $_POST['customerType'];
$amount = $_POST['amountPaid'];
$change = $_POST['changeAmount'];
$cashType = $_POST['cashType']; // Retrieve cash type

// Apply discount for senior citizens
if ($customer_type == 'Senior Citizen') {
    $total_price *= 0.8; // Apply 20% discount
}

// Prepare and bind the SQL statement
$stmt = $conn->prepare("INSERT INTO sales (saleDate, product_id, product_name, quantity, price, total_price, customer_type, amount, change, cashType) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"); // Added product_id
$stmt->bind_param("ssdddddsds", $saleDate, $product_id, $product_name, $quantity, $price, $total_price, $customer_type, $amount, $change, $cashType); // Added product_id

// Execute the statement
if ($stmt->execute()) {
    echo "<script>alert('Sale added successfully.'); window.location.href = 'sales.php';</script>";
} else {
    echo "<script>alert('Error: " . $stmt->error . "'); window.location.href = 'sales.php';</script>";
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>
