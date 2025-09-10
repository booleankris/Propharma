<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Tickets extends Mailable
{
    use Queueable, SerializesModels;
    public $data;
    public $detail;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public function __construct($data, $detail)
    {
        $this->data = $data;
        $this->detail = $detail;

    }


    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Ticket Masuk Walikota Cup 2025',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'mails.ticket',
            with: [
                'ticket' => $this->data,
                'detail' => $this->detail

            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
