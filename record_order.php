<?php
include('db.php'); // Make sure this file connects to your database

header('Content-Type: application/json');

// Get POST data
$orderType = $_POST['orderType'] ?? '';
$customerType = $_POST['customerType'] ?? '';
$cashType = $_POST['cashType'] ?? ''; // Retrieve cashType
$totalAmount = $_POST['totalAmount'] ?? '';
$amountPaid = $_POST['amountPaid'] ?? '';
$changeAmount = $_POST['changeAmount'] ?? '';
$sales = $_POST['sales'] ?? []; // Expecting an array of sale details

// Validate and sanitize inputs
$orderType = $conn->real_escape_string($orderType);
$customerType = $conn->real_escape_string($customerType);
$cashType = $conn->real_escape_string($cashType); // Sanitize cashType
$totalAmount = (float)$totalAmount;
$amountPaid = (float)$amountPaid;
$changeAmount = (float)$changeAmount;

// Check if total amount and amount paid are valid numbers
if ($totalAmount <= 0 || $amountPaid < $totalAmount) {
    echo json_encode(['success' => false, 'message' => 'Invalid order details.']);
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert order into the database
    $sql = "INSERT INTO orders (order_type, customer_type, cash_type, total_amount, amount_paid, change_amount, order_date) 
            VALUES ('$orderType', '$customerType', '$cashType', $totalAmount, $amountPaid, $changeAmount, NOW())"; // Added cashType

    if ($conn->query($sql) === TRUE) {
        // Get the last inserted order ID
        $orderId = $conn->insert_id;

        // Prepare the statement for inserting order details
        $stmt = $conn->prepare("INSERT INTO sales ( saleDate, product_name, quantity, price, total, orderType, customerType) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($sales as $sale) {
            // Sanitize and bind parameters for each sale
            $productId = $conn->real_escape_string($sale['productId']);
            $product_name = $conn->real_escape_string($sale['product_name']);
            $quantity = (int)$sale['quantity'];
            $price = (float)$sale['price'];
            $total = (float)$sale['total'];

            // Bind parameters and execute the statement
            $stmt->bind_param('isiddss', $saleDate, $product_name, $quantity, $price, $total, $orderType, $customerType); // cashType handled separately in orders
            $stmt->execute();
        }

        $stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Order placed successfully.']);
    } else {
        throw new Exception('Failed to record the order.');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
