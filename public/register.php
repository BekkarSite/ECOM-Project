<?php
session_start();
include('../includes/db.php'); // Include the database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Hash the password using bcrypt
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into the database with hashed password
        $sql = "INSERT INTO users (email, password, role) VALUES ('$email', '$hashed_password', 'customer')";
        if ($conn->query($sql) === TRUE) {
            echo "Registration successful! You can now <a href='login.php'>login</a>.";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>

<form method="POST">
    <label>Email:</label><br>
    <input type="email" name="email" required><br>
    <label>Password:</label><br>
    <input type="password" name="password" required><br>
    <label>Confirm Password:</label><br>
    <input type="password" name="confirm_password" required><br>
    <button type="submit">Register</button>
    <?php if (isset($error)) echo "<p>$error</p>"; ?>
</form>
