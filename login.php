<?php
session_start(); 
include('db.php'); 

$message = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cashierUsername = trim($_POST['username']);
    $cashierPassword = trim($_POST['password']);

    if (empty($cashierUsername) || empty($cashierPassword)) {
        $message = "Please enter both username and password.";
    } else {
       
        $stmt = $conn->prepare("SELECT * FROM cashiers WHERE username = ?");
        $stmt->bind_param("s", $cashierUsername);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
           
            if (password_verify($cashierPassword, $row['password'])) {
               
                $_SESSION['cashier_id'] = $row['cashier_id']; 
                $_SESSION['cashier_username'] = $row['username'];

                header("Location: cashier_dashboard.php");
                exit;
            } else {
                $message = "Incorrect password.";
            }
        } else {
            $message = "Cashier not found.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Login</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link your CSS file -->
    <style>
        body {
            font-family: Roboto, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            height: 100vh;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            background-image: url('loginbg.jpg');
            background-size: cover;
            filter: blur(3px); /* Adjust the blur intensity as needed */
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.8), /* Outer green glow */
            0 0 15px rgba(0, 255, 0, 0.8); /* Inner green glow */
            width: 400px;
            text-align: left;
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            transition: box-shadow 0.3s ease-in-out;
        }

        .login-container:hover {
    box-shadow: 0 0 25px rgba(0, 255, 0, 1), /* Stronger green glow on hover */
                0 0 25px rgba(0, 255, 0, 1);
}

        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 93%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: blue;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 21px;
            font-weight: bold;
        }
        button:hover {
            background-color: blue;
        }

        .centered {
        text-align: center; /* Center the text */
        margin-bottom: 20px; /* Optional: add some space below the heading */
        font-size: 30px;
    }

    
    .logo {
            width: 200px; /* Set the desired width */
            height: 200px; /* Set the same height for a square image */
            border-radius: 50%; /* Makes the image circular */
            object-fit: cover; /* Ensures the image covers the container without distortion */
            margin-bottom: 30px; /* Space below the logo */
            margin-top: 1px;
            margin-left: 100px;
        }
        .logo-name {
    margin: 5px 0; /* Set margin above and below to reduce space */
    font-size: 17px; /* Adjust font size */
    color: #333; /* Set text color */
}

.back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: blue;
            text-decoration: none;
        }

        .back-button:hover {
            color: darkblue;
        }
    </style>
</head>
<body>
    <div class="login-container">
    <a href="../index.php" class="back-button">🔙 </a>

    <img src="logo.png" alt="Logo" class="logo">
    <h2 class="centered">Cashier</h2>
        <?php if (!empty($message)): ?>
            <p class="error"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST" autocomplete="off">
    <div class="form-group">
        <label for="username">Username:</label>
        <input type="text" name="username" id="username" required autocomplete="off">
    </div>
    <div class="form-group">
    <label for="password">Password:</label>
    <div style="position: relative;">
        <input type="password" name="password" id="password" required autocomplete="new-password" style="width: 93%; padding-right: 10px;">
        <span id="togglePassword" style="position: absolute; right: 10px; top: 50%;  font-size: 20px; transform: translateY(-50%); cursor: pointer;">
            👁️
        </span>
    </div>
</div>

    <button type="submit">Login</button>
</form>

    </div>

    <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function () {
        // Toggle the type attribute
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Change the icon (optional, you can add a better icon toggle)
        this.textContent = type === 'password' ? '👁️' : '👁️';
    });
</script>

</body>
</html>