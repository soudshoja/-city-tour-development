<?php

namespace App\Mail;

use App\Models\BulkUpload;
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
     * The bulk upload ID.
     */
    protected int $bulkUploadId;

    /**
     * Create a new message instance.
     *
     * @param  int  $bulkUploadId  The bulk upload ID (not Eloquent model)
     */
    public function __construct(int $bulkUploadId)
    {
        $this->bulkUploadId = $bulkUploadId;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Load BulkUpload with eager loading
        $bulkUpload = BulkUpload::with('agent.branch.company')->findOrFail($this->bulkUploadId);

        // Load invoices with all necessary relationships
        $invoices = Invoice::whereIn('id', $bulkUpload->invoice_ids ?? [])
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
        $company = $bulkUpload->agent?->branch?->company;

        // Set subject
        $invoiceCount = $invoices->count();
        $subject = "Bulk Invoice Upload - {$invoiceCount} Invoice".($invoiceCount !== 1 ? 's' : '').' Created';

        // Return email with view and data
        return $this->subject($subject)
            ->view('email.bulk-invoices')
            ->with([
                'invoices' => $invoices,
                'bulkUpload' => $bulkUpload,
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
        // Load BulkUpload
        $bulkUpload = BulkUpload::with('agent.branch.company')->findOrFail($this->bulkUploadId);

        // Load invoices with all necessary relationships
        $invoices = Invoice::whereIn('id', $bulkUpload->invoice_ids ?? [])
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
