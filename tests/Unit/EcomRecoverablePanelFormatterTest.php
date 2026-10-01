<?php

use App\Support\EcomRecoverablePanelFormatter;
use Tests\TestCase;

uses(TestCase::class);

test('recoverable panel formatter builds sorted limited rows', function () {
    $panel = EcomRecoverablePanelFormatter::panelFromAbandonedRows([
        [
            'session_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'qty' => 2,
            'value' => 10.5,
            'occurred_at' => '2026-07-01 12:00:00',
        ],
        [
            'session_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'qty' => 1,
            'value' => 99.0,
            'occurred_at' => '2026-07-02 12:00:00',
        ],
    ], 1);

    expect($panel['session_count'])->toBe(2)
        ->and($panel['at_stake'])->toBe(109.5)
        ->and($panel['rows'])->toHaveCount(1)
        ->and($panel['rows'][0]['session_id'])->toBe('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
});
