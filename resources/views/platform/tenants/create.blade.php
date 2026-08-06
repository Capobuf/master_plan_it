@extends('layouts.app')
@section('content')
    <x-common.page-breadcrumb page-title="Create tenant" />
    @include('platform.tenants._form')
@endsection
