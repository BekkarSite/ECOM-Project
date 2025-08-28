<?php
session_start();
include('../includes/db.php'); // Include the database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check if user exists in the database
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify the password entered by the user with the stored hash
        if (password_verify($password, $user['password'])) {
            // If the password is correct, start the session and log the user in
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            header('Location: index.php'); // Redirect to the homepage after login
        } else {
            $error = "Invalid credentials!";
        }
    } else {
        $error = "No user found with that email!";
    }
}
?>

<form method="POST">
    <label>Email:</label><br>
    <input type="email" name="email" required><br>
    <label>Password:</label><br>
    <input type="password" name="password" required><br>
    <button type="submit">Login</button>
    <?php if (isset($error)) echo "<p>$error</p>"; ?>
</form>
