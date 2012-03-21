<?php
require_once("./stripe-php/lib/Stripe.php");

// SETUP:
// 1. Customize all the settings (stripe api key, email settings, email text)
// 2. Put this code somewhere where it's accessible by a URL on your server.
// 3. Add the URL of that location to the settings at https://manage.stripe.com/#account/webhooks
// 4. Have fun!

// set your secret key: remember to change this to your live secret key in production
// see your keys here https://manage.stripe.com/account
Stripe::setApiKey("SECRETE_KEY_HERE");

// retrieve the request's body and parse it as JSON
$body = @file_get_contents('php://input');
$event_json = json_decode($body);
$header = "From: MyApp Support <support@myapp.com>";
$admin = "support@myapp.com";

try {
    // for extra security, retrieve from the Stripe API, this will fail in Test Webhooks
    $event_id = $event_json->{'id'};
    $event = Stripe_Event::retrieve($event_id);
    
    if ($event->type == 'charge.succeeded') {
      email_payment_receipt($event->data->object, $admin, $header);
    }
    
    // This will send receipts on succesful invoices
    if ($event->type == 'invoice.payment_succeeded') {
      email_invoice_receipt($event->data->object, $header);
    }
        
} catch (Stripe_InvalidRequestError $e) {    
    mail($admin, $e->getMessage(), $body, $header);
}

function email_payment_receipt($payment, $email, $header) {
    $subject = 'Payment has been received';
    mail($email, $subject, payment_received_body($payment, $email), $header);     
}

function email_invoice_receipt($invoice, $header) {
  $customer = Stripe_Customer::retrieve($invoice->customer);
  $subject = 'Your payment has been received';
  mail($customer->email, $subject, invoice_payment_received_body($invoice, $customer), $header); 
}

function format_stripe_amount($amount) {
  return sprintf('$%0.2f', $amount / 100.0);
}

function format_stripe_timestamp($timestamp) {
  return strftime("%m/%d/%Y", $timestamp);
}

function invoice_payment_received_body($invoice, $customer) {
  $subscription = $invoice->lines->subscriptions[0];
  $start = format_stripe_timestamp($subscription->period->start);
  $end = format_stripe_timestamp($subscription->period->end);
  $total = format_stripe_amount($invoice->total);
  
  return <<<EOD
Dear {$customer->email}:

This is a receipt for your subscription. This is only a receipt, 
no payment is due. Thanks for your continued support!

-------------------------------------------------
SUBSCRIPTION RECEIPT

Email: {$customer->email}
Plan: {$subscription->plan->name}
Amount: {$total} (USD)

For service between {$start} and {$end}

-------------------------------------------------

EOD;
}


function payment_received_body($charge, $email) {
  $amount = format_stripe_amount($charge->amount);
  return <<<EOD

A payment has been charged successfully. 

-------------------------------------------------
PAYMENT RECEIPT

Email: {$email}
Amount: {$amount} (USD)

-------------------------------------------------

EOD;
}
?>
