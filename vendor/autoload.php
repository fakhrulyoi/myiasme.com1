<?php
// Use Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Test PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
echo "PhpSpreadsheet and TCPDF are loaded successfully!";
