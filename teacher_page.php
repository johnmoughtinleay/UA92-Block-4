<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
  header("Location: login.php");
  exit();
}

if ($_SESSION['user_type'] !== 'teacher') { 
  echo "Access denied.";
  exit();
}


if (!isset($_SESSION['username']) || $_SESSION['user_type'] != 'teacher') {
  header("Location: login.php"); 
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, Teacher!</h2>
        <p>Your role: Teacher</p>
        <p>This is your teacher dashboard where you can manage classes and view student progress.</p>

        <div class="container mt-5">
        <h2 class="mb-4">User Information Form</h2>
        <form>
            <div class="mb-3">
                <label for="firstname" class="form-label">First Name</label>
                <input type="text" class="form-control" id="firstname" placeholder="Enter your first name" required>
            </div>
            <div class="mb-3">
                <label for="surname" class="form-label">Surname</label>
                <input type="text" class="form-control" id="surname" placeholder="Enter your surname" required>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" rows="3" placeholder="Enter your address" required></textarea>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" placeholder="Enter your phone number" required>
            </div> 
            <div class="mb-3">
                <label for="parentId" class="form-label">Parent ID</label>
                <input type="text" class="form-control" id="parentId" placeholder="Enter parent ID" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
    </div>
</body>
</html>
