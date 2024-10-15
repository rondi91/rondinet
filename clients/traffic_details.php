<?php
// Include RouterOS API library (adjust the path based on your setup)
require '../vendor/autoload.php'; // Or wherever your RouterOS API is located
require '../config.php';          // Load the router configuration

use RouterOS\Client;
use RouterOS\Query;

// Get the username from the query parameter
$username = isset($_GET['username']) ? $_GET['username'] : '';

if (!$username) {
    die("No username provided!");
}

// Create a RouterOS client instance
$client = new Client([
    'host' => $mikrotikConfig['host'],
    'user' => $mikrotikConfig['user'],
    'pass' => $mikrotikConfig['pass'],
]);

// Query to get user profile and max speed from PPPoE secret
$secretQuery = new Query("/ppp/secret/print");
$allUsers = $client->query($secretQuery)->read();
$userDetails = null;

foreach ($allUsers as $user) {
    if ($user['name'] === $username) {
        $userDetails = $user;
        break;
    }
}

// If the user doesn't exist, show an error
if (!$userDetails) {
    die("User not found!");
}



// Extract user details
$profileData = $userDetails['profile'] ?? 'Unknown';

if (!empty($profileData)) {

    $userProfile = $profileData;

    // Query to fetch profile details (where max speed is stored)
    $profileDetailsQuery = (new Query("/ppp/profile/print"))
        ->where('name', $userProfile);
    $profileDetails = $client->query($profileDetailsQuery)->read();
    // var_dump($profileDetails);
    // die();

    if (!empty($profileDetails)) {
        $maxSpeed = $profileDetails[0]['rate-limit']; // Assuming the rate-limit contains the max speed
        
        // If rate-limit is in format "rx/tx", extract the max speed
        $rateLimits = explode("/", $maxSpeed);
        $rxLimit = (isset($rateLimits[0])) ? (int)$rateLimits[0] : 0;
        $txLimit = (isset($rateLimits[1])) ? (int)$rateLimits[1] : 0;
        
        // Assuming you want the higher of the two values
        $maxSpeed = max($rxLimit, $txLimit);

        // echo json_encode(array("maxSpeed" => $maxSpeed));
    } else {
        echo json_encode(array("error" => "No profile details found."));
    }
} else {
    echo json_encode(array("error" => "No PPPoE user found."));
}


// $maxSpeed = isset($userDetails['rate-limit']) ? $userDetails['rate-limit'] : 'Not Set'; // Adjust based on actual structure

// // Assuming max-speed is "rx/tx" format, get only rx
// $maxSpeedArr = explode('/', $maxSpeed);
// $rxLimit = $maxSpeedArr[0] ?? '0';
// $txLimit = $maxSpeedArr[1] ?? '0';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Details for <?php echo htmlspecialchars($username); ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Styling -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .card {
            margin: 20px;
        }
        .gauge-card {
            text-align: center;
        }
        .gauge-card h5 {
            margin-bottom: 20px;
        }
        canvas {
            margin: 0 auto;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            color: #fff;
            min-height: 100vh;
        }
        #sidebar .components a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #ddd;
            text-decoration: none;
        }
        #sidebar .components a:hover {
            background: #007bff;
            color: #fff;
        }
        .active a {
            background-color: #007bff;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>Traffic Data </h3>
                <h4><?php echo htmlspecialchars($username); ?></h4>
            </div>
            
                <li>
                    <a href="#">Profile</a>
                </li>
                <li>
                    <a href="#">Settings</a>
                </li>
                <li>
                    <a href="#">Logout</a>
                </li>
            </ul>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid">
            <div class="page-header">
                <h1 class="display-6">Traffic Details for <strong><?php echo htmlspecialchars($username); ?></strong></h1>
            </div>

            <div class="row">
                <!-- User Info Card -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>User Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                            <p><strong>Paket :</strong> up to <?php echo htmlspecialchars($profileData); ?></p>
                            
                            <p><strong>Max Speed:</strong> <?php echo htmlspecialchars($rxLimit . ' Mbps / ' . $txLimit); ?> Mbps (Rx / Tx)</p>
                        </div>
                    </div>
                </div>

                <!-- Download Speed Gauge -->
                <div class="col-md-4">
                    <div class="card gauge-card">
                        <div class="card-header">
                            <h5>Upload Speed (Tx)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="downloadGauge" width="250" height="250"></canvas>
                        </div>
                        <p id="downloadSpeedValue">0 Mbps</p> <!-- Display download speed here -->
                    </div>
                </div>

                <!-- Upload Speed Gauge -->
                <div class="col-md-4">
                    <div class="card gauge-card">
                        <div class="card-header">
                            <h5>Download Speed (Rx)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="uploadGauge" width="250" height="250"></canvas>
                        </div>
                        <p id="uploadSpeedValue">0 Mbps</p> <!-- Display upload speed here -->
                    </div>
                </div>
            </div>

            <div id="trafficDetails"></div>
        </div>
    </div>

    <script>
        var maxRx = <?php echo (int)$rxLimit; ?>; // Max download speed from PHP
        var maxTx = <?php echo (int)$txLimit; ?>; // Max upload speed from PHP

        // Initialize the download doughnut gauge
        var downloadCtx = document.getElementById('downloadGauge').getContext('2d');
        var downloadGaugeChart = new Chart(downloadCtx, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Remaining'],
                datasets: [{
                    data: [0, maxRx], // Initial values
                    backgroundColor: ['#4caf50', '#e0e0e0'],
                    hoverBackgroundColor: ['#66bb6a', '#e0e0e0']
                }]
            },
            options: {
                circumference: 360,
                rotation: -90,
                cutoutPercentage: 70,
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Initialize the upload doughnut gauge
        var uploadCtx = document.getElementById('uploadGauge').getContext('2d');
        var uploadGaugeChart = new Chart(uploadCtx, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Remaining'],
                datasets: [{
                    data: [0, maxTx], // Initial values
                    backgroundColor: ['#f44336', '#e0e0e0'],
                    hoverBackgroundColor: ['#ef5350', '#e0e0e0']
                }]
            },
            options: {
                circumference: 360,
                rotation: -90,
                cutoutPercentage: 70,
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Function to fetch real-time traffic data (adjust according to your setup)
        function fetchTrafficData() {
            var username = "<?php echo htmlspecialchars($username); ?>";

            // Send AJAX request to fetch real-time traffic data from PHP (via RouterOS API)
            fetch('get_traffic_details.php?username=' + username)
            .then(response => response.json())
            .then(data => {
                if (data.rx && data.tx) {
                    // Update the gauges with the new traffic data
                    downloadGaugeChart.data.datasets[0].data = [data.rx, maxRx - data.rx];
                    downloadGaugeChart.update();

                    uploadGaugeChart.data.datasets[0].data = [data.tx, maxTx - data.tx];
                    uploadGaugeChart.update();

                    // Update text values below gauges
                    // console.log(data.tx);
                    document.getElementById('uploadSpeedValue').innerText = data.tx + ' Mbps'; // Update upload speed display
                    document.getElementById('downloadSpeedValue').innerText = data.rx + ' Mbps'; // Update download speed display
                                }
            })
            .catch(error => console.error('Error fetching traffic data:', error));
        }

        // Fetch traffic data every 2 seconds (real-time updates)
        setInterval(fetchTrafficData, 1000);
    </script>
</body>
</html>
