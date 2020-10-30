<?php

//includes
require_once "root.php";
require_once "resources/require.php";

$processor = new copy_recording_processor;

$processor->process($_POST);
