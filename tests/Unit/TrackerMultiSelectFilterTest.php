<?php

use App\Support\TrackerMultiSelectFilter;

test('tracker multi select filter normalizes scalar and array inputs', function () {
    expect(TrackerMultiSelectFilter::values('mobile'))->toBe(['mobile'])
        ->and(TrackerMultiSelectFilter::values(['mobile', 'desktop', 'mobile']))->toBe(['mobile', 'desktop'])
        ->and(TrackerMultiSelectFilter::allowedValues(['mobile', 'invalid'], ['mobile', 'desktop']))->toBe(['mobile'])
        ->and(TrackerMultiSelectFilter::queryWithoutValue('device_type', ['mobile', 'desktop'], 'mobile'))
        ->toBe(['device_type' => ['desktop']]);
});
