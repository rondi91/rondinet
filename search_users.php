<?php
require 'vendor/autoload.php'; // Load Composer autoload
require 'config.php';          // Load the router configuration

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

// Function to find the profile of an active user by matching it with allUsers
function findUserProfile($activeUserName, $allUsers) {
    foreach ($allUsers as $user) {
        if ($user['name'] === $activeUserName) {
            return isset($user['profile']) ? $user['profile'] : '-'; // Return the profile or default to '-'
        }
    }
    return '-'; // Return '-' if no match is found
}

// Determine whether we are searching active or inactive users
$searchType = isset($_POST['searchType']) ? $_POST['searchType'] : '';
$searchQuery = isset($_POST['searchQuery']) ? strtolower($_POST['searchQuery']) : '';

// Handle search for active users
if ($searchType === 'active') {
    $activeUsers = array_filter($activeUsers, function($user) use ($searchQuery) {
        return strpos(strtolower($user['name']), $searchQuery) !== false;
    });

    // Return filtered active users
    foreach ($activeUsers as $index => $user) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td>' . htmlspecialchars($user['name']) . '</td>';
        echo '<td><a href="http://' . htmlspecialchars($user['address']) . '" target="_blank">' . htmlspecialchars($user['address']) . '</a></td>';
        echo '<td>' . htmlspecialchars(findUserProfile($user['name'], $allUsers)) . '</td>';
        echo '<td>' ;
        echo '<button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#trafficModal"';
        echo 'data-username='.$user['name'].">Details</button> ";
        echo '</td>';
        echo '<td>';
        echo '<button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"';
        echo 'data-id="' . $user['.id'] . '"';
        echo 'data-username="' . htmlspecialchars($user['name']) . '"';
        echo 'data-profile="' . htmlspecialchars(findUserProfile($user['name'], $allUsers)) . '">';
        echo 'Edit</button>';
        echo '</td>';
        echo '</tr>';
    }
}

// Handle search for inactive users
if ($searchType === 'inactive') {
    $activeUserIds = array_column($activeUsers, 'name');
    $inactiveUsers = array_filter($allUsers, function($user) use ($activeUserIds, $searchQuery) {
        return !in_array($user['name'], $activeUserIds) && strpos(strtolower($user['name']), $searchQuery) !== false;
    });

    // Return filtered inactive users
    foreach ($inactiveUsers as $index => $user) {
        echo '<tr>';
        echo '<td>' . ($index + 1) . '</td>';
        echo '<td>' . htmlspecialchars($user['name']) . '</td>';
        echo '<td>' . (isset($user['profile']) ? htmlspecialchars($user['profile']) : '-') . '</td>';
        echo '</tr>';
    }
}
