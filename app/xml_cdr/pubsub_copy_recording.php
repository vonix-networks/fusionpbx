<?php

//includes
require_once "root.php";
require_once "resources/require.php";

try {
    $processor = new copy_recording_processor;

    $processor->process(file_get_contents('php://input'));
} catch(Exception $e) {
    header('HTTP/1.1 401 Unauthorized');
    die('Invalid token');
}
