@extends('layouts.app')
@section('title','Modifica contratto')
@section('content')
@include('operational.contracts.form',['method'=>'PUT','action'=>route('operational.contracts.update',$contract['id']),'heading'=>'Modifica contratto'])
@endsection
