<?php
require 'vendor/autoload.php'; // Memuat Composer autoload

use RouterOS\Client;
use RouterOS\Query;



// // Initiate client with config object
// $client = new Client([
//     'host' => '192.168.9.1',
//     'user' => 'rondi',
//     'pass' => '21184662',
//     'port' => 8728,
// ]);

// // Create "where" Query object for RouterOS
// $query =
//     (new Query('/ppp/active/print'));
//         // ->where('mac-address', '00:00:00:00:40:29');

// // Send query and read response from RouterOS
// $response = $client->query($query)->read();

// var_dump($response);
// die();

$mikrotikIp = '192.168.9.1'; // Ganti dengan IP Mikrotik Anda
$mikrotikUsername = 'rondi'; // Ganti dengan username Mikrotik Anda
$mikrotikPassword = '21184662'; // Ganti dengan password Mikrotik Anda
// Konfigurasi client dengan parameter yang benar
$client = new Client([
    'host' => $mikrotikIp,
    'user' => $mikrotikUsername,
    'pass' => $mikrotikPassword,
]);

// Menghubungkan ke Mikrotik
// try {
//     $client->connect(); // Koneksi ke Mikrotik
// } catch (Exception $e) {
//     die('Koneksi gagal: ' . $e->getMessage());
// }

// Menangani form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $query = (new Query('/ppp/secret/add'))
            ->equal('name', $_POST['username'])
            ->equal('password', $_POST['password'])
            ->equal('service', 'pppoe');
        $client->query($query); // Mengirim query ke Mikrotik
    } elseif ($action === 'edit') {
        $query = (new Query('/ppp/secret/set'))
            ->equal('.id', $_POST['id'])
            ->equal('name', $_POST['username'])
            ->equal('password', $_POST['password']);
        $client->query($query); // Mengirim query ke Mikrotik
    } elseif ($action === 'delete') {
        $query = (new Query('/ppp/secret/remove'))
            ->equal('.id', $_POST['id']);
        $client->query($query); // Mengirim query ke Mikrotik
    }
}
// Create "where" Query object for RouterOS
$query =
    (new Query('/ppp/secret/print'));
        // ->where('mac-address', '00:00:00:00:40:29');

// Send query and read response from RouterOS
// $response = $client->query($query)->read();
// Mengambil data PPPoE
// $query = new Query('/ppp/secret/print');
$pppoeList = $client->query($query)->read(); // Mengambil data PPPoE



try {
    $client->connect(); // Koneksi ke Mikrotik
} catch (Exception $e) {
    die('Koneksi gagal: ' . $e->getMessage());
}

// var_dump($pppoeList);

// Menutup koneksi setelah selesai
// $client->disconnect();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage PPPoE</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h1>Manage PPPoE</h1>

        <!-- Add PPPoE Form -->
        <form method="POST" action="manage_pppoe.php">
            <h2>Add PPPoE User</h2>
            <div class="mb-3">
                <label for="username">Username:</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password">Password:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <input type="hidden" name="action" value="add">
            <button type="submit" class="btn btn-primary">Add User</button>
        </form>

        <hr>

        <!-- List of PPPoE Users -->
        <h2>Existing PPPoE Users</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pppoeList as $pppoe): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pppoe['name']); ?></td>
                        <td><?php echo htmlspecialchars($pppoe['profile']); ?></td>
                        <td>
                            <!-- Edit Button (trigger modal) -->
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                            data-id="<?php echo $pppoe['.id']; ?>" 
                            data-username="<?php echo htmlspecialchars($pppoe['name']); ?>" 
                            data-password="<?php echo htmlspecialchars($pppoe['profile']); ?>">
                                Edit
                            </button>

                            <!-- Delete Button -->
                            <form method="POST" action="manage_pppoe.php" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?php echo $pppoe['.id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit PPPoE Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editModalLabel">Edit PPPoE User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="POST" action="manage_pppoe.php" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label for="username">Username:</label>
                    <input type="text" name="username" id="edit-username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="edit-password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Trigger modal and pass data to it
        $('#editModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var username = button.data('username');
            var password = button.data('password');

            var modal = $(this);
            modal.find('#edit-id').val(id);
            modal.find('#edit-username').val(username);
            modal.find('#edit-password').val(password);
        });
    </script>
</body>
</html>