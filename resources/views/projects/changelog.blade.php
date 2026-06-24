@extends('layouts.canvas')

@section('title','Changelog - '. $project->name.' - '.config('app.name', 'Laravel'))

@section('content')

<pre>
        @php print_r($project) @endphp
    </pre>

@endsection