<!-- Handles server connection-->

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

     // 2. Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE ta_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
            
            // 3. Delete from Teaching_Assistant table
    $stmt = $conn->prepare("DELETE FROM Teaching_Assistant WHERE ta_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
            
            // Commit transaction if all deletions are successful
    $conn->commit();
}

// Handle Add TA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_ta'])) {
    $firstname = $_POST['firstname'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'ta';

    // Simulate or create username/password (you may collect these from a form)
    $username = ($firstname . $surname);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);


    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
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
    $firstname = $_POST['edit_ta_firstname'] ?? '';
    $surname = $_POST['edit_ta_surname'] ?? '';
    $address = $_POST['edit_ta_address'] ?? '';
    $phone = $_POST['edit_ta_phone'] ?? '';
    $salary = $_POST['edit_ta_salary'] ?? '';

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
        $error = "All fields are required for editing.";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container col-6">
    <h1 class="mt-4">Admin Dashboard</h1>

    <?php if ($successMessage): ?>
        <p class="text-success"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="text-danger"><?= $error ?></p>
    <?php endif; ?>

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
            First Name: <input class="form-control" type="text" id="edit_ta_firstname" name="edit_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_ta_surname" name="edit_surname"><br>
            Address: <input class="form-control" type="text" id="edit_ta_address" name="edit_address"><br>
            Phone: <input class="form-control" type="text" id="edit_ta_phone" name="edit_phone"><br>
            Salary: <input class="form-control" type="text" id="edit_ta_salary" name="edit_salary"><br>
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
</body>
</html>



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
    
    
     // 2. Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE teacher_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
             
             // 3. Delete from Teacher table
    $stmt = $conn->prepare("DELETE FROM Teacher WHERE teacher_id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
             
             // Commit transaction if all deletions are successful
    $conn->commit();
    $successMessage = "Teacher deleted successfully.";
}

// Handle ADD Teacher
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_teacher'])) {
    $firstname = $_POST['firstname'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $plainPassword = $_POST['password'] ?? '';
    $userType = 'teacher';

    $username = ($firstname . $surname);
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);


    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
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

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_teacher'])) {
    $editId = intval($_POST['edit_teacher_id']);
    $firstname = $_POST['edit_teacher_firstname'] ?? '';
    $surname = $_POST['edit_teacher_surname'] ?? '';
    $address = $_POST['edit_teacher_address'] ?? '';
    $phone = $_POST['edit_teacher_phone'] ?? '';
    $salary = $_POST['edit_salary'] ?? '';

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone) || empty($salary)) {
        $error = "All fields are required for editing.";
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

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container col-6">
    <?php if ($successMessage): ?>
        <p class="text-success"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="text-danger"><?= $error ?></p>
    <?php endif; ?>

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
            First Name: <input class="form-control" type="text" id="edit_teacher_firstname" name="edit_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_teacher_surname" name="edit_surname"><br>
            Address: <input class="form-control" type="text" id="edit_teacher_address" name="edit_address"><br>
            Phone: <input class="form-control" type="text" id="edit_teacher_phone" name="edit_phone"><br>
            Salary: <input class="form-control" type="text" id="edit_teacher_salary" name="edit_salary"><br>
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

</body>
</html>

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
    
    
     // 2. Delete from user table
    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = (SELECT user_id FROM user_roles WHERE student_id = ? LIMIT 1)");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
             
             // 3. Delete from student table
    $stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
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
            $stmt = $conn->prepare("INSERT INTO student (student_firstname, student_surname, student_address, student_phone_number, student_salary) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssd", $firstname, $surname, $address, $phone);
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

    if (empty($firstname) || empty($surname) || empty($address) || empty($phone)) {
        $error = "All fields are required for editing.";
    } else {
        $stmt = $conn->prepare("UPDATE student SET student_firstname=?, student_surname=?, student_address=?, student_phone_number=?, student_salary=? WHERE student_id=?");
        $stmt->bind_param("ssssdi", $firstname, $surname, $address, $phone, $editId);

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

<html lang="en">
<body>
<div class="container col-6">
    <?php if ($successMessage): ?>
        <p class="text-success"><?= $successMessage ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="text-danger"><?= $error ?></p>
    <?php endif; ?>

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
            First Name: <input class="form-control" type="text" id="edit_student_firstname" name="edit_firstname"><br>
            Surname: <input class="form-control" type="text" id="edit_student_surname" name="edit_surname"><br>
            Address: <input class="form-control" type="text" id="edit_student_address" name="edit_address"><br>
            Phone: <input class="form-control" type="text" id="edit_student_phone" name="edit_phone"><br>
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
                    <td class="table-warning">
                        <a href="javascript:void(0);" onclick="showstudentEditForm(
                            <?= $row['student_id'] ?>,
                            '<?= htmlspecialchars($row['student_firstname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['student_surname'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['student_address'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['student_phone_number'], ENT_QUOTES) ?>',
                        )">Edit</a>
                    </td>
                    <td class="table-danger">
                        <a href="?delete_student_id=<?= $row['student_id'] ?>" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
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

function showstudentEditForm(studentId, firstname, surname, address, phone) {
    document.getElementById("edit_student_id").value = studentId;
    document.getElementById("edit_student_firstname").value = firstname;
    document.getElementById("edit_student_surname").value = surname;
    document.getElementById("edit_student_address").value = address;
    document.getElementById("edit_student_phone").value = phone;

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

</body>
</html>

<?php
$conn->close();
?>
