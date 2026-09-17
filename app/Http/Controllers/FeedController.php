<?php

namespace App\Http\Controllers;

use App\Services\CollaborationService;
use App\Services\FeedViewService;
use App\Services\UserPayloadService;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(
        private FeedViewService $feedView,
        private CollaborationService $collaboration,
        private UserPayloadService $payloadService
    ) {}

    public function index(Request $request)
    {
        $viewer = $request->user();
        $stream = in_array($request->query('stream'), ['questions', 'collaboration'], true)
            ? $request->query('stream')
            : 'projects';

        if ($stream === 'collaboration') {
            return view('feed', array_merge($this->feedView->buildSidebarData($viewer), [
                'active_stream' => $stream,
                'collaboration_requests' => $this->collaboration->requests($request->only([
                    'status', 'role', 'availability', 'format', 'q',
                ]), $viewer),
                'collaboration_roles' => $this->collaboration->roleOptions(),
                'collaboration_availability' => $this->collaboration->availabilityOptions(),
                'collaboration_formats' => $this->collaboration->formatOptions(),
                'current_user' => $this->payloadService->currentUserPayload(),
            ]));
        }

        $data = $this->feedView->buildPageData(
            $viewer,
            $request->query('filter'),
            $request->query('tags'),
            $request->query('exclude'),
        );

        $data = array_merge($data, $this->feedView->buildSidebarData($viewer));
        $data['active_stream'] = $stream;
        $data['current_user'] = $this->payloadService->currentUserPayload();

        return view('feed', $data);
    }

    public function chunk(Request $request)
    {
        $stream = (string) $request->query('stream', 'projects');
        $offset = (int) $request->query('offset', 0);
        $limit = (int) $request->query('limit', 10);

        $result = $this->feedView->buildChunkData(
            $request->user(),
            $stream,
            $offset,
            $limit,
            $request->query('filter'),
            $request->query('tags'),
            $request->query('exclude'),
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
