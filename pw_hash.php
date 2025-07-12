<?php
// Your plain text password
$password = 'ilovek2d'; // Replace this with the password you want to hash

// Generate the hashed password using bcrypt
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Output the hashed password
echo 'Hashed Password: ' . $hashedPassword;
?>
