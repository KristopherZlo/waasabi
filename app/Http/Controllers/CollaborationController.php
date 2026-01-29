<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollaborationRequest;
use App\Models\Post;
use App\Services\CollaborationService;
use App\Services\UserPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollaborationController extends Controller
{
    public function __construct(
        private CollaborationService $collaboration,
        private UserPayloadService $payloadService
    ) {
    }

    public function index(Request $request)
    {
        $data = $this->collaboration->buildPageData($request->user());
        $data['current_user'] = $this->payloadService->currentUserPayload();

        return view('collaboration', $data);
    }

    public function store(StoreCollaborationRequest $request)
    {
        if (honeypotTripped($request)) {
            return back()->withErrors([
                'title' => __('ui.auth.captcha_failed'),
            ])->withInput();
        }

        $data = $request->validated();
        $roleKey = $data['role'];
        $availabilityKey = $data['availability'];
        $formatKey = $data['format'];

        $roles = $this->collaboration->roleOptions();
        $availability = $this->collaboration->availabilityOptions();
        $formats = $this->collaboration->formatOptions();

        if (!array_key_exists($roleKey, $roles)) {
            return back()->withErrors(['role' => __('validation.in', ['attribute' => 'role'])])->withInput();
        }
        if (!array_key_exists($availabilityKey, $availability)) {
            return back()->withErrors(['availability' => __('validation.in', ['attribute' => 'availability'])])->withInput();
        }
        if (!array_key_exists($formatKey, $formats)) {
            return back()->withErrors(['format' => __('validation.in', ['attribute' => 'format'])])->withInput();
        }

        $roleLabel = $roles[$roleKey];
        $availabilityLabel = $availability[$availabilityKey];
        $formatLabel = $formats[$formatKey];

        $skills = $this->parseSkills($data['skills'] ?? '');
        $tags = $this->collaboration->buildTagsForRequest($roleKey, $availabilityKey, $formatKey, $skills);

        $body = $this->collaboration->formatBody(
            $roleLabel,
            $availabilityLabel,
            $formatLabel,
            $skills,
            $data['summary'],
            $data['contact'] ?? null,
        );

        $title = $data['title'];
        $subtitle = 'Looking for: ' . $roleLabel;
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'collaboration-' . Str::random(6);
        }
        $baseSlug = $slug;
        $counter = 2;
        while (Post::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter += 1;
        }

        $post = new Post();
        $post->user_id = $request->user()->id;
        $post->type = 'post';
        $post->slug = $slug;
        $post->title = $title;
        $post->subtitle = $subtitle;
        $post->body_markdown = $body;
        $post->body_html = null;
        $post->status = 'in_progress';
        $post->tags = $tags;
        $post->read_time_minutes = max(1, (int) ceil(str_word_count($body) / 200));
        $post->save();

        return redirect()->route('collaboration')->with('toast', __('ui.collaboration.posted'));
    }

    private function parseSkills(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $skills = collect(explode(',', $raw))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->map(fn ($value) => Str::limit($value, 24, ''))
            ->filter()
            ->take(6)
            ->values()
            ->all();

        return $skills;
    }
}
