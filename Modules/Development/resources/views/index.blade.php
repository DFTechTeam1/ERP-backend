@extends('development::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('development.name') !!}</p>
@endsection
