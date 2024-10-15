<?php
require 'vendor/autoload.php'; // Load RouterOS API via Composer
require 'config.php';          // Load the router configuration

use RouterOS\Client;
use RouterOS\Query;

$client = new Client([
    'host' => $mikrotikConfig['host'],
    'user' => $mikrotikConfig['user'],
    'pass' => $mikrotikConfig['pass'],
]);

// Check if the username is passed in the request
if (isset($_POST['username'])) {
    $username = $_POST['username'];
} else {
    echo json_encode(array("error" => "No username provided."));
    exit();
}

// Query to get the PPPoE user profile
$profileQuery = (new Query("/ppp/secret/print"))
    ->where('name', $username);
$profileData = $client->query($profileQuery)->read();

if (!empty($profileData)) {
    $userProfile = $profileData[0]['profile'];

    // Query to fetch profile details (where max speed is stored)
    $profileDetailsQuery = (new Query("/ppp/profile/print"))
        ->where('name', $userProfile);
    $profileDetails = $client->query($profileDetailsQuery)->read();

    if (!empty($profileDetails)) {
        $maxSpeed = $profileDetails[0]['rate-limit']; // Assuming the rate-limit contains the max speed
        
        // If rate-limit is in format "rx/tx", extract the max speed
        $rateLimits = explode("/", $maxSpeed);
        $rxLimit = (isset($rateLimits[0])) ? (int)$rateLimits[0] : 0;
        $txLimit = (isset($rateLimits[1])) ? (int)$rateLimits[1] : 0;
        
        // Assuming you want the higher of the two values
        $maxSpeed = max($rxLimit, $txLimit);

        echo json_encode(array("maxSpeed" => $maxSpeed));
    } else {
        echo json_encode(array("error" => "No profile details found."));
    }
} else {
    echo json_encode(array("error" => "No PPPoE user found."));
}
