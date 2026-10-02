<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactInquiryReceipt extends Mailable
{
    public function __construct(public ContactInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: '【JobDD】お問い合わせを受け付けました',
            replyTo: [new Address(config('jobdd.contact_notification_email'))],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact-inquiry-receipt');
    }
}
