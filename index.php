<?php
//Starts session
session_start();

//Connects to database
$servername = "localhost";
$username = "root";
$password = "";
$database = "St_Alphonsus_Primary_School";
$conn = new mysqli($servername, $username, $password, $database);

//error message for debugging
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//defines error
$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $inputUsername = $_POST['username'] ?? ''; //collects inputs
    $inputPassword = $_POST['password'] ?? '';
    $inputRole = $_POST['role'] ?? '';

    //prepared statment to prevent sql injection, gets hashed password and user type
    $stmt = $conn->prepare("SELECT user_hashed_password, user_type FROM user WHERE username = ?");
    $stmt->bind_param("s", $inputUsername);
    $stmt->execute();
    $stmt->store_result();

    //cchecks if 1 user matches username
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_hashed_Password, $user_type);
        $stmt->fetch();

    //uses password verifiy to check hashed password
        if (password_verify($inputPassword, $user_hashed_Password)) {
            //check the role is correct
            if ($inputRole === $user_type) {
                //stores in session
                $_SESSION['username'] = $inputUsername;
                $_SESSION['user_type'] = $user_type;

        // Fetch ID student table
        if ($user_type === 'student') {
            $stmt_id = $conn->prepare("SELECT student_id FROM user_roles WHERE user_id = (SELECT user_id FROM user WHERE username = ?)");
            $stmt_id->bind_param("s", $inputUsername);
            $stmt_id->execute();
            $stmt_id->bind_result($student_id);
            if ($stmt_id->fetch()) {
                $_SESSION['student_id'] = $student_id;
            }
            $stmt_id->close();
        // Fetch ID teacher table
        } elseif ($user_type === 'parent') {
            $stmt_id = $conn->prepare("SELECT parent_id FROM user_roles WHERE user_id = (SELECT user_id FROM user WHERE username = ?)");
            $stmt_id->bind_param("s", $inputUsername);
            $stmt_id->execute();
            $stmt_id->bind_result($parent_id);
            if ($stmt_id->fetch()) {
                $_SESSION['parent_id'] = $parent_id;
            }
            $stmt_id->close();
        }
                // Redirect to correct webpage
                switch ($user_type) {
                    case 'teacher':
                        header("Location: teacher_page.php");
                        exit();
                    case 'student':
                        header("Location: student_page.php");
                        exit();
                    case 'parent':
                        header("Location: parent_page.php");
                        exit();
                    case 'ta':
                        header("Location: ta_page.php");
                        exit();
                    case 'admin':
                        header("Location: admin_page.php");
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
    <!--links bootstrap and stylesheet-->
    <link rel="stylesheet" href="School.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4 text-center">St Alphonsus Primary School</h1>
        <h2 class="mb-4">User Information Form</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
            <!--creates form using post method-->
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
                <!--dropdown option input-->
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
    <!--links bootstrap javascript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
