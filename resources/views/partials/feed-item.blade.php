@if (($item['type'] ?? '') === 'project')
    @include('partials.project-card', ['project' => $item['data']])
@elseif (($item['type'] ?? '') === 'question')
    @include('partials.question-card', ['question' => $item['data']])
@endif
