--	Vonix additions to FusionPBX
--
--	Copies a voicemail message to the voicemail archive bucket by handing it
--	to app/voicemails/resources/command/copy_cloud_storage.php. Runs from the
--	voicemail app as the message is saved.

	local api = freeswitch.API()

--quote an argument for the shell
	local function quote(s)
		return "'" .. tostring(s):gsub("'", "'\\''") .. "'"
	end

	function copy_cloud_storage(voicemail_id, message_uuid)
		if (voicemail_id == nil or message_uuid == nil) then
			return;
		end

		--php_dir, php_bin and document_root come from resources.functions.config
		if (php_dir == nil or php_bin == nil or document_root == nil) then
			freeswitch.consoleLog("warning", "[voicemail] copy_cloud_storage: php paths are not set, skipping\n");
			return;
		end

		local source = voicemail_dir .. "/" .. voicemail_id .. "/msg_" .. message_uuid .. "." .. vm_message_ext;

		local cmd = quote(php_dir .. "/" .. php_bin)
			.. " " .. quote(document_root .. "/app/voicemails/resources/command/copy_cloud_storage.php")
			.. " " .. quote("voicemail_file=" .. source)
			.. " " .. quote("voicemail_id=" .. voicemail_id)
			.. " " .. quote("message_uuid=" .. message_uuid)
			.. " " .. quote("domain_name=" .. domain_name);

		if (debug["info"]) then
			freeswitch.consoleLog("notice", "[voicemail] copy_cloud_storage: " .. cmd .. "\n");
		end

		api:execute("system", cmd);
	end
