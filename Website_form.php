<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Information Form</title>
    <link rel="stylesheet" href="Website_form.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">User Information Form</h2>
        <form action="Website.php" method = "POST">
            <div class="mb-3">
                <label for="firstname" class="form-label">Username:</label>
                <input type="text" class="form-control" id="firstname" name="username" placeholder="Enter your username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="text" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <!--<div class="mb-3">
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
            </div>-->
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
