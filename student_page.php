<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_type'] !== 'student') { 
    echo "Access denied.";
    exit();
}


if (!isset($_SESSION['username']) || $_SESSION['user_type'] != 'student') {
    header("Location: login.php"); 
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, Student!</h2>
        <p>Your role: Student</p>
        <p>This is your student dashboard where you can view your assignments and grades.</p>
    </div>
</body>
</html>
