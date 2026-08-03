@extends('superadmin.dashboard')
@section('content')
<h2>Router Commands</h2>
<table border="1" cellpadding="5">
<tr><th>ID</th><th>Router</th><th>Type</th><th>Payload</th><th>Status</th><th>Created</th></tr>
@foreach($commands as $c)
<tr>
<td>{{ $c->id }}</td>
<td>{{ $c->router?->router_id }}</td>
<td>{{ $c->command_type }}</td>
<td><pre>{{ json_encode($c->payload) }}</pre></td>
<td>{{ $c->status }}</td>
<td>{{ $c->created_at }}</td>
</tr>
@endforeach
</table>
@endsection
<form method="GET" class="mb-2"><input name="q" placeholder="Search..." value="{{ request("q") }}" class="bg-slate-800 border border-slate-700 rounded p-2 text-white"></form>
