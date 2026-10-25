<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * CMS pages and the homepage composition.
 *
 * Every seeded page carries several paragraphs of obviously-placeholder copy,
 * so the storefront has real content to lay out and an administrator can see
 * the shape of a finished page. Each body says plainly that it is placeholder
 * text: nothing here claims a delivery time, a returns window or a legal
 * position the store has not actually adopted.
 */
class CmsSeeder extends Seeder
{
    /**
     * Marker every seeded body carries, so a later run can tell its own
     * placeholder text apart from copy someone has written.
     */
    private const PLACEHOLDER_MARKER = 'This page is placeholder content';

    public function run(): void
    {
        foreach ($this->pages() as $page) {
            /** @var Page|null $existing */
            $existing = Page::query()->where('slug', $page['slug'])->first();

            if ($existing === null) {
                Page::query()->create([
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'excerpt' => $page['excerpt'],
                    'is_published' => true,
                    'published_at' => now(),
                    'seo_noindex' => false,
                ]);

                continue;
            }

            // An existing page is refreshed only while it still holds seeded
            // placeholder text, or nothing at all. Anything an administrator
            // has actually written is left exactly as it is: re-seeding a
            // development database must never eat real copy.
            if (! $this->isUntouched($existing->body)) {
                continue;
            }

            $existing->update([
                'body' => $page['body'],
                'excerpt' => $existing->excerpt ?? $page['excerpt'],
            ]);
        }

        if (HomeSection::query()->count() === 0) {
            HomeSection::query()->create([
                'type' => HomeSection::TYPE_HERO,
                'heading' => null,
                'config' => [
                    'headline' => 'Everyday things, thoughtfully chosen.',
                    'subheadline' => 'This is sample homepage content. Edit it in the admin panel.',
                    'ctaLabel' => 'Shop now',
                    'ctaHref' => '/shop',
                ],
                'sort_order' => 0,
                'is_active' => true,
            ]);

            HomeSection::query()->create([
                'type' => HomeSection::TYPE_CATEGORY_TILES,
                'heading' => 'Browse',
                'config' => [
                    'categoryIds' => Category::query()->active()->orderBy('sort_order')->pluck('id')->all(),
                ],
                'sort_order' => 1,
                'is_active' => true,
            ]);

            HomeSection::query()->create([
                'type' => HomeSection::TYPE_PRODUCT_GRID,
                'heading' => 'Sample products',
                'config' => [
                    'productIds' => Product::query()->published()->orderBy('id')->pluck('id')->all(),
                    'limit' => 8,
                ],
                'sort_order' => 2,
                'is_active' => true,
            ]);

            HomeSection::query()->create([
                'type' => HomeSection::TYPE_BENEFITS,
                'heading' => null,
                'config' => [
                    'items' => [
                        [
                            'icon' => 'truck',
                            'title' => 'Shipping as configured',
                            'body' => 'Rates and delivery windows come from your shipping settings.',
                        ],
                        [
                            'icon' => 'arrow-path',
                            'title' => 'Returns window',
                            'body' => 'Set your real returns window per product in the admin panel.',
                        ],
                        [
                            'icon' => 'lock-closed',
                            'title' => 'Payments stay server-side',
                            'body' => 'Card details are handled by your payment provider, never by this store.',
                        ],
                    ],
                ],
                'sort_order' => 3,
                'is_active' => true,
            ]);
        }

        $this->command?->info('CMS pages and homepage sections ensured.');
    }

    private function isUntouched(?string $body): bool
    {
        $body = trim((string) $body);

        return $body === ''
            || str_contains($body, self::PLACEHOLDER_MARKER)
            // The marker written by the first version of this seeder.
            || str_contains($body, 'This page is a placeholder.');
    }

    /**
     * The seeded pages.
     *
     * @return list<array{slug: string, title: string, excerpt: string, body: string}>
     */
    private function pages(): array
    {
        $notice = '<p><strong>'.self::PLACEHOLDER_MARKER
            .'.</strong> Replace it in the admin panel under Pages before launch. '
            .'It is not a legal document and makes no commitment on your behalf.</p>';

        return [
            [
                'slug' => 'about',
                'title' => 'About',
                'excerpt' => 'Who we are, what we sell and how we work - placeholder copy.',
                'body' => $notice
                    .'<p>We are a small team that picks everyday objects we would use ourselves, '
                    .'then gets them to your door without fuss. This paragraph exists to show how '
                    .'an introduction reads on this page; write your own story in its place.</p>'
                    .'<h2>What we sell</h2>'
                    .'<p>Describe your range here: the categories you focus on, the materials you '
                    .'prefer, and the kind of customer you are buying for. Two or three sentences '
                    .'is usually enough before a visitor moves on to the shop.</p>'
                    .'<h2>How we work</h2>'
                    .'<ul>'
                    .'<li>We buy in small batches and restock what sells.</li>'
                    .'<li>We list real measurements and materials on every product.</li>'
                    .'<li>We answer messages ourselves, during business hours.</li>'
                    .'</ul>'
                    .'<h2>Questions</h2>'
                    .'<p>Point people at your contact page, and say roughly how long a reply '
                    .'takes. Do not promise a response time you cannot keep.</p>',
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'excerpt' => 'How to reach us, and what to include in your message.',
                'body' => $notice
                    .'<p>Use this page to tell people the quickest way to reach you. The address, '
                    .'email and phone number in the site footer come from your store settings, so '
                    .'set them there rather than typing them twice.</p>'
                    .'<h2>Before you write</h2>'
                    .'<p>Including these saves a round trip:</p>'
                    .'<ul>'
                    .'<li>Your order number, if the message is about an order.</li>'
                    .'<li>The product name or SKU, if it is about an item.</li>'
                    .'<li>A photograph, if something arrived damaged.</li>'
                    .'</ul>'
                    .'<h2>Opening hours</h2>'
                    .'<p>State the days and hours you actually read messages, and the timezone. '
                    .'Replace this paragraph with your own, and leave out any claim about reply '
                    .'times you are not confident about.</p>',
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy policy',
                'excerpt' => 'What data this store collects, and what it is used for.',
                'body' => $notice
                    .'<p>A privacy policy has to describe what your store really does, so this is '
                    .'a skeleton to fill in - not a policy you can adopt as it stands. Have it '
                    .'reviewed before you publish it.</p>'
                    .'<h2>What we collect</h2>'
                    .'<p>List what you actually store: the name, email and address needed to ship '
                    .'an order, the contents of that order, and whatever your analytics and '
                    .'payment providers record. Card details are handled by your payment provider '
                    .'and never reach this store.</p>'
                    .'<h2>Why we collect it</h2>'
                    .'<ul>'
                    .'<li>To take payment for an order and deliver it.</li>'
                    .'<li>To answer questions about an order.</li>'
                    .'<li>To meet accounting and tax obligations.</li>'
                    .'</ul>'
                    .'<h2>Who it is shared with</h2>'
                    .'<p>Name your payment provider, your carrier and any other processor that '
                    .'sees customer data, and say what each one receives.</p>'
                    .'<h2>Your choices</h2>'
                    .'<p>Explain how someone asks for a copy of their data or asks you to delete '
                    .'it, and how long you keep order records.</p>',
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of service',
                'excerpt' => 'The terms that apply when you buy from this store.',
                'body' => $notice
                    .'<p>These headings are the usual shape of a terms page. The wording under '
                    .'each one has to match how your store actually operates, and should be '
                    .'checked by someone qualified before launch.</p>'
                    .'<h2>Using this store</h2>'
                    .'<p>Say who may place an order, and what happens to an account that abuses '
                    .'the store.</p>'
                    .'<h2>Orders and prices</h2>'
                    .'<p>Explain when a contract is formed, what happens if an item turns out to '
                    .'be unavailable after an order is placed, and how you handle a pricing '
                    .'error. Prices and taxes shown at checkout come from your store settings.</p>'
                    .'<h2>Cancellations and returns</h2>'
                    .'<p>Summarise your returns page here and link to it, rather than stating a '
                    .'second, slightly different window.</p>'
                    .'<h2>Liability</h2>'
                    .'<p>Set out the limits that apply in your jurisdiction. Do not copy another '
                    .'store&rsquo;s clause: the one that applies to you depends on where you '
                    .'trade.</p>',
            ],
            [
                'slug' => 'shipping',
                'title' => 'Shipping',
                'excerpt' => 'Where we ship, how long dispatch takes, and how rates are worked out.',
                'body' => $notice
                    .'<p>Rates and delivery options at checkout come from your shipping zones and '
                    .'rates in the admin panel. Use this page to explain them in words, and keep '
                    .'the two consistent.</p>'
                    .'<h2>Where we ship</h2>'
                    .'<p>List the countries or regions you ship to, and any you deliberately do '
                    .'not. If a zone has its own rules - a remote-area surcharge, for example - '
                    .'say so here.</p>'
                    .'<h2>Dispatch times</h2>'
                    .'<ul>'
                    .'<li>Orders placed on a working day before your cut-off leave the same day.</li>'
                    .'<li>Orders placed later, or at a weekend, leave the next working day.</li>'
                    .'<li>Public holidays add a day.</li>'
                    .'</ul>'
                    .'<h2>Delivery estimates</h2>'
                    .'<p>Give the carrier&rsquo;s own estimate per zone, and label it as an '
                    .'estimate. A delivery date you cannot control should not be written as a '
                    .'promise.</p>'
                    .'<h2>Tracking</h2>'
                    .'<p>Explain when a tracking number is sent, and where to find it.</p>',
            ],
            [
                'slug' => 'returns',
                'title' => 'Returns',
                'excerpt' => 'How to return an item, and how refunds are handled.',
                'body' => $notice
                    .'<p>Set out the window you really offer, and who pays return postage. '
                    .'Anything below is an example, not your policy.</p>'
                    .'<h2>Return window</h2>'
                    .'<p>State how many days after delivery a return can be started, and the '
                    .'condition an item has to come back in. Note anything that cannot be '
                    .'returned, and why.</p>'
                    .'<h2>Starting a return</h2>'
                    .'<ul>'
                    .'<li>Message us with your order number and the item you want to return.</li>'
                    .'<li>We reply with the return address and any paperwork needed.</li>'
                    .'<li>Send the item back in packaging that will survive the trip.</li>'
                    .'</ul>'
                    .'<h2>Refunds</h2>'
                    .'<p>Say how long a refund takes once the item arrives, and that it goes back '
                    .'to the original payment method. Refunds are issued through your payment '
                    .'provider, so part of that wait is theirs.</p>'
                    .'<h2>Damaged or wrong items</h2>'
                    .'<p>Explain what to do if something arrives broken or is not what was '
                    .'ordered, including whether you need a photograph.</p>',
            ],
        ];
    }
}
