@extends('layouts.default')
@section('content')

	{!! Form::model($company, array('method' => 'PATCH', 'route' => array('companies.update',$company->id), 'files' => true)) !!}

		@include('companies.form')

		{!! Form::BSSubmit("Submit") !!}

	{!! Form::close() !!}

@endsection