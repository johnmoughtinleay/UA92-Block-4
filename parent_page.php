<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "St_Alphonsus_Primary_School";

$conn = new mysqli($servername, $username, $password, $database);

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['username']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'parent') {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['parent_id'])) {
    $parent_id = $_SESSION['parent_id'];

    $stmt = $conn->prepare("SELECT * FROM parent_guardian WHERE parent_id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $parentData = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>parent Page</title>
    <link rel="stylesheet" href="School.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="container col-6">
    <div class="text-end mb-3">
        <a href="?logout=true" class="btn btn-danger">Logout</a>
    </div>
    <h1 class="mb-4 text-center">St Alphonsus Primary School</h1>
    <h2 class="text-center">Welcome, <?= htmlspecialchars($parentData['parent_firstname']) ?>!</h2>

    <div class="card mt-4">
        <div class="card-header">Your Details</div>
        <div class="card-body">
            <p><strong>parent ID:</strong> <?= htmlspecialchars($parentData['parent_id']) ?></p>
            <p><strong>First Name:</strong> <?= htmlspecialchars($parentData['parent_firstname']) ?></p>
            <p><strong>Last Name:</strong> <?= htmlspecialchars($parentData['parent_surname']) ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($parentData['parent_address']) ?></p>
            <p><strong>Phone number:</strong> <?= htmlspecialchars($parentData['parent_phone_number']) ?></p>
        </div>
    </div>
</div>

</body>
</html>
