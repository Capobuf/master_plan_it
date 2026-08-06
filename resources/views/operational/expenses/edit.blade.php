@extends('layouts.app')
@section('title','Modifica spesa')
@section('content')
    @include('operational.expenses.form', ['method' => 'PUT', 'action' => route('operational.expenses.update', $expense['id']), 'heading' => 'Modifica spesa'])
@endsection
