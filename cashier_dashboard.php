<?php
session_start();

// Check if the cashier is logged in
if (!isset($_SESSION['cashier_id'])) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "salesrecord_db"; // Change this to your actual database name

// Create MySQLi connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: login.php'); // Redirect to login page
    exit;
}

// Fetch cashier details if needed
$stmt = $conn->prepare("SELECT username FROM cashiers WHERE cashier_id = ?");
$stmt->bind_param("i", $_SESSION['cashier_id']);
$stmt->execute();
$cashier = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch orders data
$orders_sql = "SELECT * FROM orders ORDER BY order_date DESC";
$orders_result = $conn->query($orders_sql);

// Fetch products data
$products_sql = "SELECT * FROM products";
$products_result = $conn->query($products_sql);

// Fetch products by category
$query = "SELECT product_id, image, product_name, price, current_stock, category FROM products"; // Ensure category is included
$result = $conn->query($query); // Use $conn instead of $connection
$products_by_category = [];

// Organize products by category
while ($row = $result->fetch_assoc()) {
    $category = $row['category']; // Ensure you have a category field
    if (!isset($products_by_category[$category])) {
        $products_by_category[$category] = [];
    }
    $products_by_category[$category][] = $row;
}


// Group products by category
$products_by_category = [];
while ($product = $products_result->fetch_assoc()) {
    $category = $product['category'];
    if (!isset($products_by_category[$category])) {
        $products_by_category[$category] = [];
    }
    $products_by_category[$category][] = $product;
}

// Fetch sales data
$sales_sql = "SELECT * FROM sales ORDER BY saleDate DESC";
$sales_result = $conn->query($sales_sql);
if (!$sales_result) {
    throw new Exception("Error fetching sales: " . $conn->error);
}

// Function to update stock after a sale
function updateStockAfterSale($conn, $productId, $quantitySold) {
    // Check current stock level
    $stock_sql = "SELECT current_stock FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($stock_sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stock_result = $stmt->get_result();
    
    if ($stock_result->num_rows > 0) {
        $stock_row = $stock_result->fetch_assoc();
        $currentStock = $stock_row['current_stock'];

        // Calculate new stock level
        $newStockLevel = $currentStock - $quantitySold;

        // Update stock level in database
        $update_stock_sql = "UPDATE products SET current_stock = ? WHERE product_id = ?";
        $update_stmt = $conn->prepare($update_stock_sql);
        $update_stmt->bind_param("ii", $newStockLevel, $productId);
        $update_stmt->execute();
        $update_stmt->close();
    }
}

// Process each sale and update stock levels
while ($sale = $sales_result->fetch_assoc()) {
    $productId = $sale['product_name']; // Assuming this field matches product_id; adjust if necessary
    $quantitySold = $sale['quantity'];

    // Update stock level after sale
    updateStockAfterSale($conn, $productId, $quantitySold);
}

// Function to update low sales products based on total sales in the sales table
function updateLowSalesProducts($conn, $lowSalesThreshold) {
    // Query to get total sales for each product from the sales table
    $sql = "SELECT s.product_name, SUM(s.total) AS totalSales
            FROM sales s
            GROUP BY s.product_name
            HAVING totalSales < ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("d", $lowSalesThreshold);
    $stmt->execute();
    $lowSalesResult = $stmt->get_result();

    // Prepare statement to insert or update low sales products
    $insertOrUpdateSql = "INSERT INTO low_sales_products (product_name, total, updated_at)
                          VALUES (?, ?, NOW())
                          ON DUPLICATE KEY UPDATE total = VALUES(total), updated_at = NOW()";

    $insertOrUpdateStmt = $conn->prepare($insertOrUpdateSql);

    // Iterate over the results and insert or update the low sales products
    while ($row = $lowSalesResult->fetch_assoc()) {
        $insertOrUpdateStmt->bind_param("sd", $row['product_name'], $row['totalSales']);
        $insertOrUpdateStmt->execute();
    }

    // Close the statement
    $insertOrUpdateStmt->close();
}

// Define the low sales threshold
$lowSalesThreshold = 1000; // Set your threshold here

// Update low sales products
updateLowSalesProducts($conn, $lowSalesThreshold);

// Fetch cashiers from the database
$cashiers = []; // Initialize cashiers array

$query = "SELECT cashier_id, name FROM cashiers"; // Replace with your actual query
$result = mysqli_query($conn, $query); // Execute the query

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cashiers[] = $row; // Populate the cashiers array
    }
} else {
    echo "Error fetching cashiers: " . mysqli_error($conn); // Display any error
}

// Now you can safely use the $cashiers variable
$cashierId = $_SESSION['cashier_id']; // Retrieve the cashier_id from session

// Find the cashier's name based on the cashier_id
$cashierName = ''; // Initialize variable for cashier name
foreach ($cashiers as $cashier) {
    if ($cashier['cashier_id'] == $cashierId) {
        $cashierName = htmlspecialchars($cashier['name']); // Get the cashier's name
        break; // Exit the loop once found
    }
}
// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cashier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>

.alert {
    padding: 15px;
    margin: 10px 0;
    border: 1px solid transparent;
    border-radius: 5px;
}

.alert-warning {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

header {
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    background-color: #2E8B57; 
    color: white; 
    padding: 10px; 
    font-family: 'Arial', sans-serif; 
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); 
    display: flex;
    justify-content: space-between; /* Adjust as needed */
    width: 100%;
}

.header-title {
    display: flex;
    flex-direction: column; /* Stack h1 and date-time */
    align-items: center; /* Center align h1 and date-time */
}

header h1 {
    font-size: 1.5em; 
    text-align: left;
    margin-right: 700px;
    color: orange;
}

#currentDayTime {
    color: black; 
    font-size: 18px; 
    text-align: right; /* Ensure it's aligned properly */
}

.date-time {
    margin-right: 1200px; /* Space between date-time and logout button */
    margin-bottom: 1px; /* Space between h1 and date-time */
}

.logout-button {
    background: #f0f0f0; /* Background color */
    border-radius: 10%; /* Circular background */
    padding: 8px; /* Space inside the button */
    border: 2px solid #ccc; /* Boundary line */
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.logout-button button {
    background: none;
    border: none;
    cursor: pointer;
    outline: none;
}

.logout-button i {
    font-size: 20px; /* Icon size */
    color: #333; /* Icon color */
}
/* Additional styles for responsiveness */
@media (max-width: 500px) {
    header h1 {
        font-size: 3em; /* Smaller font size on smaller screens */
    }

    header p {
        font-size: 1em; /* Smaller font size for the date/time */
    }
}

.logo {
    width: 60px; /* Adjust as needed */
    height: auto; /* Maintain aspect ratio */
    display: block; /* Centers the logo if you use text-align */
    margin: 0 auto 10px; /* Center and add space below */
    margin-left: 0;
    border-radius: 90px;
}


        /* Basic styles for layout */
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        /* Style for category buttons */
        /* Style for category buttons */
        .category-buttons {
            display: flex;
            gap: 10px; /* Space between buttons */
            margin-bottom: 20px; /* Space below the buttons */
        }

        .category-buttons button {
            padding: 12px 22px; /* Space inside the button */
            font-size: 16px; /* Font size for button text */
            margin-right: 12px;
            border: none; /* Remove default border */
            border-radius: 5px; /* Rounded corners */
            cursor: pointer; /* Pointer cursor on hover */
            color: white; /* Text color */
            background-color: #2E8B57; /* Background color */
            transition: background-color 0.3s, transform 0.2s; /* Smooth color change */
        }

        .category-buttons button:hover {
            background-color: #1c6b40; /* Darker background color on hover */
        }


        .category-buttons button:active {
            background-color: #1a5736; /* Even darker color on click */
            transform: scale(0.98); /* Slightly shrink button on click */
        }

        /* Individual category box styling */
        .box {
            display: none; /* Start hidden */
            width: calc(25% - 10px); /* Adjust width to show 4 products in a row */
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            box-sizing: border-box;
            background-color: #fff;
        }

        /* Increase font size in category boxes */
        .box h3 {
            font-size: 24px; /* Adjust font size as needed */
            margin-bottom: 15px;
        }

        .box ul {
            font-size: 16px; /* Adjust font size as needed */
        }

        .box li {
            margin-bottom: 10px;
        }

        /* Flexbox for columns inside .content */
        .column {
            flex: 1;
            box-sizing: border-box;
            display: flex;
            flex-direction: column; /* Stack children vertically */
        }

        /* Remove sidebar space */
        .content {
    display: flex; /* Use flexbox to arrange columns */
    gap: 20px; /* Space between columns */
    padding: 20px; /* Padding around the content */
    box-sizing: border-box; /* Include padding in width/height calculations */
}
        /* Flexbox for columns inside .content */
        .column {
            flex: 1;
            box-sizing: border-box;
            display: flex;
            flex-direction: column; /* Stack children vertically */
        }

        /* Style for sales table */
        /* Ensure the table container has a fixed height and scrolls if needed */
.salesTableWrapper {
    max-height: 300px; /* Adjust height as needed */
    overflow-y: auto; /* Add vertical scrollbar */
    margin-bottom: 20px; /* Space below the table */
}
#salesTableContainer {
    position: fixed; 
    bottom: 20;
    left: 1048px; 
    width: calc(55% - 250px); /* Ensure this matches the salesTableContainer */
    margin-top: 10px;
    height: calc(523px - 20px); /* Adjust height as needed */
    border: 2px solid #ddd; 
    text-align: center; 
    background-color: gainsboro;
    padding: 10px; /* Ensure padding is set to 10px */
    
    
}

#salesTableContainer h2 {
    margin: 0; /* Removes default margin for cleaner centering */
    padding: 10px ; /* Optional: Adds vertical padding to space out the heading */
    font-size: 24px; /* Optional: Adjusts font size as needed */
    color: #333; /* Optional: Sets text color */
}

/* Custom Scrollbar for WebKit Browsers (Chrome, Safari) */
#salesTableContainer::-webkit-scrollbar {
    width: 12px; /* Width of the scrollbar */
}

#salesTableContainer::-webkit-scrollbar-track {
    background: #f1f1f1; /* Color of the track (part the scrollbar moves within) */
}

#salesTableContainer::-webkit-scrollbar-thumb {
    background: #888; /* Color of the scrollbar thumb (the draggable part) */
    border-radius: 6px; /* Rounded corners for the thumb */
}

#salesTableContainer::-webkit-scrollbar-thumb:hover {
    background: #555; /* Darker color when hovered */
}

/* Custom Scrollbar for Firefox */
#salesTableContainer {
    scrollbar-width: thin; /* Thin scrollbar */
    scrollbar-color: #888 #f1f1f1; /* Thumb color and track color */
}

/* Styling for the Sales Table */
#salesTable {
    width: 100%;
    border-collapse: collapse; /* Optional, for table styling */
    margin-top: 10px;
    margin-bottom: 20px
}

#salesTable th, #salesTable td {
    border: 1px solid #ddd; /* Optional, for table styling */
    padding: 10px; /* Optional, for spacing */
  
}

#orderSummary {
    position: fixed; 
    bottom: 0;
    left: 1048px; 
    width: calc(55% - 250px); /* Ensure this matches the salesTableContainer */
    background-color: gainsboro;
    border-top: 1px solid #ddd;
    box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
    padding: 10px; 
    box-sizing: border-box; 
    display: flex;
    margin-bottom: 0;
    flex-direction: column; 
    gap: 10px; 
    margin-top: 5px;
    z-index: 1000; 
}

/* Style for the summary items */
.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
    
}

.summary-item label {
    flex: 1;
    font-weight: bold;
    font-size: 24px;
    text-align: justify;
}

.summary-item input {
    flex: 2;
    padding: 10px;
    font-size: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
    width: 100%;
    text-align: justify;
    font-weight: bold;
    
}

.order-buttons {
    padding: 10px 20px;
    font-size: 16px;
    border: none;
    border-radius: 4px;
    color: #fff;
    background-color: #007bff;
    cursor: pointer;
    margin-right: 10px;
}

.order-buttons:hover {
    background-color: #0056b3;
}

.order-buttons:active {
    background-color: #1a5736;
    transform: scale(0.98);
}

        /* Styling for summary items container */
        /* Centering the order type and customer type containers */
.summary-items-container {
    display: flex;
    justify-content: center; /* Center the items horizontally */
    align-items: center; /* Center the items vertically */
    gap: 20px; /* Space between items */
    margin-bottom: 20px; /* Optional: space below the container */
}

/* Adjust the width of select elements if needed */
.summary-items select {
    padding: 10px;
    font-size: 14px; /* Adjust font size as needed */
    width: 150px; /* Set a fixed width for the selects */
}

        /* Individual summary item styling */
        .summary-items {
            display: flex;
            align-items: center; /* Center label and select vertically */
            gap: 10px; /* Space between label and select */
        }

        .summary-items label {
            font-size: 14px; /* Adjust font size as needed */
            font-weight: bold; /* Make label text bold */
        }

        .summary-items select {
            padding: 5px;
            font-size: 14px; /* Adjust font size as needed */
        }

        .logo-container {
    display: flex; /* Use flexbox for alignment */
    align-items: center; /* Vertically center the items */
    justify-content: center; /* Align to the right */
    margin-right: 30px;
}

.logo-container img {
    height: 80px; /* Set height */
    width: auto; /* Maintain aspect ratio */
    border-radius: 50%; /* Optional: make the logo oval */
    margin-right: 10px; /* Space between logo and info */
}

.date-time {
            margin-top: 0   ;
    font-size: 25px; /* Adjust size as needed */
    text-align: right;
}


.product-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px; /* Space between product items */
        }

.product-item {
            width: calc(25% - 5px); /* Adjust width as needed */
            padding: 5px;
            border: 1px solid #ddd; /* Border for product items */
            border-radius: 8px; /* Rounded corners */
            text-align: center; /* Center text */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Light shadow */
        }

         /* Individual category box styling */
    /* Individual product box styling */
.product-box {
    width: calc(25% - 10px); /* 4 columns */
    padding: 15px; /* Padding for product items */
    border: 1px solid #ddd; /* Border */
    border-radius: 8px; /* Rounded corners */
    text-align: center; /* Center text */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Light shadow */
    background-color: whitesmoke; /* Background color */
}

    .product-box img {
        width: 100%; /* Make the image take the full width of the box */
        height: auto; /* Maintain aspect ratio */
        max-height: 70%; /* Limit height to ensure text fits */
        object-fit: cover; /* Ensure the image covers the area without distortion */
    }

    .product-box h4,
    .product-box p {
        font-size: 14px; /* Adjust font size for better fitting */
        margin: 2px 0; /* Space above and below paragraphs */
    }

    .product-box button {
        padding: 3px 5px; /* Button padding */
        font-size: 13px; /* Button font size */
        border: none; /* Remove default border */
        border-radius: 4px; /* Rounded corners */
        background-color: #2E8B57; /* Button background color */
        color: white; /* Button text color */
        cursor: pointer; /* Pointer cursor on hover */
        transition: background-color 0.3s; /* Smooth transition */
        margin-top: 5px;
    }

    .product-box button:hover {
        background-color: #1c6b40; /* Darker background on hover */
    }

.search-bar {
    margin-bottom: 20px; /* Space below the search bar */
}

.search-bar input {
    width: 50%; /* Full width for input */
    padding: 10px; /* Padding for input */
    font-size: 14px; /* Font size for input text */
    border: 1px solid #ccc; /* Border for input */
    border-radius: 4px; /* Rounded corners */
}

/* Adjust product categories */
#productCategories {
    display: flex;
    flex-wrap: wrap; /* Allows the boxes to wrap */
    gap: 10px; /* Space between boxes */
    overflow-y: auto; /* Add scroll if necessary */
    max-height: 550px; /* Limit height */
    width: 55%; /* Adjust width as needed */
}

@media print {
    #salesTable {
        display: none; /* Hide the sales table */
    }
    #cancelButton {
        display: none; /* Hide the cancel button */
    }
}

@media print {
        /* Hide unnecessary elements in print */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: none; /* Remove table borders */
            text-align: left;
            padding: 8px;
        }

        /* Hide cancel button during print */
        td:last-child, th:last-child {
            display: none;
        }

        /* Basic formatting for receipt */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #000;
        }

        h2, label {
            text-align: left;
        }

        .summary-item, .summary-items-container {
            display: block;
        }

        /* Remove action buttons */
        button {
            display: none;
        }

        /* Optional: Customize font size */
        td, th {
            font-size: 14px;
        }

        /* Remove table lines */
        table {
            border: none;
        }
    }

    @media print {
            .print-hide {
                display: none;
            }
        }

        .product-box {
    position: relative;
    width: 200px; /* Adjust to fit your layout */
    text-align: center;
    margin: 10px;
}

.product-box img {
    width: 100%;
    height: auto;
    display: block;
}

.product-box.not-available .overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6); /* Semi-transparent overlay */
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 20px;
    font-weight: bold;
}

.product-box.not-available button {
    opacity: 0.6;
    pointer-events: none;
    cursor: not-allowed;
}

.overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 0, 0, 0.7); /* Red background for expired */
    color: white; /* White text */
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    border-radius: 5px;
}
    </style>
</head>
<body>
<header>
    <img src="logo.png" alt="" class="logo">
    <h1>DIANE'S SEAFOOD HOUSE</h1>
   
    <div class="logout-button">
        <button onclick="confirmLogout()" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </button>
    </div>
</header>



<div class="content">


    <!-- Column 1: Product Categories -->
    <div class="column" id="productCategoriesContainer">

        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products..." onkeyup="filterProducts()">
        </div>
        <div class="category-buttons">
            <?php foreach ($products_by_category as $category => $products): ?>
                <button onclick="showCategory('<?php echo htmlspecialchars($category); ?>')">
                    <?php echo htmlspecialchars($category); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div id="productCategories">
            <?php foreach ($products_by_category as $category => $products): ?>
                <?php foreach ($products as $product): ?>
                    <?php
                        $isOutOfStock = $product['current_stock'] <= 0;
                        $isExpired = strtotime($product['expiration_date']) < time(); // Check if product is expired
                        $imagePath = isset($product['image']) && !empty($product['image']) ? 'uploads/' . htmlspecialchars($product['image']) : '';
                    ?>
                    <div class="product-box <?php echo $isOutOfStock ? 'not-available' : ''; ?>" 
                         data-category="<?php echo htmlspecialchars($category); ?>" 
                         data-stock="<?php echo htmlspecialchars($product['current_stock']); ?>" 
                         data-id="<?php echo htmlspecialchars($product['product_id']); ?>">

                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        
                        <?php if ($isOutOfStock): ?>
                            <div class="overlay">Not Available</div>
                        <?php endif; ?>
                        
                        <?php if ($isExpired): ?>
                            <div class="overlay">Expired</div> <!-- Overlay for expired products -->
                        <?php endif; ?>

                        <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                        <p>₱ <?php echo htmlspecialchars($product['price']); ?></p>
                        <button onclick="selectProduct('<?php echo htmlspecialchars($product['product_id']); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>', <?php echo htmlspecialchars($product['price']); ?>)" <?php echo $isOutOfStock ? 'disabled' : ''; ?>>Select</button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
  
    <!-- Column 2: Sales Table -->
    <div class="column" id="salesTableContainer">
    <h2>ORDERS</h2> 

   
<div class="summary-items-container">


    <div class="summary-items">
        <label for="orderType">Order Type: </label>
        <select id="orderType">
            <option value="Dine In">Dine In</option>
            <option value="Take Out">Take Out</option>
        </select>
    </div>
    <div class="summary-items">
        <label for="customerType">Customer Type: </label>
        <select id="customerType">
            <option value="Regular">Regular</option>
            <option value="Senior/PWD">Senior Citizen/PWD</option>
        </select>
    </div>
    <div class="summary-items">
        <label for="cashType">Payment Type: </label>
        <select id="cashType">
            <option value="Cash">Cash</option>
            <option value="Online">Online</option>
        </select>
    </div>
</div>
<div class="salesTableWrapper">
<table id="salesTable">
<thead>
<th style="display:none;">Date</th> <!-- Hidden date -->
        <th>Product Name</th>
        <th>Quantity</th>
        <th>Price</th>
        <th>Total</th>
    </t>
</thead>
<tbody>
</table>
<td></td> <!-- Added column for action buttons -->


<div id="orderSummary">
<div class="summary-item">
<label for="totalAmount">Total Amount </label>
<input type="text" id="totalAmount" readonly>
</div>
<div class="summary-item">
<label for="amountPaid">Amount Paid </label>
<input type="number" id="amountPaid" placeholder="">
</div>
<div class="summary-item">
<label for="changeAmount">Change Amount </label>
<input type="text" id="changeAmount" readonly>
</div>
<div class="summary-item">
<button class="order-buttons" onclick="calculateChange()">Calculate Change</button> 
<button class="order-buttons" onclick="submitOrder()">ADD Order</button>

</div>
<div id="selectedCashier" style="font-size: 20px; margin-top: 0; text-align: left; margin-bottom: 10px;">
        <strong>Cashier: </strong><?php echo $cashierName; // Display the cashier's name ?>
    </div>
        <!-- Receipt Section (This will be displayed after the order is added) -->
        <div id="receiptContainer" style="display: none;">
            <h2>Receipt</h2>
            <div id="receiptContent"></div>
        </div>

    </div>
</div>
</div>

<script>

function confirmLogout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php'; // Replace with your actual logout URL
        }
    }

function filterProducts() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const productBoxes = document.querySelectorAll('.product-box');

    productBoxes.forEach(box => {
        const productName = box.querySelector('h4').textContent.toLowerCase(); // Get the product name from the heading
        if (productName.includes(input)) {
            box.style.display = 'block'; // Show matching products
        } else {
            box.style.display = 'none'; // Hide non-matching products
        }
    });
}

function showCategory(category) {
    // Hide all product boxes
    const allProductBoxes = document.querySelectorAll('.product-box');
    allProductBoxes.forEach(box => {
        box.style.display = 'none'; // Hide all product boxes
    });

    // Show the selected category boxes
    const selectedBoxes = document.querySelectorAll(`.product-box[data-category="${category}"]`);
    selectedBoxes.forEach(box => {
        box.style.display = 'block'; // Show selected boxes
    });

    // Save the selected category to local storage
    localStorage.setItem('selectedCategory', category);
}

function loadSelectedCategory() {
    // Get the selected category from local storage
    const selectedCategory = localStorage.getItem('selectedCategory');
    if (selectedCategory) {
        showCategory(selectedCategory); // Show the saved category on load
    }
}

// Load the selected category on page load
window.onload = loadSelectedCategory;

     // Function to format the date as needed
     function formatDate(date) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString(undefined, options);
    }

    // Set the current date in the specified paragraph
    document.addEventListener('DOMContentLoaded', function() {
        const dateParagraph = document.getElementById('currentDate');
        const today = new Date();
        dateParagraph.textContent = formatDate(today);
    });

    
    function navigateTo(page) {
        window.location.href = page + '.php';
    }

    function filterCategory(category) {
        const boxes = document.querySelectorAll('#productCategories .box');
        boxes.forEach(box => {
            if (category === 'Cancel' || box.dataset.category === category) {
                box.style.display = '';
            } else {
                box.style.display = 'none';
            }
        });
    }
   

    const salesTableBody = document.querySelector('#salesTable tbody');
let totalAmount = 0; // Initialize totalAmount to track the total price of all items
let orderDate = new Date().toLocaleDateString();
let saleDataArray = []; // Array to store sale data temporarily

function confirmLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php'; // Replace with your actual logout URL
    }
}

// ... (rest of your existing code)

function selectProduct(productId, product_name, productPrice) {
    const productBox = document.querySelector(`.product-box[data-id="${productId}"]`);
    
    if (!productBox) {
        alert('Product not found!');
        return;
    }

    const currentStock = parseInt(productBox.getAttribute('data-stock'));

    if (currentStock <= 0) {
        alert(`The product ${product_name} is out of stock! Unable to proceed with the order.`);
        return;
    }

    const quantity = prompt('Enter quantity:');

    // Validate quantity input
    if (quantity && !isNaN(quantity) && quantity > 0) {
        // Check if requested quantity exceeds available stock
        if (quantity > currentStock) {
            alert(`Insufficient stock! Only ${currentStock} available for ${product_name}.`);
            return; // Exit the function if stock is insufficient
        }

        const orderType = document.getElementById('orderType').value;
        const customerType = document.getElementById('customerType').value;
        const cashType = document.getElementById('cashType').value; // Get selected Cash Type
        
        let discount = 0;
        if (customerType === 'Senior/PWD') {
            discount = 0.20; // 20% discount
        }
        
        // Calculate total after applying discount
        const total = (productPrice * quantity) * (1 - discount);
        
        // Call addSale to display the order details
        addSale(orderDate, product_name, quantity, productPrice, total, cashType, productId);
    } else {
        alert('Invalid quantity. Please enter a valid number.');
    }
}

function addSale(date, product, quantity, price, total, cashType, productId) {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td style="display:none;">${date}</td> <!-- Hidden date -->
        <td>${product}</td>
        <td>${quantity}</td>
        <td>${price.toFixed(2)}</td>
        <td>${total.toFixed(2)}</td>
        <td><button onclick="cancelSale(this, '${productId}', ${quantity})">Cancel</button></td>
    `;
    salesTableBody.appendChild(row);

    totalAmount += total;
    document.getElementById('totalAmount').value = totalAmount.toFixed(2) + ' Php';

    // Store sale data in the array for later recording
    saleDataArray.push({
        saleDate: date,
        product_name: product,
        quantity: quantity,
        price: price,
        total: total,
        orderType: document.getElementById('orderType').value,
        customerType: document.getElementById('customerType').value,
        cashType: cashType,
        productId: productId // Store product ID for stock updates
    });
}

function cancelSale(button, productId, quantity) {
    const row = button.closest('tr');
    const total = parseFloat(row.cells[4].textContent); // Get the total from the table cell
    
    // Remove the row from the sales table
    salesTableBody.removeChild(row);
    
    // Update totalAmount
    totalAmount -= total;
    document.getElementById('totalAmount').value = totalAmount.toFixed(2) + ' Php';

    // Return the stock of the canceled product
    const productBox = document.querySelector(`.product-box[data-id="${productId}"]`);
    const currentStock = parseInt(productBox.getAttribute('data-stock'));
    
    // Add the canceled quantity back to stock
    const newStock = currentStock + parseInt(quantity);
    productBox.setAttribute('data-stock', newStock); // Update the stock attribute

    const stockLabel = productBox.querySelector('.stock-display'); // Update the UI
    if (stockLabel) {
        stockLabel.textContent = `Current Stock: ${newStock}`;
    }

    // Optionally, show an alert to confirm cancellation
    alert(`Order for ${productId} canceled. Stock returned: ${quantity}.`);
}

function calculateChange() {
        const amountPaid = parseFloat(document.getElementById('amountPaid').value);
        if (isNaN(amountPaid)) {
            alert('Please enter a valid amount paid.');
            return;
        }

        const changeAmount = amountPaid - totalAmount;
        if (changeAmount < 0) {
            alert('Amount paid is insufficient. Please enter a higher amount.');
            document.getElementById('changeAmount').value = '';
        } else {
            document.getElementById('changeAmount').value = changeAmount.toFixed(2) + ' Php';
        }
    }

function submitOrder() {
    const orderType = document.getElementById('orderType').value;
    const customerType = document.getElementById('customerType').value;
    const cashType = document.getElementById('cashType').value; // Get the selected cash type
    const totalAmount = parseFloat(document.getElementById('totalAmount').value.replace(' Php', ''));
    const amountPaid = parseFloat(document.getElementById('amountPaid').value);
    const changeAmount = parseFloat(document.getElementById('changeAmount').value.replace(' Php', ''));

    // Format orderDate as YYYY-MM-DD
    const orderDate = new Date().toISOString().split('T')[0];

    // Generate or get a unique customer ID
    const customerId = generateUniqueCustomerId();

    // Record the customer data
    recordCustomer(customerId, customerType, orderDate);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'record_order.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert('Order placed successfully!');

                // Automatically display the receipt after placing the order
                displayReceipt(orderType, customerType, cashType, totalAmount, amountPaid, changeAmount, orderDate);
                
                // Now, record each sale from the saleDataArray
                saleDataArray.forEach(sale => {
                    recordSale(sale); // Call recordSale for each item in the array
                });
                
                // Clear the saleDataArray after recording
                saleDataArray = [];
            } else {
                alert('Failed to place the order. ' + response.message);
            }
        } else {
            alert('An error occurred while placing the order.');
        }
    };

    const data = `orderType=${orderType}&customerType=${customerType}&cashType=${cashType}&totalAmount=${totalAmount}&amountPaid=${amountPaid}&changeAmount=${changeAmount}&customerId=${customerId}`;
    xhr.send(data);
}

// Function to record each sale in the database
function recordSale(sale) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'record_sale.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    const data = `saleDate=${sale.saleDate}&product_name=${sale.product_name}&quantity=${sale.quantity}&price=${sale.price}&total=${sale.total}&orderType=${sale.orderType}&customerType=${sale.customerType}&cashType=${sale.cashType}&productId=${sale.productId}`;
    
    xhr.send(data);

    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                console.log(`Sale recorded successfully for ${sale.product_name}`);
            } else {
                console.error(`Failed to record sale for ${sale.product_name}: ${response.message}`);
            }
        } else {
            console.error('An error occurred while recording the sale.');
        }
    };
}



function displayReceipt(orderType, customerType, cashType, totalAmount, amountPaid, changeAmount, orderDate) {
    let itemsHtml = '';
    const salesTable = document.getElementById('salesTable');

    salesTable.querySelectorAll('tr').forEach(row => {
        const productName = row.cells[1].innerText;
        const quantity = row.cells[2].innerText;
        const price = row.cells[3].innerText;
        const totalPrice = row.cells[4].innerText;

        itemsHtml += `
            <div style="display: flex; justify-content: space-between; padding: 5px 0; margin-top: 20px;">
                <span>${productName}</span>
                <span>${quantity}</span>
                <span>${price} Php</span>
                <span>${totalPrice} Php</span>
            </div>
        `;
    });

    const receiptContent = `
        <div style="width: 250px; margin: 0 auto; text-align: left; font-family: 'Courier New', monospace; font-size: 12px;">
            <h3 style="text-align: center;">Diane's Seafood House</h3>
            <p style="text-align: center;">Tulay, Pob.3 Cracra City, Cebu</p>
            <p style="text-align: center;">Tel: (123) 456-7890</p>
            <hr style="border: 1px dashed #000;">
            <p style="white-space: nowrap;">
                <strong>Date:</strong> ${orderDate} | 
                <strong></strong> ${orderType} | 
                <strong></strong> ${customerType} | 
                <strong></strong> ${cashType}
            </p>
            <hr style="border: 1px dashed #000;">
            ${itemsHtml}
            <hr style="border: 1px dashed #000;">
            <div style="display: flex; justify-content: space-between;">
                <span><strong>Total Amount:</strong></span>
                <span><strong>${totalAmount.toFixed(2)} Php</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Amount Paid:</span>
                <span>${amountPaid.toFixed(2)} Php</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Change:</span>
                <span>${changeAmount.toFixed(2)} Php</span>
            </div>
            <hr style="border: 1px dashed #000;">
            <p style="text-align: center;">"Thank you for dining with us! Please come again!"</p>
        </div>
    `;

    const printWindow = window.open('', '', 'width=700,height=600');
    printWindow.document.write(receiptContent);
    printWindow.document.close();
    
    // Add a timeout to close the print window and reset order data without reload
    printWindow.print();
    setTimeout(() => {
        printWindow.close();

        // Reset all input fields and table rows after printing
        document.getElementById('salesTable').innerHTML = '';
        document.getElementById('totalAmount').value = '0.00 Php';
        document.getElementById('amountPaid').value = '';
        document.getElementById('changeAmount').value = '';
        
        // Reset any additional order-related fields if needed
        document.getElementById('orderType').value = '';
        document.getElementById('customerType').value = '';
        document.getElementById('cashType').value = '';
        
    }, 500); // Adjust the delay if needed
}


function generateUniqueCustomerId() {
    // Implement a method to generate a unique customer ID
    // For example, use a UUID or a session-based ID
    return 'CUSTOMER_' + Math.random().toString(36).substr(2, 9); // Example implementation
}


function recordCustomer(customerId, customerType, orderDate) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'record_customer.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                console.log(response.message); // Customer recorded successfully
            } else {
                console.error(response.message); // Failed to record customer
            }
        } else {
            console.error('An error occurred while recording the customer.');
        }
    };

    // Encode data for URL-encoded format
    const data = `customerId=${encodeURIComponent(customerId)}&customerType=${encodeURIComponent(customerType)}&orderDate=${encodeURIComponent(orderDate)}`;
    xhr.send(data);
}


function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    document.getElementById('currentDayTime').textContent = now.toLocaleDateString('en-US', options);
}

updateDateTime();
setInterval(updateDateTime, 60000);


</script>

</body>
</html>