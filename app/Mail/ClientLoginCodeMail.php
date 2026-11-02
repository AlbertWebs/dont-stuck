<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  bool  $previewOnly  Whether this message is a visual preview with an invalid code.
     */
    public function __construct(public string $code, public bool $previewOnly = false) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->previewOnly ? '[VISUAL TEST] ' : '')."Your Don't Stuck Solutions sign-in code",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.client-login-code',
            text: 'mail.client-login-code-text',
            with: [
                'code' => $this->code,
                'expiresMinutes' => 10,
                'portalUrl' => route('client.login'),
                'previewOnly' => $this->previewOnly,
                'supportEmail' => config('mail.from.address'),
            ],
        );
    }
}
