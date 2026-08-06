@extends('layouts.app')
@section('title','Nuovo contratto')
@section('content')
@include('operational.contracts.form',['method'=>'POST','action'=>route('operational.contracts.store'),'heading'=>'Nuovo contratto'])
@endsection
