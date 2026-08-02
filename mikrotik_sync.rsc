# Oyalo command sync for RouterOS 7
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

:local scriptContent ($fetchResult->"data")
:if ([:len $scriptContent] > 0) do={
    :onerror runError in={
        :local runScript [:parse $scriptContent]
        $runScript
    } do={
        :log error ("Oyalo script execution failed: ".$runError)
    }
}
