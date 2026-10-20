<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Ten room-specific listings of the same bamboo drawer divider set.
 *
 * Every specification below is taken from the supplier's published product
 * attributes: bamboo, lacquer finish, rectangular, 43.5 x 6 x 1.5 cm, pack of
 * four, spring-loaded and extendable with anti-slip end pads, made in Fujian,
 * 1.5 kg gross in a 50 x 10 x 10 cm carton.
 *
 * Deliberately NOT carried over from the supplier listing, because none of it
 * is HappyKoop store data:
 *   - wholesale/MOQ tier pricing (EUR 1.18 per piece at 500-1000, etc.)
 *   - "8618 sold" and "#14 Featured Sales in Drawer Organizers"
 *   - the "FSC certified" badge - an unverified certification claim
 *   - supplier reviews, supplier customisation capability, OEM/ODM terms
 *     and sample lead times, which are buyer-to-supplier facts
 *
 * RETAIL PRICES ARE PLACEHOLDERS chosen for the demo. They are not derived
 * from the supplier's wholesale tiers and must be replaced with real retail
 * pricing before launch.
 */
class DrawerDividerCatalogSeeder extends Seeder
{
    private const GALLERY = [
        'divider-set-of-4.jpg',
        'divider-install-anti-slip.jpg',
        'divider-kitchen-drawer.jpg',
        'divider-utensil-before-after.jpg',
        'divider-clothing-drawer.jpg',
        'divider-vanity-drawer.jpg',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DrawerDividerCatalogSeeder skipped in production.');

            return;
        }

        $categoryIds = $this->ensureCategories();
        $created = 0;

        foreach ($this->products() as $index => $data) {
            $product = Product::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'brand' => 'LEGEND',
                    'short_description' => $data['short'],
                    'description' => $data['description'],
                    'status' => Product::STATUS_PUBLISHED,
                    'price_amount' => $data['price'],
                    // No compare_at_amount anywhere: inventing a "was" price
                    // would be a fake discount.
                    'compare_at_amount' => null,
                    'currency' => 'USD',
                    'inventory_policy' => Product::POLICY_TRACK,
                    'stock_quantity' => $data['stock'],
                    'low_stock_threshold' => $data['lowStock'],
                    // Shipping carton, from the supplier's packaging data.
                    'weight_grams' => 1500,
                    'length_mm' => 500,
                    'width_mm' => 100,
                    'height_mm' => 100,
                    'value_props' => $this->valueProps(),
                    'whats_included' => ['4 x bamboo drawer dividers'],
                    'shipping_summary' => 'Ships flat in a 50 x 10 x 10 cm carton, 1.5 kg gross.',
                    'returns_summary' => 'Unused items can be returned within 30 days.',
                    'warranty_text' => null,
                    'returns_window_days' => 30,
                    'badges' => [],
                    'seo_title' => null,
                    'seo_description' => $data['short'],
                    'seo_noindex' => false,
                    'published_at' => now(),
                ]
            );

            $product->categories()->syncWithoutDetaching([$categoryIds[$data['category']]]);

            $this->attachMedia($product, $data['primary']);
            $this->attachSpecifications($product, $data['useLabel']);
            $this->attachFaqs($product);

            $created++;
        }

        $this->linkRelatedProducts();

        $this->command?->info("Drawer divider catalogue ensured ({$created} listings across 10 categories).");
        $this->command?->warn('Retail prices are PLACEHOLDERS - set real pricing before launch.');
    }

    /** @return array<string, int> slug => id */
    private function ensureCategories(): array
    {
        $categories = [
            ['name' => 'Kitchen', 'slug' => 'kitchen', 'image' => 'divider-kitchen-drawer.jpg'],
            ['name' => 'Bedroom', 'slug' => 'bedroom', 'image' => 'divider-clothing-drawer.jpg'],
            ['name' => 'Bathroom', 'slug' => 'bathroom', 'image' => null],
            ['name' => 'Home office', 'slug' => 'home-office', 'image' => null],
            ['name' => 'Closet & wardrobe', 'slug' => 'closet-wardrobe', 'image' => 'divider-clothing-drawer.jpg'],
            ['name' => 'Garage & tools', 'slug' => 'garage-tools', 'image' => null],
            ['name' => 'Living room', 'slug' => 'living-room', 'image' => null],
            ['name' => 'Craft & hobby', 'slug' => 'craft-hobby', 'image' => null],
            ['name' => 'Beauty & vanity', 'slug' => 'beauty-vanity', 'image' => 'divider-vanity-drawer.jpg'],
            ['name' => 'Kids room', 'slug' => 'kids-room', 'image' => null],
        ];

        $ids = [];

        foreach ($categories as $order => $category) {
            $model = Category::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                    'sort_order' => $order + 1,
                    'description' => null,
                ]
            );

            if ($category['image'] !== null && $model->image_media_id === null) {
                $media = MediaLibrarySeeder::find($category['image']);

                if ($media !== null) {
                    $model->forceFill(['image_media_id' => $media->id])->save();
                }
            }

            $ids[$category['slug']] = $model->id;
        }

        return $ids;
    }

    /**
     * Each listing leads with the image that best shows that room, then the
     * rest of the shared gallery.
     *
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'slug' => 'bamboo-drawer-dividers-kitchen-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Kitchen - Set of 4',
                'sku' => 'HK-BDD-KIT-01',
                'category' => 'kitchen',
                'primary' => 'divider-kitchen-drawer.jpg',
                'price' => 2290,
                'stock' => 86,
                'lowStock' => 10,
                'useLabel' => 'Cutlery, utensils, chopsticks and spice packets',
                'short' => 'Set of four spring-loaded bamboo dividers that split a kitchen drawer into tidy sections.',
                'description' => '<p>Four adjustable bamboo dividers that turn one deep kitchen drawer into separate lanes for cutlery, cooking utensils and spice packets. Each divider is spring-loaded, so it presses against the drawer walls and holds its position without screws or adhesive.</p><p>The bamboo is finished with a clear lacquer and the ends carry anti-slip pads, so the dividers stay put as the drawer opens and closes.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-bedroom-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Bedroom - Set of 4',
                'sku' => 'HK-BDD-BED-01',
                'category' => 'bedroom',
                'primary' => 'divider-clothing-drawer.jpg',
                'price' => 2190,
                'stock' => 54,
                'lowStock' => 10,
                'useLabel' => 'Folded clothing, socks and underwear',
                'short' => 'Set of four extendable bamboo dividers for keeping folded clothing in neat columns.',
                'description' => '<p>Keep folded clothing standing upright instead of collapsing into a pile. These four bamboo dividers expand to fit the drawer and create separate columns for socks, underwear and folded tops.</p><p>Because the fit is spring-loaded, you can re-space the compartments whenever the contents change.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-bathroom-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Bathroom - Set of 4',
                'sku' => 'HK-BDD-BAT-01',
                'category' => 'bathroom',
                'primary' => 'divider-install-anti-slip.jpg',
                'price' => 1990,
                'stock' => 7,
                'lowStock' => 10,
                'useLabel' => 'Toiletries, brushes and small bottles',
                'short' => 'Set of four bamboo dividers for sorting toiletries and small bottles in a bathroom drawer.',
                'description' => '<p>Bathroom drawers fill up with small items that slide around. These four bamboo dividers hold toiletries, brushes and bottles in defined sections so nothing rolls to the back.</p><p>Bamboo with a lacquer finish wipes clean. Keep them out of standing water and dry any splashes rather than soaking them.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-desk-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Desk Drawers - Set of 4',
                'sku' => 'HK-BDD-OFF-01',
                'category' => 'home-office',
                'primary' => 'divider-set-of-4.jpg',
                'price' => 2090,
                'stock' => 120,
                'lowStock' => 15,
                'useLabel' => 'Pens, cables, notebooks and chargers',
                'short' => 'Set of four adjustable bamboo dividers for separating stationery and cables in a desk drawer.',
                'description' => '<p>Stop a desk drawer turning into a single tangle of pens, cables and sticky notes. Four bamboo dividers create lanes you can size to whatever you keep in there.</p><p>No tools and no adhesive: each divider is held by spring tension alone, so a rented desk is left exactly as you found it.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-wardrobe-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Wardrobes - Set of 4',
                'sku' => 'HK-BDD-CLO-01',
                'category' => 'closet-wardrobe',
                'primary' => 'divider-clothing-drawer.jpg',
                'price' => 2390,
                'stock' => 31,
                'lowStock' => 10,
                'useLabel' => 'Accessories, scarves and folded knitwear',
                'short' => 'Set of four bamboo dividers that section wardrobe drawers for accessories and knitwear.',
                'description' => '<p>Wardrobe drawers are usually wider than kitchen drawers, which is where adjustable dividers earn their place. These four expand across the drawer and hold scarves, belts and folded knitwear in separate bays.</p><p>Each divider measures 43.5 x 6 x 1.5 cm and is finished in natural bamboo.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-workshop-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Workshop Drawers - Set of 4',
                'sku' => 'HK-BDD-GAR-01',
                'category' => 'garage-tools',
                'primary' => 'divider-utensil-before-after.jpg',
                'price' => 2490,
                'stock' => 0,
                'lowStock' => 10,
                'useLabel' => 'Hand tools, fixings and cable ties',
                'short' => 'Set of four bamboo dividers for sorting hand tools and fixings in workshop drawers.',
                'description' => '<p>Sort a workbench drawer into lanes for screwdrivers, pliers, fixings and cable ties. The dividers are 1.5 cm thick bamboo and sit on anti-slip pads, so they hold their spacing when the drawer is pulled hard.</p><p>Bamboo is a timber product: it suits dry tool storage rather than damp or oily environments.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-sideboard-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Sideboards - Set of 4',
                'sku' => 'HK-BDD-LIV-01',
                'category' => 'living-room',
                'primary' => 'divider-install-anti-slip.jpg',
                'price' => 2190,
                'stock' => 44,
                'lowStock' => 10,
                'useLabel' => 'Remotes, chargers, headphones and cables',
                'short' => 'Set of four bamboo dividers for organising remotes, cables and headphones in a sideboard.',
                'description' => '<p>A living-room drawer collects remotes, chargers and headphone cables. These four bamboo dividers give each of them a lane, so the drawer stops being a single knot of wires.</p><p>They install by hand in seconds and can be repositioned whenever you like.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-craft-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Craft Supplies - Set of 4',
                'sku' => 'HK-BDD-CRA-01',
                'category' => 'craft-hobby',
                'primary' => 'divider-vanity-drawer.jpg',
                'price' => 1890,
                'stock' => 63,
                'lowStock' => 10,
                'useLabel' => 'Threads, beads, brushes and small tools',
                'short' => 'Set of four bamboo dividers for separating craft supplies and small tools by type.',
                'description' => '<p>Craft storage works best when every material has its own lane. These four adjustable bamboo dividers separate threads, beads, brushes and小 tools so you can see the whole drawer at a glance.</p><p>Re-space them as projects change - nothing is fixed in place.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-vanity-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Vanity Drawers - Set of 4',
                'sku' => 'HK-BDD-VAN-01',
                'category' => 'beauty-vanity',
                'primary' => 'divider-vanity-drawer.jpg',
                'price' => 2090,
                'stock' => 72,
                'lowStock' => 10,
                'useLabel' => 'Cosmetics, brushes and palettes',
                'short' => 'Set of four bamboo dividers that sort cosmetics, brushes and palettes into sections.',
                'description' => '<p>Lay palettes flat in one bay and stand lipsticks and brushes in another. Four bamboo dividers split a vanity drawer into sections sized to what you actually own.</p><p>The natural bamboo finish is left unpainted, so it suits most bedroom and bathroom furniture.</p>',
            ],
            [
                'slug' => 'bamboo-drawer-dividers-kids-room-set-of-4',
                'name' => 'Bamboo Drawer Dividers for Kids Rooms - Set of 4',
                'sku' => 'HK-BDD-KID-01',
                'category' => 'kids-room',
                'primary' => 'divider-clothing-drawer.jpg',
                'price' => 1990,
                'stock' => 58,
                'lowStock' => 10,
                'useLabel' => 'Small clothing, socks and toy pieces',
                'short' => 'Set of four bamboo dividers for keeping small clothes and toy pieces separated.',
                'description' => '<p>Small clothes disappear into big drawers. These four bamboo dividers make narrower compartments that are easier for a child to use and to put back.</p><p>They are held by spring tension rather than fixings. Fit them in a drawer an adult controls, and check the tension periodically.</p>',
            ],
        ];
    }

    /** @return list<array<string, string>> */
    private function valueProps(): array
    {
        return [
            ['icon' => 'leaf', 'title' => 'Bamboo, not plastic', 'body' => 'Solid bamboo with a clear lacquer finish.'],
            ['icon' => 'move-horizontal', 'title' => 'Spring-loaded fit', 'body' => 'Extends against the drawer walls, so no screws or adhesive are needed.'],
            ['icon' => 'grip-horizontal', 'title' => 'Anti-slip ends', 'body' => 'Padded ends help each divider hold its position as the drawer moves.'],
        ];
    }

    private function attachMedia(Product $product, string $primaryFilename): void
    {
        if ($product->media()->count() > 0) {
            return;
        }

        // Lead with the image that shows this listing's room, then the rest.
        $ordered = array_values(array_unique(array_merge([$primaryFilename], self::GALLERY)));
        $sort = 0;
        $primaryId = null;

        foreach ($ordered as $filename) {
            $media = MediaLibrarySeeder::find($filename);

            if ($media === null) {
                continue;
            }

            $product->media()->attach($media->id, ['sort_order' => $sort]);
            $primaryId ??= $media->id;
            $sort++;
        }

        if ($primaryId !== null) {
            $product->forceFill(['primary_media_id' => $primaryId])->save();
        }
    }

    private function attachSpecifications(Product $product, string $useLabel): void
    {
        if ($product->specifications()->count() > 0) {
            return;
        }

        $specifications = [
            ['group' => 'Dimensions', 'label' => 'Length', 'value' => '43.5', 'unit' => 'cm'],
            ['group' => 'Dimensions', 'label' => 'Height', 'value' => '6', 'unit' => 'cm'],
            ['group' => 'Dimensions', 'label' => 'Thickness', 'value' => '1.5', 'unit' => 'cm'],
            ['group' => 'Dimensions', 'label' => 'Dimensional tolerance', 'value' => 'Under +/- 1 mm on thickness', 'unit' => null],

            ['group' => 'Materials & care', 'label' => 'Material', 'value' => 'Bamboo', 'unit' => null],
            ['group' => 'Materials & care', 'label' => 'Finish', 'value' => 'Lacquer', 'unit' => null],
            ['group' => 'Materials & care', 'label' => 'Colour', 'value' => 'Natural', 'unit' => null],
            ['group' => 'Materials & care', 'label' => 'Care', 'value' => 'Wipe clean with a damp cloth. Do not soak.', 'unit' => null],

            ['group' => 'Design & fit', 'label' => 'Shape', 'value' => 'Rectangle', 'unit' => null],
            ['group' => 'Design & fit', 'label' => 'Adjustment', 'value' => 'Spring-loaded, extendable', 'unit' => null],
            ['group' => 'Design & fit', 'label' => 'Installation', 'value' => 'Standing, no tools or adhesive required', 'unit' => null],
            ['group' => 'Design & fit', 'label' => 'End pads', 'value' => 'Anti-slip', 'unit' => null],
            ['group' => 'Design & fit', 'label' => 'Suited to', 'value' => $useLabel, 'unit' => null],

            ['group' => 'In the box', 'label' => 'Pack contents', 'value' => '4 dividers', 'unit' => null],

            ['group' => 'Shipping', 'label' => 'Carton size', 'value' => '50 x 10 x 10', 'unit' => 'cm'],
            ['group' => 'Shipping', 'label' => 'Gross weight', 'value' => '1.5', 'unit' => 'kg'],

            ['group' => 'Product details', 'label' => 'Brand', 'value' => 'LEGEND', 'unit' => null],
            ['group' => 'Product details', 'label' => 'Model', 'value' => 'LG-1178', 'unit' => null],
            ['group' => 'Product details', 'label' => 'Country of origin', 'value' => 'Fujian, China', 'unit' => null],
        ];

        foreach ($specifications as $index => $spec) {
            $product->specifications()->create($spec + ['sort_order' => $index]);
        }
    }

    private function attachFaqs(Product $product): void
    {
        if ($product->faqs()->count() > 0) {
            return;
        }

        // Every answer is grounded in the specification table above. No
        // delivery promises, no certification claims.
        $faqs = [
            [
                'question' => 'How many dividers come in a pack?',
                'answer' => 'Four. Each one measures 43.5 x 6 x 1.5 cm.',
            ],
            [
                'question' => 'Will they fit my drawer?',
                'answer' => 'The dividers are spring-loaded and extend to press against the drawer walls. Measure the internal width of your drawer before ordering and compare it against the 43.5 cm length so you know the range you need.',
            ],
            [
                'question' => 'Do I need screws or adhesive?',
                'answer' => 'No. They are held by spring tension and sit on anti-slip end pads, so nothing is fixed to the drawer.',
            ],
            [
                'question' => 'What are they made of?',
                'answer' => 'Bamboo with a clear lacquer finish, in a natural colour.',
            ],
            [
                'question' => 'How do I clean them?',
                'answer' => 'Wipe with a damp cloth and dry. Do not soak them or put them in a dishwasher.',
            ],
        ];

        foreach ($faqs as $index => $faq) {
            $product->faqs()->create($faq + ['sort_order' => $index]);
        }
    }

    /** Cross-links the ten room listings so "related products" is real. */
    private function linkRelatedProducts(): void
    {
        $products = Product::query()
            ->where('sku', 'like', 'HK-BDD-%')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            if ($product->relatedProducts()->count() > 0) {
                continue;
            }

            $others = $products->where('id', '!=', $product->id)->take(4)->values();

            foreach ($others as $index => $other) {
                $product->relatedProducts()->attach($other->id, ['sort_order' => $index]);
            }
        }
    }
}
