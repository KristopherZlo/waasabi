<?php

use App\Models\ContentReport;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\TopbarPromo;
use App\Models\User;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ProfileBadgeController;
use App\Http\Controllers\ProfileFollowController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReadLaterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Services\AutoModerationService;
use App\Services\BadgePayloadService;
use App\Services\BadgeCatalogService;
use App\Services\ContentModerationService;
use App\Services\FeedService;
use App\Services\ImageUploadService;
use App\Services\VisibilityService;
use App\Services\MakerPromotionService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\TopbarPromoService;
use App\Services\UserSlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Route::get('/promos/{promo}/click', function (TopbarPromo $promo) {
    if (safeHasColumn('topbar_promos', 'clicks_count')) {
        TopbarPromo::query()
            ->where('id', $promo->id)
            ->update(['clicks_count' => DB::raw('clicks_count + 1')]);
    }
    return redirect()->away($promo->url);
})->name('promos.click');

Route::get('/feed/chunk', function (Request $request) use ($feed_projects, $feed_questions, $feed_page_size, $useDbFeed, $normalizeFeedFilter, $applyFeedFilter) {
    $stream = $request->query('stream', 'projects');
    $stream = in_array($stream, ['projects', 'questions'], true) ? $stream : 'projects';
    $offset = max(0, (int) $request->query('offset', 0));
    $limit = (int) $request->query('limit', $feed_page_size);
    $limit = max(1, min(20, $limit));
    $filter = $normalizeFeedFilter($request->query('filter'));

    if ($useDbFeed) {
        $result = FeedService::buildFeedChunk($stream, $offset, $limit, Auth::user(), $filter);
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

    $source = $stream === 'questions' ? $feed_questions : $feed_projects;
    if ($filter !== 'all') {
        $source = $applyFeedFilter($source, $filter);
    }
    $slice = array_slice($source, $offset, $limit);
    $items = array_map(static function (array $item) {
        return view('partials.feed-item', ['item' => $item])->render();
    }, $slice);

    $nextOffset = $offset + count($slice);
    $total = count($source);

    return response()->json([
        'items' => $items,
        'next_offset' => $nextOffset,
        'has_more' => $nextOffset < $total,
        'total' => $total,
        'stream' => $stream,
    ]);
})->name('feed.chunk');

Route::get('/', function (Request $request) use ($feed_items_page, $feed_tags, $qa_threads, $top_projects, $reading_now, $feed_projects_total, $feed_questions_total, $feed_projects_offset, $feed_questions_offset, $feed_page_size, $useDbFeed, $feed_projects, $feed_questions, $normalizeFeedFilter, $applyFeedFilter, $sortFeedItems, $sortFeedByScore) {
    $current_user = app(\App\Services\UserPayloadService::class)->currentUserPayload();
    $feedItems = $feed_items_page;
    $projectsTotal = $feed_projects_total;
    $questionsTotal = $feed_questions_total;
    $projectsOffset = $feed_projects_offset;
    $questionsOffset = $feed_questions_offset;
    $filter = $normalizeFeedFilter($request->query('filter'));
    if ($useDbFeed) {
        $feedData = FeedService::buildInitialFeed(Auth::user(), $feed_page_size, $filter);
        $feedItems = $feedData['items'];
        $projectsTotal = $feedData['projects_total'];
        $questionsTotal = $feedData['questions_total'];
        $projectsOffset = $feedData['projects_offset'];
        $questionsOffset = $feedData['questions_offset'];
    } else {
        $filteredProjects = $applyFeedFilter($feed_projects, $filter);
        $filteredQuestions = $applyFeedFilter($feed_questions, $filter);
        $projectsTotal = count($filteredProjects);
        $questionsTotal = count($filteredQuestions);
        $projectsPage = array_slice($filteredProjects, 0, $feed_page_size);
        $questionsPage = array_slice($filteredQuestions, 0, $feed_page_size);
        $projectsOffset = count($projectsPage);
        $questionsOffset = count($questionsPage);
        $feedItems = array_merge($projectsPage, $questionsPage);
        $sorter = $filter === 'best' ? $sortFeedByScore : $sortFeedItems;
        usort($feedItems, $sorter);
    }

    $subscriptions = [];
    $viewer = Auth::user();
    if ($viewer && safeHasTable('user_follows') && safeHasTable('users')) {
        $followRows = DB::table('user_follows')
            ->join('users', 'user_follows.following_id', '=', 'users.id')
            ->where('user_follows.follower_id', $viewer->id)
            ->orderBy('users.name')
            ->limit(6)
            ->get(['users.id', 'users.name', 'users.slug']);
        foreach ($followRows as $row) {
            $slug = $row->slug ?? Str::slug((string) ($row->name ?? ''));
            if ($slug === '') {
                $slug = 'user-' . $row->id;
            }
            $count = safeHasTable('posts')
                ? DB::table('posts')->where('user_id', $row->id)->count()
                : 0;
            $subscriptions[] = [
                'name' => $row->name,
                'slug' => $slug,
                'count' => (int) $count,
            ];
        }
    }

    return view('feed', [
        'feed_items' => $feedItems,
        'feed_tags' => $feed_tags,
        'current_user' => $current_user,
        'qa_threads' => $qa_threads,
        'top_projects' => $top_projects,
        'reading_now' => $reading_now,
        'subscriptions' => $subscriptions,
        'feed_projects_total' => $projectsTotal,
        'feed_questions_total' => $questionsTotal,
        'feed_projects_offset' => $projectsOffset,
        'feed_questions_offset' => $questionsOffset,
        'feed_page_size' => $feed_page_size,
    ]);
})->name('feed');

