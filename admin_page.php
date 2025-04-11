<!-- Handles server connection-->

<?php
session_start();

//logs user out 
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Redirect if not logged in or not admin
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
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


//////////////////////////////////////////////////////////////////////////////////////////////////////////
//Handles Teaching Assistants//


// Handle Delete TA
if (isset($_GET['delete_ta_id'])) {
    $deleteId = intval($_GET['delete_ta_id']);
    $stmt = $conn->prepare("DELETE FROM Teaching_Assistant WHERE ta_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    $successMessage = "Teaching Assistant deleted successfully.";
    
    //Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE ta_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    // Delete from user_roles table
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE ta_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();         
            
    // Commit transaction if all deletions are successful
    $conn->commit();
}

// Handle Add TA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_ta'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'ta';

    $username = ($firstname . $surname);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    // Validate inputs
    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary) || empty($plainPassword)) {
        $error = "All fields are required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } elseif (!is_numeric($salary) || $salary <= 0) { // Ensure salary is a positive number
        $error = "Salary must be a positive number.";
    } else {
        // 1. Insert into `user`
        $stmt = $conn->prepare("INSERT INTO user (username, user_hashed_password, user_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $userType);
        if (!$stmt->execute()) {
            $error = "Failed to add user: " . $stmt->error;
            $stmt->close();
        } else {
            $userId = $stmt->insert_id;
            $stmt->close();

            // 2. Insert into Teaching_Assistant
            $stmt = $conn->prepare("INSERT INTO Teaching_Assistant (ta_firstname, ta_surname, ta_address, ta_phone_number, ta_salary) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssd", $firstname, $surname, $address, $phone, $salary);
            if (!$stmt->execute()) {
                $error = "Failed to add TA: " . $stmt->error;
                $stmt->close();
            } else {
                $taId = $stmt->insert_id;
                $stmt->close();

                // 3. Insert into user_roles
                $stmt = $conn->prepare("INSERT INTO user_roles (user_id, ta_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $taId);
                if ($stmt->execute()) {
                    $successMessage = "Teaching Assistant added successfully.";
                } else {
                    $error = "Failed to add user role: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_ta'])) {
    $editId = intval($_POST['edit_ta_id']);
    $firstname = trim($_POST['edit_ta_firstname'] ?? '');
    $surname = trim($_POST['edit_ta_surname'] ?? '');
    $address = trim($_POST['edit_ta_address'] ?? '');
    $phone = trim($_POST['edit_ta_phone'] ?? '');
    $salary = trim($_POST['edit_ta_salary'] ?? '');

    // Validate inputs
    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
        $error = "All fields are required for editing.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } elseif (!is_numeric($salary) || $salary <= 0) { // Ensure salary is a positive number
        $error = "Salary must be a positive number.";
    } else {
        $stmt = $conn->prepare("UPDATE Teaching_Assistant SET ta_firstname=?, ta_surname=?, ta_address=?, ta_phone_number=?, ta_salary=? WHERE ta_id=?");
        $stmt->bind_param("ssssdi", $firstname, $surname, $address, $phone, $salary, $editId);

        if ($stmt->execute()) {
            $successMessage = "Teaching Assistant updated successfully.";
        } else {
            $error = "Error updating TA: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all TAs
$tas = $conn->query("SELECT * FROM Teaching_Assistant");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Page</title>
    <link rel="stylesheet" href="School.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
</head>
<body>

<div class="container col-6">
    <div class="text-end mb-3">
        <a href="?logout=true" class="btn btn-danger">Logout</a>
    </div>
    <h1 class="mb-4 text-center">St Alphonsus Primary School</h1>
    <h2 class="mt-4">Admin Dashboard</h2>

    <p>This is the admin dashboard. Here you can make add, edit, or delete from Teaching Assistant, Teachers, Students, Parents, Admins and Classes!<p>
    
    <p>Manage Teaching Assistant</p>
    <button class="btn btn-primary mb-3" onclick="toggleTASection()">Manage Teaching Assistants</button>

    <!-- Add TA Form -->
    <div id="addtaFormContainer" style="display: none;">
        <h2>Add Teaching Assistant</h2>
        <form method="post">
            <input type="hidden" name="add_ta" value="1">
            First Name: <input class="form-control" type="text" name="firstname"><br>
            Surname: <input class="form-control" type="text" name="surname"><br>
            Address: <input class="form-control" type="text" name="address"><br>
            Phone: <input class="form-control" type="text" name="phone"><br>
            Salary: <input class="form-control" type="text" name="salary"><br>
            Password: <input class="form-control" type="password" name="password"><br>
            <input class="btn btn-success" type="submit" value="Add TA">
        </form>
        <hr>
    </div>

    <!-- Edit TA Form -->
    <div id="edittaFormContainer" style="display: none;">
        <h2>Edit Teaching Assistant</h2>
        <form method="post">
            <input type="hidden" name="edit_ta" value="1">
            <input type="hidden" id="edit_ta_id" name="edit_ta_id">
            First Name: <input class="form-control" type="text" id="edit_ta_firstname" name="edit_ta_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_ta_surname" name="edit_ta_surname"><br>
            Address: <input class="form-control" type="text" id="edit_ta_address" name="edit_ta_address"><br>
            Phone: <input class="form-control" type="text" id="edit_ta_phone" name="edit_ta_phone"><br>
            Salary: <input class="form-control" type="text" id="edit_ta_salary" name="edit_ta_salary"><br>
            <input class="btn btn-warning" type="submit" value="Update TA">
            <button class="btn btn-secondary" type="button" onclick="cancelTaEdit()">Cancel</button>
        </form>
        <hr>
    </div>

    <!-- TA Table -->
    <div id="taTableContainer" style="display: none;">
        <h2>Existing Teaching Assistants</h2>
        <table class="table table-bordered">
            <tr>
                <th class="table-primary">ID</th>
                <th class="table-primary">First Name</th>
                <th class="table-primary">Surname</th>
                <th class="table-primary">Address</th>
                <th class="table-primary">Phone</th>
                <th class="table-primary">Salary</th>
                <th class="table-warning">Edit</th>
                <th class="table-danger">Delete</th>
            </tr>
            <?php $tas->data_seek(0); while ($row = $tas->fetch_assoc()): ?>
                <tr>
                    <td class="table-primary"><?= $row['ta_id'] ?></td>
                    <td class="table-primary"><?= $row['ta_firstname'] ?></td>
                    <td class="table-primary"><?= $row['ta_surname'] ?></td>
                    <td class="table-primary"><?= $row['ta_address'] ?></td>
                    <td class="table-primary"><?= $row['ta_phone_number'] ?></td>
                    <td class="table-primary"><?= $row['ta_salary'] ?></td>
                    <td class="table-warning">
                        <a href="javascript:void(0);" onclick="showEditTAForm(
                            <?= $row['ta_id'] ?>,
                            '<?= htmlspecialchars($row['ta_firstname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['ta_surname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['ta_address'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['ta_phone_number'], ENT_QUOTES) ?>',
                            <?= $row['ta_salary'] ?>
                        )">Edit</a>
                    </td>
                    <td class="table-danger">
                        <a href="?delete_ta_id=<?= $row['ta_id'] ?>" onclick="return confirm('Are you sure you want to delete this TA?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

<script>
function toggleTASection() {
    const addForm = document.getElementById("addtaFormContainer");
    const table = document.getElementById("taTableContainer");
    const editForm = document.getElementById("edittaFormContainer");

    const isVisible = addForm.style.display === "block";

    addForm.style.display = isVisible ? "none" : "block";
    table.style.display = isVisible ? "none" : "block";
    editForm.style.display = "none"; // hide edit if toggling
}

function showEditTAForm(taId, firstname, surname, address, phone, salary) {
    document.getElementById("edit_ta_id").value = taId;
    document.getElementById("edit_ta_firstname").value = firstname;
    document.getElementById("edit_ta_surname").value = surname;
    document.getElementById("edit_ta_address").value = address;
    document.getElementById("edit_ta_phone").value = phone;
    document.getElementById("edit_ta_salary").value = salary;

    document.getElementById("edittaFormContainer").style.display = "block";
    document.getElementById("addtaFormContainer").style.display = "none";
    document.getElementById("taTableContainer").style.display = "none";
}

function cancelTaEdit() {
    document.getElementById("edittaFormContainer").style.display = "none";
    document.getElementById("addtaFormContainer").style.display = "block";
    document.getElementById("taTableContainer").style.display = "block";
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>




<!-- //////////////////////////////////////////////////////////////////////// -->
<!-- Handles Teachers -->
 


<?php
// Handle delete request
if (isset($_GET['delete_teacher_id'])) {
    $deleteId = intval($_GET['delete_teacher_id']);
    $stmt = $conn->prepare("DELETE FROM Teacher WHERE teacher_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
     //Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE teacher_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
             
     //Delete from user_roles table
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE teacher_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();         
             // Commit transaction if all deletions are successful
    $conn->commit();
    $successMessage = "Teacher deleted successfully.";
}

// Handle ADD Teacher
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_teacher'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'teacher';

    $username = $firstname . $surname;

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary) || empty($plainPassword)) {
        $error = "All fields are required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } elseif (!is_numeric($salary) || $salary <= 0) { // Ensure salary is a positive number
        $error = "Salary must be a positive number.";
    } else{
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // 1. Insert into `user`
        $stmt = $conn->prepare("INSERT INTO user (username, user_hashed_password, user_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $userType);
        if (!$stmt->execute()) {
            $error = "Failed to add user: " . $stmt->error;
            $stmt->close();
        } else {
            $userId = $stmt->insert_id;
            $stmt->close();

            // 2. Insert into Teacher
            $stmt = $conn->prepare("INSERT INTO teacher (teacher_firstname, teacher_surname, teacher_address, teacher_phone_number, teacher_salary) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssd", $firstname, $surname, $address, $phone, $salary);
            if (!$stmt->execute()) {
                $error = "Failed to add teacher: " . $stmt->error;
                $stmt->close();
            } else {
                $teacherId = $stmt->insert_id;
                $stmt->close();

                // 3. Insert into user_roles
                $stmt = $conn->prepare("INSERT INTO user_roles (user_id, teacher_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $teacherId);
                if ($stmt->execute()) {
                    $successMessage = "Teacher added successfully.";
                } else {
                    $error = "Failed to add user role: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle EDIT Teacher
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_teacher'])) {
    $editId = intval($_POST['edit_teacher_id']);
    $firstname = trim($_POST['edit_teacher_firstname'] ?? '');
    $surname = trim($_POST['edit_teacher_surname'] ?? '');
    $address = trim($_POST['edit_teacher_address'] ?? '');
    $phone = trim($_POST['edit_teacher_phone'] ?? '');
    $salary = trim($_POST['edit_teacher_salary'] ?? '');

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
        $error = "All fields are required for editing.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } elseif (!is_numeric($salary) || $salary <= 0) { // Ensure salary is a positive number
        $error = "Salary must be a positive number.";
    } else {
        $stmt = $conn->prepare("UPDATE Teacher SET teacher_firstname=?, teacher_surname=?, teacher_address=?, teacher_phone_number=?, teacher_salary=? WHERE teacher_id=?");
        $stmt->bind_param("ssssdi", $firstname, $surname, $address, $phone, $salary, $editId);

        if ($stmt->execute()) {
            $successMessage = "Teacher updated successfully.";
        } else {
            $error = "Error updating Teacher: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all Teachers
$teachers = $conn->query("SELECT * FROM Teacher");
?>



    <p>Manage Teachers</p>
    <button class="btn btn-primary mb-3" onclick="toggleteacherSection()">Manage Teachers</button>

    <!-- Add Teacher Form -->
    <div id="addteacherFormContainer" style="display: none;">
        <h2>Add Teacher</h2>
        <form method="post">
            <input type="hidden" name="add_teacher" value="1">
            First Name: <input class="form-control" type="text" name="firstname"><br>
            Surname: <input class="form-control" type="text" name="surname"><br>
            Address: <input class="form-control" type="text" name="address"><br>
            Phone: <input class="form-control" type="text" name="phone"><br>
            Salary: <input class="form-control" type="text" name="salary"><br>
            Password: <input class="form-control" type="password" name="password"><br>
            <input class="btn btn-success" type="submit" value="Add Teacher">
        </form>
        <hr>
    </div>

    <!-- Edit Teacher Form -->
    <div id="editteacherFormContainer" style="display: none;">
        <h2>Edit Teacher</h2>
        <form method="post">
            <input type="hidden" name="edit_teacher" value="1">
            <input type="hidden" id="edit_teacher_id" name="edit_teacher_id">
            First Name: <input class="form-control" type="text" id="edit_teacher_firstname" name="edit_teacher_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_teacher_surname" name="edit_teacher_username"><br>
            Address: <input class="form-control" type="text" id="edit_teacher_address" name="edit_teacher_address"><br>
            Phone: <input class="form-control" type="text" id="edit_teacher_phone" name="edit_teacher_phone"><br>
            Salary: <input class="form-control" type="text" id="edit_teacher_salary" name="edit_teacher_salary"><br>
            <input class="btn btn-warning" type="submit" value="Update teacher">
            <button class="btn btn-secondary" type="button" onclick="cancelteacherEdit()">Cancel</button>
        </form>
        <hr>
    </div>

    <!-- Teacher Table -->
    <div id="teacherTableContainer" style="display: none;">
        <h2>Existing Teachers</h2>
        <table class="table table-bordered">
            <tr>
                <th class="table-primary">ID</th>
                <th class="table-primary">First Name</th>
                <th class="table-primary">Surname</th>
                <th class="table-primary">Address</th>
                <th class="table-primary">Phone</th>
                <th class="table-primary">Salary</th>
                <th class="table-warning">Edit</th>
                <th class="table-danger">Delete</th>
            </tr>
            <?php $teachers->data_seek(0); while ($row = $teachers->fetch_assoc()): ?>
                <tr>
                    <td class="table-primary"><?= $row['teacher_id'] ?></td>
                    <td class="table-primary"><?= $row['teacher_firstname'] ?></td>
                    <td class="table-primary"><?= $row['teacher_surname'] ?></td>
                    <td class="table-primary"><?= $row['teacher_address'] ?></td>
                    <td class="table-primary"><?= $row['teacher_phone_number'] ?></td>
                    <td class="table-primary"><?= $row['teacher_salary'] ?></td>
                    <td class="table-warning">
                        <a href="javascript:void(0);" onclick="showteacherEditForm(
                            <?= $row['teacher_id'] ?>,
                            '<?= htmlspecialchars($row['teacher_firstname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['teacher_surname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['teacher_address'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['teacher_phone_number'], ENT_QUOTES) ?>',
                            <?= $row['teacher_salary'] ?>
                        )">Edit</a>
                    </td>
                    <td class="table-danger">
                        <a href="?delete_teacher_id=<?= $row['teacher_id'] ?>" onclick="return confirm('Are you sure you want to delete this teacher?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

<script>
function toggleteacherSection() {
    const addForm = document.getElementById("addteacherFormContainer");
    const table = document.getElementById("teacherTableContainer");
    const editForm = document.getElementById("editteacherFormContainer");

    const isVisible = addForm.style.display === "block";

    addForm.style.display = isVisible ? "none" : "block";
    table.style.display = isVisible ? "none" : "block";
    editForm.style.display = "none"; // hide edit if toggling
}

function showteacherEditForm(teacherId, firstname, surname, address, phone, salary) {
    document.getElementById("edit_teacher_id").value = teacherId;
    document.getElementById("edit_teacher_firstname").value = firstname;
    document.getElementById("edit_teacher_surname").value = surname;
    document.getElementById("edit_teacher_address").value = address;
    document.getElementById("edit_teacher_phone").value = phone;
    document.getElementById("edit_teacher_salary").value = salary;

    document.getElementById("editteacherFormContainer").style.display = "block";
    document.getElementById("addteacherFormContainer").style.display = "none";
    document.getElementById("teacherTableContainer").style.display = "none";
}

function cancelteacherEdit() {
    document.getElementById("editteacherFormContainer").style.display = "none";
    document.getElementById("addteacherFormContainer").style.display = "block";
    document.getElementById("teacherTableContainer").style.display = "block";
}
</script>



<!-- //////////////////////////////////////////////////////////////////////// -->
<!-- Handles Students -->



<?php
// Handle delete request
if (isset($_GET['delete_student_id'])) {
    $deleteId = intval($_GET['delete_student_id']);
    $stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    
     //Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE student_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
             
    //Delete from user_roles table
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE student_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();         

             // Commit transaction if all deletions are successful
    $conn->commit();
    $successMessage = "student deleted successfully.";
}

// Handle ADD student
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_student'])) {
    $firstname = $_POST['firstname'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $classId = $_POST['class_id'] ?? null;
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'student';

    $username = ($firstname . $surname);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone)) {
        $error = "All fields are required.";
    } else {
        // 1. Insert into `user`
        $stmt = $conn->prepare("INSERT INTO user (username, user_hashed_password, user_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $userType);
        if (!$stmt->execute()) {
            $error = "Failed to add user: " . $stmt->error;
            $stmt->close();
        } else {
            $userId = $stmt->insert_id;
            $stmt->close();

            // 2. Insert into student
            $stmt = $conn->prepare("INSERT INTO student (student_firstname, student_surname, student_address, student_phone_number, class_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdi", $firstname, $surname, $address, $phone, $classId);
            if (!$stmt->execute()) {
                $error = "Failed to add student: " . $stmt->error;
                $stmt->close();
            } else {
                $studentId = $stmt->insert_id;
                $stmt->close();

                // 3. Insert into user_roles
                $stmt = $conn->prepare("INSERT INTO user_roles (user_id, student_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $studentId);
                if ($stmt->execute()) {
                    $successMessage = "student added successfully.";
                } else {
                    $error = "Failed to add user role: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_student'])) {
    $editId = intval($_POST['edit_student_id']);
    $firstname = $_POST['edit_student_firstname'] ?? '';
    $surname = $_POST['edit_student_surname'] ?? '';
    $address = $_POST['edit_student_address'] ?? '';
    $phone = $_POST['edit_student_phone'] ?? '';
    $classId = $_POST['edit_class_id'] ?? null;

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone)) {
        $error = "All fields are required for editing.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } else {
        $stmt = $conn->prepare("UPDATE student SET student_firstname=?, student_surname=?, student_address=?, student_phone_number=?, class_id=? WHERE student_id=?");
        $stmt->bind_param("ssssii", $firstname, $surname, $address, $phone, $classId, $editId);

        if ($stmt->execute()) {
            $successMessage = "student updated successfully.";
        } else {
            $error = "Error updating student: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all students
$students = $conn->query("SELECT * FROM student");
?>


    <p>Manage Students</p>

    <button class="btn btn-primary mb-3" onclick="togglestudentSection()">Manage students</button>

    <!-- Add student Form -->
    <div id="addstudentFormContainer" style="display: none;">
        <h2>Add student</h2>
        <form method="post">
            <input type="hidden" name="add_student" value="1">
            First Name: <input class="form-control" type="text" name="firstname"><br>
            Surname: <input class="form-control" type="text" name="surname"><br>
            Address: <input class="form-control" type="text" name="address"><br>
            Phone: <input class="form-control" type="text" name="phone"><br>
            Class ID: <input class="form-control" type="number" name="class_id"><br>
            Password: <input class="form-control" type="password" name="password"><br>
            <input class="btn btn-success" type="submit" value="Add student">
        </form>
        <hr>
    </div>

    <!-- Edit student Form -->
    <div id="editstudentFormContainer" style="display: none;">
        <h2>Edit student</h2>
        <form method="post">
            <input type="hidden" name="edit_student" value="1">
            <input type="hidden" id="edit_student_id" name="edit_student_id">
            First Name: <input class="form-control" type="text" id="edit_student_firstname" name="edit_student_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_student_surname" name="edit_student_surname"><br>
            Address: <input class="form-control" type="text" id="edit_student_address" name="edit_student_address"><br>
            Phone: <input class="form-control" type="text" id="edit_student_phone" name="edit_student_phone"><br>
            Class ID: <input class="form-control" type="number" id="edit_student_class_id" name="edit_class_id"><br>
            <input class="btn btn-warning" type="submit" value="Update student">
            <button class="btn btn-secondary" type="button" onclick="cancelstudentEdit()">Cancel</button>
        </form>
        <hr>
    </div>

    <!-- student Table -->
    <div id="studentTableContainer" style="display: none;">
        <h2>Existing students</h2>
        <table class="table table-bordered">
            <tr>
                <th class="table-primary">ID</th>
                <th class="table-primary">First Name</th>
                <th class="table-primary">Surname</th>
                <th class="table-primary">Address</th>
                <th class="table-primary">Phone</th>
                <th class="table-primary">Class ID</th>
                <th class="table-warning">Edit</th>
                <th class="table-danger">Delete</th>
            </tr>
            <?php $students->data_seek(0); while ($row = $students->fetch_assoc()): ?>
                <tr>
                    <td class="table-primary"><?= $row['student_id'] ?></td>
                    <td class="table-primary"><?= $row['student_firstname'] ?></td>
                    <td class="table-primary"><?= $row['student_surname'] ?></td>
                    <td class="table-primary"><?= $row['student_address'] ?></td>
                    <td class="table-primary"><?= $row['student_phone_number'] ?></td>
                    <td class="table-primary"><?= $row['class_id'] ?></td>
                    <td class="table-warning">
                        <a href="javascript:void(0);" onclick="showstudentEditForm(
                            <?= $row['student_id'] ?>,
                            '<?= htmlspecialchars($row['student_firstname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['student_surname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['student_address'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['student_phone_number'], ENT_QUOTES) ?>',
                            <?= $row['class_id'] ?? 'null' ?>
                        )">Edit</a>
                    </td>
                    <td class="table-danger">
                        <a href="?delete_student_id=<?= $row['student_id'] ?>" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>


<script>
function togglestudentSection() {
    const addForm = document.getElementById("addstudentFormContainer");
    const table = document.getElementById("studentTableContainer");
    const editForm = document.getElementById("editstudentFormContainer");

    const isVisible = addForm.style.display === "block";

    addForm.style.display = isVisible ? "none" : "block";
    table.style.display = isVisible ? "none" : "block";
    editForm.style.display = "none"; // hide edit if toggling
}

function showstudentEditForm(studentId, firstname, surname, address, phone, classId) {
    document.getElementById("edit_student_id").value = studentId;
    document.getElementById("edit_student_firstname").value = firstname;
    document.getElementById("edit_student_surname").value = surname;
    document.getElementById("edit_student_address").value = address;
    document.getElementById("edit_student_phone").value = phone;
    document.getElementById("edit_student_class_id").value = classId;

    document.getElementById("editstudentFormContainer").style.display = "block";
    document.getElementById("addstudentFormContainer").style.display = "none";
    document.getElementById("studentTableContainer").style.display = "none";
}

function cancelstudentEdit() {
    document.getElementById("editstudentFormContainer").style.display = "none";
    document.getElementById("addstudentFormContainer").style.display = "block";
    document.getElementById("studentTableContainer").style.display = "block";
}
</script>




<!-- //////////////////////////////////////////////////////////////////////// -->
<!-- Handles Parents -->

<?php
// Handle delete request
if (isset($_GET['delete_parent_id'])) {
    $deleteId = intval($_GET['delete_parent_id']);
    $stmt = $conn->prepare("DELETE FROM parent_guardian WHERE parent_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    
     //Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE parent_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();

    //Delete from user_roles table
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE Pasrent_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();         
             

             // Commit transaction if all deletions are successful
    $conn->commit();
    $successMessage = "parent deleted successfully.";
}

// Handle ADD parent
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_parent'])) {
    $firstname = $_POST['firstname'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? ''; // <-- added
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'parent';

    $username = ($firstname . $surname);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);


    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) ||  empty($plainPassword)) {
        $error = "All fields are required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } else{
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // 1. Insert into `user`
        $stmt = $conn->prepare("INSERT INTO user (username, user_hashed_password, user_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $userType);
        if (!$stmt->execute()) {
            $error = "Failed to add user: " . $stmt->error;
            $stmt->close();
        } else {
            $userId = $stmt->insert_id;
            $stmt->close();

            // 2. Insert into parent
            $stmt = $conn->prepare("INSERT INTO parent_guardian (parent_firstname, parent_surname, parent_address, parent_phone_number, parent_email) VALUES (?, ?, ?, ?, ?)"); // <-- modified
            $stmt->bind_param("sssss", $firstname, $surname, $address, $phone, $email); // <-- modified
            if (!$stmt->execute()) {
                $error = "Failed to add parent: " . $stmt->error;
                $stmt->close();
            } else {
                $parentId = $stmt->insert_id;
                $stmt->close();

                // 3. Insert into user_roles
                $stmt = $conn->prepare("INSERT INTO user_roles (user_id, parent_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $parentId);
                if ($stmt->execute()) {
                    $successMessage = "parent added successfully.";
                } else {
                    $error = "Failed to add user role: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_parent'])) {
    $editId = intval($_POST['edit_parent_id']);
    $firstname = $_POST['edit_parent_firstname'] ?? '';
    $surname = $_POST['edit_parent_surname'] ?? '';
    $address = $_POST['edit_parent_address'] ?? '';
    $phone = $_POST['edit_parent_phone'] ?? '';
    $email = $_POST['edit_email'] ?? ''; // <-- added

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone)) {
        $error = "All fields are required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } else {
        $stmt = $conn->prepare("UPDATE parent_guardian SET parent_firstname=?, parent_surname=?, parent_address=?, parent_phone_number=?, parent_email=? WHERE parent_id=?"); // <-- modified
        $stmt->bind_param("sssssi", $firstname, $surname, $address, $phone, $email, $editId); // <-- modified

        if ($stmt->execute()) {
            $successMessage = "parent updated successfully.";
        } else {
            $error = "Error updating parent: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all parents
$parents = $conn->query("SELECT * FROM parent_guardian");
?>


    <P>Manage Parents</p>

    <button class="btn btn-primary mb-3" onclick="toggleparentSection()">Manage parents</button>

    <!-- Add parent Form -->
    <div id="addparentFormContainer" style="display: none;">
        <h2>Add parent</h2>
        <form method="post">
            <input type="hidden" name="add_parent" value="1">
            First Name: <input class="form-control" type="text" name="firstname"><br>
            Surname: <input class="form-control" type="text" name="surname"><br>
            Address: <input class="form-control" type="text" name="address"><br>
            Phone: <input class="form-control" type="text" name="phone"><br>
            Email: <input class="form-control" type="email" name="email"><br> <!-- added -->
            Password: <input class="form-control" type="password" name="password"><br>
            <input class="btn btn-success" type="submit" value="Add parent">
        </form>
        <hr>
    </div>

    <!-- Edit parent Form -->
    <div id="editparentFormContainer" style="display: none;">
        <h2>Edit parent</h2>
        <form method="post">
            <input type="hidden" name="edit_parent" value="1">
            <input type="hidden" id="edit_parent_id" name="edit_parent_id">
            First Name: <input class="form-control" type="text" id="edit_parent_firstname" name="edit_parent_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_parent_surname" name="edit_parent_surname"><br>
            Address: <input class="form-control" type="text" id="edit_parent_address" name="edit_parent_address"><br>
            Phone: <input class="form-control" type="text" id="edit_parent_phone" name="edit_parent_phone"><br>
            Email: <input class="form-control" type="email" id="edit_parent_email" name="edit_email"><br> <!-- added -->
            <input class="btn btn-warning" type="submit" value="Update parent">
            <button class="btn btn-secondary" type="button" onclick="cancelparentEdit()">Cancel</button>
        </form>
        <hr>
    </div>

    <!-- parent Table -->
    <div id="parentTableContainer" style="display: none;">
        <h2>Existing parents</h2>
        <table class="table table-bordered">
            <tr>
                <th class="table-primary">ID</th>
                <th class="table-primary">First Name</th>
                <th class="table-primary">Surname</th>
                <th class="table-primary">Address</th>
                <th class="table-primary">Phone</th>
                <th class="table-primary">Email</th> <!-- added -->
                <th class="table-warning">Edit</th>
                <th class="table-danger">Delete</th>
            </tr>
            <?php $parents->data_seek(0); while ($row = $parents->fetch_assoc()): ?>
                <tr>
                    <td class="table-primary"><?= $row['parent_id'] ?></td>
                    <td class="table-primary"><?= $row['parent_firstname'] ?></td>
                    <td class="table-primary"><?= $row['parent_surname'] ?></td>
                    <td class="table-primary"><?= $row['parent_address'] ?></td>
                    <td class="table-primary"><?= $row['parent_phone_number'] ?></td>
                    <td class="table-primary"><?= $row['parent_email'] ?></td> <!-- added -->
                    <td class="table-warning">
                        <a href="javascript:void(0);" onclick="showparentEditForm(
                            <?= $row['parent_id'] ?>,
                            '<?= htmlspecialchars($row['parent_firstname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['parent_surname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['parent_address'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['parent_phone_number'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['parent_email'], ENT_QUOTES) ?>' // added
                        )">Edit</a>
                    </td>
                    <td class="table-danger">
                        <a href="?delete_parent_id=<?= $row['parent_id'] ?>" onclick="return confirm('Are you sure you want to delete this parent?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

<script>
function toggleparentSection() {
    const addForm = document.getElementById("addparentFormContainer");
    const table = document.getElementById("parentTableContainer");
    const editForm = document.getElementById("editparentFormContainer");

    const isVisible = addForm.style.display === "block";

    addForm.style.display = isVisible ? "none" : "block";
    table.style.display = isVisible ? "none" : "block";
    editForm.style.display = "none"; // hide edit if toggling
}

function showparentEditForm(parentId, firstname, surname, address, phone, email) {
    document.getElementById("edit_parent_id").value = parentId;
    document.getElementById("edit_parent_firstname").value = firstname;
    document.getElementById("edit_parent_surname").value = surname;
    document.getElementById("edit_parent_address").value = address;
    document.getElementById("edit_parent_phone").value = phone;
    document.getElementById("edit_parent_email").value = email; 
    document.getElementById("editparentFormContainer").style.display = "block";
    document.getElementById("addparentFormContainer").style.display = "none";
    document.getElementById("parentTableContainer").style.display = "none";
}

function cancelparentEdit() {
    document.getElementById("editparentFormContainer").style.display = "none";
    document.getElementById("addparentFormContainer").style.display = "block";
    document.getElementById("parentTableContainer").style.display = "block";
}
</script>


<!-- //////////////////////////////////////////////////////////////////////// -->
<!-- Handles admins -->



<?php
// Handle delete request
if (isset($_GET['delete_admin_id'])) {
    $deleteId = intval($_GET['delete_admin_id']);
    $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    
     //Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE admin_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();

    //Delete from user_roles table
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE admin_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();         
             

             // Commit transaction if all deletions are successful
    $conn->commit();
    $successMessage = "admin deleted successfully.";
}

// Handle ADD admin
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_admin'])) {
    $firstname = $_POST['firstname'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'admin';

    $username = ($firstname . $surname);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone)|| empty($salary) ) {
        $error = "All fields are required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } elseif (!is_numeric($salary) || $salary <= 0) { // Ensure salary is a positive number
        $error = "Salary must be positive.";
    } else {
        // 1. Insert into `user`
        $stmt = $conn->prepare("INSERT INTO user (username, user_hashed_password, user_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashedPassword, $userType);
        if (!$stmt->execute()) {
            $error = "Failed to add user: " . $stmt->error;
            $stmt->close();
        } else {
            $userId = $stmt->insert_id;
            $stmt->close();

            // 2. Insert into admin
            $stmt = $conn->prepare("INSERT INTO admin (admin_firstname, admin_surname, admin_address, admin_phone_number, admin_salary) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssd", $firstname, $surname, $address, $phone, $salary);
            if (!$stmt->execute()) {
                $error = "Failed to add admin: " . $stmt->error;
                $stmt->close();
            } else {
                $adminId = $stmt->insert_id;
                $stmt->close();

                // 3. Insert into user_roles
                $stmt = $conn->prepare("INSERT INTO user_roles (user_id, admin_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $adminId);
                if ($stmt->execute()) {
                    $successMessage = "admin added successfully.";
                } else {
                    $error = "Failed to add user role: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_admin'])) {
    $editId = intval($_POST['edit_admin_id']);
    $firstname = $_POST['edit_admin_firstname'] ?? '';
    $surname = $_POST['edit_admin_surname'] ?? '';
    $address = $_POST['edit_admin_address'] ?? '';
    $phone = $_POST['edit_admin_phone'] ?? '';
    $salary = $_POST['edit_admin_salary'] ?? '';

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
        $error = "All fields are required for editing.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) { // Ensure phone number is a 10-digit number
        $error = "Phone number must be 10 digits.";
    } elseif (!is_numeric($salary) || $salary <= 0) { // Ensure salary is a positive number
        $error = "Salary must be a positive number.";
    } else {
        $stmt = $conn->prepare("UPDATE admin SET admin_firstname=?, admin_surname=?, admin_address=?, admin_phone_number=?, admin_salary=? WHERE admin_id=?");
        $stmt->bind_param("ssssdi", $firstname, $surname, $address, $phone, $salary, $editId);

        if ($stmt->execute()) {
            $successMessage = "admin updated successfully.";
        } else {
            $error = "Error updating admin: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all admins
$admins = $conn->query("SELECT * FROM admin");
?>

    <p>Manage Admin</p>
    <button class="btn btn-primary mb-3" onclick="toggleadminSection()">Manage admins</button>

    <!-- Add admin Form -->
    <div id="addadminFormContainer" style="display: none;">
        <h2>Add admin</h2>
        <form method="post">
            <input type="hidden" name="add_admin" value="1">
            First Name: <input class="form-control" type="text" name="firstname"><br>
            Surname: <input class="form-control" type="text" name="surname"><br>
            Address: <input class="form-control" type="text" name="address"><br>
            Phone: <input class="form-control" type="text" name="phone"><br>
            Salary: <input class="form-control" type="text" name="salary"><br>
            Password: <input class="form-control" type="password" name="password"><br>
            <input class="btn btn-success" type="submit" value="Add admin">
        </form>
        <hr>
    </div>

    <!-- Edit admin Form -->
    <div id="editadminFormContainer" style="display: none;">
        <h2>Edit admin</h2>
        <form method="post">
            <input type="hidden" name="edit_admin" value="1">
            <input type="hidden" id="edit_admin_id" name="edit_admin_id">
            First Name: <input class="form-control" type="text" id="edit_admin_firstname" name="edit_admin_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_admin_surname" name="edit_admin_surname"><br>
            Address: <input class="form-control" type="text" id="edit_admin_address" name="edit_admin_address"><br>
            Phone: <input class="form-control" type="text" id="edit_admin_phone" name="edit_admin_phone"><br>
            Salary: <input class="form-control" type="text" id="edit_admin_salary" name="edit_admin_salary"><br>
            <input class="btn btn-warning" type="submit" value="Update admin">
            <button class="btn btn-secondary" type="button" onclick="canceladminEdit()">Cancel</button>
        </form>
        <hr>
    </div>

    <!-- admin Table -->
    <div id="adminTableContainer" style="display: none;">
        <h2>Existing admins</h2>
        <table class="table table-bordered">
            <tr>
                <th class="table-primary">ID</th>
                <th class="table-primary">First Name</th>
                <th class="table-primary">Surname</th>
                <th class="table-primary">Address</th>
                <th class="table-primary">Phone</th>
                <th class="table-primary">Salary</th>
                <th class="table-warning">Edit</th>
                <th class="table-danger">Delete</th>
            </tr>
            <?php $admins->data_seek(0); while ($row = $admins->fetch_assoc()): ?>
                <tr>
                    <td class="table-primary"><?= $row['admin_id'] ?></td>
                    <td class="table-primary"><?= $row['admin_firstname'] ?></td>
                    <td class="table-primary"><?= $row['admin_surname'] ?></td>
                    <td class="table-primary"><?= $row['admin_address'] ?></td>
                    <td class="table-primary"><?= $row['admin_phone_number'] ?></td>
                    <td class="table-primary"><?= $row['admin_salary'] ?></td>
                    <td class="table-warning">
                        <a href="javascript:void(0);" onclick="showadminEditForm(
                            <?= $row['admin_id'] ?>,
                            '<?= htmlspecialchars($row['admin_firstname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['admin_surname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['admin_address'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['admin_phone_number'], ENT_QUOTES) ?>',
                            <?= $row['admin_salary'] ?>
                        )">Edit</a>
                    </td>
                    <td class="table-danger">
                        <a href="?delete_admin_id=<?= $row['admin_id'] ?>" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
    

<script>
function toggleadminSection() {
    const addForm = document.getElementById("addadminFormContainer");
    const table = document.getElementById("adminTableContainer");
    const editForm = document.getElementById("editadminFormContainer");

    const isVisible = addForm.style.display === "block";

    addForm.style.display = isVisible ? "none" : "block";
    table.style.display = isVisible ? "none" : "block";
    editForm.style.display = "none"; // hide edit if toggling
}

function showadminEditForm(adminId, firstname, surname, address, phone, salary) {
    document.getElementById("edit_admin_id").value = adminId;
    document.getElementById("edit_admin_firstname").value = firstname;
    document.getElementById("edit_admin_surname").value = surname;
    document.getElementById("edit_admin_address").value = address;
    document.getElementById("edit_admin_phone").value = phone;
    document.getElementById("edit_admin_salary").value = salary;

    document.getElementById("editadminFormContainer").style.display = "block";
    document.getElementById("addadminFormContainer").style.display = "none";
    document.getElementById("adminTableContainer").style.display = "none";
}

function canceladminEdit() {
    document.getElementById("editadminFormContainer").style.display = "none";
    document.getElementById("addadminFormContainer").style.display = "block";
    document.getElementById("adminTableContainer").style.display = "block";
}
</script>

</body>
</html>


<!-- //////////////////////////////////////////////////////////////////////// -->
<!-- Handles classes -->


<?php

// Handle edit class
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_class'])) {
    $editId = intval($_POST['edit_class_id']);
    $name = trim($_POST['edit_class_name'] ?? '');
    $capacity = intval($_POST['edit_class_capacity'] ?? 0);
    $teacher_id = $_POST['edit_teacher_id_class'] !== '' ? intval($_POST['edit_teacher_id_class']) : null;
    $ta_id = $_POST['edit_ta_id_class'] !== '' ? intval($_POST['edit_ta_id_class']) : null;

    if (empty($name) || $capacity <= 0) {
        $error = "All fields are required with valid values.";
    } else {
        $stmt = $conn->prepare("UPDATE class SET class_name=?, class_capacity=?, teacher_id=?, ta_id=? WHERE class_id=?");
        $stmt->bind_param("siiii", $name, $capacity, $teacher_id, $ta_id, $editId);
        if ($stmt->execute()) {
            $successMessage = "Class updated successfully.";
        } else {
            $error = "Error updating class: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all classes
$classes = $conn->query("SELECT * FROM class");

// Fetch teachers and TAs for dropdowns
$teachers = $conn->query("SELECT teacher_id, teacher_firstname FROM teacher");
$tas = $conn->query("SELECT ta_id, ta_firstname FROM teaching_assistant");
?>
<hr>
<button class="btn btn-primary mb-3" onclick="toggleClassSection()">Manage Classes</button>

<!-- Edit Class Form -->
<div id="editClassFormContainer" style="display: none;">
    <h2>Edit Class</h2>
    <form method="post">
        <input type="hidden" name="edit_class" value="1">
        <input type="hidden" id="edit_class_id" name="edit_class_id">
        Class Name: <input class="form-control" type="text" id="edit_class_name" name="edit_class_name"><br>
        Capacity: <input class="form-control" type="number" id="edit_class_capacity" name="edit_class_capacity" min="1"><br>
        Teacher ID: <input class="form-control" type="number" id="edit_teacher_id_class" name="edit_teacher_id_class"><br>
        TA ID: <input class="form-control" type="number" id="edit_ta_id_class" name="edit_ta_id_class"><br>
        <input class="btn btn-warning" type="submit" value="Update Class">
        <button class="btn btn-secondary" type="button" onclick="cancelClassEdit()">Cancel</button>
    </form>
    <hr>
</div>

<!-- Class Table -->
<div id="classTableContainer" style="display: none;">
    <h2>Existing Classes</h2>
    <table class="table table-bordered">
        <tr>
            <th class="table-primary">ID</th>
            <th class="table-primary">Name</th>
            <th class="table-primary">Capacity</th>
            <th class="table-primary">Teacher ID</th>
            <th class="table-primary">TA ID</th>
            <th class="table-warning">Edit</th>
        </tr>
        <?php $classes->data_seek(0); while ($row = $classes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['class_id'] ?></td>
                <td><?= htmlspecialchars($row['class_name']) ?></td>
                <td><?= $row['class_capacity'] ?></td>
                <td><?= $row['teacher_id'] ?? '—' ?></td>
                <td><?= $row['ta_id'] ?? '—' ?></td>
                <td class="table-warning">
                    <a href="javascript:void(0);" onclick="showClassEditForm(
                        <?= $row['class_id'] ?>,
                        '<?= htmlspecialchars($row['class_name'], ENT_QUOTES) ?>',
                        <?= $row['class_capacity'] ?>,
                        <?= $row['teacher_id'] ?? 'null' ?>,
                        <?= $row['ta_id'] ?? 'null' ?>
                    )">Edit</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php if ($successMessage): ?>
        <p class="text-success"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="text-danger"><?= $error ?></p>
    <?php endif; ?>
</div>
</div>
<script>
function toggleClassSection() {
    const table = document.getElementById("classTableContainer");
    const editForm = document.getElementById("editClassFormContainer");

    const isVisible = table.style.display === "block";

    table.style.display = isVisible ? "none" : "block";
    editForm.style.display = "none";
}

function showClassEditForm(id, name, capacity, teacher_Id, ta_Id) {
    // Set the values into the form fields
    document.getElementById("edit_class_id").value = id;
    document.getElementById("edit_class_name").value = name;
    document.getElementById("edit_class_capacity").value = capacity;
    document.getElementById("edit_teacher_id_class").value = teacher_Id !== null ? teacher_Id : '';
    document.getElementById("edit_ta_id_class").value = ta_Id !== null ? ta_Id : ''; 

    document.getElementById("editClassFormContainer").style.display = "block";
    document.getElementById("classTableContainer").style.display = "none";
}


function cancelClassEdit() {
    document.getElementById("editClassFormContainer").style.display = "none";
    document.getElementById("classTableContainer").style.display = "block";
}
</script>

<?php
$conn->close();
?>
