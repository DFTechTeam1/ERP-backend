@extends('addon::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('addon.name') !!}</p>
@endsection
