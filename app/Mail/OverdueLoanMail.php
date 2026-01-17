<?php

namespace App\Mail;

use App\Models\Loan;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class OverdueLoanMail extends Mailable
{
    use Queueable, SerializesModels;

    public $loan;
    public $arrearsInfo;
    public $customSubject;
    public $customBody;
    public $senderName;
    public $senderAddress;

    /**
     * Create a new message instance.
     */
    public function __construct(Loan $loan, array $arrearsInfo)
    {
        $this->loan = $loan;
        $this->arrearsInfo = $arrearsInfo;

        // Fetch settings or defaults
        $this->customSubject = Setting::where('key', 'overdue_email_subject')->value('value') ?? 'Aviso de Atraso en Préstamo';
        $this->customBody = Setting::where('key', 'overdue_email_body')->value('value') ?? 'Estimado {client_name}, le recordamos que tiene un monto vencido de {amount_due} con {days_overdue} días de atraso. Por favor regularice su situación.';
        $this->senderName = Setting::where('key', 'email_sender_name')->value('value') ?? config('mail.from.name');
        $this->senderAddress = Setting::where('key', 'email_sender_address')->value('value') ?? config('mail.from.address');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->senderAddress, $this->senderName),
            subject: $this->customSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.loans.overdue',
            with: [
                'body' => $this->parseBody(),
                'loan' => $this->loan,
                'arrears' => $this->arrearsInfo
            ]
        );
    }

    protected function parseBody()
    {
        $body = $this->customBody;
        $body = str_replace('{client_name}', $this->loan->client->first_name . ' ' . $this->loan->client->last_name, $body);
        $body = str_replace('{amount_due}', number_format($this->arrearsInfo['amount'], 2), $body);
        $body = str_replace('{days_overdue}', $this->arrearsInfo['days'], $body);

        return nl2br($body);
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
