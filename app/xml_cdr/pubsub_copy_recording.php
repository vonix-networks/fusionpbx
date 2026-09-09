<?php
/*
	Vonix additions to FusionPBX

	Google Cloud Pub/Sub push endpoint. Pub/Sub POSTs the CDR messages
	published by xml_cdr_publisher here; if the call recording named in the
	message happens to live on this machine it is copied to the recordings
	bucket.

	This is a plain push handler: it reads the standard push envelope out of
	the request body and verifies the OIDC token Pub/Sub signs the request
	with. It deliberately does not use the pub/sub client library, whose
	message parsing lives behind internal APIs.

	Configure the subscription with an OIDC service account and set:
	  pubsub / push_audience        text  the audience configured on the subscription
	  pubsub / push_service_account text  optional, the service account email to require
	  recordings / bucket           text  destination bucket
	  server / project              text  google cloud project
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once dirname(__DIR__, 2) . "/vendor/autoload.php";

	use Firebase\JWT\JWK;
	use Firebase\JWT\JWT;

	const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
	const GOOGLE_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

/**
 * Ends the request. Pub/Sub retries anything that is not a 2xx, so a message
 * this machine simply cannot act on is acknowledged rather than retried.
 */
	function respond(int $status, string $message = ''): void {
		http_response_code($status);
		if ($message !== '') {
			error_log('[pubsub_copy_recording] '.$message);
		}
		exit;
	}

/**
 * Fetches Google's signing keys, cached on disk so every push does not go out
 * to the network.
 */
	function google_certs(settings $settings): array {
		$cache_dir = $settings->get('server', 'temp') ?: sys_get_temp_dir();
		$cache_file = rtrim($cache_dir, '/').'/google_oauth2_certs.json';

		if (is_readable($cache_file) && (time() - filemtime($cache_file)) < 3600) {
			$cached = json_decode(file_get_contents($cache_file), true);
			if (!empty($cached['keys'])) {
				return $cached;
			}
		}

		$context = stream_context_create(['http' => ['timeout' => 10]]);
		$body = @file_get_contents(GOOGLE_CERTS_URL, false, $context);
		if ($body === false) {
			throw new RuntimeException('could not retrieve the google signing keys');
		}
		$certs = json_decode($body, true);
		if (empty($certs['keys'])) {
			throw new RuntimeException('the google signing keys were not in the expected format');
		}
		@file_put_contents($cache_file, $body, LOCK_EX);
		return $certs;
	}

/**
 * Verifies the OIDC token Pub/Sub signs the push request with.
 */
	function verify_push_token(settings $settings): void {
		$audience = $settings->get('pubsub', 'push_audience');
		if (empty($audience)) {
			//fail closed: without an expected audience the endpoint cannot be trusted
			respond(500, 'pubsub / push_audience is not configured, refusing to accept pushes');
		}

		$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
		if (stripos($header, 'bearer ') !== 0) {
			respond(401, 'missing bearer token');
		}
		$token = trim(substr($header, 7));

		try {
			$claims = JWT::decode($token, JWK::parseKeySet(google_certs($settings), 'RS256'));
		}
		catch (Throwable $t) {
			respond(401, 'token rejected: '.$t->getMessage());
		}

		if (!in_array($claims->iss ?? '', GOOGLE_ISSUERS, true)) {
			respond(401, 'unexpected token issuer');
		}
		if (($claims->aud ?? '') !== $audience) {
			respond(401, 'unexpected token audience');
		}

		$expected_account = $settings->get('pubsub', 'push_service_account');
		if (!empty($expected_account) && ($claims->email ?? '') !== $expected_account) {
			respond(401, 'unexpected service account');
		}
	}

//only pub/sub posts here
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		respond(405);
	}

	$settings = new settings();

	verify_push_token($settings);

//read the push envelope
	$envelope = json_decode(file_get_contents('php://input'), true);
	if (!is_array($envelope) || !isset($envelope['message'])) {
		respond(400, 'the request body was not a pub/sub push envelope');
	}

	$payload = json_decode(base64_decode($envelope['message']['data'] ?? '', true) ?: '', true);
	if (!is_array($payload)) {
		//nothing this endpoint can ever do with it, so acknowledge it
		respond(204, 'message data was not json, acknowledging');
	}

//the recording named in the record, if there is one
	$record_path = $payload['record_path'] ?? '';
	$record_name = $payload['record_name'] ?? '';
	if (empty($record_path) || empty($record_name)) {
		respond(204);
	}

	$source = $record_path.'/'.$record_name;
	if (!file_exists($source)) {
		//the call was handled by another machine, acknowledge and move on
		respond(204);
	}

	$bucket = $settings->get('recordings', 'bucket');
	if (empty($bucket)) {
		respond(500, 'recordings / bucket is not configured');
	}

	try {
		$storage = new cloud_storage($settings);
		$copied = $storage->upload($source, $bucket, $storage->relative_recording_path($source));
	}
	catch (Throwable $t) {
		//let pub/sub retry, the failure may be transient
		respond(500, 'copy failed: '.$t->getMessage());
	}

	if (!$copied) {
		respond(500, 'copy of '.$source.' did not complete');
	}

	respond(204);
