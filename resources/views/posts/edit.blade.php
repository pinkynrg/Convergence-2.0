@extends('layouts.default')
@section('content')

	{!! Form::model($post, array('method' => 'PATCH', 'route' => array('posts.update',$post->id), 'id' => 'post_form')) !!}

		@include('posts.form')

		{!! Form::BSSubmit("Submit",['bclass' => 'col-xs-12']) !!}

	{!! Form::close() !!}

@endsection