--      xml_handler.lua
--      Part of FusionPBX
--      Copyright (C) 2016-2020 Mark J Crane <markjcrane@fusionpbx.com>
--      All rights reserved.
--
--      Redistribution and use in source and binary forms, with or without
--      modification, are permitted provided that the following conditions are met:
--
--      1. Redistributions of source code must retain the above copyright notice,
--         this list of conditions and the following disclaimer.
--
--      2. Redistributions in binary form must reproduce the above copyright
--         notice, this list of conditions and the following disclaimer in the
--         documentation and/or other materials provided with the distribution.
--
--      THIS SOFTWARE IS PROVIDED ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
--      INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
--      AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
--      AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
--      OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
--      SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
--      INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
--      CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
--      ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
--      POSSIBILITY OF SUCH DAMAGE.

--get the ivr name
	ivr_survey_uuid = params:getHeader("Menu-Name");
	original_ivr_survey_uuid = ivr_survey_uuid

	local log = require "resources.functions.log".ivr_survey

--get the cache
	local cache = require "resources.functions.cache"
	local ivr_survey_cache_key = "configuration:ivr_survey.conf:" .. ivr_survey_uuid
	XML_STRING, err = cache.get(ivr_survey_cache_key)

--set the cache
	if not XML_STRING  then
		--log cache error
			if (debug["cache"]) then
				freeswitch.consoleLog("warning", "[xml_handler] " .. ivr_survey_cache_key .. " can not be get from the cache: " .. tostring(err) .. "\n");
			end

		--required includes
			local Database = require "resources.functions.database"
			local Settings = require "resources.functions.lazy_settings"
			local json
			if (debug["sql"]) then
				json = require "resources.functions.lunajson"
			end

		--start the xml array
			local xml = {}
			table.insert(xml, [[<?xml version="1.0" encoding="UTF-8" standalone="no"?>]]);
			table.insert(xml, [[<document type="freeswitch/xml">]]);
			table.insert(xml, [[	<section name="configuration">]]);
			table.insert(xml, [[		<configuration name="ivr_survey.conf" description="IVR Menus">]]);
			table.insert(xml, [[			<menus>]]);

		--set the sound prefix
			sound_prefix = sounds_dir.."/${default_language}/${default_dialect}/${default_voice}/";

		--connect to the database
			local dbh = Database.new('system');

		--exits the script if we didn't connect properly
			assert(dbh:connected());

		--get the ivr menu from the database
			local sql = [[
				with recursive ivr_surveys as (
					select * 
						from v_ivr_surveys 
						where ivr_survey_uuid = :ivr_survey_uuid
						and ivr_survey_enabled = 'true'
						union all 
						select child.*
						from v_ivr_surveys as child, ivr_surveys as parent 
						where child.ivr_survey_parent_uuid = parent.ivr_survey_uuid
						and child.ivr_survey_enabled = 'true'
					)
					select * from ivr_surveys
			]];
			local params = {ivr_survey_uuid = ivr_survey_uuid};
			if (debug["sql"]) then
				freeswitch.consoleLog("notice", "[ivr_survey] SQL: " .. sql .. "; params:" .. json.encode(params) .. "\n");
			end

			dbh:query(sql, params, function(row)

				--set the variables
					domain_uuid = row["domain_uuid"];
					ivr_survey_uuid = row["ivr_survey_uuid"];
					ivr_survey_name = row["ivr_survey_name"];
					ivr_survey_extension = row["ivr_survey_extension"];
					ivr_survey_greet_long = row["ivr_survey_greet_long"];
					ivr_survey_greet_short = row["ivr_survey_greet_short"];
					ivr_survey_invalid_sound = row["ivr_survey_invalid_sound"];
					ivr_survey_exit_sound = row["ivr_survey_exit_sound"];
					ivr_survey_pin_number = row["ivr_survey_pin_number"];
					ivr_survey_confirm_macro = row["ivr_survey_confirm_macro"];
					ivr_survey_confirm_key = row["ivr_survey_confirm_key"];
					ivr_survey_tts_engine = row["ivr_survey_tts_engine"];
					ivr_survey_tts_voice = row["ivr_survey_tts_voice"];
					ivr_survey_confirm_attempts = row["ivr_survey_confirm_attempts"];
					ivr_survey_timeout = row["ivr_survey_timeout"];
					ivr_survey_exit_app = row["ivr_survey_exit_app"];
					ivr_survey_exit_data = row["ivr_survey_exit_data"];
					ivr_survey_inter_digit_timeout = row["ivr_survey_inter_digit_timeout"];
					ivr_survey_max_failures = row["ivr_survey_max_failures"];
					ivr_survey_max_timeouts = row["ivr_survey_max_timeouts"];
					ivr_survey_digit_len = row["ivr_survey_digit_len"];
					ivr_survey_direct_dial = row["ivr_survey_direct_dial"];
					ivr_survey_ringback = row["ivr_survey_ringback"];
					ivr_survey_cid_prefix = row["ivr_survey_cid_prefix"];
					ivr_survey_description = row["ivr_survey_description"];

				--set variables from settings
					local settings = Settings.new(dbh, domain_name, domain_uuid)

				--direct dial regex
					direct_dial_digits = settings:get('ivr_survey', 'direct_dial_digits', 'text')

				--storage path
					local storage_type = settings:get('recordings', 'storage_type', 'text')
					local storage_path = settings:get('recordings', 'storage_path', 'text')
					if (storage_path ~= nil) then
						storage_path = storage_path:gsub("${domain_name}", domain_name)
						storage_path = storage_path:gsub("${domain_uuid}", domain_uuid)
					end

				--get the recordings from the database
					ivr_survey_greet_long_is_base64 = false;
					ivr_survey_greet_short_is_base64 = false;
					ivr_survey_invalid_sound_is_base64 = false;
					ivr_survey_exit_sound_is_base64 = false;
					if (storage_type == "base64") then
						--include the file io
							local file = require "resources.functions.file"

						--connect to db
							local dbh = Database.new('system', 'base64/read');

						--base path for recordings
							local base_path = recordings_dir.."/"..domain_name

						--function to get recording to local fs
							local function load_record(name)
								local path = base_path .. "/" .. name;
								local is_base64 = false;
		
								if not file_exists(path) then
									local sql = "SELECT recording_base64 FROM v_recordings " .. 
										"WHERE domain_uuid = :domain_uuid " ..
										"AND recording_filename = :name "
									local params = {domain_uuid = domain_uuid, name = name};
									if (debug["sql"]) then
										freeswitch.consoleLog("notice", "[ivr_survey] SQL: "..sql.."; params:" .. json.encode(params) .. "\n");
									end

									dbh:query(sql, params, function(row)
										--save the recording to the file system
										if #row.recording_base64 > 32 then
											is_base64 = true;
											file.write_base64(path, row.recording_base64);
											--add the full path and file name
											name = path;
										end
									end);
								end
								return name, is_base64
							end

						--greet long
							if #ivr_survey_greet_long > 1 then
								ivr_survey_greet_long, ivr_survey_greet_long_is_base64 = load_record(ivr_survey_greet_long)
							end

						--greet short
							if #ivr_survey_greet_short > 1 then
								ivr_survey_greet_short, ivr_survey_greet_short_is_base64 = load_record(ivr_survey_greet_short)
							end

						--invalid sound
							if #ivr_survey_invalid_sound > 1 then
								ivr_survey_invalid_sound, ivr_survey_invalid_sound_is_base64 = load_record(ivr_survey_invalid_sound)
							end

						--exit sound
							if #ivr_survey_exit_sound > 1 then
								ivr_survey_exit_sound, ivr_survey_exit_sound_is_base64 = load_record(ivr_survey_exit_sound)
							end

							dbh:release()
					elseif (storage_type == "http_cache") then
						--add the path to file name
						ivr_survey_greet_long = storage_path.."/"..ivr_survey_greet_long;
						ivr_survey_greet_short = storage_path.."/"..ivr_survey_greet_short;
						ivr_survey_invalid_sound = storage_path.."/"..ivr_survey_invalid_sound;
						ivr_survey_exit_sound = storage_path.."/"..ivr_survey_exit_sound;
					end

				--greet long
					if (not ivr_survey_greet_long_is_base64 and not file_exists(ivr_survey_greet_long)) then
						if (file_exists(recordings_dir.."/"..domain_name.."/"..ivr_survey_greet_long)) then
							ivr_survey_greet_long = recordings_dir.."/"..domain_name.."/"..ivr_survey_greet_long;
						elseif (file_exists(sounds_dir.."/en/us/callie/8000/"..ivr_survey_greet_long)) then
							ivr_survey_greet_long = sounds_dir.."/${default_language}/${default_dialect}/${default_voice}/"..ivr_survey_greet_long;
						end
					end

				--greet short
					if (string.len(ivr_survey_greet_short) > 1) then
						if (not ivr_survey_greet_short_is_base64 and not file_exists(ivr_survey_greet_short)) then
							if (file_exists(recordings_dir.."/"..domain_name.."/"..ivr_survey_greet_short)) then
								ivr_survey_greet_short = recordings_dir.."/"..domain_name.."/"..ivr_survey_greet_short;
							elseif (file_exists(sounds_dir.."/en/us/callie/8000/"..ivr_survey_greet_short)) then
								ivr_survey_greet_short = sounds_dir.."/${default_language}/${default_dialect}/${default_voice}/"..ivr_survey_greet_short;
							end
						end
					else
						ivr_survey_greet_short = ivr_survey_greet_long;
					end

				--invalid sound
					if (not ivr_survey_invalid_sound_is_base64 and not file_exists(ivr_survey_invalid_sound)) then
						if (file_exists(recordings_dir.."/"..domain_name.. "/"..ivr_survey_invalid_sound)) then
							ivr_survey_invalid_sound = recordings_dir.."/"..domain_name.."/"..ivr_survey_invalid_sound;
						elseif (file_exists(sounds_dir.."/en/us/callie/8000/"..ivr_survey_invalid_sound)) then
							ivr_survey_invalid_sound = sounds_dir.."/${default_language}/${default_dialect}/${default_voice}/"..ivr_survey_invalid_sound;
						end
					end

				--exit sound
					if (not ivr_survey_exit_sound_is_base64 and not file_exists(ivr_survey_exit_sound)) then
						if (file_exists(recordings_dir.."/"..ivr_survey_exit_sound)) then
							if (ivr_survey_exit_sound ~= nil and ivr_survey_exit_sound ~= "") then
								ivr_survey_exit_sound = recordings_dir.."/"..domain_name.."/"..ivr_survey_exit_sound;
							end
						elseif (file_exists(sounds_dir.."/en/us/callie/8000/"..ivr_survey_exit_sound)) then
							ivr_survey_exit_sound = sounds_dir.."/${default_language}/${default_dialect}/${default_voice}/"..ivr_survey_exit_sound;
						end
					end

				--add xml to the array
					table.insert(xml, [[				<menu name="]]..ivr_survey_uuid..[[" description="]]..ivr_survey_name..[[" ]]);
					table.insert(xml, [[				greet-long="]]..ivr_survey_greet_long..[[" ]]);
					table.insert(xml, [[				greet-short="]]..ivr_survey_greet_short..[[" ]]);
					table.insert(xml, [[				invalid-sound="]]..ivr_survey_invalid_sound..[[" ]]);
					table.insert(xml, [[				exit-sound="]]..ivr_survey_exit_sound..[[" ]]);
					table.insert(xml, [[				pin="]]..ivr_survey_pin_number..[[" ]]);
					table.insert(xml, [[				confirm-macro="]]..ivr_survey_confirm_macro..[[" ]]);
					table.insert(xml, [[				confirm-key="]]..ivr_survey_confirm_key..[[" ]]);
					table.insert(xml, [[				tts-engine="]]..ivr_survey_tts_engine..[[" ]]);
					table.insert(xml, [[				tts-voice="]]..ivr_survey_tts_voice..[[" ]]);
					table.insert(xml, [[				confirm-attempts="]]..ivr_survey_confirm_attempts..[[" ]]);
					table.insert(xml, [[				timeout="]]..ivr_survey_timeout..[[" ]]);
					table.insert(xml, [[				inter-digit-timeout="]]..ivr_survey_inter_digit_timeout..[[" ]]);
					table.insert(xml, [[				max-failures="]]..ivr_survey_max_failures..[[" ]]);
					table.insert(xml, [[				max-timeouts="]]..ivr_survey_max_timeouts..[[" ]]);
					table.insert(xml, [[				digit-len="]]..ivr_survey_digit_len..[[" ]]);
					table.insert(xml, [[				ivr_survey_exit_app="]]..ivr_survey_exit_app..[[" ]]);
					table.insert(xml, [[				ivr_survey_exit_data="]]..ivr_survey_exit_data..[[" ]]);
					table.insert(xml, [[				>]]);

				--get the ivr menu options
					local sql = [[SELECT * FROM v_ivr_survey_options WHERE ivr_survey_uuid = :ivr_survey_uuid ORDER BY ivr_survey_option_order asc ]];
					local params = {ivr_survey_uuid = ivr_survey_uuid};
					if (debug["sql"]) then
						freeswitch.consoleLog("notice", "[ivr_survey] SQL: " .. sql .. "; params:" .. json.encode(params) .. "\n");
					end
					dbh:query(sql, params, function(r)
						ivr_survey_option_digits = r.ivr_survey_option_digits
						ivr_survey_option_action = r.ivr_survey_option_action
						ivr_survey_option_param = r.ivr_survey_option_param
						ivr_survey_option_description = r.ivr_survey_option_description
						table.insert(xml, [[					<entry action="]]..ivr_survey_option_action..[[" digits="]]..ivr_survey_option_digits..[[" param="]]..ivr_survey_option_param..[[" description="]]..ivr_survey_option_description..[["/>]]);
					end);

				--direct dial
					if (ivr_survey_direct_dial == "true") then
						table.insert(xml, [[					<entry action="menu-exec-app" digits="]]..direct_dial_digits..[[" param="set ${cond(${user_exists id $1 ]]..domain_name..[[} == true ? user_exists=true : user_exists=false)}" description="direct dial"/>\n]]);
						--table.insert(xml, [[					<entry action="menu-exec-app" digits="]]..direct_dial_digits..[[" param="set ${cond(${user_exists} == true ? user_exists=true : ivr_max_failures=${system(expr ${ivr_max_failures} + 1)})}" description="increment max failures"/>\n]]);
						table.insert(xml, [[					<entry action="menu-exec-app" digits="]]..direct_dial_digits..[[" param="playback ${cond(${user_exists} == true ? ]]..sound_prefix..[[ivr/ivr-call_being_transferred.wav : ]]..sound_prefix..[[ivr/ivr-that_was_an_invalid_entry.wav)}" description="play sound"/>\n]]);
						--table.insert(xml, [[					<entry action="menu-exec-app" digits="]]..direct_dial_digits..[[" param="transfer ${cond(${ivr_max_failures} == ]]..ivr_survey_max_failures..[[ ? ]]..ivr_survey_exit_data..[[)}" description="max fail transfer"/>\n]]);
						if (#ivr_survey_cid_prefix > 0) then
							table.insert(xml, [[					<entry action="menu-exec-app" digits="]]..direct_dial_digits..[[" param="set effective_caller_id_name=]]..ivr_survey_cid_prefix..[[#${caller_id_name}" description="direct dial"/>\n]]);
						end
						table.insert(xml, [[					<entry action="menu-exec-app" digits="]]..direct_dial_digits..[[" param="transfer ${cond(${user_exists} == true ? $1 XML ]]..domain_name..[[)}" description="direct dial transfer"/>\n]]);
					end

				--close the extension tag if it was left open
					table.insert(xml, [[				</menu>]]);

			end);

		--add the xml closing tags
			table.insert(xml, [[			</menus>]]);
			table.insert(xml, [[		</configuration>]]);
			table.insert(xml, [[	</section>]]);
			table.insert(xml, [[</document>]]);

		--save the xml table into a string
			XML_STRING = table.concat(xml, "\n");

		--optinonal debug message
			if (debug["xml_string"]) then
					freeswitch.consoleLog("notice", "[xml_handler] XML_STRING: " .. XML_STRING .. "\n");
			end

		--close the database connection
			dbh:release();
			--freeswitch.consoleLog("notice", "[xml_handler]"..api:execute("eval ${dsn}"));

		--set the cache
			local ok, err = cache.set("configuration:ivr_survey.conf:" .. original_ivr_survey_uuid, XML_STRING, expire["ivr"]);
			if debug["cache"] then
				if ok then
					freeswitch.consoleLog("notice", "[xml_handler] " .. ivr_survey_uuid .. " stored in the cache\n");
				else
					freeswitch.consoleLog("warning", "[xml_handler] " .. ivr_survey_uuid .. " can not be stored in the cache: " .. tostring(err) .. "\n");
				end
			end

		--send the xml to the console
			if (debug["xml_string"]) then
				local file = assert(io.open(temp_dir .. "/ivr-"..ivr_survey_uuid..".conf.xml", "w"));
				file:write(XML_STRING);
				file:close();
			end

		--send to the console
			if (debug["cache"]) then
				freeswitch.consoleLog("notice", "[xml_handler] " .. ivr_survey_cache_key .. " source: database\n");
			end

	else
		--send to the console
			if (debug["cache"]) then
				freeswitch.consoleLog("notice", "[xml_handler] " .. ivr_survey_cache_key .. " source: cache\n");
			end
	end --if XML_STRING
