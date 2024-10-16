<?php
require '../vendor/autoload.php'; // Load Composer autoload
require '../config.php';          // Load the router configuration

use RouterOS\Client;
use RouterOS\Query;

// Create the Mikrotik client using the configuration from config.php
$client = new Client([
    'host' => $mikrotikConfig['host'],
    'user' => $mikrotikConfig['user'],
    'pass' => $mikrotikConfig['pass'],
]);

// Get all PPPoE secrets (users) using print and read()
$secretQuery = new Query('/ppp/secret/print');
$allUsers = $client->query($secretQuery)->read(); // Fetch users with read()

// Get all active PPPoE connections
$activeQuery = new Query('/ppp/active/print');
$activeUsers = $client->query($activeQuery)->read(); // Fetch active users with read()

// Get all profiles
$profileQuery = new Query('/ppp/profile/print');
$profiles = $client->query($profileQuery)->read(); // Fetch profiles with read()

// Function to find the profile of an active user by matching it with allUsers
function findUserProfile($activeUserName, $allUsers) {
    foreach ($allUsers as $user) {
        if ($user['name'] === $activeUserName) {
            return isset($user['profile']) ? $user['profile'] : '-'; // Return the profile or default to '-'
        }
    }
    return '-'; // Return '-' if no match is found
}

// Handle form submissions for editing users
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_single') {
    $userId = $_POST['id'];
   // Get the current user and new profile data from the POST request
$username = $_POST['username'];
$newProfile = $_POST['profile'];

// Step 1: Fetch the user .id from /ppp/secret
$secretQuery = (new Query('/ppp/secret/print'))->where('name', $username);
$userSecret = $client->query($secretQuery)->read();
// var_dump($userSecret);

// Ensure the user was found
if (isset($userSecret[0]['.id'])) {
    $userId = $userSecret[0]['.id'];

    // Step 2: Update the user's profile using the .id from /ppp/secret
    $editQuery = (new Query('/ppp/secret/set'))
        ->equal('.id', $userId)
        ->equal('profile', $newProfile);
    
    $response = $client->query($editQuery)->read();

    // Step 3: Check if the user is currently active and remove the active connection if found
    $activeQuery = (new Query('/ppp/active/print'))->where('name', $username);
    $activeUser = $client->query($activeQuery)->read();

    if (isset($activeUser[0]['.id'])) {
        $activeUserId = $activeUser[0]['.id'];

        // Remove the active connection
        $removeActiveQuery = (new Query('/ppp/active/remove'))
            ->equal('.id', $activeUserId);
        $client->query($removeActiveQuery)->read();
    }

    echo "Profile updated and active connection removed (if any).";
} else {
    echo "User not found.";
}
}

// Process active and inactive users
$activeUserIds = array_column($activeUsers, 'name');
$inactiveUsers = array_filter($allUsers, function($user) use ($activeUserIds) {
    return !in_array($user['name'], $activeUserIds);
});

// Calculate totals
$totalSecrets = count($allUsers);
$totalActiveUsers = count($activeUsers);
$totalInactiveUsers = count($inactiveUsers);


// Function to get traffic details for a PPPoE user by interface
function getTrafficData($interface) {
    global $client;

    // Fetch traffic stats for the user's PPPoE interface
    $trafficQuery = (new Query('/interface/monitor-traffic'))
        ->equal('interface', $interface)
        ->equal('once', true);
    
    $trafficData = $client->query($trafficQuery)->read();
    
    return $trafficData;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage PPPoE Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <!-- Include Chart.js -->
    
<!-- CSS to adjust the size -->
<style>
    #downloadGauge, #uploadGauge {
        width: 50px;  /* Set desired width */
        height: 50px; /* Set desired height */
    }
</style>
</head>
<body>
    <div class="container mt-4">
        <h1>Manage PPPoE Users</h1>

        <!-- Total Stats -->
        <div class="mb-4">
            <h3>Summary</h3>
            <p>Total Secrets (Users): <strong><?php echo $totalSecrets; ?></strong></p>
            <p>Total Active Users: <strong><?php echo $totalActiveUsers; ?></strong></p>
            <p>Total Inactive Users: <strong><?php echo $totalInactiveUsers; ?></strong></p>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="userTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="active-tab" data-bs-toggle="tab" href="#activeUsers" role="tab" aria-controls="activeUsers" aria-selected="true">Active Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="inactive-tab" data-bs-toggle="tab" href="#inactiveUsers" role="tab" aria-controls="inactiveUsers" aria-selected="false">Inactive Users</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-3" id="userTabContent">
            <!-- Active Users Tab -->
            <div class="tab-pane fade show active" id="activeUsers" role="tabpanel" aria-labelledby="active-tab">
                <!-- Live Search for Active Users -->
                <form class="mb-4">
                    <div class="input-group">
                        <input type="text" id="activeSearch" class="form-control" placeholder="Search Active Users by Username">
                    </div>
                </form>

                <h3>Active Users</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                        <th><a href="javascript:void(0)" onclick="sortTable(0, 'activeUsersTableBody')">No</a></th>
                            <th><a href="javascript:void(0)" onclick="sortTable(1, 'activeUsersTableBody')">Username</a></th>
                            <th><a href="javascript:void(0)" onclick="sortTable(2, 'activeUsersTableBody')">IP Address</a></th>
                            <th><a href="javascript:void(0)" onclick="sortTable(3, 'activeUsersTableBody')">Profile</a></th>
                            <th><a href="javascript:void(0)" onclick="sortTable(4, 'activeUsersTableBody')">Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="activeUsersTableBody">
                        <?php foreach ($activeUsers as $index => $user): ?>
                            <?php
                            
                            // var_dump($user['interface']);
                            // die();
                             ?>
                            <tr>
                                <td><?php echo ($index + 1); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><a href="http://<?php echo htmlspecialchars($user['address']); ?>" target="_blank"><?php echo htmlspecialchars($user['address']); ?></a></td>
                                <td><?php echo htmlspecialchars(findUserProfile($user['name'], $allUsers)); ?></td>
                                <td>
                                    <!-- Button to open traffic details page -->
                                    <button type="button" class="btn btn-info" onclick="window.location.href='traffic_details.php?username=<?php echo $user['name']; ?>'">
                                        Details
                                    </button>
                                 </td>
                                <td>
                                    <!-- Edit Button (trigger modal) -->
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?php echo $user['.id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['name']); ?>"
                                        data-profile="<?php echo htmlspecialchars(findUserProfile($user['name'], $allUsers)); ?>">
                                        Edit
                                    </button>
                                    <!-- Existing Edit Button -->
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $user['.id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['name']); ?>"
                                            data-profile="<?php echo htmlspecialchars(findUserProfile($user['name'], $allUsers)); ?>">
                                            Edit
                                        </button>

                                        <!-- WhatsApp Button -->
                                        <a href="https://api.whatsapp.com/send?text=Check out the traffic details for user: http://rondinet.net/rondinet/traffic_details.php?username=<?php echo urlencode($user['name']); ?>" 
                                        class="btn btn-success" target="_blank">
                                        Send to WhatsApp
                                        </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Inactive Users Tab -->
            <div class="tab-pane fade" id="inactiveUsers" role="tabpanel" aria-labelledby="inactive-tab">
                <!-- Live Search for Inactive Users -->
                <form class="mb-4">
                    <div class="input-group">
                        <input type="text" id="inactiveSearch" class="form-control" placeholder="Search Inactive Users by Username">
                    </div>
                </form>

                <h3>Inactive Users</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                        <th><a href="javascript:void(0)" onclick="sortTable(0, 'inactiveUsersTableBody')">No</a></th>
                            <th><a href="javascript:void(0)" onclick="sortTable(1, 'inactiveUsersTableBody')">Username</a></th>
                            <th><a href="javascript:void(0)" onclick="sortTable(2, 'inactiveUsersTableBody')">Profile</a></th>
                        </tr>
                    </thead>
                    <tbody id="inactiveUsersTableBody">
                        <?php foreach ($inactiveUsers as $index => $user): ?>
                            <tr>
                                <td><?php echo ($index + 1); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo isset($user['profile']) ? htmlspecialchars($user['profile']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                <form method="POST" action="manage_pppoe.php">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label for="username">Username:</label>
                        <input type="text" name="username" id="edit-username" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="profile">Select New Profile:</label>
                        <select name="profile" id="edit-profile" class="form-control">
                            <?php foreach ($profiles as $profile): ?>
                                <option value="<?php echo $profile['name']; ?>"><?php echo $profile['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="action" value="edit_single">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
              </div>
            </div>
          </div>
        </div>
    </div>
<!-- Traffic Details Modal -->
<div class="modal fade" id="trafficModal" tabindex="-1" aria-labelledby="trafficModalLabel" aria-hidden="true">
    <div class="modal-dialog .modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trafficModalLabel">Traffic Details  </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
<!-- Flexbox container to hold both gauges side by side -->
                <div class="gauge-container" style="display: flex; justify-content: space-between; align-items: center;">
                    <!-- Download Gauge -->
                    <div style="flex: 1; text-align: center;">
                        <h5>Upload Speed (Tx)</h5>
                        <canvas id="downloadGauge" width="300" height="300"></canvas>
                    </div>
                    
                    <!-- Upload Gauge -->
                    <div style="flex: 1; text-align: center;">
                        <h5>Download Speed (Rx)</h5>
                        <canvas id="uploadGauge" width="300" height="300"></canvas>
                    </div>
                </div>
                <!-- Additional details -->
                <div id="trafficDetails"></div>
            </div>
        </div>
    </div>
</div>







<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Live Search for Active Users
        $('#activeSearch').on('input', function() {
            var query = $(this).val();
            $.ajax({
                url: 'search_users.php',
                type: 'POST',
                data: { searchType: 'active', searchQuery: query },
                success: function(data) {
                    $('#activeUsersTableBody').html(data);
                }
            });
        });

        // Sorting function for tables
        function sortTable(columnIndex, tableBodyId) {
            var tableBody = document.getElementById(tableBodyId);
            var rows = Array.from(tableBody.getElementsByTagName("tr"));

            // Toggle sorting order
            var isAscending = tableBody.getAttribute("data-sort-order") === "asc";
            tableBody.setAttribute("data-sort-order", isAscending ? "desc" : "asc");

            rows.sort(function(a, b) {
                var cellA = a.getElementsByTagName("td")[columnIndex].innerText.toLowerCase();
                var cellB = b.getElementsByTagName("td")[columnIndex].innerText.toLowerCase();

                if (cellA < cellB) return isAscending ? -1 : 1;
                if (cellA > cellB) return isAscending ? 1 : -1;
                return 0;
            });

            // Clear existing rows and append sorted rows
            tableBody.innerHTML = "";
            rows.forEach(function(row) {
                tableBody.appendChild(row);
            });
        }

        // Live Search for Inactive Users
        $('#inactiveSearch').on('input', function() {
            var query = $(this).val();
            $.ajax({
                url: 'search_users.php',
                type: 'POST',
                data: { searchType: 'inactive', searchQuery: query },
                success: function(data) {
                    $('#inactiveUsersTableBody').html(data);
                }
            });
        });

        // Trigger modal and pass data to it
        document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var profile = button.getAttribute('data-profile');

            var modal = this;
            modal.querySelector('#edit-id').value = id;
            modal.querySelector('#edit-username').value = username;

            // Pre-select the old profile in the dropdown
            var profileDropdown = modal.querySelector('#edit-profile');
            profileDropdown.value = profile;  // Set the dropdown to the user's current profile
        });

            // MEMBUAT DOUGHNATE GAUGE VIEW MODAL 

            var intervalID; // To store the interval ID for polling
            var downloadGaugeChart; // To store the Download gauge instance
            var uploadGaugeChart;   // To store the Upload gauge instance
            var maxSpeed = 100;     // Default max speed, can be set dynamically

            // 3.Function to initialize the Download and Upload gauges
            function initTrafficGauges() {
                // Get the context of both canvases
                var downloadCtx = document.getElementById('downloadGauge').getContext('2d');
                var uploadCtx = document.getElementById('uploadGauge').getContext('2d');

                // Initialize Download Gauge
                downloadGaugeChart = new Chart(downloadCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Used', 'Remaining'],
                        datasets: [{
                            data: [0, maxSpeed], // Initial values (0 used, maxSpeed remaining)
                            backgroundColor: ['#4caf50', '#e0e0e0'], // Green for usage, grey for remaining
                            hoverBackgroundColor: ['#66bb6a', '#e0e0e0']
                        }]
                    },
                    options: {
                        circumference: 360, // Full circle
                        rotation: -90,      // Start from top
                        cutoutPercentage: 70, // Thickness of the gauge
                        responsive: true,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Initialize Upload Gauge
                uploadGaugeChart = new Chart(uploadCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Used', 'Remaining'],
                        datasets: [{
                            data: [0, maxSpeed], // Initial values (0 used, maxSpeed remaining)
                            backgroundColor: ['#f44336', '#e0e0e0'], // Red for usage, grey for remaining
                            hoverBackgroundColor: ['#ef5350', '#e0e0e0']
                        }]
                    },
                    options: {
                        circumference: 360, // Full circle
                        rotation: -90,      // Start from top
                        cutoutPercentage: 70, // Thickness of the gauge
                        responsive: true,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            //5. Function to update the Download and Upload gauges with new traffic data
            function updateTrafficGauges(rxMbps, txMbps) {
                downloadGaugeChart.data.datasets[0].data = [rxMbps, maxSpeed - rxMbps]; // Update download gauge
                downloadGaugeChart.update(); // Redraw the download gauge

                uploadGaugeChart.data.datasets[0].data = [txMbps, maxSpeed - txMbps]; // Update upload gauge
                uploadGaugeChart.update(); // Redraw the upload gauge
            }

            // 2.Function to start real-time traffic monitoring
            function startRealTimeTraffic(username) {
                clearInterval(intervalID); // Clear any existing interval

                // Fetch max speed from the user's PPPoE profile or set dynamically
                $.ajax({
                    url: 'get_max_speed.php',
                    type: 'POST',
                    data: { username: username },
                    success: function(data) {
                        let result = JSON.parse(data);
                        if (result.maxSpeed) {
                            maxSpeed = result.maxSpeed; // Set max speed dynamically
                            initTrafficGauges(); // Initialize the gauges with the dynamic max speed
                            intervalID = setInterval(function() {
                                fetchTrafficDetails(username);
                            }, 1000); // Poll every 2 seconds
                        } else {
                            $('#trafficDetails').html(`<p>Error: Unable to fetch max speed for user.</p>`);
                        }
                    }
                });
            }

            // 4.Function to fetch real-time traffic details and update the gauges
            function fetchTrafficDetails(username) {
                $.ajax({
                    url: 'get_traffic_details.php',
                    type: 'POST',
                    data: { username: username },
                    success: function(data) {
                        let result = JSON.parse(data);
                        if (result.rx && result.tx) {
                            updateTrafficGauges(result.rx, result.tx); // Update gauges with real-time data
                            $('#trafficDetails').html(`
                                <p> Upload (Tx): ${result.rx} Mbps</p>
                                <p>Download (Rx): ${result.tx} Mbps</p>
                            `);
                        } else if (result.error) {
                            $('#trafficDetails').html(`<p>Error: ${result.error}</p>`);
                        }
                    },
                    error: function() {
                        $('#trafficDetails').html(`<p>Error fetching traffic data.</p>`);
                    }
                });
            }

            // 1.Start real-time traffic monitoring when the modal is shown
            document.getElementById('trafficModal').addEventListener('show.bs.modal', function (event) {
                var username = event.relatedTarget.getAttribute('data-username'); // Get the username from the button
                // Update the modal's title with the PPPoE username
                var modalTitle = document.getElementById('trafficModalLabel');
                modalTitle.textContent = 'Traffic Details for ' + username  ;
                startRealTimeTraffic(username); // Start monitoring
            });

            //6. Stop polling when the modal is hidden
            document.getElementById('trafficModal').addEventListener('hide.bs.modal', function (event) {
                clearInterval(intervalID); // Stop polling
            });
    </script>


</body>
</html>
