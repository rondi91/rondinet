<?php
// Path to the routers.json file
$json_file = 'routers.json';

// Load existing routers from the JSON file
$routers = [];
if (file_exists($json_file)) {
    $json_data = file_get_contents($json_file);
    $routers = json_decode($json_data, true);
}

// Handle form submissions for adding, editing, or deleting routers
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new router
    if (isset($_POST['add_router'])) {
        $new_id = count($routers) + 1; // Generate new ID
        $new_router = [
            "id" => $new_id,
            "name" => $_POST['name'],
            "ip_address" => $_POST['ip_address'],
            "username" => $_POST['username'],
            "password" => $_POST['password']
        ];
        $routers[$new_id] = $new_router;
    }

    // Edit existing router
    if (isset($_POST['edit_router'])) {
        $id = $_POST['id'];
        if (isset($routers[$id])) {
            $routers[$id]['name'] = $_POST['name'];
            $routers[$id]['ip_address'] = $_POST['ip_address'];
            $routers[$id]['username'] = $_POST['username'];
            $routers[$id]['password'] = $_POST['password'];
        }
    }

    // Delete router
    if (isset($_POST['delete_router'])) {
        $id = $_POST['id'];
        unset($routers[$id]);
    }

    // Save the updated data back to the JSON file
    file_put_contents($json_file, json_encode($routers, JSON_PRETTY_PRINT));
    header("Location: setting.php"); // Redirect to prevent form resubmission
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routers</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Manage Routers</h1>

        <!-- Add Router Form -->
        <form method="POST" action="setting.php">
            <h2>Add Router</h2>
            <label for="name">Router Name:</label>
            <input type="text" name="name" required>
            <label for="ip_address">IP Address:</label>
            <input type="text" name="ip_address" required>
            <label for="username">Username:</label>
            <input type="text" name="username" required>
            <label for="password">Password:</label>
            <input type="password" name="password" required>
            <button type="submit" name="add_router">Add Router</button>
        </form>

        <hr>

        <!-- List of Routers -->
        <h2>Existing Routers</h2>
        <table>
            <thead>
                <tr>
                    <th>Router Name</th>
                    <th>IP Address</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routers as $id => $router): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($router['name']); ?></td>
                        <td><?php echo htmlspecialchars($router['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars($router['username']); ?></td>
                        <td><?php echo htmlspecialchars($router['password']); ?></td>
                        <td>
                            <!-- Edit Button -->
                            <form method="POST" action="manage_routers.php" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <input type="hidden" name="name" value="<?php echo htmlspecialchars($router['name']); ?>">
                                <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($router['ip_address']); ?>">
                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($router['username']); ?>">
                                <input type="hidden" name="password" value="<?php echo htmlspecialchars($router['password']); ?>">
                                <button type="submit" name="edit_router">Edit</button>
                            </form>

                            <!-- Delete Button -->
                            <form method="POST" action="manage_routers.php" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <button type="submit" name="delete_router">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
