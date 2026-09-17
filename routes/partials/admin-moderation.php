<?php

use App\Http\Controllers\Admin\AdminCollaborationController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminMediaController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:moderate'])->group(function () {
    Route::get('/admin/journal', [JournalController::class, 'moderation'])->name('journal.moderation');
    Route::patch('/admin/journal/{projectUpdate}', [JournalController::class, 'moderate'])->name('journal.moderate');
    Route::get('/admin', [CommunityPageController::class, 'moderation'])->name('admin');
    Route::get('/admin/tools', AdminDashboardController::class)->name('admin.tools');

    Route::post('/admin/content/bulk', [AdminContentController::class, 'bulk'])
        ->name('admin.content.bulk');
    Route::post('/admin/collaborations/bulk', [AdminCollaborationController::class, 'bulk'])
        ->name('admin.collaborations.bulk');
    Route::delete('/admin/collaboration-applications/{application}', [AdminCollaborationController::class, 'destroyApplication'])
        ->name('admin.collaboration-applications.delete');
    Route::delete('/admin/collaboration-comments/{comment}', [AdminCollaborationController::class, 'destroyComment'])
        ->name('admin.collaboration-comments.delete');
    Route::post('/admin/collaborations/{collaboration}/reports/dismiss', [AdminCollaborationController::class, 'dismissRequestReport'])
        ->name('admin.collaborations.reports.dismiss');
    Route::post('/admin/collaboration-comments/{comment}/reports/dismiss', [AdminCollaborationController::class, 'dismissCommentReport'])
        ->name('admin.collaboration-comments.reports.dismiss');

    Route::post('/admin/support-tickets/{ticket}/respond', [AdminSupportController::class, 'respond'])
        ->name('admin.support-tickets.respond');

    Route::post('/admin/media/{report}/resolve', [AdminMediaController::class, 'resolve'])
        ->name('admin.media.resolve');

    Route::post('/admin/users/{user}/ban', [AdminUserController::class, 'toggleBan'])
        ->name('admin.users.ban');

    Route::post('/admin/moderation/posts/{post}/queue', [ModerationController::class, 'queuePost'])
        ->name('moderation.posts.queue');

    Route::post('/admin/moderation/posts/{post}/hide', [ModerationController::class, 'hidePost'])
        ->name('moderation.posts.hide');

    Route::post('/admin/moderation/posts/{post}/restore', [ModerationController::class, 'restorePost'])
        ->name('moderation.posts.restore');

    Route::post('/admin/moderation/posts/{post}/nsfw', [ModerationController::class, 'nsfwPost'])
        ->name('moderation.posts.nsfw');

    Route::post('/admin/moderation/reports/{type}/{id}/dismiss', [ModerationController::class, 'dismissReport'])
        ->whereIn('type', ['post', 'comment', 'review'])
        ->whereNumber('id')
        ->name('moderation.reports.dismiss');

    Route::post('/admin/moderation/comments/{comment}/queue', [ModerationController::class, 'queueComment'])
        ->name('moderation.comments.queue');

    Route::post('/admin/moderation/comments/{comment}/hide', [ModerationController::class, 'hideComment'])
        ->name('moderation.comments.hide');

    Route::post('/admin/moderation/comments/{comment}/restore', [ModerationController::class, 'restoreComment'])
        ->name('moderation.comments.restore');

    Route::post('/admin/moderation/reviews/{review}/queue', [ModerationController::class, 'queueReview'])
        ->name('moderation.reviews.queue');

    Route::post('/admin/moderation/reviews/{review}/hide', [ModerationController::class, 'hideReview'])
        ->name('moderation.reviews.hide');

    Route::post('/admin/moderation/reviews/{review}/restore', [ModerationController::class, 'restoreReview'])
        ->name('moderation.reviews.restore');

});
