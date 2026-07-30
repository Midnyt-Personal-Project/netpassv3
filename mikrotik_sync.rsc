/tool fetch url="https://wifi.oyalo.net/api/router/commands" http-method=get http-header-field=("X-Router-ID: RTR-000001,X-Router-Token: oyalo_demo_token_east_legon_xyz") dst-path=commands.json check-certificate=no
:local resp [file get commands.json contents]

# For each command id found, ack after processing
:local idStart [:find $resp "\"id\":" $pos]
# ... (simplified: ack by extracting id from response)
/tool fetch url=("https://wifi.oyalo.net/api/router/commands/".$cmdId."/ack") http-method=post http-header-field=("X-Router-ID: RTR-000001,X-Router-Token: oyalo_demo_token_east_legon_xyz,Content-Type: application/json") http-data=("{\"status\":\"completed\"}") dst-path=ack.json check-certificate=no
