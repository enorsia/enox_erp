<?php

namespace App\View\Composers;

use App\Models\TrackerUtmFilter;
use Illuminate\View\View;

class EcomTrackerUtmFilterComposer
{
    public function compose(View $view): void
    {
        $view->with(TrackerUtmFilter::formState(
            request('utm_source'),
            request('utm_medium'),
        ));
    }
}
