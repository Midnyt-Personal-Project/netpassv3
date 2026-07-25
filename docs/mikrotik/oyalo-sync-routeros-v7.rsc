# Oyalo Cloud router sync script for RouterOS v7.13+.
# Requires RouterOS JSON support (:deserialize from=json).
# It automatically creates/updates plan profiles fetched from Oyalo, such as
# oyalo-2days-12, oyalo-1hour-8, and oyalo-1month-23. Do not create them manually.
# Create a script called oyalo-sync, paste this content as its Source,
# then create the scheduler command shown at the bottom of this file.
#
# Replace the three values below with the Router ID and API token shown
# in Oyalo Super Admin > Generate Router Token. Never reuse a token between routers.

:local baseUrl "https://YOUR-OYALO-DOMAIN"
:local routerId "RTR-REPLACE-ME"
:local routerToken "oyalo_REPLACE_ME"

:local headers ("X-Router-ID: " . $routerId . ",X-Router-Token: " . $routerToken)
:local postHeaders ($headers . ",Content-Type: application/x-www-form-urlencoded")
:local routerModel [/system resource get board-name]

# Send a heartbeat. This is how Oyalo shows the router as online.
:do {
    /tool fetch url=($baseUrl . "/api/router/heartbeat") http-method=post http-data=("model=" . $routerModel) http-header-field=$postHeaders output=none
} on-error={
    :log error "OYALO: heartbeat failed"
}

# Read pending commands assigned to this router only.
:local response ""
:do {
    :set response [/tool fetch url=($baseUrl . "/api/router/commands") http-method=get http-header-field=$headers output=user as-value]
} on-error={
    :log error "OYALO: command download failed"
    :error "OYALO: cannot download commands"
}

:local raw ($response->"data")
:if ([:len $raw] = 0) do={
    :log info "OYALO: no command response"
    :error "OYALO: empty command response"
}

:local apiData [:deserialize from=json value=$raw]
:local commands ($apiData->"commands")

:foreach command in=$commands do={
    :local commandId ($command->"id")
    :local commandType ($command->"type")
    :local payload ($command->"payload")
    :local commandStatus "completed"
    :local commandError ""

    :do {
        :if ($commandType = "CREATE_USER") do={
            :local username ($payload->"username")
            :local password ($payload->"password")
            :local profile ($payload->"profile")
            :local rateLimit ($payload->"rate_limit")
            :local durationMinutes ($payload->"duration_minutes")
            :local comment ("Oyalo:" . $username)

            # Create/update the plan profile. Empty package speeds mean no rate limit.
            :local profileId [/ip hotspot user profile find where name=$profile]
            :if ([:len $profileId] = 0) do={
                /ip hotspot user profile add name=$profile shared-users=1
                :set profileId [/ip hotspot user profile find where name=$profile]
            }
            :if (([:typeof $rateLimit] != "nil") && ($rateLimit != "")) do={
                /ip hotspot user profile set $profileId rate-limit=$rateLimit
            } else={
                # No speed values in Oyalo means unrestricted speed for this plan.
                /ip hotspot user profile set $profileId rate-limit=""
            }

            # The voucher is deliberately both the hotspot user name and password.
            :local userId [/ip hotspot user find where name=$username]
            :if ([:len $userId] = 0) do={
                /ip hotspot user add name=$username password=$password profile=$profile disabled=no comment=$comment
                :set userId [/ip hotspot user find where name=$username]
            } else={
                /ip hotspot user set $userId password=$password profile=$profile disabled=no comment=$comment
            }

            # Renewal gets a fresh usage counter. The Laravel expiry job is still the
            # source of truth for calendar expiry and disables the user every five minutes.
            /ip hotspot user reset-counters $userId
            :if (([:typeof $durationMinutes] != "nil") && ($durationMinutes != "")) do={
                /ip hotspot user set $userId limit-uptime=($durationMinutes . "m")
            }
            :log info ("OYALO: created/renewed voucher " . $username)
        }

        :if ($commandType = "DISABLE_USER") do={
            :local username ($payload->"username")
            :local userId [/ip hotspot user find where name=$username]
            :if ([:len $userId] > 0) do={ /ip hotspot user disable $userId }
            /ip hotspot active remove [find where user=$username]
            :log info ("OYALO: disabled expired voucher " . $username)
        }

        :if ($commandType = "REMOVE_USER") do={
            :local username ($payload->"username")
            /ip hotspot active remove [find where user=$username]
            :local userId [/ip hotspot user find where name=$username]
            :if ([:len $userId] > 0) do={ /ip hotspot user remove $userId }
            :log info ("OYALO: removed voucher " . $username)
        }

        :if ($commandType = "ADD_MAC") do={
            :local mac ($payload->"mac")
            :local username ($payload->"username")
            :local comment ("Oyalo:" . $username)
            :local binding [/ip hotspot ip-binding find where mac-address=$mac]
            :if ([:len $binding] = 0) do={
                /ip hotspot ip-binding add mac-address=$mac type=bypassed comment=$comment
            } else={
                /ip hotspot ip-binding set $binding type=bypassed comment=$comment disabled=no
            }
            :log info ("OYALO: added MAC " . $mac . " for " . $username)
        }

        :if ($commandType = "REMOVE_MAC") do={
            :local mac ($payload->"mac")
            # Remove only bindings managed by Oyalo, not an unrelated manual binding.
            :foreach binding in=[/ip hotspot ip-binding find where mac-address=$mac] do={
                :local bindingComment [/ip hotspot ip-binding get $binding comment]
                :if ([:pick $bindingComment 0 6] = "Oyalo:") do={
                    /ip hotspot ip-binding remove $binding
                }
            }
            :log info ("OYALO: removed MAC " . $mac)
        }
    } on-error={
        :set commandStatus "failed"
        :set commandError $message
        :log error ("OYALO: command " . $commandId . " failed: " . $commandError)
    }

    # Tell Oyalo not to give this command to the router again.
    :do {
        /tool fetch url=($baseUrl . "/api/router/commands/" . $commandId . "/ack") http-method=post http-data=("status=" . $commandStatus) http-header-field=$postHeaders output=none
    } on-error={
        :log error ("OYALO: acknowledgement failed for command " . $commandId)
    }
}

:log info "OYALO: sync complete"

# Run this once after saving the script:
# /system scheduler add name=oyalo-sync interval=1m start-time=startup on-event=oyalo-sync policy=read,write,policy,test,ftp,sensitive
