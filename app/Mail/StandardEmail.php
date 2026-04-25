<?php
// app/Mail/StandardEmail.php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StandardEmail extends Mailable
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $mailBody,
        public readonly ?string $ctaUrl   = null,
        public readonly ?string $ctaLabel = null,
        public readonly ?string $tip      = null,
        public readonly ?string $logoPath = null,
        public readonly bool    $isHtml   = true
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.standard',
            with: [
                'title'    => $this->title,
                'mailBody' => $this->mailBody,
                'ctaUrl'   => $this->ctaUrl,
                'ctaLabel' => $this->ctaLabel,
                'tip'      => $this->tip,
                'logoPath' => $this->logoPath,
                'isHtml'   => $this->isHtml,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}