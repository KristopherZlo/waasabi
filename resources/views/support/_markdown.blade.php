@php
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Str;

    $markdownPath = isset($path) ? base_path($path) : null;
    $markdown = ($markdownPath && File::exists($markdownPath))
        ? File::get($markdownPath)
        : '# Document not found' . PHP_EOL . PHP_EOL . 'Please contact support.';
    $html = Str::markdown($markdown, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
@endphp

<article class="legal-content legal-content--markdown support-document__content">
    {!! $html !!}
</article>
