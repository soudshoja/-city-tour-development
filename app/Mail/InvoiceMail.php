<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $invoiceId;

    public function __construct(int $invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    public function build()
    {
        $invoice = Invoice::with([
            'client',
            'agent.branch.company',
            'invoiceDetails.task.supplier',
            'invoiceDetails.task.flightDetails',
            'invoiceDetails.task.hotelDetails.hotel',
            'invoiceDetails.task.visaDetails',
            'invoiceDetails.task.insuranceDetails',
            'invoicePartials.paymentMethod',
            'invoicePartials.client',
        ])->findOrFail($this->invoiceId);

        $company = $invoice->agent?->branch?->company;
        $invoiceDetails = $invoice->invoiceDetails ?? collect([]);

        $subject = "Invoice #{$invoice->invoice_number} - " .
            ($invoice->status === 'paid' ? 'Payment Received' : 'Payment Required');

        $viewData = [
            'invoice' => $invoice,
            'company' => $company,
            'invoiceDetails' => $invoiceDetails,
            'isPdf' => false,
        ];

        // $pdfData = array_merge($viewData, ['isPdf' => true]);
        // $pdf = Pdf::loadView('invoice.pdf.invoice', $pdfData)
        //     ->setPaper('a4', 'portrait');

        return $this->subject($subject)
            ->view('invoice.pdf.invoice')
            ->with($viewData);
        // ->attachData(
        //     $pdf->output(),
        //     "Invoice-{$invoice->invoice_number}.pdf",
        //     ['mime' => 'application/pdf']
        // );
    }
}
