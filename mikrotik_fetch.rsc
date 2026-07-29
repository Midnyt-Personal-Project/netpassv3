/tool fetch url="http://netpassv3.test/api/router/data" http-method=get http-header-field="X-Router-ID: RTR-000001,X-Router-Token: oyalo_demo_token_east_legon_xyz" dst-path=data.json
:put [file get data.json contents]
