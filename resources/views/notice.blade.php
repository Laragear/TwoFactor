@extends('two-factor::layout')

@section('card-body')
    <p class="text-center">
        {{ trans('two-factor::messages.enable') }}
    </p>
    @isset($url)
    <div class="col-auto mb-3">
        <a href="{{ $url }}" class="btn btn-primary btn-lg">
            {{ trans('two-factor::messages.switch_on') }} &raquo;
        </a>
    </div>
    @endisset
@endsection
