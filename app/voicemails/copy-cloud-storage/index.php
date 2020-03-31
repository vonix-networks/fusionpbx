<?php

// Include Google Cloud dependencies using Composer
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;

function copy_cloud_storage($src, $dest) {

	try {
            openlog("FusionPBX", LOG_PID | LOG_PERROR, LOG_LOCAL0); 
            $bucket = (new StorageClient())
                ->bucket($_SESSION['voicemail']['voicemail_bucket']['text']);

            $object = $bucket->upload(fopen($src, 'r'), [ 'name' => $dest ]);
	} catch (Exception $e) {
		syslog(LOG_WARNING, "Error during transcription: " . $e->getMessage());
	} finally {
        	closelog();
	}
}
