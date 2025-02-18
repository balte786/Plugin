
<?php
// Define Google Client Credentials, Scopes, and URIs
define('GCLIENT_ID', "1090939656304-bnfbahc6961u59r1iu6up3fi8odr1u1j.apps.googleusercontent.com");
define('GCLIENT_SECRET', "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");
define('GCLIENT_SCOPE', "https://www.googleapis.com/auth/drive");
define('GCLIENT_REDIRECT', "/googledrive/index.php");

define('OAUTH2_TOKEN_URI',"https://oauth2.googleapis.com/token");

define('DRIVE_FILE_UPLOAD_URI',"https://www.googleapis.com/upload/drive/v3/files");
define('DRIVE_FILE_META_URI',"https://www.googleapis.com/drive/v3/files/");

if(!session_id()) session_start();

// Authentication URL
$gOauthURL = "https://accounts.google.com/o/oauth2/auth?scope=".(urldecode(GCLIENT_SCOPE))."&redirect_uri=".(urlencode(GCLIENT_REDIRECT))."&client_id=".(urlencode(GCLIENT_ID))."&access_type=online&response_type=code";

if(isset($_GET['code'])){
    $_SESSION['code'] = $_GET['code'];

   function GetAccessToken() { 
        $curlPost = 'client_id='.GCLIENT_ID.'&redirect_uri=' .GCLIENT_REDIRECT. '&client_secret=' . GCLIENT_SECRET . '&code='. $_SESSION['code'] . '&grant_type=authorization_code'; 
        $ch = curl_init();         
        curl_setopt($ch, CURLOPT_URL, OAUTH2_TOKEN_URI);         
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);         
        curl_setopt($ch, CURLOPT_POST, 1);         
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);     
        $data = json_decode(curl_exec($ch), true); 
        $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE); 
         
        if ($http_code != 200) { 
            $error_msg = 'Failed to receieve access token'; 
            if (curl_errno($ch)) { 
                $error_msg = curl_error($ch); 
            } 
            print_r($data);
            throw new Exception('Error '.$http_code.': '.$error_msg); 
        } 
             
        return $data; 
    } 
    // Save Access Token
    $_SESSION['access_token'] = GetAccessToken()['access_token'];
    header('location:./');
}

if (isset($_POST['folderName']) && isset($_POST['days'])) {
    $folderName = $_POST['folderName'];
    $days = (int) $_POST['days'];

    // Get the current date
    $currentDate = new DateTime();
    $currentDate->setTime(0, 0); // Set to start of the day
    $currentDate->modify("-$days day");

    // Convert the date to a format Google Drive API understands
    $formattedDate = $currentDate->format(DateTime::ATOM);

    $accessToken = $_SESSION['access_token'];

    // Step 1: Get folder ID by folder name
    $folderId = getFolderIdByName($folderName, $accessToken);

    if ($folderId) {
        // Step 2: Fetch the files inside the folder
        $files = getFilesInFolder($folderId, $formattedDate, $accessToken);

        // Step 3: Delete files
        foreach ($files as $file) {
            deleteFile($file['id'], $accessToken);
        }
        echo "Files deleted successfully.";
    } else {
        echo "Folder not found.";
    }
}

function getFolderIdByName($folderName, $accessToken) {
    // Ensure the folder name is correctly encoded for the query
    $encodedFolderName = addslashes($folderName); // Escape special characters in the name
    

    $query = sprintf(
        "'root' in parents and mimeType='application/vnd.google-apps.folder' and name='%s'",
        $encodedFolderName
    );

    // URL encode the full query string
    $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query) . "&fields=files(id)";

    // Make the request
    $response = makeRequest($url, $accessToken);

    //print_r($response, true);
    // exit;

    // Return the folder ID if found, otherwise return null
    return isset($response['files'][0]['id']) ? $response['files'][0]['id'] : null;
}


function getFilesInFolder($folderId, $modifiedDate, $accessToken) {
    // Construct the query string with properly formatted and escaped values
    $query = sprintf(
        '"%s" in parents and modifiedTime < "%s"',
        addslashes($folderId), // Escape special characters in folder ID
        addslashes($modifiedDate) // Escape special characters in modified date
    );



    // URL encode the full query string
    $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query);

    
    $response = makeRequest($url, $accessToken);

    //print_r($response, true);
    // exit;

    // Return the list of files if available
    return isset($response['files']) ? $response['files'] : [];
}


function deleteFile($fileId, $accessToken) {
    $url = "https://www.googleapis.com/drive/v3/files/{$fileId}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_exec($ch);
    curl_close($ch);
}

function makeRequest($url, $accessToken) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);

    if(curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        return null; // Handle the error
    }

    curl_close($ch);

    // Decode the JSON response
    $decodedResponse = json_decode($response, true);

    if (isset($decodedResponse['error'])) {
        echo 'Google API error: ' . $decodedResponse['error']['message'];
        return null; // Handle Google API errors
    }

    return $decodedResponse;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP - Upload File in Gdrive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/js/all.min.js" integrity="sha512-naukR7I+Nk6gp7p5TMA4ycgfxaZBJ7MO5iC3Fp6ySQyKFHOGfpkSZkYVWV5R7u7cfAicxanwYQ5D1e17EfJcMA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://code.jquery.com/jquery-3.6.1.js" integrity="sha256-3zlB5s2uwoUzrXK3BT7AX3FyvojsraNFxCc2vC/7pNI=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>

    <script src="assets/js/script.js"></script>
</head>

<body>
    <main>
        <nav class="navbar navbar-expand-lg navbar-dark bg-gradient">
            <div class="container">
                <a class="navbar-brand" href="./">Delete File in Gdrive </a>
               
                <?php if (isset($_SESSION['access_token']) && !empty($_SESSION['access_token'])): ?>
                <a class="btn btn-danger rounded-0" href="./logout.php"><i class="fa fa-sign-out"></i> Logout</a>
                <?php endif; ?>
            </div>
        </nav>
        <div id="main-wrapper">
            <div class="container px-5 my-3">
                <div class="mx-auto col-lg-10 col-md-12 col-sm-12 col-xs-12">
                    <?php if (isset($_SESSION['access_token']) && !empty($_SESSION['access_token'])): ?>
                        <div class="card rounded-0 shadow">
                            <div class="card-header rounded-0">
                                <div class="card-title"><b>Delete Form</b></div>
                            </div>
                            <div class="card-body rounded-0">
                                <div class="container-fluid">
                                    <form action="index.php" method="POST">
                                        <label for="folderName">Folder Name:</label>
                                        <input type="text" id="folderName" name="folderName" required>

                                        <label for="days">Number of Days:</label>
                                        <input type="number" id="days" name="days" required min="1">

                                        <button type="submit" class="btn btn-danger">Delete Files</button>
                                    </form>
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

</body>

</html>