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
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
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
?>


<html lang="en">
<head>
    <!--links style sheet and bootstrap-->
    <title>Admin Page</title>
    <link rel="stylesheet" href="School.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<body>

<!--creates container-->
<div class="container col-6">
    <div class="text-end mb-3">
    <a href="?logout=true" class="btn btn-danger">Logout</a>
    </div>
    <h1 class="mb-4 text-center">St Alphonsus Primary School</h1>
    <h2>Welcome to Teacher page</h2>

<!--/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////// 
//handle students -->


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

<html>
<body>
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
            <input class="btn btn-success" type="submit" value="Add student"> <!--submit button-->
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
            <button class="btn btn-secondary" type="button" onclick="cancelstudentEdit()">Cancel</button> <!--cancels edit when clicked-->
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
                        <a href="javascript:void(0);" onclick="showstudentEditForm( //fills out rows
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
//displays edit form
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
//hide edit form
function cancelstudentEdit() {
    document.getElementById("editstudentFormContainer").style.display = "none";
    document.getElementById("addstudentFormContainer").style.display = "block";
    document.getElementById("studentTableContainer").style.display = "block";
}
</script>


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
        <button class="btn btn-secondary" type="button" onclick="cancelClassEdit()">Cancel</button> <!--when clicked hides edit-->
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
        <?php $classes->data_seek(0); while ($row = $classes->fetch_assoc()): ?> <!--fills in rows-->
            <tr>
                <td><?= $row['class_id'] ?></td>
                <td><?= htmlspecialchars($row['class_name']) ?></td>
                <td><?= $row['class_capacity'] ?></td>
                <td><?= $row['teacher_id'] ?? '—' ?></td>
                <td><?= $row['ta_id'] ?? '—' ?></td>
                <td class="table-warning">
                    <a href="javascript:void(0);" onclick="showClassEditForm(  //displays edit form when clicked
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
<!--displays success or error msg-->
<?php if ($successMessage): ?> 
        <p class="text-success"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="text-danger"><?= $error ?></p>
    <?php endif; ?>
</div>
</div>
<script>
function toggleClassSection() { //togles if class is visible
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


function cancelClassEdit() { //cancels the edit
    document.getElementById("editClassFormContainer").style.display = "none";
    document.getElementById("classTableContainer").style.display = "block";
}
</script>

<?php
$conn->close();
?>
</body>
</html>
