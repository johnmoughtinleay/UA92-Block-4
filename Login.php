<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$database = "St_Alphonsus_Primary_School";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $inputUsername = $_POST['username'] ?? '';
    $inputPassword = $_POST['password'] ?? '';
    $inputRole = $_POST['role'] ?? '';

    $stmt = $conn->prepare("SELECT user_hashed_password, user_type FROM user WHERE username = ?");
    $stmt->bind_param("s", $inputUsername);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_hashed_Password, $user_type);
        $stmt->fetch();

        if (password_verify($inputPassword, $user_hashed_Password)) {
            if ($inputRole === $user_type) {
                $_SESSION['username'] = $inputUsername;
                $_SESSION['user_type'] = $user_type;

                // Redirect based on role
                switch ($user_type) {
                    case 'teacher':
                        header("Location: teacher_dashboard.php");
                        exit();
                    case 'student':
                        header("Location: student_dashboard.php");
                        exit();
                    case 'parent':
                        header("Location: parent_dashboard.php");
                        exit();
                    case 'ta':
                        header("Location: ta_dashboard.php");
                        exit();
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        exit();
                    default:
                        $error = "Unknown role.";
                }
            } else {
                $error = "Invalid role selected for this username.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>St Alphonsus Primary School - Login</title>
    <link rel="stylesheet" href="css/Main_style.css">
    <link rel="stylesheet" href="Website_form.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4 text-center">St Alphonsus Primary School</h1>
        <h2 class="mb-4">User Information Form</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="firstname" class="form-label">Username:</label>
                <input type="text" class="form-control" id="firstname" name="username" placeholder="Enter your username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role:</label>
                <select class="form-control" id="role" name="role" required>
                    <option value="" disabled selected>Select your role</option>
                    <option value="teacher">Teacher</option>
                    <option value="student">Student</option>
                    <option value="parent">Parent</option>
                    <option value="ta">TA</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
