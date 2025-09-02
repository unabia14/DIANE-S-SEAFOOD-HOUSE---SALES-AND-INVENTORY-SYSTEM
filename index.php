<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
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
            background-image: url('admin/loginbg.jpg');
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
    text-align: center;
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



        .button-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 70px; /* Adjust margin for better spacing */
        }

        .button {
            padding: 25px 35px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            background-color: #007BFF;
            color: white;
            text-decoration: none; /* Remove underline from links */
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #0056b3;
        }
        .logo {
            width: 200px; /* Set the desired width */
            height: 200px; /* Set the same height for a square image */
            border-radius: 50%; /* Makes the image circular */
            object-fit: cover; /* Ensures the image covers the container without distortion */
            margin-bottom: 30px; /* Space below the logo */
            margin-top: 1px;
        }
        .logo-name {
    margin: 5px 0; /* Set margin above and below to reduce space */
    font-size: 17px; /* Adjust font size */
    color: #333; /* Set text color */
}

h2 {
    margin: 5px 0; /* Reduce margin to bring h2 closer to h3 */
    font-family: Matura MT Script Capitals;
    font-size: 25px;
}
.error-message {
            color: red;
            margin-top: 10px;
            font-size: 16px;
            text-align: center;
            display: none; /* Hidden by default */
        }

    </style>
</head>
<body>
<div class="login-container">
        <img src="admin/logo.png" alt="Logo" class="logo">
        <h3 class="logo-name">Diane's Seafood House</h3>
        <h2>Sales and Inventory System</h2>
        <div class="button-container">
            <button class="button" onclick="enterAdminPin()">Admin Login</button>
            <a href="cashier/login.php" class="button">Cashier Login</a>
        </div>
        <div id="error-message" class="error-message">Incorrect PIN. Please try again.</div>
    </div>

    <script>
           function enterAdminPin() {
            
            var pin = prompt("Please enter PIN:");

            if (pin === null) {
              
                return;
            }

          
            var correctPin = "1234"; 
            if (pin === correctPin) {
                
                var form = document.createElement("form");
                form.method = "POST";
                form.action = "set_pin.php"; 

               
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "admin_pin";
                input.value = pin;
                form.appendChild(input);

                
                document.body.appendChild(form);
                form.submit();
            } else {
                
                var errorMessage = document.getElementById("error-message");
                errorMessage.style.display = "block";

               
                setTimeout(function() {
                    enterAdminPin(); 
                }, 1000); 
            }
        }
    </script>
</body>
</html>