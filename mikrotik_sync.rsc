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
    :local commandScript ($command->"script")
    :local commandStatus "completed"

    :onerror commandError in={
        :if ([:len $commandScript] = 0) do={
            :error ("Missing script for command ".$commandId)
        }
        :local scriptFn [:parse $commandScript]
        $scriptFn
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
