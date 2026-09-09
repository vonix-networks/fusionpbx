<?php
/*
	Vonix additions to FusionPBX

	Copies a voicemail message to the voicemail archive bucket. Called from
	the voicemail lua as the message is left, see
	app/switch/resources/scripts/app/voicemail/resources/functions/copy_cloud_storage.lua

	Usage:
	  php copy_cloud_storage.php voicemail_file=<path> voicemail_id=<id> \
	      message_uuid=<uuid> domain_name=<domain>

	Configure:
	  server / project             text  google cloud project
	  voicemail / voicemail_bucket text  destination bucket
*/

//command line only
	if (!defined('STDIN')) {
		exit;
	}

//includes files
	require_once dirname(__DIR__, 4) . "/resources/require.php";

//keep a stuck upload from holding the voicemail script open forever
	set_time_limit(300);
	ini_set('memory_limit', '96M');

//read the name=value arguments
	$arguments = [];
	foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
		if (strpos($argument, '=') === false) {
			continue;
		}
		[$name, $value] = explode('=', $argument, 2);
		$arguments[$name] = $value;
	}

	$voicemail_file = $arguments['voicemail_file'] ?? '';
	$voicemail_id = $arguments['voicemail_id'] ?? '';
	$message_uuid = $arguments['message_uuid'] ?? '';
	$domain_name = $arguments['domain_name'] ?? '';

	if ($voicemail_file === '' || $voicemail_id === '' || $message_uuid === '' || $domain_name === '') {
		error_log('[copy_cloud_storage] missing one of voicemail_file, voicemail_id, message_uuid, domain_name');
		exit(1);
	}

	if (!file_exists($voicemail_file)) {
		error_log('[copy_cloud_storage] voicemail file not found: '.$voicemail_file);
		exit(1);
	}

	$settings = new settings();

	$bucket = $settings->get('voicemail', 'voicemail_bucket');
	if (empty($bucket)) {
		error_log('[copy_cloud_storage] voicemail / voicemail_bucket is not configured');
		exit(1);
	}

//keep the extension so the object is still playable from the bucket
	$extension = pathinfo($voicemail_file, PATHINFO_EXTENSION);
	$object = $domain_name.'/'.$voicemail_id.'/'.$message_uuid.($extension !== '' ? '.'.$extension : '');

	try {
		$storage = new cloud_storage($settings);
		$copied = $storage->upload($voicemail_file, $bucket, $object);
	}
	catch (Throwable $t) {
		error_log('[copy_cloud_storage] '.$t->getMessage());
		exit(1);
	}

	exit($copied ? 0 : 1);
