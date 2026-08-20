<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SellingChartDiscountMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $item;

    public function __construct($scdh)
    {
        $this->item = json_decode($scdh['items'], true);
    }

    public function build()
    {
        $subject = config('app.name') . ' - Discount Assigned for '. $this->item['platform'];

        return $this->subject($subject)
            ->view('selling_chart.discounts.email_body');
    }
}
