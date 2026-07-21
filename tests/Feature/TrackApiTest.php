<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = 'test-tracker-key-' . Str::random(16);
    config(['tracker.api_key_hash' => bcrypt($this->apiKey)]);
});

function trackPayload(string $sessionId, array $events, array $sessionMeta = []): array
{
    return [
        'session' => array_merge(['session_id' => $sessionId], $sessionMeta),
        'events' => $events,
    ];
}

test('track endpoint accepts valid batch and returns accepted ids', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'category_code' => 'WOM',
        'page_url' => 'https://enorsia.com/c/women',
        'referer' => 'https://enorsia.com/',
        'start_time' => '2026-07-13T06:47:51.183Z',
        'end_time' => '2026-07-13T06:48:21.183Z',
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk()
        ->assertJson(['accepted_ids' => [$eventId]]);

    expect(ActivityEcomUser::where('session_id', $sessionId)->exists())->toBeTrue();
    expect(ActivityEcomUserAction::where('event_id', $eventId)->exists())->toBeTrue();
});

test('track endpoint rejects invalid api key', function () {
    $response = $this->postJson('/api/track', trackPayload(Str::uuid()->toString(), [[
        'id' => Str::uuid()->toString(),
        'session_id' => Str::uuid()->toString(),
        'action_type' => 'category_view',
    ]]), [
        'Authorization' => 'Bearer wrong-key',
    ]);

    $response->assertUnauthorized();
});

test('duplicate event id is deduped and still accepted', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $event = [
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'product_code' => 'GS123',
    ];

    $headers = ['Authorization' => 'Bearer ' . $this->apiKey];

    $this->postJson('/api/track', trackPayload($sessionId, [$event]), $headers)->assertOk();
    $this->postJson('/api/track', trackPayload($sessionId, [$event]), $headers)
        ->assertOk()
        ->assertJson(['accepted_ids' => [$eventId]]);

    expect(ActivityEcomUserAction::where('event_id', $eventId)->count())->toBe(1);
});

test('track endpoint stores visitor id and session duration', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'Europe/London'));
    config(['tracker.visitor_timezone' => 'Europe/London', 'tracker.session_gap_minutes' => 30]);

    $sessionId = Str::uuid()->toString();
    $visitorId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();
    $headers = ['Authorization' => 'Bearer ' . $this->apiKey];

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'category_code' => 'WOM',
    ]], [
        'visitor_id' => $visitorId,
    ]), $headers)->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-07-16 12:05:00', 'Europe/London'));

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'category_code' => 'WOM',
    ]], [
        'visitor_id' => $visitorId,
    ]), $headers)->assertOk();

    $session = ActivityEcomUser::query()
        ->where('visitor_id', $visitorId)
        ->orderByDesc('last_active_at')
        ->first();

    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(1);
    expect($session)->not->toBeNull();
    expect($session->visitor_id)->toBe($visitorId);
    expect($session->session_duration_seconds)->toBe(300);

    Carbon::setTestNow();
});

test('track endpoint applies manager session gap for same visitor', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 10:00:00', 'Europe/London'));
    config(['tracker.visitor_timezone' => 'Europe/London', 'tracker.session_gap_minutes' => 30]);
    Cache::flush();

    $sessionId = Str::uuid()->toString();
    $visitorId = Str::uuid()->toString();
    $headers = ['Authorization' => 'Bearer ' . $this->apiKey];

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'category_code' => 'WOM',
    ]], [
        'visitor_id' => $visitorId,
    ]), $headers)->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'Europe/London'));

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'category_code' => 'WOM',
    ]], [
        'visitor_id' => $visitorId,
    ]), $headers)->assertOk();

    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(2);
    expect(\App\Models\ActivityEcomDailyVisitor::where('visitor_id', $visitorId)->count())->toBe(1);

    Carbon::setTestNow();
});

test('track endpoint accepts product view popup', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'product_view_popup',
        'page_url' => 'http://store.test/women',
        'referer' => 'http://store.test/women',
        'product_name' => 'Dress',
        'product_code' => 'GS123',
        'product_color_id' => '42',
        'general_color_name' => 'Brown',
        'product_price' => 29.99,
        'start_time' => now()->toIso8601String(),
        'end_time' => now()->addSeconds(5)->toIso8601String(),
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk()
        ->assertJson(['accepted_ids' => [$eventId]]);

    $action = ActivityEcomUserAction::where('event_id', $eventId)->first();

    expect($action->action_type)->toBe('product_view_popup');
    expect($action->product_name)->toBe('Dress');
    expect($action->general_color_name)->toBe('Brown');
});

test('track endpoint accepts add to cart with color and size details', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'product_name' => 'Dress',
        'product_code' => 'GS123',
        'product_color_id' => '42',
        'general_color_name' => 'Brown',
        'add_to_cart' => [
            'qty' => 1,
            'unit_price' => 29.99,
            'cart_total' => 29.99,
            'product_id' => '101',
            'color_id' => '42',
            'color_name' => 'Brown',
            'size_id' => '7',
            'size_name' => 'M',
            'items' => [[
                'product_id' => '101',
                'product_code' => 'GS123-M',
                'qty' => 1,
                'price' => 29.99,
                'color_id' => '42',
                'color_name' => 'Brown',
                'size_id' => '7',
                'size_name' => 'M',
            ]],
        ],
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk()
        ->assertJson(['accepted_ids' => [$eventId]]);

    $action = ActivityEcomUserAction::where('event_id', $eventId)->first();

    expect($action->add_to_cart['color_id'])->toBe('42');
    expect($action->add_to_cart['product_id'])->toBe('101');
    expect($action->add_to_cart['size_name'])->toBe('M');
});

test('track endpoint accepts begin checkout with product line details', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'begin_checkout',
        'begin_checkout' => [
            'cart_total' => 59.98,
            'coupon_code' => 'SAVE10',
            'cart_items' => [[
                'product_id' => '101',
                'product_code' => 'GS123-M',
                'product_name' => 'Dress',
                'qty' => 2,
                'price' => 29.99,
                'color_id' => '42',
                'color_name' => 'Brown',
                'size_id' => '7',
                'size_name' => 'M',
            ]],
        ],
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk();

    $action = ActivityEcomUserAction::where('event_id', $eventId)->first();

    expect($action->begin_checkout['cart_items'][0]['product_id'])->toBe('101');
    expect($action->begin_checkout['cart_items'][0]['color_name'])->toBe('Brown');
});

test('track endpoint accepts long product color slug codes', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();
    $longSlug = str_repeat('a', 120);

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'product_code' => 'SKU-1',
        'product_color_id' => '42',
        'product_color_code' => $longSlug,
        'general_color_name' => 'Navy',
        'product_price' => 29.99,
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk();

    $action = ActivityEcomUserAction::where('event_id', $eventId)->first();

    expect($action->product_color_code)->toBe($longSlug);
    expect(strlen($action->product_color_code))->toBe(120);
});

test('track endpoint accepts proceed checkout with product line details', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'proceed_checkout',
        'proceed_to_checkout' => [
            'cart_total' => 59.98,
            'coupon_code' => 'SAVE10',
            'customer' => [
                'full_name' => 'Jane Doe',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'phone' => '07123456789',
                'shipping' => [
                    'country' => 'United Kingdom',
                    'line_1' => '10 High Street',
                    'line_2' => '',
                    'postcode' => 'SW1A 1AA',
                    'town_city' => 'London',
                ],
            ],
            'cart_items' => [[
                'product_id' => '101',
                'product_code' => 'GS123-M',
                'product_name' => 'Dress',
                'qty' => 2,
                'price' => 29.99,
                'color_id' => '42',
                'color_name' => 'Brown',
                'size_id' => '7',
                'size_name' => 'M',
            ]],
        ],
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk();

    $action = ActivityEcomUserAction::where('event_id', $eventId)->first();

    expect($action->proceed_to_checkout['cart_items'][0]['product_id'])->toBe('101');
    expect($action->proceed_to_checkout['cart_items'][0]['color_name'])->toBe('Brown');
    expect($action->proceed_to_checkout['customer']['email'])->toBe('jane@example.com');
    expect($action->proceed_to_checkout['customer']['shipping']['postcode'])->toBe('SW1A 1AA');
});

test('track endpoint accepts payment success with checkout info', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-1001',
            'amount_paid' => 89.99,
            'payment_method' => 'card',
            'currency' => 'GBP',
            'checkout_info' => [
                'order_number' => 'ORD-1001',
                'order_pk' => '55',
                'coupon_code' => 'SAVE10',
                'items' => [[
                    'product_id' => '101',
                    'product_name' => 'Dress',
                    'color_id' => '42',
                    'color_name' => 'Brown',
                    'size_id' => '7',
                    'size_name' => 'M',
                    'qty' => 1,
                    'price' => 29.99,
                ]],
                'totals' => [
                    'grand_total' => 89.99,
                ],
            ],
        ],
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk();

    $action = ActivityEcomUserAction::where('event_id', $eventId)->first();

    expect($action->payment_success['checkout_info']['order_pk'])->toBe('55');
    expect($action->payment_success['checkout_info']['items'][0]['size_name'])->toBe('M');
});

test('payment success updates session user from checkout customer info', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'product_code' => 'GS123',
    ]], [
        'is_logged_in' => false,
    ]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])->assertOk();

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-2001',
            'amount_paid' => 59.99,
            'payment_method' => 'card',
            'currency' => 'GBP',
            'checkout_info' => [
                'order_number' => 'ORD-2001',
                'customer' => [
                    'first_name' => 'Sam',
                    'last_name' => 'Taylor',
                    'email' => 'sam.taylor@example.com',
                    'phone' => '07123456789',
                ],
                'items' => [[
                    'product_id' => '101',
                    'product_name' => 'Dress',
                    'qty' => 1,
                    'price' => 59.99,
                    'color_name' => 'Navy',
                ]],
            ],
        ],
    ]], [
        'is_logged_in' => false,
    ]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])->assertOk();

    $session = ActivityEcomUser::where('session_id', $sessionId)->first();

    expect($session->user_name)->toBe('Sam Taylor');
    expect($session->user_email)->toBe('sam.taylor@example.com');
    expect($session->is_logged_in)->toBeFalse();
});

test('proceed checkout updates session user from checkout customer info', function () {
    $sessionId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'product_code' => 'GS123',
    ]], [
        'is_logged_in' => false,
    ]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])->assertOk();

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => $eventId,
        'session_id' => $sessionId,
        'action_type' => 'proceed_checkout',
        'proceed_to_checkout' => [
            'customer' => [
                'first_name' => 'Alex',
                'last_name' => 'Guest',
                'email' => 'alex.guest@example.com',
            ],
        ],
    ]], [
        'is_logged_in' => false,
    ]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])->assertOk();

    $session = ActivityEcomUser::where('session_id', $sessionId)->first();

    expect($session->user_name)->toBe('Alex Guest');
    expect($session->user_email)->toBe('alex.guest@example.com');
    expect($session->is_logged_in)->toBeFalse();
});

test('session ingest only marks logged in when user id is present', function () {
    $sessionId = Str::uuid()->toString();

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'product_code' => 'GS123',
    ]], [
        'user_name' => 'Guest Shopper',
        'user_email' => 'guest@example.com',
        'is_logged_in' => true,
    ]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])->assertOk();

    $session = ActivityEcomUser::where('session_id', $sessionId)->first();

    expect($session->is_logged_in)->toBeFalse();
    expect($session->user_name)->toBe('Guest Shopper');
    expect($session->user_email)->toBe('guest@example.com');

    $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'product_code' => 'GS124',
    ]], [
        'user_id' => 42,
        'user_name' => 'Registered Shopper',
        'user_email' => 'registered@example.com',
        'is_logged_in' => false,
    ]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ])->assertOk();

    $session->refresh();

    expect($session->user_id)->toBe(42);
    expect($session->is_logged_in)->toBeTrue();
});

test('payment success rejects disallowed fields', function () {
    $sessionId = Str::uuid()->toString();

    $response = $this->postJson('/api/track', trackPayload($sessionId, [[
        'id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-1',
            'amount_paid' => 99.99,
            'payment_method' => 'klarna',
            'currency' => 'GBP',
            'card_number' => '4111',
        ],
    ]]), [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertUnprocessable();
});
