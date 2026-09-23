<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CompanyJobReviewRequested extends Mailable
{
    public function __construct(public string $companyName, public string $jobTitle, public string $requestedAt, public string $reviewUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【JobDD】求人の公開申請が届きました');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.company-job-review-requested');
    }
}
