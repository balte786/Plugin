<?php 
session_start();
foreach($_SESSION as $k => $v){
    unset($_SESSION[$k]);
}
header('location:http://localhost/googledrive/wp-admin/admin.php?page=gdrive-uploader');