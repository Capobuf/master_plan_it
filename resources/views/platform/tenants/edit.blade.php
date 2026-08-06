@extends('layouts.app')
@section('content')
    <x-common.page-breadcrumb page-title="Edit tenant" />
    @include('platform.tenants._form', ['record' => $record])
@endsection
