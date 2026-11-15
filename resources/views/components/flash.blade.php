@if (session('status'))
    <div class="nr-success mb-5">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="nr-error mb-5">
        <p class="font-semibold">{{ __('Please review the following problems:') }}</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
