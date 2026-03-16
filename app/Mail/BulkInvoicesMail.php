<?php

namespace App\Mail;

use App\Models\BulkInvoice;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * BulkInvoicesMail
 *
 * Mailable for bulk invoice delivery with PDF attachments.
 * Sends generated invoice PDFs to company accountant and uploading agent.
 * Does NOT implement ShouldQueue (handled by SendInvoiceEmailsJob).
 */
class BulkInvoicesMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The bulk invoice ID.
     */
    protected int $bulkInvoiceId;

    /**
     * Create a new message instance.
     *
     * @param  int  $bulkInvoiceId  The bulk invoice ID (not Eloquent model)
     */
    public function __construct(int $bulkInvoiceId)
    {
        $this->bulkInvoiceId = $bulkInvoiceId;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Load BulkInvoice with eager loading
        $bulkInvoice = BulkInvoice::with('agent.branch.company')->findOrFail($this->bulkInvoiceId);

        // Load invoices with all necessary relationships
        $invoices = Invoice::whereIn('id', $bulkInvoice->invoice_ids ?? [])
            ->with([
                'client',
                'agent.branch.company',
                'invoiceDetails.task.supplier',
                'invoiceDetails.task.flightDetails',
                'invoiceDetails.task.hotelDetails.hotel',
                'invoiceDetails.task.visaDetails',
                'invoiceDetails.task.insuranceDetails',
            ])
            ->get();

        // Get company from agent relationship
        $company = $bulkInvoice->agent?->branch?->company;

        // Set subject
        $invoiceCount = $invoices->count();
        $subject = "Bulk Invoice Upload - {$invoiceCount} Invoice".($invoiceCount !== 1 ? 's' : '').' Created';

        // Return email with view and data
        return $this->subject($subject)
            ->view('bulk-invoice.pdf.bulk-invoices')
            ->with([
                'invoices' => $invoices,
                'bulkInvoice' => $bulkInvoice,
                'company' => $company,
            ]);
    }

    /**
     * Get the attachments for the message.
     *
     * Generates one PDF attachment per invoice using the existing invoice.pdf.invoice template.
     *
     * @return array<int, \Illuminate\Mail\Attachment>
     */
    public function attachments(): array
    {
        // Load BulkInvoice
        $bulkInvoice = BulkInvoice::with('agent.branch.company')->findOrFail($this->bulkInvoiceId);

        // Load invoices with all necessary relationships
        $invoices = Invoice::whereIn('id', $bulkInvoice->invoice_ids ?? [])
            ->with([
                'client',
                'agent.branch.company',
                'invoiceDetails.task.supplier',
                'invoiceDetails.task.flightDetails',
                'invoiceDetails.task.hotelDetails.hotel',
                'invoiceDetails.task.visaDetails',
                'invoiceDetails.task.insuranceDetails',
            ])
            ->get();

        $attachments = [];

        // Generate PDF attachment for each invoice
        foreach ($invoices as $invoice) {
            $company = $invoice->agent?->branch?->company;
            $invoiceDetails = $invoice->invoiceDetails ?? collect([]);

            // Prepare view data (matches InvoiceMail pattern)
            $viewData = [
                'invoice' => $invoice,
                'company' => $company,
                'invoiceDetails' => $invoiceDetails,
                'isPdf' => true,
            ];

            // Generate PDF in memory
            $pdf = Pdf::loadView('invoice.pdf.invoice', $viewData)
                ->setPaper('a4', 'portrait');

            // Add attachment using Laravel 11 Attachment::fromData pattern
            $attachments[] = Attachment::fromData(
                fn () => $pdf->output(),
                "Invoice-{$invoice->invoice_number}.pdf"
            )->withMime('application/pdf');
        }

        return $attachments;
    }
}
