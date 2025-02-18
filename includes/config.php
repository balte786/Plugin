<?php
$gdrive_clientid=get_option('gdrive_clientid');
$gdrive_clientsecrete=get_option('gdrive_clientsecrete');
$gdrive_redirecturi=get_option('gdrive_redirecturi');



// Google Drive API settings-----------------------
define('GCLIENT_ID', $gdrive_clientid);
define('GCLIENT_SECRET',$gdrive_clientsecrete);
define('GCLIENT_SCOPE', "https://www.googleapis.com/auth/drive");
define('GCLIENT_REDIRECT', $gdrive_redirecturi);
define('OAUTH2_TOKEN_URI', "https://oauth2.googleapis.com/token");
define('DRIVE_FILE_UPLOAD_URI', "https://www.googleapis.com/upload/drive/v3/files");
define('DRIVE_FILE_META_URI', "https://www.googleapis.com/drive/v3/files/");
// Google Drive API settings -----------------------
