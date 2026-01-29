<?php

namespace App\Http\Controllers;

use App\Services\DemoContentService;
use App\Services\FeedViewService;
use App\Services\UserPayloadService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(
        private DemoContentService $demoContent,
        private FeedViewService $feedView,
        private UserPayloadService $payloadService
    ) {
    }

    public function index(Request $request)
    {
        $projects = $this->demoContent->projects();
        $questions = $this->demoContent->questions();

        $data = $this->feedView->buildPageData(
            $request->user(),
            $projects,
            $questions,
            $request->query('filter')
        );

        $data['current_user'] = $this->payloadService->currentUserPayload();
        $data['subscriptions'] = $this->feedView->buildSubscriptions($request->user());

        return view('feed', $data);
    }

    public function chunk(Request $request)
    {
        $projects = $this->demoContent->projects();
        $questions = $this->demoContent->questions();
        $stream = (string) $request->query('stream', 'projects');
        $offset = (int) $request->query('offset', 0);
        $limit = (int) $request->query('limit', 10);

        $result = $this->feedView->buildChunkData(
            $request->user(),
            $projects,
            $questions,
            $stream,
            $offset,
            $limit,
            $request->query('filter')
        );

        $items = array_map(static function (array $item) {
            return view('partials.feed-item', ['item' => $item])->render();
        }, $result['items']);

        return response()->json([
            'items' => $items,
            'next_offset' => $result['next_offset'],
            'has_more' => $result['has_more'],
            'total' => $result['total'],
            'stream' => $result['stream'],
        ]);
    }
}
