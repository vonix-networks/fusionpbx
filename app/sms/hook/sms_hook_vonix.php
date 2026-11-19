<?php
/*
	Inbound SMS from the Vonix API (subscription-service message webhooks).

	The Vonix API POSTs one JSON object per event:
		{"event":"message","id":"...","type":"message-received","subscription":"...",
		 "owner":"+15551234567","recipient":"...","time":"...",
		 "message":{"from":"+15557654321","to":["+15551234567"],"text":"hi","media":[...]}}

	Only "message-received" is routed. Delivery receipts (message-sending,
	message-delivered, message-failed) are logged and acknowledged.

	Every request must carry HTTP Basic auth. The password is per domain: the
	domain setting sms.vonix_webhook_password of the domain whose v_sms_destinations
	row (carrier "vonix", enabled) holds the owner number. migrate.sh writes the
	same password into that company's api_subscription.
*/

include "../root.php";

require_once "resources/require.php";
require_once "../sms_hook_common.php";

function vonix_hook_reply($code, $message) {
	http_response_code($code);
	header('Content-Type: application/json');
	echo json_encode(array('status' => $message));
	exit;
}

// The ten digit NANP number, or null.
function vonix_hook_ten_digits($number) {
	$digits = preg_replace('/\D/', '', (string)$number);
	if (strlen($digits) == 11 && $digits[0] == '1') {
		$digits = substr($digits, 1);
	}
	return strlen($digits) == 10 ? $digits : null;
}

function vonix_hook_basic_auth() {
	if (isset($_SERVER['PHP_AUTH_USER'])) {
		return array($_SERVER['PHP_AUTH_USER'], isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '');
	}
	foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
		if (!empty($_SERVER[$key]) && stripos($_SERVER[$key], 'basic ') === 0) {
			$decoded = base64_decode(substr($_SERVER[$key], 6), true);
			if ($decoded !== false && strpos($decoded, ':') !== false) {
				return explode(':', $decoded, 2);
			}
		}
	}
	return array(null, null);
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	vonix_hook_reply(405, 'method not allowed');
}
if (stripos(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '', 'application/json') !== 0) {
	error_log('[SMS] vonix: request without a JSON body');
	vonix_hook_reply(415, 'expected application/json');
}

$data = json_decode(file_get_contents("php://input"));
if (!is_object($data) || !isset($data->owner)) {
	vonix_hook_reply(400, 'invalid payload');
}

$owner = vonix_hook_ten_digits($data->owner);
if ($owner === null) {
	error_log('[SMS] vonix: owner is not a NANP number: ' . $data->owner);
	vonix_hook_reply(400, 'invalid owner');
}

// The domain that owns this number on the vonix carrier, and its webhook password.
$sql = "select s.domain_uuid, ds.domain_setting_value as webhook_password ";
$sql .= "from v_sms_destinations s ";
$sql .= "join v_domains d on d.domain_uuid = s.domain_uuid ";
$sql .= "left join v_domain_settings ds on ds.domain_uuid = s.domain_uuid ";
$sql .= "and ds.domain_setting_category = 'sms' ";
$sql .= "and ds.domain_setting_subcategory = 'vonix_webhook_password' ";
$sql .= "and ds.domain_setting_enabled = 'true' ";
$sql .= "where s.carrier = 'vonix' and s.enabled = 'true' ";
$sql .= "and right(regexp_replace(s.destination, '\\D', '', 'g'), 10) = :owner";
$prep_statement = $db->prepare($sql);
$prep_statement->execute(array(':owner' => $owner));
$rows = $prep_statement->fetchAll(PDO::FETCH_ASSOC);
unset($prep_statement);

if (count($rows) != 1) {
	// Not ours (another number of the same company) or ambiguous: acknowledge so
	// the API does not log an error, but do nothing.
	error_log('[SMS] vonix: ' . count($rows) . ' vonix sms destination(s) for ' . $owner . ', ignoring ' . (isset($data->type) ? $data->type : ''));
	vonix_hook_reply(200, 'ignored');
}

$expected = (string)$rows[0]['webhook_password'];
list($user, $password) = vonix_hook_basic_auth();
if ($expected === '' || $password === null || !hash_equals($expected, (string)$password)) {
	error_log('[SMS] vonix: ACCESS DENIED for ' . $owner . ' from ' . $_SERVER['REMOTE_ADDR']);
	vonix_hook_reply(401, 'unauthorized');
}

if (!isset($data->type) || $data->type != 'message-received') {
	if ($debug) {
		error_log('[SMS] vonix: ' . (isset($data->type) ? $data->type : 'untyped') . ' event ' . (isset($data->id) ? $data->id : '') . ' for ' . $owner);
	}
	vonix_hook_reply(200, 'ok');
}

$from = isset($data->message->from) ? preg_replace('/\D/', '', $data->message->from) : '';
$text = isset($data->message->text) ? (string)$data->message->text : '';
if ($from === '') {
	vonix_hook_reply(400, 'missing from');
}

// MMS attachments are only fetched for telnyx in sms_email.php; say so in the text
// instead of passing media through and promising an email attachment.
$media = array();
if (isset($data->message->media) && is_array($data->message->media)) {
	foreach ($data->message->media as $url) {
		if (is_string($url) && substr($url, -8) != 'smil.xml') {
			$media[] = $url;
		}
	}
}
if (count($media) > 0) {
	$note = '(MMS with ' . count($media) . ' attachment' . (count($media) > 1 ? 's' : '') . ' not shown)';
	$text = $text === '' ? $note : $text . ' ' . $note;
}

route_and_send_sms($from, $owner, $text);
vonix_hook_reply(200, 'ok');
?>
