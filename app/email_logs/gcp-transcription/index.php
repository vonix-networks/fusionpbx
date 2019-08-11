<?php

// Include Google Cloud dependendencies using Composer
require_once __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;

function get_transcription($attachment_str, $filename) {
	

	$enabled = false;
	if (isset($_SESSION['voicemail']['transcription']) && $_SESSION['voicemail']['transcription']['boolean'] == "true") {
		$enabled = true;
	}

	if (!$enabled) {
		file_put_contents("/tmp/transcription.log", "NOT ENABLED");
		return "";
	}

	try {
		openlog("FusionPBX", LOG_PID | LOG_PERROR, LOG_LOCAL0);
		$tmp_filename = "/tmp/vm_".microtime(1);
		$vm_ext = pathinfo($filename, PATHINFO_EXTENSION);

		file_put_contents("$tmp_filename.$vm_ext", $attachment_str);
		system("sox $tmp_filename.$vm_ext $tmp_filename.flac rate 8k");

		$content = file_get_contents($tmp_filename.".flac");

		// Clean up temporary files
		@unlink("$tmp_filename.$vm_ext");
		@unlink("$tmp_filename.flac");

		$audio = (new RecognitionAudio())
		    ->setContent($content);

		$config = (new RecognitionConfig())
		    ->setEncoding(AudioEncoding::FLAC)
		    ->setSampleRateHertz(8000)
		    ->setLanguageCode('en-US')
		    ->setUseEnhanced(true)
		    ->setModel('phone_call');

		$client = new SpeechClient();

		$response = $client->recognize($config, $audio);
		$results = $response->getResults();
		$transcription = '';

		if ($results[0]) {
			$transcription = $results[0]->getAlternatives()[0]->getTranscript();
		}
		$client->close();
		return $transcription;
	} catch (Exception $e) {
		syslog(LOG_WARNING, "Error during transcription: " . $e->getMessage());
	} finally {
		closelog();
	}
}

function __gcp_get_vm_ext() {
	$sql = "select * from v_vars where var_name = 'vm_message_ext'";
	$pre_statement = $db->prepare(check_sql($sql));
	$pre_statement->execute();
	$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);
	foreach ($result as &$row) {
		return $row["var_value"];
	}
	if ($var_value) return $var_value;
	return "wav";
}
