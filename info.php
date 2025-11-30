<?php
echo "ZipArchive: " . (extension_loaded('zip') ? 'Enabled' : 'Not enabled') . "<br>";
echo "cURL: " . (extension_loaded('curl') ? 'Enabled' : 'Not enabled') . "<br>";

// Also check if the ZipArchive class exists
echo "ZipArchive class: " . (class_exists('ZipArchive') ? 'Available' : 'Not available') . "<br>";
?>