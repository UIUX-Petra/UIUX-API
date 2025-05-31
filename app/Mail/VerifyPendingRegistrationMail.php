<?php

namespace App\Mail;

use App\Models\PendingUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyPendingRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public PendingUser $pendingUser;
    public bool $isSettingPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(PendingUser $pendingUser, bool $isSettingPassword = false)
    {
        $this->pendingUser = $pendingUser;
        $this->isSettingPassword = $isSettingPassword;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isSettingPassword ? 'Verify Email to Set Your Password' : 'Verify Your Email for ' . config('app.name');
        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendVerificationUrl = rtrim(config('app.frontend_url'), '/') .
            '/handle-pending-verification?token=' . $this->pendingUser->verification_token;

        return new Content(
            markdown: 'emails.auth.verify_pending_registration',
            with: [
                'url' => $frontendVerificationUrl,
                'name' => $this->pendingUser->username,
                'expires_in_hours' => config('auth.verification.pending_expire', 24),
                'isSettingPassword' => $this->isSettingPassword,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}