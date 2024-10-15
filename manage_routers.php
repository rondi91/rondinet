<?php
// Path to the routers.json file
$json_file = 'routers.json';

// Load existing routers from the JSON file
$routers = [];
if (file_exists($json_file)) {
    $json_data = file_get_contents($json_file);
    $routers = json_decode($json_data, true);

    if (!isset($routers['routers'])) {
        $routers['routers'] = [];
    }
} else {
    $routers['routers'] = [];
}

// Handle form submissions for adding, editing, or deleting routers
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new router
    if (isset($_POST['add_router'])) {
        $new_id = count($routers['routers']) + 1; // Generate new ID
        $new_router = [
            "id" => $new_id,
            "name" => $_POST['name'],
            "ip_address" => $_POST['ip_address'],
            "username" => $_POST['username'],
            "password" => $_POST['password']
        ];
        $routers['routers'][$new_id] = $new_router;
    }

    // Edit existing router
    if (isset($_POST['save_edit'])) {
        $id = $_POST['id'];
        if (isset($routers['routers'][$id])) {
            $routers['routers'][$id]['name'] = $_POST['name'];
            $routers['routers'][$id]['ip_address'] = $_POST['ip_address'];
            $routers['routers'][$id]['username'] = $_POST['username'];
            $routers['routers'][$id]['password'] = $_POST['password'];
        }
    }

    // Delete router
    if (isset($_POST['delete_router'])) {
        $id = $_POST['id'];
        unset($routers['routers'][$id]);
    }

    // Save the updated data back to the JSON file
    file_put_contents($json_file, json_encode($routers, JSON_PRETTY_PRINT));
    header("Location: manage_routers.php"); // Redirect to prevent form resubmission
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
</head>
<body>
<div class="wrapper">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <div class="container mt-4">
                <h1>Manage Routers</h1>

                <!-- Add Router Form -->
                <form method="POST" action="manage_routers.php">
                    <h2>Add Router</h2>
                    <div class="mb-3">
                        <label for="name">Router Name:</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="ip_address">IP Address:</label>
                        <input type="text" name="ip_address" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="username">Username:</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password">Password:</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="add_router" class="btn btn-primary">Add Router</button>
                </form>

                <hr>

                <!-- List of Routers -->
                <h2>Existing Routers</h2>
                <table class="table table-bordered">
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
                        <?php if (!empty($routers['routers'])): ?>
                            <?php foreach ($routers['routers'] as $id => $router): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($router['name']); ?></td>
                                    <td><?php echo htmlspecialchars($router['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($router['username']); ?></td>
                                    <td><?php echo htmlspecialchars($router['password']); ?></td>
                                    <td>
                                        <!-- Edit Button (trigger modal) -->
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                                        data-id="<?php echo $id; ?>" 
                                        data-name="<?php echo htmlspecialchars($router['name']); ?>" 
                                        data-ip_address="<?php echo htmlspecialchars($router['ip_address']); ?>" 
                                        data-username="<?php echo htmlspecialchars($router['username']); ?>" 
                                        data-password="<?php echo htmlspecialchars($router['password']); ?>">
                                            Edit
                                        </button>

                                        <!-- Delete Button -->
                                        <form method="POST" action="manage_routers.php" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                                            <button type="submit" name="delete_router" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No routers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

    <!-- Edit Router Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editModalLabel">Edit Router</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="POST" action="manage_routers.php" id="editForm">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label for="name">Router Name:</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="ip_address">IP Address:</label>
                    <input type="text" name="ip_address" id="edit-ip_address" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="username">Username:</label>
                    <input type="text" name="username" id="edit-username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="edit-password" class="form-control" required>
                </div>
                <button type="submit" name="save_edit" class="btn btn-primary">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    
</div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Trigger modal and pass data to it
        $('#editModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var id = button.data('id'); // Extract info from data-* attributes
            var name = button.data('name');
            var ip_address = button.data('ip_address');
            var username = button.data('username');
            var password = button.data('password');

            // Update the modal's content
            var modal = $(this);
            modal.find('#edit-id').val(id);
            modal.find('#edit-name').val(name);
            modal.find('#edit-ip_address').val(ip_address);
            modal.find('#edit-username').val(username);
            modal.find('#edit-password').val(password);
        });
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>
