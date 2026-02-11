<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoanStatusSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $overdueLoans;
    public array $legalLoans;

    public function __construct(array $overdueLoans, array $legalLoans)
    {
        $this->overdueLoans = $overdueLoans;
        $this->legalLoans = $legalLoans;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte diario: Mora y Legal',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.loans.admin-summary',
            with: [
                'overdueLoans' => $this->overdueLoans,
                'legalLoans' => $this->legalLoans,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
