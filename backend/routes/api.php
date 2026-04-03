<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\InquiryController as AdminInquiryController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Api\V1\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Artisan\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\CustomerQuoteController;
use App\Http\Controllers\Api\V1\InquiryController;
use App\Http\Controllers\Api\V1\OrderProgressController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('catalog/categories', [CatalogController::class, 'categories']);
    Route::get('catalog/products', [CatalogController::class, 'products']);
    Route::get('catalog/products/{product}', [CatalogController::class, 'product']);

    Route::get('pricing/gold/current', [PricingController::class, 'goldCurrent']);
    Route::get('pricing/fx/current', [PricingController::class, 'fxCurrent']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::post('uploads/presign', [UploadController::class, 'presign']);
        Route::post('uploads', [UploadController::class, 'store']);

        Route::middleware('role:customer')->group(function () {
            Route::post('inquiries', [InquiryController::class, 'store']);
            Route::get('inquiries/mine', [InquiryController::class, 'mine']);
            Route::get('inquiries/{inquiry}', [InquiryController::class, 'show']);

            Route::get('quotes/mine', [CustomerQuoteController::class, 'mine']);
            Route::get('quotes/{quote}', [CustomerQuoteController::class, 'show']);

            Route::get('orders/mine', [CustomerOrderController::class, 'mine']);
            Route::get('orders/{order}', [CustomerOrderController::class, 'show']);
        });

        Route::post('conversations/{inquiry}/firebase-bootstrap', [ConversationController::class, 'firebaseBootstrap']);

        Route::get('orders/{order}/progress', [OrderProgressController::class, 'index']);

        Route::middleware('role:artisan')->group(function () {
            Route::get('artisan/assignments', [AssignmentController::class, 'index']);
            Route::get('artisan/assignments/{assignment}', [AssignmentController::class, 'show']);
            Route::patch('artisan/assignments/{assignment}', [AssignmentController::class, 'update']);
            Route::post('artisan/orders/{order}/progress', [OrderProgressController::class, 'store']);
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('admin/users', [AdminUserController::class, 'index']);
            Route::post('admin/users', [AdminUserController::class, 'store']);
            Route::patch('admin/users/{user}', [AdminUserController::class, 'update']);

            Route::post('admin/categories', [AdminCategoryController::class, 'store']);
            Route::patch('admin/categories/{category}', [AdminCategoryController::class, 'update']);
            Route::delete('admin/categories/{category}', [AdminCategoryController::class, 'destroy']);

            Route::post('admin/products', [AdminProductController::class, 'store']);
            Route::patch('admin/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('admin/products/{product}', [AdminProductController::class, 'destroy']);
            Route::post('admin/products/{product}/images', [AdminProductController::class, 'storeImage']);
            Route::delete('admin/product-images/{product_image}', [AdminProductImageController::class, 'destroy']);

            Route::get('admin/inquiries', [AdminInquiryController::class, 'index']);
            Route::get('admin/inquiries/{inquiry}', [AdminInquiryController::class, 'show']);
            Route::patch('admin/inquiries/{inquiry}', [AdminInquiryController::class, 'update']);

            Route::get('admin/quotes', [AdminQuoteController::class, 'index']);
            Route::post('admin/quotes', [AdminQuoteController::class, 'store']);
            Route::get('admin/quotes/{quote}', [AdminQuoteController::class, 'show']);
            Route::patch('admin/quotes/{quote}', [AdminQuoteController::class, 'update']);

            Route::get('admin/orders', [AdminOrderController::class, 'index']);
            Route::post('admin/orders', [AdminOrderController::class, 'store']);
            Route::get('admin/orders/{order}', [AdminOrderController::class, 'show']);
            Route::patch('admin/orders/{order}', [AdminOrderController::class, 'update']);
            Route::post('admin/orders/{order}/assignments', [AdminOrderController::class, 'storeAssignment']);
            Route::post('admin/orders/{order}/progress', [OrderProgressController::class, 'store']);

            Route::get('pricing/gold/history', [PricingController::class, 'goldHistory']);
            Route::post('pricing/gold', [PricingController::class, 'storeGold']);
            Route::get('pricing/fx/history', [PricingController::class, 'fxHistory']);
            Route::post('pricing/fx', [PricingController::class, 'storeFx']);

            Route::get('admin/dashboard/summary', [DashboardController::class, 'summary']);
            Route::get('admin/reports/orders', [ReportController::class, 'orders']);
        });
    });
});
