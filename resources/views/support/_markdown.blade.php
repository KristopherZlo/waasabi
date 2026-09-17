@php
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Str;

    $markdownPath = isset($path) ? base_path($path) : null;
    abort_unless($markdownPath && File::exists($markdownPath), 404);
    $markdown = File::get($markdownPath);
    $html = Str::markdown($markdown, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
@endphp

<article class="legal-content legal-content--markdown support-document__content">
    {!! $html !!}
</article>
