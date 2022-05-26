@extends('two-factor::layout')

@section('card-body')
    <form method="post">
        @csrf
        <p class="text-center">
            {{ trans('two-factor::messages.continue') }}
        </p>
        <div class="form-row justify-content-center py-3">
            @if($errors->isNotEmpty())
                <div class="col-12 alert alert-danger pb-0">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="col-sm-8 col-8 mb-3">
                <input type="text" name="{{ $input }}" id="{{ $input }}"
                       class="@error($input) is-invalid @enderror form-control form-control-lg"
                       minlength="6" placeholder="123456" required>
            </div>
            <div class="w-100"></div>
            <div class="col-auto mb-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    {{ trans('two-factor::messages.confirm') }}
                </button>
            </div>
        </div>
    </form>
@endsection
