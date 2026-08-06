@extends('layouts.app')
@section('title','Nuova spesa')
@section('content')
    @include('operational.expenses.form', ['method' => 'POST', 'action' => route('operational.expenses.store'), 'heading' => 'Nuova spesa'])
@endsection
