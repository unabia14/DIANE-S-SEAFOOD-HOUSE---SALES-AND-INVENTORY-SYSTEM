<?php
header('Content-Type: application/json');
session_start(); // Start the session to access session variables
include 'db.php'; // Include your database connection file

// Retrieve POST data
$saleDate = $_POST['saleDate'] ?? '';
$product_name = $_POST['product_name'] ?? '';
$quantity = $_POST['quantity'] ?? 0;
$price = $_POST['price'] ?? 0.0;
$total = $_POST['total'] ?? 0.0;
$orderType = $_POST['orderType'] ?? '';
$customerType = $_POST['customerType'] ?? '';
$cashType = $_POST['cashType'] ?? ''; // Retrieve cashType
$cashierId = $_SESSION['cashier_id'] ?? ''; // Retrieve cashier_id from session

// Validate the saleDate and ensure it's not empty
if (empty($saleDate)) {
    echo json_encode(['success' => false, 'message' => 'Sale date is empty']);
    exit();
}

// Convert saleDate to correct format if necessary
$formattedSaleDate = date('Y-m-d', strtotime($saleDate));

// Prepare and execute SQL query for sales
$sql = "INSERT INTO sales (saleDate, product_name, quantity, price, total, orderType, customerType, cashType, cashier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

// Check if the statement was prepared correctly
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Error preparing the SQL statement: ' . $conn->error]);
    exit();
}

// Bind the parameters (ensure to use the correct types for binding)
$stmt->bind_param('ssiddsssd', $formattedSaleDate, $product_name, $quantity, $price, $total, $orderType, $customerType, $cashType, $cashierId);

// Execute the statement for sales and check if it was successful
if ($stmt->execute()) {
    // Get the product ID corresponding to the product name for stock_out entry
    $productQuery = "SELECT product_id FROM products WHERE product_name = ?";
    $productStmt = $conn->prepare($productQuery);
    $productStmt->bind_param('s', $product_name);
    $productStmt->execute();
    $productStmt->bind_result($product_id);
    $productStmt->fetch();
    $productStmt->close();

    // Now insert into stock_out table
    $insertStockOut = "INSERT INTO stock_out (product_id, total_sold, date) VALUES (?, ?, ?)";
    $stockOutStmt = $conn->prepare($insertStockOut);
    $stockOutStmt->bind_param('ids', $product_id, $quantity, $formattedSaleDate);

    // Execute the stock out statement and check if it was successful
    if ($stockOutStmt->execute()) {
        // Prepare to update the stock table
        $currentStockQuery = "SELECT COALESCE(SUM(s.stock_in), 0) AS total_stock_in, 
                                      COALESCE(SUM(s.stock_out), 0) AS total_stock_out
                               FROM stock s
                               WHERE s.product_id = ?";
        $currentStockStmt = $conn->prepare($currentStockQuery);
        $currentStockStmt->bind_param('i', $product_id);
        $currentStockStmt->execute();
        $currentStockResult = $currentStockStmt->get_result();
        $currentStockRow = $currentStockResult->fetch_assoc();

        $total_stock_in = $currentStockRow['total_stock_in'] ?: 0;
        $total_stock_out = $currentStockRow['total_stock_out'] ?: 0;
        
        // Calculate new current stock
        $current_stock = $total_stock_in - ($total_stock_out + $quantity);

        // Prepare insert into stock table
        $insertStock = "INSERT INTO stock (product_id, stock_in, stock_out, current_stock, date) VALUES (?, 0, ?, ?, ?)";
        $stockStmt = $conn->prepare($insertStock);
        $stockStmt->bind_param('iids', $product_id, $quantity, $current_stock, $formattedSaleDate);

        // Execute the stock insert statement
        if ($stockStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Sale recorded and stock updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating stock table: ' . $stockStmt->error]);
        }

        // Close the stock statement
        $stockStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating stock_out: ' . $stockOutStmt->error]);
    }

    // Close the stock_out statement
    $stockOutStmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error recording sale: ' . $stmt->error]);
}

// Close the sales statement and connection
$stmt->close();
$conn->close();
?>
