<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Services\RAGService;
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

            $queryMode = $request->query_mode ?? 'general';
            $sqlType = $request->sql_type;

            if ($queryMode === 'sql' && $sqlType) {
                $email = $this->extractEmail($request->message);
                
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
                        $response = $this->handleInvoiceQuery($email);
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
                        $response = $this->handleUserQuery($email);
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
                        $response = $this->handleOrderQuery($email);
                        break;
                        
                    case 'account':
                        if (!$email) {
                            return response()->json([
                                'reply' => 'Please provide your email address to view your account details.',
                                'sources' => [],
                                'learning_data_id' => null,
                                'show_contact_info' => false
                            ]);
                        }
                        $response = $this->handleAccountQuery($email);
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
                        $response = $this->handleSupportQuery($email);
                        break;
                        
                    case 'payment':
                        // Try to extract invoice number first, then email
                        $invoiceNumber = $this->extractInvoiceNumber($request->message);
                        if ($invoiceNumber) {
                            $response = $this->handlePaymentHistoryByInvoice($invoiceNumber);
                        } elseif ($email) {
                            $response = $this->handlePaymentHistory($email);
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
                        $response = $this->handleSubscriptionStatus($email);
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
                        $response = $this->handleUpcomingRenewals($email);
                        break;
                        
                    case 'stock-status':
                        $response = $this->handleStockStatus($request->message);
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

    private function handleUserQuery(?string $email): string
    {
        if (!$email) {
            return "Please provide an email address to search for user information.";
        }

        try {
            $client = DB::connection('crm')
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

    private function handleOrderQuery(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view your orders.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            $orders = DB::connection('crm')
                ->table('tblorders')
                ->where('clientid', $client->userid)
                ->orderBy('datecreated', 'desc')
                ->limit(5)
                ->get(['id', 'status', 'total', 'datecreated']);
            
            if ($orders->isEmpty()) {
                return "No orders found for your account.";
            }

            $response = "Here are your recent orders:\n\n";
            foreach ($orders as $order) {
                $response .= "Order #{$order->id}: Status - {$order->status}, Total - $" . number_format($order->total, 2) . ", Date - " . date('Y-m-d', strtotime($order->datecreated)) . "\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Order Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving your orders. Please try again.";
        }
    }

    private function handleInvoiceQuery(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view your invoices.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            $invoices = DB::connection('crm')
                ->table('tblinvoices')
                ->where('clientid', $client->userid)
                ->orderBy('datecreated', 'desc')
                ->limit(5)
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

    private function handleAccountQuery(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view your account details.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblclients')
                ->join('tblcontacts', 'tblclients.userid', '=', 'tblcontacts.userid')
                ->where('tblcontacts.email', $email)
                ->select('tblclients.company', 'tblclients.active', 'tblcontacts.firstname', 'tblcontacts.lastname')
                ->first();
            
            if (!$client) {
                return "No account found with email {$email}.";
            }

            $balance = 0;
            
            return "Here are your account details:\n\n" .
                   "Name: {$client->firstname} {$client->lastname}\n" .
                   "Company: {$client->company}\n" .
                   "Status: " . ($client->active ? 'Active' : 'Inactive') . "\n" .
                   "Account Balance: $" . number_format($balance, 2);
        } catch (\Exception $e) {
            Log::error('CRM Account Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving your account details. Please try again.";
        }
    }

    private function handleSupportQuery(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view your support tickets.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            $tickets = DB::connection('crm')
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

    private function handlePaymentHistory(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view payment history.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Get invoice IDs for this client
            $invoiceIds = DB::connection('crm')
                ->table('tblinvoices')
                ->where('clientid', $client->userid)
                ->pluck('id');

            if ($invoiceIds->isEmpty()) {
                return "No payment history found for your account.";
            }

            // Get payment records directly using invoice IDs
            $payments = DB::connection('crm')
                ->table('tblinvoicepaymentrecords')
                ->whereIn('invoiceid', $invoiceIds)
                ->orderBy('date', 'desc')
                ->limit(10)
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

    private function handlePaymentHistoryByInvoice(?string $invoiceNumber): string
    {
        if (!$invoiceNumber) {
            return "Please provide an invoice number to view payment history.";
        }

        try {
            // Check if invoice exists
            $invoice = DB::connection('crm')
                ->table('tblinvoices')
                ->where('id', $invoiceNumber)
                ->first(['id', 'clientid', 'total', 'status']);

            if (!$invoice) {
                return "Invoice #{$invoiceNumber} not found.";
            }

            // Get payment records for this invoice
            $payments = DB::connection('crm')
                ->table('tblinvoicepaymentrecords')
                ->select('tblinvoicepaymentrecords.*','tblpayment_modes.name as mode_name')
                ->leftJoin('tblpayment_modes', 'tblpayment_modes.id', '=', 'tblinvoicepaymentrecords.paymentmode')
                ->where('tblinvoicepaymentrecords.invoiceid', $invoiceNumber)
                ->orderBy('tblinvoicepaymentrecords.date', 'desc')
                ->get([
                    
                ]);

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

    private function handleSubscriptionStatus(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view subscription status.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            $subscriptions = DB::connection('crm')
                ->table('tblsubscriptions')
                ->where('clientid', $client->userid)
                ->orderBy('date_subscribed', 'desc')
                ->get(['id', 'name', 'status', 'date_subscribed', 'next_billing_cycle']);
            
            if ($subscriptions->isEmpty()) {
                return "No active subscriptions found for your account.";
            }

            $response = "Here are your subscriptions:\n\n";
            foreach ($subscriptions as $sub) {
                $statusLabel = $sub->status === 'active' ? '✓ Active' : '✗ Inactive';
                $response .= "Subscription: {$sub->name} - {$statusLabel}\n";
                $response .= "Subscribed: " . date('Y-m-d', strtotime($sub->date_subscribed));
                if ($sub->next_billing_cycle) {
                    $response .= " | Next Billing: " . date('Y-m-d', strtotime($sub->next_billing_cycle));
                }
                $response .= "\n\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Subscription Status Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving subscription status. Please try again.";
        }
    }

    private function handleUpcomingRenewals(?string $email): string
    {
        if (!$email) {
            return "Please provide your email address to view upcoming renewals.";
        }

        try {
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Get renewals from subscriptions
            $renewals = DB::connection('crm')
                ->table('tblsubscriptions')
                ->where('clientid', $client->userid)
                ->where('status', 'active')
                ->whereNotNull('next_billing_cycle')
                ->where('next_billing_cycle', '>=', now())
                ->where('next_billing_cycle', '<=', now()->addDays(60))
                ->orderBy('next_billing_cycle', 'asc')
                ->get(['id', 'name', 'next_billing_cycle']);
            
            if ($renewals->isEmpty()) {
                return "No upcoming renewals in the next 60 days for your account.";
            }

            $response = "Here are your upcoming renewals:\n\n";
            foreach ($renewals as $renewal) {
                $daysUntil = now()->diffInDays($renewal->next_billing_cycle);
                $response .= "• {$renewal->name} - Renews on " . date('Y-m-d', strtotime($renewal->next_billing_cycle)) . " ({$daysUntil} days)\n";
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('CRM Upcoming Renewals Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving upcoming renewals. Please try again.";
        }
    }

    private function handleStockStatus(string $message): string
    {
        try {
            $product = DB::connection('crm')
                ->table('tblitems')
                ->select('id', 'description')
                ->where('description', 'like', '%' . $message . '%')
                ->first();
            
            if (!$product) {
                return "Product not found. Please provide the correct product name.";
            }

            $warehouse_id = 1;
            $inventory_stock = DB::connection('crm')
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
