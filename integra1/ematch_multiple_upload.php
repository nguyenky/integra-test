<?php
$ds = DIRECTORY_SEPARATOR;

$storeFolder = 'system/upload_handler/files';

if (!empty($_FILES))
{
    $targetPath = dirname(__FILE__) . $ds . $storeFolder . $ds;
    $name = uniqid(rand(), true);
    $extension = trim(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $basename = $name . '.' . $extension;
    $targetFile = $targetPath . $basename;
    move_uploaded_file($_FILES['file']['tmp_name'], $targetFile);
    echo $basename;
}
?>    
