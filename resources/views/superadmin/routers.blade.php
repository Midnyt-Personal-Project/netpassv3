@extends('superadmin.dashboard')
@section('content')
<h2>Routers</h2>
<table border="1" cellpadding="5">
<tr><th>ID</th><th>Name</th><th>Location</th><th>Status</th><th>Last Heartbeat</th><th>Router ID</th><th>Token</th></tr>
@foreach($routers as $r)
<tr>
<td>{{ $r->id }}</td>
<td>{{ $r->name }}</td>
<td>{{ $r->location?->name }}</td>
<td>{{ $r->status }}</td>
<td>{{ $r->last_heartbeat }}</td>
<td><input type="text" value="{{ $r->router_id }}" onclick="this.select()"></td>
<td><input type="text" value="{{ $r->api_token }}" onclick="this.select()"></td>
</tr>
@endforeach
</table>
@endsection
<form method="GET" class="mb-2"><input name="q" placeholder="Search..." value="{{ request("q") }}" class="bg-slate-800 border border-slate-700 rounded p-2 text-white"></form>
