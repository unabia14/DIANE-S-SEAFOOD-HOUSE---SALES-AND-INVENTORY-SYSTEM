<?php
include 'db.php'; // Include your database connection file

// Get POST data
$customerId = $_POST['customerId'];
$customerType = $_POST['customerType'];
$orderDate = $_POST['orderDate'];

// Prepare and execute SQL query
$sql = "INSERT INTO customers (customer_id, customer_type, order_date) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $customerId, $customerType, $orderDate);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Customer recorded successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record customer: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
