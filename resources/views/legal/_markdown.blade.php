@php
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Str;

    $markdownPath = isset($path) ? base_path($path) : null;
    $markdown = ($markdownPath && File::exists($markdownPath))
        ? File::get($markdownPath)
        : '# Document not found' . PHP_EOL . PHP_EOL . 'Please contact zloydeveloper.info@gmail.com.';
    $html = Str::markdown($markdown, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ]);
@endphp

<section class="section legal-section">
    <article class="legal-content legal-content--markdown">
        {!! $html !!}
    </article>
</section>
