<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Services\RAGService;
use App\Services\TenantDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    protected RAGService $ragService;

    public function __construct(RAGService $ragService)
    {
        $this->ragService = $ragService;
    }

    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'chatbot_id' => 'required|exists:chatbots,id',
            'session_id' => 'nullable|string|max:255',
            'tenant_id' => 'nullable|string|max:255',
            'query_mode' => 'nullable|in:general,sql',
            'sql_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $chatbot = Chatbot::findOrFail($request->chatbot_id);

            if (!$chatbot->is_active) {
                return response()->json([
                    'error' => 'Chatbot is not active',
                ], 403);
            }

            // Get tenant ID and set up database connection
            $tenantId = $request->tenant_id;
            $crmConnection = TenantDatabaseService::setTenantConnection($tenantId);

            $queryMode = $request->query_mode ?? 'general';
            $sqlType = $request->sql_type;

            if ($queryMode === 'sql' && $sqlType) {
                $email = $this->extractEmail($request->message);
                
                $isAdmin = DB::connection($crmConnection)
                ->table('tblstaff')
                ->where('admin', 1)
                ->first();
    
                $wantsMyAccount = preg_match('/\b(my|me|my account|show my)\b/i', $request->message);
    
                $targetEmail = null;
                if ($wantsMyAccount) {
                    $targetEmail = DB::connection($crmConnection)->table('tblstaff')->where('email', $email)->first();
                } elseif ($email) {
                    $targetEmail = $email;
                }

                // dd($targetEmail);

                switch ($sqlType) {
                    case 'invoice':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view your invoices.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        $response = $this->handleInvoiceQuery($email, $crmConnection);
                        break;
                        
                    case 'user':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide an email address to search for user information.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        $response = $this->handleUserQuery($email, $crmConnection);
                        break;
                        
                    case 'order':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view your orders.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        $response = $this->handleOrderQuery($email, $crmConnection);
                        break;
                        
                    case 'account':
                        if (!$targetEmail) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view your account details.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        $response = $this->handleAccountQuery($targetEmail, $crmConnection);
                        break;
                        
                    case 'support':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view your support tickets.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        $response = $this->handleSupportQuery($email, $crmConnection);
                        break;
                        
                    case 'payment':
                        // Try to extract invoice number first, then email
                        $invoiceNumber = $this->extractInvoiceNumber($request->message);
                        if ($invoiceNumber) {
                            $response = $this->handlePaymentHistoryByInvoice($invoiceNumber, $crmConnection);
                        } elseif ($email) {
                            $response = $this->handlePaymentHistory($email, $crmConnection);
                        } else {
                            return response()->json([
                                'reply' => 'Please provide either your email address or invoice number to view payment history.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        break;
                        
                    case 'subscription':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view subscription status.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        // Check if user is from tenant or main database
                        $isFromTenant = !empty($tenantId) && $tenantId !== 'perfexcrm' && $tenantId !== 'crm';
                        // Always use main CRM connection for subscriptions (tblclient_plan only exists in main DB)
                        $response = $this->handleSubscriptionStatus($email, 'crm', $isFromTenant, $tenantId);
                        break;
                        
                    case 'renewal':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view upcoming renewals.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        // Check if user is from tenant or main database
                        $isFromTenant = !empty($tenantId) && $tenantId !== 'perfexcrm' && $tenantId !== 'crm';
                        // Always use main CRM connection for renewals (tblclient_plan only exists in main DB)
                        $response = $this->handleUpcomingRenewals($email, 'crm', $isFromTenant, $tenantId);
                        break;
                        
                    case 'stock-status':
                        $response = $this->handleStockStatus($request->message, $crmConnection);
                        break;
                        
                    case 'other':
                        $response = 'Other database queries are not yet implemented. Please try a specific query type.';
                        break;
                        
                    default:
                        $response = 'Invalid SQL query type selected. Please choose from the menu.';
                }

                // Save messages to conversation
                $conversation = Conversation::firstOrCreate([
                    'chatbot_id' => $chatbot->id,
                    'session_id' => $request->session_id,
                ], [
                    'ip_address' => $request->ip(),
                ]);

                $conversation->messages()->create([
                    'content' => $request->message,
                    'is_bot' => false,
                    'sources' => [],
                ]);

                $conversation->messages()->create([
                    'content' => $response,
                    'is_bot' => true,
                    'sources' => [],
                ]);

                return response()->json([
                    'reply' => $response,
                    'sources' => [],
                    'learning_data_id' => null,
                    'show_contact_info' => false
                ]);
            }

            $response = $this->ragService->generateResponse(
                question: $request->message,
                chatbotId: $chatbot->id,
                sessionId: $request->session_id,
                ipAddress: $request->ip()
            );

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your request',
                'message' => config('app.debug') ? $e->getMessage() : 'Please try again later',
            ], 500);
        }
    }

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        // Match invoice number patterns: #123, INV-123, invoice 123, etc.
        if (preg_match('/(invoice\s*#?|#|inv[-_]?)(\d+)/i', $text, $matches)) {
            return $matches[2];
        }
        // Match standalone numbers
        if (preg_match('/\b(\d+)\b/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function handleUserQuery(?string $email, string $connection = 'crm'): string
    {
        if (!$email) {
            return "Please provide an email address to search for user information.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('firstname', 'lastname', 'email', 'phonenumber', 'active')
                ->first();
            
            if ($client) {
                return "Here is the user information:\n\n" .
                       "First Name: {$client->firstname}\n" .
                       "Last Name: {$client->lastname}\n" .
                       "Email: {$client->email}\n" .
                       "Phone: {$client->phonenumber}\n" .
                       "Status: " . ($client->active ? 'Active' : 'Inactive');
            } else {
                return "No user found with email {$email} in the CRM database.";
            }
        } catch (\Exception $e) {
            Log::error('CRM User Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while searching for user information. Please try again.";
        }
    }

    private function handleOrderQuery(?string $email, string $connection = 'crm'): string
    {
        if (!$email) {
            return "Please provide your email address to view your orders.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // $orders = DB::connection($connection)
            //     ->table('tblorders')
            //     ->where('clientid', $client->userid)
            //     ->orderBy('datecreated', 'desc')
            //     ->limit(10)
            //     ->get(['id', 'status', 'total', 'datecreated']);



            $orders = DB::connection($connection)
                ->table('tbltechoflyorder')
                ->where('client_id', $client->userid)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'status', 'created_at']);
            
            if ($orders->isEmpty()) {
                return "No orders found for your account.";
            }

            $response = "Here are your recent orders:\n\n";
            foreach ($orders as $order) {
                $response .= "Order #{$order->id}: Status - {$order->status}, Date - " . date('Y-m-d', strtotime($order->created_at)) . "\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Order Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving your orders. Please try again.";
        }
    }

    private function handleInvoiceQuery(?string $email, string $connection = 'crm'): string
    {
        if (!$email) {
            return "Please provide your email address to view your invoices.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            $invoices = DB::connection($connection)
                ->table('tblinvoices')
                ->where('clientid', $client->userid)
                ->orderBy('datecreated', 'desc')
                ->limit(10)
                ->get(['id', 'status', 'total', 'datecreated']);
            
            
            if ($invoices->isEmpty()) {
                return "No invoices found for your account.";
            }

            $response = "Here are your recent invoices:\n\n";
            foreach ($invoices as $invoice) {
                $response .= "Invoice #{$invoice->id}: Status - {$invoice->status}, Total - $" . number_format($invoice->total, 2) . ", Date - " . date('Y-m-d', strtotime($invoice->datecreated)) . "\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Invoice Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving your invoices. Please try again.";
        }
    }

    private function handleAccountQuery(?string $email, string $connection = 'crm'): string
    {
        if (!$email) {
            return "Please provide your email address to view account details.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblclients')
                ->join('tblcontacts', 'tblclients.userid', '=', 'tblcontacts.userid')
                ->where('tblcontacts.email', $email)
                ->select(
                    'tblclients.userid',
                    'tblclients.company',
                    'tblclients.active',
                    'tblclients.datecreated',
                    'tblcontacts.firstname',
                    'tblcontacts.lastname',
                    'tblcontacts.email',
                    'tblcontacts.phonenumber',
                    DB::raw('(SELECT COUNT(*) FROM tblinvoices WHERE tblinvoices.clientid = tblclients.userid) as invoices_count'),
                    DB::raw('(SELECT IFNULL(SUM(tblinvoices.total),0) FROM tblinvoices WHERE tblinvoices.clientid = tblclients.userid) as invoices_total'),
                    DB::raw('(SELECT IFNULL(SUM(tblinvoicepaymentrecords.amount),0) FROM tblinvoicepaymentrecords WHERE tblinvoicepaymentrecords.invoiceid IN (SELECT id FROM tblinvoices WHERE clientid = tblclients.userid)) as payments_total')
                )
                ->first();

            if (!$client) {
                return "No account found with email {$email}.";
            }

            $balance = (float)$client->invoices_total - (float)$client->payments_total;

            //recent invoices
            $recentInvoices = DB::connection($connection)
                ->table('tblinvoices')
                ->where('clientid', $client->userid)
                ->orderBy('datecreated', 'desc')
                ->limit(5)
                ->get(['id', 'status', 'total', 'datecreated']);

            // recent payments
            $recentPayments = DB::connection('crm')
                ->table('tblinvoicepaymentrecords')
                ->whereIn('invoiceid', function ($q) use ($client) {
                    $q->select('id')->from('tblinvoices')->where('clientid', $client->userid);
                })
                ->orderBy('date', 'desc')
                ->limit(5)
                ->get(['id', 'invoiceid', 'amount', 'paymentmethod', 'paymentmode', 'transactionid', 'date']);

            // recent orders
            $recentOrders = DB::connection('crm')
                ->table('tbltechoflyorder')
                ->where('client_id', $client->userid)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'status', 'created_at']);

            $response = "Here are your account details:\n\n";
            $response .= "Name: {$client->firstname} {$client->lastname}\n";
            $response .= "Company: {$client->company}\n";
            $response .= "Status: " . ($client->active ? 'Active' : 'Inactive') . "\n";
            $response .= "Member Since: " . date('Y-m-d', strtotime($client->datecreated)) . "\n\n";
            $response .= "Invoices: {$client->invoices_count} | Total: $" . number_format($client->invoices_total, 2) . " | Paid: $" . number_format($client->payments_total, 2) . " | Balance: $" . number_format($balance, 2) . "\n\n";

            if ($recentInvoices->isNotEmpty()) {
                $response .= "Recent Invoices:\n";
                foreach ($recentInvoices as $inv) {
                    $response .= "Invoice #{$inv->id}: {$inv->status} - $" . number_format($inv->total, 2) . " (" . date('Y-m-d', strtotime($inv->datecreated)) . ")\n";
                }
                $response .= "\n";
            }

            if ($recentPayments->isNotEmpty()) {
                $response .= "Recent Payments:\n";
                foreach ($recentPayments as $p) {
                    $method = $p->paymentmethod ?: $p->paymentmode ?: 'N/A';
                    $response .= "Payment #{$p->id}: $" . number_format($p->amount, 2) . " via {$method}";
                    if ($p->transactionid) { $response .= " (Txn: {$p->transactionid})"; }
                    $response .= " | Invoice #{$p->invoiceid} | " . date('Y-m-d', strtotime($p->date)) . "\n";
                }
                $response .= "\n";
            }

            if ($recentOrders->isNotEmpty()) {
                $response .= "Recent Orders:\n";
                foreach ($recentOrders as $o) {
                    // $response .= "Order #{$o->id}: {$o->status} - $" . number_format($o->total, 2) . " (" . date('Y-m-d', strtotime($o->created_at)) . ")\n";
                    $response .= "Order #{$o->id}: {$o->status} - " . date('Y-m-d', strtotime($o->created_at)) . "\n";
                }
                $response .= "\n";
            }

            $response .= "For complete history, ask: 'show all invoices', 'show all payments', 'show all orders', or 'show subscriptions'.";
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Account Query Error: ' . $e->getMessage());
            return $e->getMessage(). "Sorry, I encountered an error while retrieving your account details. Please try again.";
        }
    }

    private function handleSupportQuery(?string $email, string $connection = 'crm'): string
    {
        if (!$email) {
            return "Please provide your email address to view support tickets.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            $tickets = DB::connection($connection)
                ->table('tbltickets')
                ->where('userid', $client->userid)
                ->orWhere('contactid', $client->userid)
                ->orderBy('date', 'desc')
                ->limit(5)
                ->get(['ticketid', 'subject', 'status', 'date']);
            
            if ($tickets->isEmpty()) {
                return "No support tickets found for your account.";
            }

            $response = "Here are your recent support tickets:\n\n";
            foreach ($tickets as $ticket) {
                $response .= "Ticket #{$ticket->ticketid}: {$ticket->subject} - Status: {$ticket->status}, Date: " . date('Y-m-d', strtotime($ticket->date)) . "\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Support Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving your support tickets. Please try again.";
        }
    }

    private function handlePaymentHistory(?string $email, string $connection = 'crm'): string
    {
        if (!$email) {
            return "Please provide your email address to view payment history.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Get invoice IDs for this client
            $invoiceIds = DB::connection($connection)
                ->table('tblinvoices')
                ->where('clientid', $client->userid)
                ->pluck('id');

            if ($invoiceIds->isEmpty()) {
                return "No payment history found for your account.";
            }

            // Get payment records directly using invoice IDs
            $payments = DB::connection($connection)
                ->table('tblinvoicepaymentrecords')
                ->whereIn('invoiceid', $invoiceIds)
                ->orderBy('date', 'desc')
                ->limit(20)
                ->get(['id', 'invoiceid', 'amount', 'paymentmode', 'paymentmethod', 'date', 'transactionid']);

            if ($payments->isEmpty()) {
                return "No payment history found for your account.";
            }

            $response = "Here is your payment history:\n\n";
            foreach ($payments as $payment) {
                $paymentMethod = $payment->paymentmethod ?: $payment->paymentmode;
                $response .= "Payment #{$payment->id}: $" . number_format($payment->amount, 2) . " via {$paymentMethod}";
                if ($payment->transactionid) {
                    $response .= " (Txn: {$payment->transactionid})";
                }
                $response .= "\nDate: " . date('Y-m-d', strtotime($payment->date)) . " | Invoice #{$payment->invoiceid}\n\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Payment History Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving payment history. Please try again.";
        }
    }

    private function handlePaymentHistoryByInvoice(?string $invoiceNumber, string $connection = 'crm'): string
    {
        if (!$invoiceNumber) {
            return "Please provide an invoice number to view payment history.";
        }

        try {
            // Check if invoice exists
            $invoice = DB::connection($connection)
                ->table('tblinvoices')
                ->where('id', $invoiceNumber)
                ->first(['id', 'clientid', 'total', 'status']);

            if (!$invoice) {
                return "Invoice #{$invoiceNumber} not found.";
            }

            // Get payment records for this invoice
            $payments = DB::connection($connection)
                ->table('tblinvoicepaymentrecords')
                ->select('tblinvoicepaymentrecords.id', 'tblinvoicepaymentrecords.invoiceid', 'tblinvoicepaymentrecords.amount', 'tblinvoicepaymentrecords.paymentmethod', 'tblinvoicepaymentrecords.paymentmode', 'tblinvoicepaymentrecords.transactionid', 'tblinvoicepaymentrecords.date', 'tblpayment_modes.name as mode_name')
                ->leftJoin('tblpayment_modes', 'tblpayment_modes.id', '=', 'tblinvoicepaymentrecords.paymentmode')
                ->where('tblinvoicepaymentrecords.invoiceid', $invoiceNumber)
                ->orderBy('tblinvoicepaymentrecords.date', 'desc')
                ->get();

            if ($payments->isEmpty()) {
                return "No payments found for Invoice #{$invoiceNumber}.\n\n" .
                       "Invoice Total: $" . number_format($invoice->total, 2) . "\n" .
                       "Status: {$invoice->status}";
            }

            $totalPaid = $payments->sum('amount');
            $response = "Payment History for Invoice #{$invoiceNumber}:\n\n";
            $response .= "Invoice Total: $" . number_format($invoice->total, 2) . "\n";
            $response .= "Total Paid: $" . number_format($totalPaid, 2) . "\n";
            $response .= "Balance: $" . number_format($invoice->total - $totalPaid, 2) . "\n\n";
            $response .= "Payment Records:\n\n";

            foreach ($payments as $payment) {
                $paymentMethod = $payment->paymentmethod ?: ($payment->mode_name ?: 'N/A');
                $response .= "Payment #{$payment->id}: $" . number_format($payment->amount, 2) . " via {$paymentMethod}";
                if ($payment->transactionid) {
                    $response .= " (Txn: {$payment->transactionid})";
                }
                $response .= "\nDate: " . date('Y-m-d', strtotime($payment->date)) . "\n\n";
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Payment History By Invoice Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving payment history for this invoice. Please try again.";
        }
    }

    private function handleSubscriptionStatus(?string $email, string $connection = 'crm', bool $restrictToOwnEmail = false, ?string $tenantId = null): string
    {
        if (!$email) {
            return "Please provide your email address to view subscription status.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Build query for subscriptions
            $query = DB::connection($connection)
                ->table('tblclient_plan')
                ->where('userid', $client->userid);

            // Security: If from tenant portal, restrict to current tenant only
            if ($restrictToOwnEmail && $tenantId) {
                $query->where('tenants_db', $tenantId);
            }

            $subscriptions = $query
                ->orderBy('subscription_start_date', 'desc')
                ->get(['id', 'tenants_name', 'subscription_status', 'trial_days', 'subscription_start_date', 'subscription_end_date', 'trial_start_time']);
            
            if ($subscriptions->isEmpty()) {
                return "No subscriptions found for your account.";
            }

            $response = "Here are your subscriptions:\n\n";
            foreach ($subscriptions as $sub) {
                $statusLabel = match($sub->subscription_status) {
                    'active' => '✓ Active',
                    'trial' => '🧪 Trial',
                    'expired' => '✗ Expired',
                    'cancelled' => '✗ Cancelled',
                    default => 'Unknown'
                };
                
                $response .= "Subscription: {$sub->tenants_name} - {$statusLabel}\n";
                
                if ($sub->subscription_status === 'trial') {
                    $trialStart = $sub->trial_start_time ? strtotime($sub->trial_start_time) : null;
                    $trialDays = (int)($sub->trial_days ?? 0);
                    if ($trialStart && $trialDays > 0) {
                        $trialEnd = strtotime("+{$trialDays} days", $trialStart);
                        $daysLeft = ceil(($trialEnd - time()) / (60*60*24));
                        $response .= "{$daysLeft} days remaining (ends " . date('Y-m-d', $trialEnd) . ")\n";
                    } else {
                        $response .= "{$trialDays} days\n";
                    }
                } elseif ($sub->subscription_status === 'active') {
                    if ($sub->subscription_end_date) {
                        $response .= "Expires: " . date('Y-m-d', strtotime($sub->subscription_end_date));
                    }
                    $response .= "\n";
                } else {
                    if ($sub->subscription_end_date) {
                        $response .= "Ended: " . date('Y-m-d', strtotime($sub->subscription_end_date)) . "\n";
                    }
                }
                
                $response .= "Started: " . date('Y-m-d', strtotime($sub->subscription_start_date ?? $sub->trial_start_time ?? now())) . "\n\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Subscription Status Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving subscription status. Please try again.";
        }
    }

    private function handleUpcomingRenewals(?string $email, string $connection = 'crm', bool $restrictToOwnEmail = false, ?string $tenantId = null): string
    {
        if (!$email) {
            return "Please provide your email address to view upcoming renewals.";
        }

        try {
            $client = DB::connection($connection)
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Build query for renewals
            $query = DB::connection($connection)
                ->table('tblclient_plan')
                ->where('userid', $client->userid)
                ->whereIn('subscription_status', ['active', 'trial']);

            // Security: If from tenant portal, restrict to current tenant only
            if ($restrictToOwnEmail && $tenantId) {
                $query->where('tenants_db', $tenantId);
            }

            // Get renewals from subscriptions (active or trial)
            $renewals = $query->get(['id', 'tenants_name', 'subscription_status', 'subscription_end_date', 'trial_days', 'trial_start_time']);
            
            // Filter and calculate end dates
            $filteredRenewals = collect();
            foreach ($renewals as $renewal) {
                $endDate = null;
                if ($renewal->subscription_status === 'active' && $renewal->subscription_end_date) {
                    $endDate = $renewal->subscription_end_date;
                } elseif ($renewal->subscription_status === 'trial') {
                    $trialStart = $renewal->trial_start_time ? strtotime($renewal->trial_start_time) : null;
                    $trialDays = (int)($renewal->trial_days ?? 0);
                    if ($trialStart && $trialDays > 0) {
                        $endDate = date('Y-m-d H:i:s', strtotime("+{$trialDays} days", $trialStart));
                    }
                }
                
                if ($endDate && strtotime($endDate) >= time() && strtotime($endDate) <= strtotime('+60 days')) {
                    $renewal->calculated_end_date = $endDate;
                    $filteredRenewals->push($renewal);
                }
            }
            
            $renewals = $filteredRenewals->sortBy('calculated_end_date');
            
            if ($renewals->isEmpty()) {
                return "No upcoming expirations in the next 60 days for your account.";
            }

            $response = "Here are your upcoming expirations:\n\n";
            foreach ($renewals as $renewal) {
                $daysUntil = ceil(now()->diffInDays($renewal->calculated_end_date));
                $status = $renewal->subscription_status === 'trial' ? ' (Trial)' : '';
                $response .= "• {$renewal->tenants_name}{$status} - Expires on " . date('Y-m-d', strtotime($renewal->calculated_end_date)) . " ({$daysUntil} days)\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Upcoming Renewals Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving upcoming renewals. Please try again.";
        }
    }

    private function handleStockStatus(string $message, string $connection = 'crm'): string
    {
        try {
            $product = DB::connection($connection)
                ->table('tblitems')
                ->select('id', 'description')
                ->where('description', 'like', '%' . $message . '%')
                ->first();
                
                if (!$product) {
                    return "Product not found. Please provide the correct product name.";
                }
                
                $warehouse_id = 1;
                $inventory_stock = DB::connection($connection)
                ->table('tblinventory_manage')
                ->select(DB::raw('sum(inventory_number) as total_stock'))
                ->where('warehouse_id', $warehouse_id)
                ->where('commodity_id', $product->id)
                ->groupBy('warehouse_id', 'commodity_id')
                ->first();

            $stockLevel = $inventory_stock->total_stock ?? 0;
            $status = $stockLevel > 10 ? '✓ In Stock' : ($stockLevel > 0 ? '⚠️ Low Stock' : '✗ Out of Stock');

            return "Stock Status for {$product->description}:\n\n" .
                   "Status: {$status}\n" .
                   "Available Quantity: {$stockLevel} units\n\n" .
                   "For product details or recommendations, please use the General chat mode.";

        } catch (\Exception $e) {
            Log::error('CRM Stock Status Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while checking stock status. Please ensure the product name is correct.";
        }
    }

    public function getChatbotInfo(string $chatbotId): JsonResponse
    {
        try {
            $chatbot = Chatbot::with('user:id,name')
                ->where('id', $chatbotId)
                ->where('is_active', true)
                ->firstOrFail();

            return response()->json([
                'id' => $chatbot->id,
                'name' => $chatbot->name,
                'description' => $chatbot->description,
                'welcome_message' => $chatbot->welcome_message,
                'appearance' => $chatbot->appearance,
                'owner' => $chatbot->user->name,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Chatbot not found or inactive',
            ], 404);
        }
    }

    public function getConversationMessages(string $sessionId): JsonResponse
    {
        $conversation = Conversation::where('session_id', $sessionId)
            ->with(['messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->first();
        return response()->json($conversation);
        
        if (!$conversation) {
            return response()->json(['messages' => []]);
        }
        
        return response()->json([
            'messages' => $conversation->messages->map(function($msg) {
                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'isBot' => $msg->is_bot,
                    'timestamp' => $msg->created_at,
                    'sources' => $msg->sources ?? [],
                    'learningDataId' => $msg->learning_data_id ?? null,
                    'feedback' => null,
                    'showContactInfo' => false
                ];
            })
        ]);
    }
}
