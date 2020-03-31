--	Part of FusionPBX
--	Copyright (C) 2013-2019 Mark J Crane <markjcrane@fusionpbx.com>
--	All rights reserved.
--
--	Redistribution and use in source and binary forms, with or without
--	modification, are permitted provided that the following conditions are met:
--
--	1. Redistributions of source code must retain the above copyright notice,
--	  this list of conditions and the following disclaimer.
--
--	2. Redistributions in binary form must reproduce the above copyright
--	  notice, this list of conditions and the following disclaimer in the
--	  documentation and/or other materials provided with the distribution.
--
--	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
--	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
--	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
--	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
--	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
--	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
--	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
--	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
--	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
--	POSSIBILITY OF SUCH DAMAGE.

local api = freeswitch.API()

local IS_WINDOWS = (package.config:sub(1,1) == '\\') 
local function quote(s)
	local q = IS_WINDOWS and '"' or "'"
        if s:find('%s') or s:find(q, nil, true) then
        	s = q .. s:gsub(q, q..q) .. q
        end
        return s
end

-- escape shell arguments to prevent command injection
local function shell_esc(x)
        return (x:gsub('\\', '\\\\')
               :gsub('\'', '\\\''))
end

function copy_cloud_storage(voicemail_id, message_uuid) 
	local src = voicemail_dir.."/"..voicemail_id.."/msg_"..message_uuid.."."..vm_message_ext
		
	cmd = quote(shell_esc(php_dir).."/"..shell_esc(php_bin)).." "..quote(shell_esc(document_root).."/secure/copy_cloud_storage.php")
	cmd = cmd .. " voicemail_file="..quote(shell_esc(src))
	cmd = cmd .. " voicemail_id="..quote(shell_esc(voicemail_id))
	cmd = cmd .. " message_uuid="..quote(shell_esc(message_uuid))
	cmd = cmd .. " vm_message_ext="..quote(shell_esc(vm_message_ext))
	cmd = cmd .. " domain_uuid="..quote(shell_esc(domain_uuid))
	cmd = cmd .. " domain_name="..quote(shell_esc(domain_name))
	freeswitch.consoleLog("notice", "voicemail cmd: "..cmd.."\n")
	api:execute("system", cmd)
end
