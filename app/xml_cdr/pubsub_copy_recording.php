<?php

//includes
require_once "root.php";
require_once "resources/require.php";

try {
    $processor = new copy_recording_processor;

    $processor->process(file_get_contents('php://input'));
} catch(AuthenticationException $authenticationException) {
    header('HTTP/1.1 401 Unauthorized');
    die('Invalid token');
} catch (\http\Exception\InvalidArgumentException $invalidArgumentException) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid configuration');
} catch(Exception $exception) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Unexpected error');
}
