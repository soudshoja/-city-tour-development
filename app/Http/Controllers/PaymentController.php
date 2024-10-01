<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice; 
use App\Models\Transaction; 
use Exception;

class PaymentController extends Controller
{
    public function processPayment($invoiceNumber, Request $request)
    {
        // Fetch the invoice to get payment details
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }

        try {

            // Assuming you are using a payment gateway's API (like Stripe, PayPal, etc.)
            // Call the payment gateway API to process the payment
            
            // For example, with Stripe:
            // $charge = \Stripe\Charge::create([
            //     'amount' => $invoice->total * 100, // Convert to cents
            //     'currency' => 'usd',
            //     'source' => $request->stripeToken, // Token received from the payment form
            //     'description' => 'Payment for Invoice #' . $invoice->invoice_number,
            // ]);

            // Update the invoice status to 'paid'
            $invoice->status = 'paid';
            $invoice->paid_date = now();
            $invoice->save(); // Save the updated invoice
        
            // Insert a new transaction record
            $transaction = new Transaction();
            $transaction->invoice_id = $invoice->id;
            $transaction->agent_id = $invoice->agent_id;
            $transaction->client_id = $invoice->client_id;
            $transaction->transaction_amount = $invoice->amount; // Assuming you want to use the invoice total
            $transaction->created_at = now(); // Use the current date and time
            $transaction->payment_type ='online';
            $transaction->save(); // Save the transaction record

            // Handle successful payment
            return redirect()->route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])
                             ->with('status', 'Payment successful! Thank you for your payment.');
        } catch (Exception $e) {
            // Handle payment failure
            return redirect()->back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }
}
