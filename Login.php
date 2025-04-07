<?php
session_start();

// Redirect if not logged in or not admin
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "St_Alphonsus_Primary_School";

$conn = new mysqli($servername, $username, $password, $database);

// Connection error check
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$successMessage = "";
$error = "";

// Handle delete request
if (isset($_GET['delete_ta_id'])) {
    $deleteId = intval($_GET['delete_ta_id']);
    $conn->query("DELETE FROM Teaching_Assistant WHERE ta_id = $deleteId");
    $successMessage = "Teaching Assistant deleted successfully.";
}

// Handle add request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstname = $_POST['firstname'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $salary = $_POST['salary'] ?? '';

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
        $error = "All fields are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO Teaching_Assistant (ta_firstname, ta_surname, ta_address, ta_phone_number, ta_salary) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $firstname, $surname, $address, $phone, $salary);

        if ($stmt->execute()) {
            $successMessage = "Teaching Assistant added successfully.";
        } else {
            $error = "Error adding TA: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all TAs for display
$tas = $conn->query("SELECT * FROM Teaching_Assistant");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>
    <h1>Admin Dashboard</h1>

    <?php if ($successMessage): ?>
        <p style="color: green;"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p style="color: red;"><?= $error ?></p>
    <?php endif; ?>

    <h2>Add Teaching Assistant</h2>
    <form method="post">
        First Name: <input type="text" name="firstname"><br>
        Surname: <input type="text" name="surname"><br>
        Address: <input type="text" name="address"><br>
        Phone: <input type="text" name="phone"><br>
        Salary: <input type="text" name="salary"><br>
        <input type="submit" value="Add TA">
    </form>

    <h2>Existing Teaching Assistants</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>First Name</th>
            <th>Surname</th>
            <th>Address</th>
            <th>Phone</th>
            <th>Salary</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $tas->fetch_assoc()): ?>
            <tr>
                <td><?= $row['ta_id'] ?></td>
                <td><?= $row['ta_firstname'] ?></td>
                <td><?= $row['ta_surname'] ?></td>
                <td><?= $row['ta_address'] ?></td>
                <td><?= $row['ta_phone_number'] ?></td>
                <td><?= $row['ta_salary'] ?></td>
                <td><a href="?delete_ta_id=<?= $row['ta_id'] ?>" onclick="return confirm('Are you sure you want to delete this TA?');">Delete</a></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
$conn->close();
?>
