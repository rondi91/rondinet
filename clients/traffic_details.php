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

    if (!empty($profileDetails)) {
        $maxSpeed = $profileDetails[0]['name']; // Assuming the rate-limit contains the max speed
        $maxSpeed = intval($maxSpeed);
        $rxLimit = $maxSpeed ? : 0;
        $txLimit = $maxSpeed ? : 0;
        
        // If rate-limit is in format "rx/tx", extract the max speed
        // $rateLimits = explode("/", $maxSpeed);
        // $rxLimit = (isset($rateLimits[5])) ? (int)$rateLimits[0] : 0;
        // $txLimit = (isset($rateLimits[6])) ? (int)$rateLimits[1] : 0;
        
        // Assuming you want the higher of the two values
        $maxSpeed = max($rxLimit, $txLimit);
        
        // echo json_encode(array("maxSpeed" => $maxSpeed));
    } else {
        echo json_encode(array("error" => "No profile details found."));
    }
} else {
    echo json_encode(array("error" => "No PPPoE user found."));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Details for <?php echo htmlspecialchars($username); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .page-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .gauge-card {
            text-align: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex: 1; /* Make cards flex to share space equally */
            margin: 10px; /* Add margin between the cards */
        }
        .gauge-card h5 {
            margin-bottom: 10px;
        }
        canvas {
            display: block;
            margin: 0 auto;
            max-width: 100% !important;
            height: 150px !important;
        }
        .gauge-value {
            font-size: 1.5em;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="page-header">
            <h1 class="display-6">Traffic Details for <strong><?php echo htmlspecialchars($username); ?></strong></h1>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5>User Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                        <p><strong>Paket:</strong> up to <?php echo htmlspecialchars($profileData); ?></p>
                        <p><strong>Max Speed:</strong> <?php echo htmlspecialchars($rxLimit . ' Mbps / ' . $txLimit. '/Mbps'); ?> (Rx / Tx)</p>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-3 d-flex">
                <div class="gauge-card">
                    <h5>Download Speed (Tx)</h5>
                    <canvas id="uploadGauge"></canvas>
                    <p id="uploadSpeedValue" class="gauge-value">0 Mbps</p>
                </div>
                <div class="gauge-card">
                    <h5>Upload Speed (Rx)</h5>
                    <canvas id="downloadGauge"></canvas>
                    <p id="downloadSpeedValue" class="gauge-value">0 Mbps</p>
                </div>
            </div>
        </div>

        <div id="trafficDetails"></div>
    </div>

    <script>
        var maxRx = <?php echo (int)$rxLimit; ?>; // Max download speed from PHP
        var maxTx = <?php echo (int)$txLimit; ?>; // Max upload speed from PHP

        // Initialize the download gauge
        var downloadGaugeChart = new Chart(document.getElementById('downloadGauge').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Remaining'],
                datasets: [{
                    data: [0, maxRx],
                    backgroundColor: ['#4caf50', '#e0e0e0'],
                    hoverBackgroundColor: ['#66bb6a', '#e0e0e0']
                }]
            },
            options: {
                circumference: 360,
                rotation: -90,
                cutoutPercentage: 70,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Initialize the upload gauge
        var uploadGaugeChart = new Chart(document.getElementById('uploadGauge').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Remaining'],
                datasets: [{
                    data: [0, maxTx],
                    backgroundColor: ['#f44336', '#e0e0e0'],
                    hoverBackgroundColor: ['#ef5350', '#e0e0e0']
                }]
            },
            options: {
                circumference: 360,
                rotation: -90,
                cutoutPercentage: 70,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Fetch real-time traffic data
        function fetchTrafficData() {
            var username = "<?php echo htmlspecialchars($username); ?>";

            fetch('get_traffic_details.php?username=' + username)
            .then(response => response.json())
            .then(data => {
                if (data.rx && data.tx) {
                    downloadGaugeChart.data.datasets[0].data = [data.rx, maxRx - data.rx];
                    downloadGaugeChart.update();
                    uploadGaugeChart.data.datasets[0].data = [data.tx, maxTx - data.tx];
                    uploadGaugeChart.update();
                    document.getElementById('uploadSpeedValue').innerText = data.tx + ' Mbps';
                    document.getElementById('downloadSpeedValue').innerText = data.rx + ' Mbps';
                }
            })
            .catch(error => console.error('Error fetching traffic data:', error));
        }

        // Fetch traffic data every 2 seconds
        setInterval(fetchTrafficData, 2000);
    </script>
</body>
</html>
