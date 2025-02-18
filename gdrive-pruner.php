<?php

/**
 * Plugin Name: GDrive Pruner
 * Description: A plugin that communicates with Google Drive to schedule deletion of files within folders or drives.
 * Version: 1.0
 * Author: Your Name
 */

require_once plugin_dir_path(__FILE__) . 'includes/config.php';

if (!session_id())
    session_start();
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


add_action("gdrive_cron_job", 'gdrive_cron_handler', 10, 2);


// Add admin menu
add_action('admin_menu', 'gdrive_uploader_menu');

function gdrive_uploader_menu()
{
    // Add the main menu page
    add_menu_page(
        'GDrive Pruner',               // Page title
        'GDrive Pruner',               // Menu title
        'manage_options',                // Capability
        'gdrive-uploader',               // Menu slug
        'gdrive_uploader_admin_page',    // Callback function for content
        'dashicons-upload',              // Icon
        25                               // Position
    );

    // Add a submenu page under the main menu
    add_submenu_page(
        'gdrive-uploader',               // Parent slug (main menu slug)
        'Settings',                      // Page title
        'Settings',                      // Submenu title
        'manage_options',                // Capability
        'gdrive-uploader-settings',      // Submenu slug
        'gdrive_uploader_settings_page'  // Callback function for submenu content
    );
}
function gdrive_uploader_settings_page()
{

    if (isset($_POST['clientid']) && isset($_POST['clientsecrete']) && isset($_POST['redirecturi'])) {
        // Get and sanitize input data
        $clientid = sanitize_text_field($_POST['clientid']);
        $clientsecrete = sanitize_text_field($_POST['clientsecrete']);
        $redirecturi = sanitize_text_field($_POST['redirecturi']);
        $id = 1; // Assuming you're updating based on ID

        // Validate input
        if (empty($clientid) || empty($clientsecrete) || empty($redirecturi)) {
            wp_die('Invalid input data');
        }
        update_option('gdrive_clientid', $clientid);
        update_option('gdrive_clientsecrete', $clientsecrete);
        update_option('gdrive_redirecturi', $redirecturi);
        echo 'Data updated successfully!';
    }

    ?>
    <div class="settingsgdrive">
        <p>enter your google drive api credentials here</p>
        <form action="" method="POST">
            <label>enter client id here </label>
            <input type="password" name="clientid" value="<?php echo get_option('gdrive_clientid'); ?>">
            <label>enter client secret here </label>
            <input type="password" name="clientsecrete" value="<?php echo get_option('gdrive_clientsecrete'); ?>">
            <label>enter redirect url </label>
            <input type="text" name="redirecturi" value="<?php echo get_option('gdrive_redirecturi'); ?>">
            <button type="submit">Submit</button>
        </form>
    </div>

    <?php
}
// Admin page callback
function gdrive_uploader_admin_page()
{
    // Define Google Client Credentials, Scopes, and URIs
    // Authentication URL
    $gOauthURL = "https://accounts.google.com/o/oauth2/auth?scope=" . (urldecode(GCLIENT_SCOPE))
    . "&redirect_uri=" . (urlencode(GCLIENT_REDIRECT))
    . "&client_id=" . (urlencode(GCLIENT_ID))
    . "&access_type=offline"
    . "&response_type=code"
    . "&prompt=consent";

if (isset($_GET['code']) && empty(get_option('my_access_token'))) {
    $_SESSION['code'] = $_GET['code'];
    
   
    // Retrieve initial tokens
    $tokens = GetAccessToken();
    error_log(  'tockens'.$tokens );
    update_option('my_access_token', $tokens['access_token']);
    if (isset($tokens['refresh_token'])) {
        update_option('my_refresh_token', $tokens['refresh_token']); // Save refresh token
    }
}

// Check if Access Token has expired
$accessToken = getValidAccessToken();

    if (isset($_POST['folderName']) && isset($_POST['days'])) {
        // Get and sanitize input data
        $folder_name = sanitize_text_field($_POST['folderName']);
        $number_of_days = intval($_POST['days']);
        $id = 1; // Assuming you're updating based on ID

        // Validate input
        if (empty($folder_name) || $number_of_days < 0 || $id <= 0) {
            wp_die('Invalid input data');
        }
        update_option('gdrive_folder_name', $folder_name);
        update_option('gdrive_number_of_days', $number_of_days);
        echo 'Data updated successfully!';
    }

    $folderName = sanitize_text_field(get_option('gdrive_folder_name'));
    $days = (int) get_option('gdrive_number_of_days');

    $crons = _get_cron_array();

    $cronJobFound = 0; // Initialize the flag

    if ($crons) {
        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hookName => $hookDetails) {
                if ($hookName == 'gdrive_cron_job') {
                    $cronJobFound = 1;
                    break 2;
                }
            }
        }
    }
    if ($cronJobFound === 0) {
        update_option('gdrive_cron_running', 'false');
    }
    function custom_cron_schedules($schedules) {
        $schedules['every_five_minutes'] = [
            'interval' => 300, // 300 seconds = 5 minutes
            'display'  => __('Every 5 Minutes'),
        ];
        return $schedules;
    }
    add_filter('cron_schedules', 'custom_cron_schedules');
    

    if ($cronJobFound !== 1 && get_option('gdrive_cron_running') !== 'true') {
        wp_schedule_event(time(), 'every_five_minutes', 'gdrive_cron_job');
        echo "<div class='notice notice-success'><p>Cron job scheduled to delete files in the specified folder.</p></div>";
    } else {
        echo "<div class='notice notice-warning'><p>Cron job is already scheduled or currently running.</p></div>";
        error_log("Cron job already scheduled or running.");
    }
    

    // Example usage

    // Ensure user is signed in (check if access token exists)
    $accessToken = get_option('my_access_token');

    // if (empty($accessToken)) {
    //     // If no access token, check if the user is trying to log in
    //     if (isset($_GET['code'])) {
    //         // Handle the OAuth2 code exchange here to get the access token
    //         try {
    //             // Assume the GetAccessToken function is called here to get the token
    //             $accessToken = GetAccessToken();
    //             // Check if the token is successfully retrieved
    //             if (!empty($accessToken)) {
    //                 echo 'You have successfully logged in!';
    //             } else {
    //                 wp_die('Failed to retrieve the access token. Please try again.');
    //             }
    //         } catch (Exception $e) {
    //             wp_die('Error during authentication: ' . $e->getMessage());
    //         }
    //     } else {
    //         // Redirect user to Google OAuth for login
    //         $gOauthURL = "https://accounts.google.com/o/oauth2/auth?scope=" . urlencode(GCLIENT_SCOPE) . "&redirect_uri=" . urlencode(GCLIENT_REDIRECT) . "&client_id=" . urlencode(GCLIENT_ID) . "&access_type=online&response_type=code";
    //         echo "<div class='alert alert-warning'>Please log in to continue. <a href='$gOauthURL'>Click here to log in with Google</a></div>";
    //     }
    // } else {
    //     // Access token is available, continue with the operations
    //     try {
    //         // You can now use the access token for API calls
    //         $folders = getAllDrivesAndFolders($accessToken); // Make sure this function is properly defined
    //         // Continue with your folder operations here
    //     } catch (Exception $e) {
    //         wp_die('Error while fetching Google Drive folders: ' . $e->getMessage());
    //     }
    // }

    $folders = getAllDrivesAndFolders(accessToken: $accessToken);
    // print_r( $folders);
    // echo $refresh_token = get_option('my_refresh_token');
    ?>
    <main>
        <nav class="navbar navbar-expand-lg navbar-dark bg-gradient">
            <div class="container">
                <a class="navbar-brand" href="./">Delete File in Gdrive </a>

                <?php if (!empty(get_option('my_access_token'))): ?>
                    <a class="btn btn-danger rounded-0"
                        href="<?php echo esc_url(admin_url('admin-ajax.php?action=custom_logout')); ?>"><i
                            class="fa fa-sign-out"></i> Logout</a>
                <?php endif; ?>
            </div>
        </nav>
        <div id="main-wrapper">
            <div class="container px-5 my-3">
                <div class="mx-auto col-lg-10 col-md-12 col-sm-12 col-xs-12">
                    <?php if (get_option('my_access_token')): ?>
                        <div class="card rounded-0 shadow">
                            <div class="card-header rounded-0">
                                <div class="card-title"><b>Delete Form</b></div>
                            </div>

                            <div class="card-body rounded-0">
                                <div class="container-fluid">
                                    <form action="" method="POST" style="max-width: 400px; margin: auto;">
                                        <!-- Selected Folder Section -->
                                        <div style="margin-bottom: 15px;">
                                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                                <label for="folderName" style="font-weight: bold; margin-right: 10px;">Selected
                                                    Folder:</label>
                                                <span id="selectedFolderLabel" style="font-size: 16px; flex-grow: 1;">
                                                    <?php echo htmlspecialchars($folderName ?? "Select a folder"); ?>
                                                </span>
                                            </div>
                                            <button type="button" id="changeFolderBtn" class="btn btn-secondary"
                                                style="margin-top: 5px; display: block;">Change Folder</button>
                                            <input type="hidden" id="folderId" name="folderName"
                                                value="<?php echo htmlspecialchars($folderName ?? ''); ?>">
                                        </div>

                                        <!-- Number of Days Section -->
                                        <div style="margin-bottom: 15px;">
                                            <label for="days" style="font-weight: bold; display: block;">Number of Days:</label>
                                            <input type="number" id="days" name="days"
                                                value="<?php echo htmlspecialchars($days ?? 1); ?>" required min="0"
                                                style="width: 100%; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px;">
                                        </div>

                                        <!-- Submit Button -->
                                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 10px;">Update
                                            Files Setting</button>
                                    </form>


                                    <!-- Popup for Folder Selection -->
                                    <div id="folderPopup"
                                        style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background: #fff; border: 1px solid #ccc; padding: 20px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);">
                                        <h3>Select a Folder</h3>
                                        <div id="folderContent"
                                            style="max-height: 400px; overflow-y: auto; padding: 10px; border: 1px solid #ddd;">
                                            <ul id="folderList" style="list-style: none; padding: 0;">
                                                <?php foreach ($folders as $folderName => $folderList): ?>
                                                    <li data-type="folder" data-id="<?php echo htmlspecialchars($folderName); ?>">
                                                        <span class="folder-name" style="cursor: pointer; font-weight: bold;"><i
                                                                class="fas fa-hdd"></i>&nbsp;<?php echo htmlspecialchars($folderName); ?></span>
                                                        <ul class="subfolders"
                                                            style="list-style: none; padding-left: 15px; display: none;">
                                                            <?php foreach ($folderList as $folder): ?>
                                                                <li data-type="folder"
                                                                    data-id="<?php echo htmlspecialchars($folder['name']); ?>"
                                                                    data-name="<?php echo htmlspecialchars($folder['name']); ?>">
                                                                    <span class="folder-name" style="cursor: pointer;"><i
                                                                            class="fas fa-folder"></i>&nbsp;<?php echo htmlspecialchars($folder['name']); ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <button id="updateFolderBtn" class="btn btn-primary">Update Folder</button>
                                        <button id="closePopupBtn" class="btn btn-secondary">Close</button>
                                    </div>
                                    <div id="popupOverlay"
                                        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999;">
                                    </div>


                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-lg-3 col-md-5 col-sm-10 col-xs-12 mx-auto">
                                <a class="btn btn-primary rounded-pill w-100" href="<?= $gOauthURL ?>">Sign with Google</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
    </main>
    <?php
}
function getValidAccessToken()
{
    if (!get_option('my_access_token')) {
        return false; // No valid token, logout is probably in progress
    }
    $access_token = get_option('my_access_token');
    $token_expiry_time = get_option('my_access_token_expiry');
    
    if (!$access_token || time() >= $token_expiry_time) {
        try {
            $access_token = RefreshAccessToken(); // Use refresh token to get a new access token
            update_option('my_access_token_expiry', time() + 3599); // Save new expiry time (1 hour)
        } catch (Exception $e) {
            error_log('Error refreshing access token: ' . $e->getMessage());
        }
    }
    return $access_token;
}
function GetAccessToken()
{
    $curlPost = http_build_query([
        'client_id'     => GCLIENT_ID,
        'client_secret' => GCLIENT_SECRET,
        'redirect_uri'  => GCLIENT_REDIRECT,
        'grant_type'    => 'authorization_code',
        'code'          => $_SESSION['code'],
    ]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OAUTH2_TOKEN_URI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    $data = json_decode($response, true);
    
    if ($http_code != 200) {
        error_log('Token API Response: ' . $response);
        throw new Exception('Error ' . $http_code . ': ' . ($data['error_description'] ?? 'Unknown error'));
    }
    
    // Save access token and expiration time
    update_option('my_access_token', $data['access_token']);
    update_option('my_access_token_expiry', time() + $data['expires_in']); // Store expiry time (current time + expires_in)

    // Save refresh token if available
    if (isset($data['refresh_token'])) {
        update_option('my_refresh_token', $data['refresh_token']);
    }

    return $data;
}

function RefreshAccessToken()
{
    $refresh_token = get_option('my_refresh_token');
    if (!$refresh_token) {
        throw new Exception('No refresh token available.');
    }
    $curlPost = http_build_query([
        'client_id'     => GCLIENT_ID,
        'client_secret' => GCLIENT_SECRET,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
    ]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OAUTH2_TOKEN_URI);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    $data = json_decode($response, true);
    if ($http_code != 200) {
        error_log('Token Refresh Response: ' . $response);
        throw new Exception(message: 'Error ' . $http_code . ': ' . ($data['error_description'] ?? 'Unknown error'));
    }
    update_option('my_access_token', $data['access_token']); // Update access token
    return $data['access_token'];
}


// Enqueue styles and scripts
add_action('admin_enqueue_scripts', 'gdrive_uploader_enqueue_assets');

function gdrive_uploader_enqueue_assets($hook_suffix)
{
    if ($hook_suffix === 'toplevel_page_gdrive-uploader' || $hook_suffix === 'gdrive-uploader_page_gdrive-uploader-settings') {
        wp_enqueue_style('gdrive-uploader-styles', plugins_url('assets/css/styles.css', __FILE__));
        wp_enqueue_script('gdrive-uploader-scripts', plugins_url('assets/js/script.js', __FILE__), ['jquery'], null, true);
        wp_enqueue_style('gdrive-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css');
        wp_enqueue_style('gdrive-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css');
        wp_enqueue_script('gdrive-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js', [], null, true);
    }
}


function gdrive_cron_handler() {
    // Fetch the latest options for folder name and number of days
    $folderName = sanitize_text_field(get_option('gdrive_folder_name'));
    $days = intval(get_option('gdrive_number_of_days'));

    // Validate folder name and days
    if (empty($folderName) || $days <= 0) {
        error_log("Invalid folder name or days value. Cron job aborted.");
        return;
    }

    // Mark the cron job as running
    update_option('gdrive_cron_running', 'true');

    error_log("gdrive_cron_handler triggered with folderName: $folderName and days: $days");

    // Ensure user is signed in (check if access token exists)
    $accessToken = get_option('my_access_token');

    if (empty($accessToken)) {
        error_log("Access token is missing. Please log in to Google.");
        return;
    }

    // Calculate the cutoff date based on the number of days
    $estTimezone = new DateTimeZone("America/New_York");
    $utcTimezone = new DateTimeZone("UTC");

    $date = new DateTime("now", $estTimezone);
    $date->modify("-$days days");

    // Convert to UTC and format the date for the Google Drive API
    $formattedDate = $date->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z');

    error_log("Formatted cutoff date: $formattedDate");

    // Get the folder ID by folder name
    $folderId = getFolderIdByName($folderName, $accessToken);

    if (!$folderId) {
        error_log("Folder '$folderName' not found in Google Drive.");
        return;
    }

    // Process files and subfolders based on folder ID
    if ($folderId === 'root') {
        // Fetch and delete files in the root folder
        $files = getFilesInFolder('root', $formattedDate, $accessToken);
        foreach ($files as $file) {
            $deleteSuccess = deleteFile($file['id'], $accessToken);
            error_log("File deletion result for {$file['id']}: " . ($deleteSuccess ? "Success" : "Failure"));
        }

        // Recursively delete files in subfolders
        deleteSubfolders('root', $formattedDate, $accessToken);
    } else {
        // Fetch and delete files in the specific folder
        $files = getFilesInFolder($folderId, $formattedDate, $accessToken);
        foreach ($files as $file) {
            $deleteSuccess = deleteFile($file['id'], $accessToken);
            error_log("File deletion result for {$file['id']}: " . ($deleteSuccess ? "Success" : "Failure"));
        }

        // Recursively delete files in subfolders
        deleteSubfolders($folderId, $formattedDate, $accessToken);
    }

    // Mark the cron job as completed
    update_option('gdrive_cron_running', 'false');
    error_log("gdrive_cron_handler completed.");
}


// function deleteSubfolders($folderId, $modifiedDate, $accessToken)
// {
//     // Get the list of subfolders (not files)
//     $subfolders = getSubfolders($folderId, $accessToken);
//     foreach ($subfolders as $subfolder) {
//         // Delete files in the subfolder (but not the subfolder itself)
//         deleteFilesInFolder($subfolder['id'], $modifiedDate, $accessToken);

//         // Recursively check for files in sub-subfolders
//         deleteSubfolders($subfolder['id'], $modifiedDate, $accessToken);
//     }
// }


function deleteFilesInFolder($folderId, $modifiedDate, $accessToken)
{
    $files = getFilesInFolder($folderId, $modifiedDate, $accessToken);
    error_log("Files to be deleted in folder {$folderId}: " . print_r($files, true));  // Log files to be deleted
    foreach ($files as $file) {
        deleteFile($file['id'], $accessToken);
        error_log("Deleted file: {$file['id']}");  // Log deleted file
    }
}

function deleteSubfolders($folderId, $modifiedDate, $accessToken)
{
    $subfolders = getSubfolders($folderId, $accessToken);
    foreach ($subfolders as $subfolder) {
        error_log("Processing subfolder: {$subfolder['id']}");  // Log subfolder being processed
        deleteFilesInFolder($subfolder['id'], $modifiedDate, $accessToken);
        deleteSubfolders($subfolder['id'], $modifiedDate, $accessToken);
    }
}

function getSubfolders($folderId, $accessToken)
{
    // Ensure only folders are fetched (mimeType="application/vnd.google-apps.folder")
    $query = sprintf(
        '"%s" in parents and mimeType="application/vnd.google-apps.folder"',
        addslashes($folderId)
    );

    // Add parameters to support shared drives
    $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query) .
        "&supportsAllDrives=true&includeItemsFromAllDrives=true";

    // Make the API request
    $response = makeRequest($url, $accessToken);

    // Return the list of folders or an empty array if none found
    return isset($response['files']) ? $response['files'] : [];
}


function getFolderIdByName($folderName, $accessToken)
{
    error_log("Looking for folder: $folderName");

    if ($folderName === 'My Drive') {
        error_log("Folder is root ('My Drive'). Returning 'root'.");
        return 'root';
    }

    $encodedFolderName = addslashes($folderName);

    // Step 1: Search in "My Drive" (root)
    $query = sprintf(
        "'root' in parents and mimeType='application/vnd.google-apps.folder' and name='%s'",
        $encodedFolderName
    );
    $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query) . "&fields=files(id)&supportsAllDrives=true";

    $response = makeRequest($url, $accessToken);
    error_log("Response from My Drive search: " . print_r($response, true));

    if (isset($response['files'][0]['id'])) {
        error_log("Found folder in My Drive. ID: " . $response['files'][0]['id']);
        return $response['files'][0]['id'];
    }

    // Step 2: Search for shared drives
    $sharedDriveQuery = sprintf("name='%s'", $encodedFolderName);
    $urlSharedDrives = "https://www.googleapis.com/drive/v3/drives?q=" . urlencode($sharedDriveQuery) . "&fields=drives(id,name)";
    $responseSharedDrives = makeRequest($urlSharedDrives, $accessToken);
    error_log("Response from shared drive search: " . print_r($responseSharedDrives, true));

    if (isset($responseSharedDrives['drives'][0]['id'])) {
        error_log("Found shared drive. ID: " . $responseSharedDrives['drives'][0]['id']);
        return $responseSharedDrives['drives'][0]['id'];
    }

    // Step 3: Search in shared drive folders
    $allSharedDrives = getAllSharedDrives($accessToken);
    foreach ($allSharedDrives as $sharedDrive) {
        $sharedDriveId = $sharedDrive['id'];
        error_log("Searching in shared drive: " . $sharedDrive['name'] . " (ID: $sharedDriveId)");

        $queryFolders = sprintf(
            "'%s' in parents and mimeType='application/vnd.google-apps.folder' and name='%s'",
            $sharedDriveId,
            $encodedFolderName
        );

        $urlFolders = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($queryFolders) . "&fields=files(id)&supportsAllDrives=true&includeItemsFromAllDrives=true";
        $responseFolders = makeRequest($urlFolders, $accessToken);
        error_log("Response from folder search in shared drive '$sharedDriveId': " . print_r($responseFolders, true));

        if (isset($responseFolders['files'][0]['id'])) {
            error_log("Found folder in shared drive. ID: " . $responseFolders['files'][0]['id']);
            return $responseFolders['files'][0]['id'];
        }
    }

    error_log("Folder '$folderName' not found in My Drive or Shared Drives.");
    return null;
}



function getAllSharedDrives1($accessToken)
{
    $url = "https://www.googleapis.com/drive/v3/drives?pageSize=100&fields=drives(id,name)";
    $response = makeRequest($url, $accessToken);

    return $response['drives'] ?? [];
}


function getFilesInFolder($folderId, $modifiedDate, $accessToken)
{
    error_log($modifiedDate) ;
    // Query for files (not folders) that are modified after the specified date
    $query = sprintf(
        '"%s" in parents and createdTime < "%s" and mimeType != "application/vnd.google-apps.folder"',
        addslashes($folderId),
        addslashes($modifiedDate)
    );

    $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query) .
        "&supportsAllDrives=true&includeItemsFromAllDrives=true";

    $response = makeRequest($url, $accessToken);

    return isset($response['files']) ? $response['files'] : [];
}


function deleteFile($fileId, $accessToken)
{
    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?supportsAllDrives=true";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("cURL error while deleting file {$fileId}: " . curl_error($ch));
        curl_close($ch);
        return false; // uploader failed
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204) {
        // HTTP 204 indicates the file was successfully deleted
        return true;
    } else {
        // Log the error for debugging
        error_log("Failed to delete file {$fileId}. HTTP status code: {$httpCode}. Response: {$response}");
        return false;
    }
}


function makeRequest($url, $accessToken)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null; // Handle the error
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Decode the JSON response
    $decodedResponse = json_decode($response, true);

    // Check for HTTP errors or Google API errors
    if ($httpCode >= 400 || isset($decodedResponse['error'])) {
        $errorMsg = isset($decodedResponse['error']['message'])
            ? $decodedResponse['error']['message']
            : 'HTTP error code: ' . $httpCode;
        error_log('Google API error: ' . $errorMsg);
        return null; // Handle errors gracefully
    }

    return $decodedResponse;
}



register_deactivation_hook(__FILE__, function () {
    // Remove the action hook (if still added)
    remove_action("gdrive_cron_job", 'gdrive_cron_handler', 10, 2);

    // 1. Unschedule the cron job by passing the arguments
    $folderName = sanitize_text_field(get_option('gdrive_folder_name'));
    $days = (int) get_option('gdrive_number_of_days');

    // Check for scheduled cron job and unschedule if found
    $timestamp = wp_next_scheduled("gdrive_cron_job", [$folderName, $days]);
    if ($timestamp) {
        wp_unschedule_event($timestamp, "gdrive_cron_job", [$folderName, $days]);
    }

    // 2. Clear any remaining scheduled cron jobs (safety measure)
    wp_clear_scheduled_hook("gdrive_cron_job");

    // 3. Delete plugin options
    $options = [
        'gdrive_cron_running',
        'gdrive_folder_name',
        'gdrive_number_of_days',
        'my_access_token'
    ];
    foreach ($options as $option) {
        delete_option($option);
    }
});



function custom_logout_and_redirect()
{
    // Clear the stored access token
    delete_option('my_access_token');
    delete_option('my_access_token_expiry');
    // Redirect the user to the desired page
    wp_redirect(get_site_url() . '/wp-admin/admin.php?page=gdrive-uploader');
    exit();
}
// Register the AJAX action for both logged-in and non-logged-in users
add_action('wp_ajax_custom_logout', 'custom_logout_and_redirect');
add_action('wp_ajax_nopriv_custom_logout', 'custom_logout_and_redirect');


function getAllRootFolders($accessToken)
{
    // Define the query for all folders in the root directory
    $query = "'root' in parents and mimeType='application/vnd.google-apps.folder'";
    // $query = "(mimeType='application/vnd.google-apps.folder') and (('root' in parents) or sharedWithMe)";

    // Construct the API URL
    $url = sprintf(
        "https://www.googleapis.com/drive/v3/files?q=%s&fields=files(id,name)",
        urlencode($query)
    );

    // Make the API request
    $response = makeRequest($url, $accessToken);

    // Extract folder names and IDs
    $folders = [];
    if (isset($response['files'])) {
        foreach ($response['files'] as $file) {
            $folders[] = [
                'id' => $file['id'],
                'name' => $file['name']
            ];
        }
    }

    return $folders;
}


function getAllDrivesAndFolders($accessToken)
{
    // Fetch shared drives first
    $sharedDrives = getAllSharedDrives($accessToken);

    // Initialize a list to store all folders from all drives
    $allFolders = [];

    // Add folders from 'My Drive' (root)
    $rootFolders = getAllRootFolders1($accessToken);
    $allFolders['My Drive'] = $rootFolders;

    // Add folders from each shared drive
    foreach ($sharedDrives as $drive) {
        $sharedDriveFolders = getFoldersFromSharedDrive($accessToken, $drive['id']);
        $allFolders[$drive['name']] = $sharedDriveFolders;
    }

    return $allFolders;
}

function getAllRootFolders1($accessToken)
{
    // Define the query for all folders in My Drive (root)
    $query = "'root' in parents and mimeType='application/vnd.google-apps.folder'";

    // Construct the API URL
    $url = sprintf(
        "https://www.googleapis.com/drive/v3/files?q=%s&fields=files(id,name)&supportsAllDrives=true&includeItemsFromAllDrives=true&orderBy=name",
        urlencode($query)
    );

    // Make the API request
    $response = makeRequest($url, $accessToken);

    // Extract folder names and IDs
    $folders = [];
    if (isset($response['files'])) {
        foreach ($response['files'] as $file) {
            $folders[] = [
                'id' => $file['id'],
                'name' => $file['name']
            ];
        }
    }

    return $folders;
}

function getAllSharedDrives($accessToken)
{
    // Define the API URL to list shared drives
    $url = "https://www.googleapis.com/drive/v3/drives";

    // Make the API request to get shared drives
    $response = makeRequest($url, $accessToken);

    // Extract drive names and IDs
    $sharedDrives = [];
    if (isset($response['drives'])) {
        foreach ($response['drives'] as $drive) {
            $sharedDrives[] = [
                'id' => $drive['id'],
                'name' => $drive['name']
            ];
        }
    }

    return $sharedDrives;
}

function getFoldersFromSharedDrive($accessToken, $sharedDriveId)
{
    // Define the query for all folders in the shared drive
    $query = "mimeType='application/vnd.google-apps.folder' and trashed=false";

    // Construct the API URL
    $url = sprintf(
        "https://www.googleapis.com/drive/v3/files?q=%s&fields=files(id,name)&supportsAllDrives=true&includeItemsFromAllDrives=true&driveId=%s&corpora=drive&orderBy=name",
        urlencode($query),
        $sharedDriveId
    );

    // Make the API request
    $response = makeRequest($url, $accessToken);

    // Extract folder names and IDs
    $folders = [];
    if (isset($response['files'])) {
        foreach ($response['files'] as $file) {
            $folders[] = [
                'id' => $file['id'],
                'name' => $file['name']
            ];
        }
    }

    return $folders;
}
