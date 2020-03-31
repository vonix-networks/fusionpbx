<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2018
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	James Rose <james.o.rose@gmail.com>
*/

$output_type = "file"; //file or console

if (defined('STDIN')) {
	//get the document root php file must be executed with the full path
		$document_root = str_replace("\\", "/", $_SERVER["PHP_SELF"]);
		$document_root = str_replace("\\", "/", $_SERVER["PHP_SELF"]);
		preg_match("/^(.*)\/secure\/.*$/", $document_root, $matches);
		$document_root = $matches[1];
	//set the include path
		set_include_path($document_root);
		$_SERVER["DOCUMENT_ROOT"] = $document_root;
		//echo "$document_root is document_root\n";
}

if (stristr(PHP_OS, 'WIN')) { $IS_WINDOWS = true; } else { $IS_WINDOWS = false; }

//includes
	if (!defined('STDIN')) { include "root.php"; }
	require_once "resources/require.php";

	include "app/voicemails/copy-cloud-storage/index.php";

//set php ini values
	ini_set(max_execution_time,900); //15 minutes
	ini_set('memory_limit', '96M');

//start the to cache the output
	if ($output_type == "file") {
		ob_end_clean();
		ob_start();
	}

//add a delimeter to the log
	echo "\n---------------------------------\n";

//get the parameters and save them as variables
	$php_version = substr(phpversion(), 0, 1);
	if ($php_version == '4') {
		$voicemail_file = $_REQUEST["voicemail_file"];
		$voicemail_id = $_REQUEST["voicemail_id"];
		$message_uuid = $_REQUEST["message_uuid"];
		$vm_message_ext = $_REQUEST["vm_message_ext"];
		$domain_uuid = $_REQUEST["domain_uuid"];
		$domain_name = $_REQUEST["domain_name"];
	}
	else {
		$tmp_array = explode("=", $_SERVER["argv"][1]);
		$voicemail_file = $tmp_array[1];
		unset($tmp_array);

		$tmp_array = explode("=", $_SERVER["argv"][2]);
		$voicemail_id = $tmp_array[1];
		unset($tmp_array);

		$tmp_array = explode("=", $_SERVER["argv"][3]);
		$message_uuid = $tmp_array[1];
		unset($tmp_array);

		$tmp_array = explode("=", $_SERVER["argv"][4]);
		$vm_message_ext = $tmp_array[1];
		unset($tmp_array);

		$tmp_array = explode("=", $_SERVER["argv"][5]);
		$domain_uuid = $tmp_array[1];
		unset($tmp_array);

		$tmp_array = explode("=", $_SERVER["argv"][6]);
		$domain_name = $tmp_array[1];
		unset($tmp_array);

	}

	copy_cloud_storage($voicemail_file, $domain_name."/".$voicemail_id."/".$message_uuid);
	
//open the file for writing
	if ($output_type == "file") {
		//open the file
			$fp = fopen($_SESSION['server']['temp']['dir']."/copy_cloud.log", "w");
		//get the output from the buffer
			$content = ob_get_contents();
		//clean the buffer
			ob_end_clean();
		//write the contents of the buffer
			fwrite($fp, $content);
			fclose($fp);
	}
?>
