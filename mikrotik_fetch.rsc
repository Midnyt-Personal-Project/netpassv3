# Minimal RouterOS 7 API connectivity test.
# Replace the URL, router ID, and token before running it.
:local baseUrl "https://wifi.oyalo.net"
:local routerId "RTR-REPLACE-ME"
:local routerToken "oyalo_REPLACE_ME"
:local headers ("X-Router-ID: ".$routerId.",X-Router-Token: ".$routerToken)

:local result [/tool fetch url=($baseUrl."/api/router/data") http-method=get \
    http-header-field=$headers output=user as-value check-certificate=yes]
:put ($result->"data")
