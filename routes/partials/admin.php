<?php

use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminPromoController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::post('/admin/users/{user}/role', [AdminUserController::class, 'updateRole'])
        ->name('admin.users.role');

    Route::delete('/admin/comments/{comment}', [AdminContentController::class, 'deleteComment'])
        ->name('admin.comments.delete');

    Route::delete('/admin/reviews/{review}', [AdminContentController::class, 'deleteReview'])
        ->name('admin.reviews.delete');

    Route::delete('/admin/posts/{post}', [AdminContentController::class, 'deletePost'])
        ->name('admin.posts.delete');

    Route::post('/admin/promos', [AdminPromoController::class, 'store'])->name('admin.promos.store');
    Route::put('/admin/promos/{promo}', [AdminPromoController::class, 'update'])->name('admin.promos.update');
    Route::delete('/admin/promos/{promo}', [AdminPromoController::class, 'destroy'])->name('admin.promos.delete');
});
