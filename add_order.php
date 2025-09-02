<?php
include 'db.php';
// Retrieve form data
$sale_date = $_POST['sale_date'];
$product_name = $_POST['product_name'];
$quantity = $_POST['quantity'];
$total_price = $_POST['total_price'];
$customer_type = $_POST['customer_type'];
$amount = $_POST['amount'];
$change = $_POST['change'];
$cashType = $_POST['cashType']; // Retrieve cash type

// Apply discount for senior citizens
if ($customer_type == 'Senior Citizen') {
    $total_price *= 0.8; // Apply 20% discount
}

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO sales (sale_date, product_name, quantity, total_price, customer_type, amount, change, cashType) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"); // Added cashType
$stmt->bind_param("ssddddds", $sale_date, $product_name, $quantity, $total_price, $customer_type, $amount, $change, $cashType); // Added cashType
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
