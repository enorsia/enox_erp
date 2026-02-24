<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellingChartDiscountMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $discounts;
    public $type;

    public function __construct($discounts, $type)
    {
        $this->discounts = $discounts;
        $this->type = $type;
    }

    public function build()
    {
        $subject = $this->type == 'approval'
            ? config('app.name') . ' - Selling Chart Discount Approval Request'
            : config('app.name') . ' - Discount Assigned for '. $this->discounts->first()?->platform?->name;

        return $this->subject($subject)
            ->view('selling_chart.discounts.email_body');
    }
}
