<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Account\AccountOrderController;
use App\Http\Controllers\Api\V1\Account\AccountReviewController;
use App\Http\Controllers\Api\V1\Account\AddressController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\HomeSectionController;
use App\Http\Controllers\Api\V1\Admin\MediaController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\V1\Admin\PaymentSettingsController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductRelationsController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\StoreSettingsController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Payments\PayPalPaymentController;
use App\Http\Controllers\Api\V1\Payments\StripePaymentController;
use App\Http\Controllers\Api\V1\Public\CartController;
use App\Http\Controllers\Api\V1\Public\CategoryController;
use App\Http\Controllers\Api\V1\Public\CheckoutController;
use App\Http\Controllers\Api\V1\Public\HomeController;
use App\Http\Controllers\Api\V1\Public\OrderLookupController;
use App\Http\Controllers\Api\V1\Public\PageController;
use App\Http\Controllers\Api\V1\Public\ProductController;
use App\Http\Controllers\Api\V1\Public\ProductReviewController;
use App\Http\Controllers\Api\V1\Public\ReviewController;
use App\Http\Controllers\Api\V1\Public\RuntimeConfigController;
use App\Http\Controllers\Api\V1\Public\ShippingController;
use App\Http\Controllers\Api\V1\Webhooks\PayPalWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HappyKoop API v1
|--------------------------------------------------------------------------
|
| Route table for docs/api-contract.md. Throttles use the named limiters
| registered in HappyKoopServiceProvider.
|
*/

Route::prefix('v1')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Webhooks — no auth, CSRF-exempt, excluded from CORS
    |----------------------------------------------------------------------
    */
    Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function (): void {
        Route::post('stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
        Route::post('paypal', [PayPalWebhookController::class, 'handle'])->name('webhooks.paypal');
    });

    /*
    |----------------------------------------------------------------------
    | Authentication
    |----------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login');

        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:auth-forgot-password');

        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:auth-reset-password');

        Route::post('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:auth-email-verify'])
            ->name('verification.verify');

        Route::middleware(['auth:sanctum', 'abilities:customer'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])
                ->middleware('throttle:auth-logout');

            Route::post('logout-all', [AuthController::class, 'logoutAll'])
                ->middleware('throttle:auth-logout-all');

            Route::get('me', [AuthController::class, 'me'])
                ->middleware('throttle:auth-me');

            Route::post('email/resend', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:auth-email-resend');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Public storefront
    |----------------------------------------------------------------------
    */
    Route::middleware('throttle:public-read')->group(function (): void {
        Route::get('public/runtime-config', [RuntimeConfigController::class, 'show']);

        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{slug}', [CategoryController::class, 'show']);

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{slug}', [ProductController::class, 'show']);
        Route::get('products/{slug}/reviews', [ProductReviewController::class, 'index']);
        Route::get('products/{slug}/related', [ProductController::class, 'related']);

        Route::get('pages/{slug}', [PageController::class, 'show']);
        Route::get('home', [HomeController::class, 'index']);

        Route::get('shipping/options', [ShippingController::class, 'options']);

        Route::get('orders/by-number/{orderNumber}', [OrderLookupController::class, 'show']);
    });

    Route::post('cart/validate', [CartController::class, 'validateCart'])
        ->middleware('throttle:cart-validate');

    Route::post('checkout/initialize', [CheckoutController::class, 'initialize'])
        ->middleware('throttle:checkout-initialize');

    Route::post('reviews', [ReviewController::class, 'store'])
        ->middleware(['auth:sanctum', 'abilities:customer', 'throttle:reviews-submit']);

    /*
    |----------------------------------------------------------------------
    | Payments (guest token or bearer)
    |----------------------------------------------------------------------
    */
    Route::prefix('payments')->middleware('throttle:payments')->group(function (): void {
        Route::post('stripe/intent', [StripePaymentController::class, 'intent']);
        Route::post('stripe/checkout-session', [StripePaymentController::class, 'checkoutSession']);
        Route::post('paypal/order', [PayPalPaymentController::class, 'createOrder']);
        Route::post('paypal/capture', [PayPalPaymentController::class, 'capture']);
    });

    /*
    |----------------------------------------------------------------------
    | Customer account — every lookup scoped by user_id
    |----------------------------------------------------------------------
    */
    Route::prefix('account')
        ->middleware(['auth:sanctum', 'abilities:customer', 'throttle:account'])
        ->group(function (): void {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::patch('profile', [ProfileController::class, 'update']);
            Route::put('password', [ProfileController::class, 'updatePassword']);

            Route::get('orders', [AccountOrderController::class, 'index']);
            Route::get('orders/{orderNumber}', [AccountOrderController::class, 'show']);

            Route::get('addresses', [AddressController::class, 'index']);
            Route::post('addresses', [AddressController::class, 'store']);
            Route::patch('addresses/{id}', [AddressController::class, 'update'])->whereNumber('id');
            Route::delete('addresses/{id}', [AddressController::class, 'destroy'])->whereNumber('id');
            Route::post('addresses/{id}/default', [AddressController::class, 'setDefault'])->whereNumber('id');

            Route::get('reviews', [AccountReviewController::class, 'index']);
        });

    /*
    |----------------------------------------------------------------------
    | Admin
    |
    | Three independent layers: Sanctum authentication, the `admin` token
    | ability, and the EnsureUserIsAdmin role check. Each controller action
    | additionally authorizes through a Policy.
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'abilities:admin', 'admin', 'throttle:admin'])
        ->group(function (): void {
            Route::get('dashboard', [DashboardController::class, 'index']);

            // --- catalog ---------------------------------------------------
            Route::get('products', [AdminProductController::class, 'index']);
            Route::post('products', [AdminProductController::class, 'store']);
            Route::get('products/{id}', [AdminProductController::class, 'show'])->whereNumber('id');
            Route::patch('products/{id}', [AdminProductController::class, 'update'])->whereNumber('id');
            Route::delete('products/{id}', [AdminProductController::class, 'destroy'])->whereNumber('id');

            Route::post('products/{id}/media', [AdminProductController::class, 'attachMedia'])->whereNumber('id');
            Route::patch('products/{id}/media/reorder', [AdminProductController::class, 'reorderMedia'])->whereNumber('id');

            Route::put('products/{id}/variants', [ProductRelationsController::class, 'replaceVariants'])->whereNumber('id');
            Route::put('products/{id}/specifications', [ProductRelationsController::class, 'replaceSpecifications'])->whereNumber('id');
            Route::put('products/{id}/faqs', [ProductRelationsController::class, 'replaceFaqs'])->whereNumber('id');
            Route::put('products/{id}/related', [ProductRelationsController::class, 'replaceRelated'])->whereNumber('id');

            Route::get('categories', [AdminCategoryController::class, 'index']);
            Route::post('categories', [AdminCategoryController::class, 'store']);
            Route::patch('categories/{id}', [AdminCategoryController::class, 'update'])->whereNumber('id');
            Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy'])->whereNumber('id');

            // --- orders ----------------------------------------------------
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{id}', [AdminOrderController::class, 'show'])->whereNumber('id');
            Route::patch('orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('id');
            Route::patch('orders/{id}/fulfilment', [AdminOrderController::class, 'updateFulfilment'])->whereNumber('id');
            Route::post('orders/{id}/refund', [AdminOrderController::class, 'refund'])->whereNumber('id');

            // --- customers & reviews --------------------------------------
            Route::get('customers', [CustomerController::class, 'index']);
            Route::get('customers/{id}', [CustomerController::class, 'show'])->whereNumber('id');

            Route::get('reviews', [AdminReviewController::class, 'index']);
            Route::patch('reviews/{id}', [AdminReviewController::class, 'update'])->whereNumber('id');

            // --- content ---------------------------------------------------
            Route::get('pages', [AdminPageController::class, 'index']);
            Route::post('pages', [AdminPageController::class, 'store']);
            Route::get('pages/{id}', [AdminPageController::class, 'show'])->whereNumber('id');
            Route::patch('pages/{id}', [AdminPageController::class, 'update'])->whereNumber('id');
            Route::delete('pages/{id}', [AdminPageController::class, 'destroy'])->whereNumber('id');

            Route::get('home-sections', [HomeSectionController::class, 'index']);
            Route::put('home-sections', [HomeSectionController::class, 'replace']);

            // --- media -----------------------------------------------------
            Route::get('media', [MediaController::class, 'index']);
            Route::post('media', [MediaController::class, 'store'])
                ->middleware('throttle:admin-media-upload');
            Route::delete('media/{id}', [MediaController::class, 'destroy'])->whereNumber('id');

            // --- settings --------------------------------------------------
            Route::middleware('throttle:admin-settings')->group(function (): void {
                Route::get('settings/store', [StoreSettingsController::class, 'show']);
                Route::put('settings/store', [StoreSettingsController::class, 'update']);

                Route::get('settings/payments', [PaymentSettingsController::class, 'show']);
                Route::put('settings/payments', [PaymentSettingsController::class, 'update']);
                Route::post('settings/payments/test', [PaymentSettingsController::class, 'test']);
            });

            // --- users & audit ---------------------------------------------
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::get('users/{id}', [AdminUserController::class, 'show'])->whereNumber('id');
            Route::patch('users/{id}', [AdminUserController::class, 'update'])->whereNumber('id');
            Route::delete('users/{id}', [AdminUserController::class, 'destroy'])->whereNumber('id');

            Route::get('audit-logs', [AuditLogController::class, 'index']);
        });
});
