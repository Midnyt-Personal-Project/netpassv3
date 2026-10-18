:local routerId "RTR-238605"
:local routerToken "oyalo_26lWGsL0vqStZ2YEOQu9PKaznlhZcGCQq1tj5mKqrZBpQ4yx"

# Get router information
:local identity [/system identity get name]
:local version [/system resource get version]
:local board [/system resource get board-name]
:local uptime [/system resource get uptime]

# Build POST data
:local postData ("identity=" . $identity . \
"&version=" . $version . \
"&board=" . $board . \
"&uptime=" . $uptime)

# Send heartbeat
/tool fetch \
url="https://honneycole.oyalo.net/api/router/heartbeat" \
http-method=post \
http-header-field=("X-Router-ID: " . $routerId . ",X-Router-Token: " . $routerToken . ",Content-Type: application/x-www-form-urlencoded") \
http-data=$postData \
keep-result=no

:log info "Heartbeat sent."