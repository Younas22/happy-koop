<?php

declare(strict_types=1);

use App\Services\Settings\StoreCurrency;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time data fix: products, shipping rates and fixed coupons created before
 * the store currency was changed still carry the old code (seeded as USD), so
 * a EUR store showed "$" prices, refused those products at checkout and found
 * no shipping rate. From now on the settings save keeps them in step; this
 * brings existing rows to the current store currency.
 *
 * Amounts are left as entered. Orders are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(StoreCurrency::class)->syncCatalog();
    }

    public function down(): void
    {
        // Not reversible: the previous per-row currency is not recorded, and
        // a mixed-currency catalog was the bug.
    }
};
