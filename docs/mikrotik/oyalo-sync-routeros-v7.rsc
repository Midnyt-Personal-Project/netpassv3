# Save this source as a RouterOS system script named "oyalo-sync".
# Then schedule it, for example:
# /system scheduler add name=oyalo-sync interval=1m start-time=startup on-event=oyalo-sync policy=read,write,policy,test,ftp,sensitive
#
# Oyalo command sync for RouterOS 7.13+ (requires :deserialize from=json).
# Replace these three values before importing the script.
:local baseUrl "https://wifi.oyalo.net"
:local routerId "RTR-REPLACE-ME"
:local routerToken "oyalo_REPLACE_ME"

:local authHeaders ("X-Router-ID: ".$routerId.",X-Router-Token: ".$routerToken)

# Tell the API that this router is alive.
:onerror heartbeatError in={
    /tool fetch url=($baseUrl."/api/router/heartbeat") http-method=post \
        http-header-field=($authHeaders.",Content-Type: application/json") \
        http-data="{}" output=none check-certificate=yes
} do={
    :log warning ("Oyalo heartbeat failed: ".$heartbeatError)
}

:local fetchResult
:onerror fetchError in={
    :set fetchResult [/tool fetch url=($baseUrl."/api/router/commands") http-method=get \
        http-header-field=$authHeaders output=user as-value check-certificate=yes]
} do={
    :log error ("Oyalo command fetch failed: ".$fetchError)
    :error "Oyalo sync stopped"
}

:local response [:deserialize from=json value=($fetchResult->"data") options=json.no-string-conversion]
:local commands ($response->"commands")

:foreach command in=$commands do={
    :local commandId ($command->"id")
    :local commandType ($command->"type")
    :local payload ($command->"payload")
    :local commandStatus "completed"

    :onerror commandError in={
        :if (($commandType != "CREATE_PROFILE") && ($commandType != "CREATE_USER") && \
            ($commandType != "ADD_MAC") && ($commandType != "REMOVE_MAC") && \
            ($commandType != "DISABLE_USER") && ($commandType != "REMOVE_USER")) do={
            :error ("Unsupported command type ".$commandType)
        }

        :if ($commandType = "CREATE_PROFILE") do={
            :local ProfileName ($payload->"name")
            :local SpeedDown ($payload->"speed_down")
            :local SpeedUp ($payload->"speed_up")
            :local SharedUsers ($payload->"share_users")
            :local RateLimit ""
            :if (($SpeedDown != "0") || ($SpeedUp != "0")) do={
                :set RateLimit ($SpeedDown."/".$SpeedUp)
            }

            :local ProfileIds [/ip hotspot user profile find where name=$ProfileName]
            :if ([:len $ProfileIds] = 0) do={
                /ip hotspot user profile add name=$ProfileName rate-limit=$RateLimit shared-users=$SharedUsers
            } else={
                /ip hotspot user profile set $ProfileIds rate-limit=$RateLimit shared-users=$SharedUsers
            }
        }

        :if ($commandType = "CREATE_USER") do={
            :local VoucherName ($payload->"username")
            :local UserPassword ($payload->"password")
            :local UserProfile ($payload->"profile")
            :local DurationMinutes ($payload->"duration_minutes")
            :local UserIds [/ip hotspot user find where name=$VoucherName]

            :if ([:len $UserIds] = 0) do={
                /ip hotspot user add name=$VoucherName password=$UserPassword profile=$UserProfile \
                    limit-uptime=($DurationMinutes."m") comment="Managed by Oyalo"
            } else={
                /ip hotspot user set $UserIds password=$UserPassword profile=$UserProfile disabled=no \
                    limit-uptime=($DurationMinutes."m") comment="Managed by Oyalo"
                /ip hotspot user reset-counters $UserIds
            }
        }

        :if ($commandType = "ADD_MAC") do={
            :local MacAddress ($payload->"mac")
            :local VoucherName ($payload->"username")
            :local BindingIds [/ip hotspot ip-binding find where mac-address=$MacAddress]

            :if ([:len $BindingIds] = 0) do={
                /ip hotspot ip-binding add mac-address=$MacAddress type=bypassed comment=("Oyalo:".$VoucherName)
            } else={
                /ip hotspot ip-binding set $BindingIds type=bypassed disabled=no comment=("Oyalo:".$VoucherName)
            }
        }

        :if ($commandType = "REMOVE_MAC") do={
            :local MacAddress ($payload->"mac")
            # Do not remove an unrelated binding that was created manually.
            :foreach BindingId in=[/ip hotspot ip-binding find where mac-address=$MacAddress] do={
                :local BindingComment [/ip hotspot ip-binding get $BindingId comment]
                :if ([:pick $BindingComment 0 6] = "Oyalo:") do={
                    /ip hotspot ip-binding remove $BindingId
                }
            }
        }

        :if ($commandType = "DISABLE_USER") do={
            :local VoucherName ($payload->"username")
            :local UserIds [/ip hotspot user find where name=$VoucherName]
            :if ([:len $UserIds] > 0) do={ /ip hotspot user set $UserIds disabled=yes }
            :local ActiveIds [/ip hotspot active find where user=$VoucherName]
            :if ([:len $ActiveIds] > 0) do={ /ip hotspot active remove $ActiveIds }
        }

        :if ($commandType = "REMOVE_USER") do={
            :local VoucherName ($payload->"username")
            :local ActiveIds [/ip hotspot active find where user=$VoucherName]
            :if ([:len $ActiveIds] > 0) do={ /ip hotspot active remove $ActiveIds }
            :local UserIds [/ip hotspot user find where name=$VoucherName]
            :if ([:len $UserIds] > 0) do={ /ip hotspot user remove $UserIds }
        }
    } do={
        :set commandStatus "failed"
        :log error ("Oyalo command ".$commandId." failed: ".$commandError)
    }

    :local ackData ("{\"status\":\"".$commandStatus."\"}")
    :onerror ackError in={
        /tool fetch url=($baseUrl."/api/router/commands/".$commandId."/ack") http-method=post \
            http-header-field=($authHeaders.",Content-Type: application/json") \
            http-data=$ackData output=none check-certificate=yes
    } do={
        :log warning ("Oyalo acknowledgement failed for command ".$commandId.": ".$ackError)
    }
}
