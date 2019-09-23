<?php

// Include Google Cloud dependencies using Composer
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Storage\StorageClient;

function get_transcription($attachment_str, $filename) {

	$enabled = false;
	if (isset($_SESSION['voicemail']['transcription']) && $_SESSION['voicemail']['transcription']['boolean'] == "true") {
		$enabled = true;
	}

	if (!$enabled) {
		return "";
	}

    $tmp_filename = "/tmp/vm_".microtime(1);
    $vm_ext = pathinfo($filename, PATHINFO_EXTENSION);
	try {
		openlog("FusionPBX", LOG_PID | LOG_PERROR, LOG_LOCAL0);

		file_put_contents("$tmp_filename.$vm_ext", $attachment_str);
		system("sox $tmp_filename.$vm_ext $tmp_filename.flac rate 8k");

		if (isset($_SESSION['voicemail']['transcription_bucket']) && $_SESSION['voicemail']['transcription_bucket']['text']) {
		    // If we have a bucket to store to, save to the bucket temporarily to use the async api
            // to support voicemails over 1 minute
            $object_name = uniqid("vm_");

            $bucket = (new StorageClient())
                ->bucket($_SESSION['voicemail']['transcription_bucket']['text']);

            $file = fopen($tmp_filename.".flac", 'r');
            $object = $bucket->upload($file, [ 'name' => $object_name ]);

            $audio = (new RecognitionAudio())
                ->setUri($object->gcsUri());
        } else {
            $content = file_get_contents($tmp_filename.".flac");
            $audio = (new RecognitionAudio())
                ->setContent($content);
        }

        $config = (new RecognitionConfig())
            ->setEncoding(AudioEncoding::FLAC)
            ->setSampleRateHertz(8000)
            ->setLanguageCode('en-US')
            ->setUseEnhanced(true)
            ->setModel('phone_call');

        $client = new SpeechClient();

        $op = $client->longRunningRecognize($config, $audio);
        $op->pollUntilComplete();

        $transcription = "";
        if ($op->operationSucceeded()) {
            $response = $op->getResult();
            foreach ($response->getResults() as $result) {
                $transcription .= $result->getAlternatives()[0]->getTranscript()." ";
            }
        } else {
            syslog(LOG_WARNING, "Error returned during transcription: " . $op->getError()->getMessage());
        }

		$client->close();
		return $transcription;
	} catch (Exception $e) {
		syslog(LOG_WARNING, "Error during transcription: " . $e->getMessage());
	} finally {
        // Clean up temporary files
        @unlink("$tmp_filename.$vm_ext");
        @unlink("$tmp_filename.flac");

        closelog();
	}
}
