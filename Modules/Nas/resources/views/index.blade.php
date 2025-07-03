@extends('nas::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('nas.name') !!}</p>
@endsection
