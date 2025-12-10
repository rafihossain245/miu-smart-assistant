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
            // Find the chatbot
            $chatbot = Chatbot::findOrFail($request->chatbot_id);

            // Check if chatbot is active
            if (!$chatbot->is_active) {
                return response()->json([
                    'error' => 'Chatbot is not active',
                ], 403);
            }

            // Get query mode and SQL type from request
            $queryMode = $request->query_mode ?? 'general';
            $sqlType = $request->sql_type;

            // Handle SQL queries directly when mode is explicitly set to 'sql'
            if ($queryMode === 'sql' && $sqlType) {
                $email = $this->extractEmail($request->message);
                
                // Handle based on SQL type
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
                        
                    case 'product-details':
                        $response = $this->handleProductQuery($request->message);
                        break;
                        
                    case 'product-stock':
                        $response = $this->handleStockQuery($request->message);
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

            // Check if this is a database query and get query type (for backward compatibility)
            $email = $this->extractEmail($request->message);
            $queryType = $this->detectQueryType($request->message);

            // Check for a pending DB query that needs an email (stored in cache)
            $pendingKey = 'pending_db_query:' . ($request->session_id ?? $request->ip());
            $pending = Cache::get($pendingKey);

            // If there's no explicit query type but we have an email and a pending request, use it
            if (!$queryType && $email && $pending && isset($pending['type'])) {
                $queryType = $pending['type'];
                $originalMessage = $pending['message'] ?? $request->message;
                // clear pending
                Cache::forget($pendingKey);
            } else {
                $originalMessage = $request->message;
            }

            if ($queryType) {
                // If query requires email and none provided, ask and store pending (except for product queries)
                if (!$email && $queryType !== 'product') {
                    // store pending query for next message (5 minutes)
                    Cache::put($pendingKey, ['type' => $queryType, 'message' => $request->message], 300);
                    $prompt = "Please provide your email address to view your {$queryType}s.";

                    return response()->json([
                        'reply' => $prompt,
                        'sources' => [],
                        'learning_data_id' => null,
                        'show_contact_info' => false
                    ]);
                }

                // Handle database query directly based on type
                $response = $this->handleDatabaseQueryByType($queryType, $originalMessage, $email);

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

            // For non-database queries, use RAG service
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

    private function detectQueryType(string $message): ?string
    {
        $lowerMessage = strtolower($message);
        
        // User queries
        $userKeywords = ['find user', 'user information', 'user details', 'get user', 'search user', 'client info', 'customer details', 'lookup user', 'user data'];
        foreach ($userKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'user';
            }
        }
        
        // Order queries
        $orderKeywords = ['my orders', 'order status', 'recent orders', 'order history'];
        foreach ($orderKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'order';
            }
        }
        
        // Invoice queries
        $invoiceKeywords = ['my invoices', 'invoice details', 'billing history'];
        foreach ($invoiceKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'invoice';
            }
        }
        
        // Account queries
        $accountKeywords = ['account balance', 'account details', 'my account'];
        foreach ($accountKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'account';
            }
        }
        
        // Support queries
        $supportKeywords = ['support tickets', 'my tickets', 'ticket status'];
        foreach ($supportKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'support';
            }
        }
        
        // Product queries
        $productKeywords = ['product details', 'product information', 'find product', 'stock information', 'product stock', 'item details', 'product availability'];
        foreach ($productKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return 'product';
            }
        }
        
        return null; // Not a database query
    }

    private function handleDatabaseQueryByType(string $queryType, string $message, ?string $email): string
    {
        dd($queryType, $message, $email);
        switch ($queryType) {
            case 'user':
                return $this->handleUserQuery($email);
            case 'order':
                return $this->handleOrderQuery($email);
            case 'invoice':
                return $this->handleInvoiceQuery($email);
            case 'account':
                return $this->handleAccountQuery($email);
            case 'support':
                return $this->handleSupportQuery($email);
            case 'product':
                return $this->handleProductQuery($message);
            case 'stock':
                return $this->handleStockQuery($message);
            default:
                return "I'm sorry, I couldn't understand your request. Please try rephrasing.";
        }
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
            // First get client ID from email
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Get recent orders (assuming PerfexCRM has orders table)
            $orders = DB::connection('crm')
                ->table('tblorders') // Adjust table name if different
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
            // First get client ID from email
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Get recent invoices
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
            // Get client information
            $client = DB::connection('crm')
                ->table('tblclients')
                ->join('tblcontacts', 'tblclients.userid', '=', 'tblcontacts.userid')
                ->where('tblcontacts.email', $email)
                ->select('tblclients.company', 'tblclients.active', 'tblcontacts.firstname', 'tblcontacts.lastname')
                ->first();
            
            if (!$client) {
                return "No account found with email {$email}.";
            }

            // Calculate account balance (simplified - adjust based on your CRM structure)
            $balance = 0; // You may need to calculate from invoices/payments
            
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
            // First get client ID from email
            $client = DB::connection('crm')
                ->table('tblcontacts')
                ->where('email', $email)
                ->select('userid')
                ->first();
            
            if (!$client) {
                return "No user found with email {$email}.";
            }

            // Get recent support tickets
            $tickets = DB::connection('crm')
                ->table('tbltickets')
                ->where('userid', $client->userid)
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

    private function handleProductQuery(string $message): string
    {
        try {
            $lowerMessage = strtolower($message);
            
            // Get products with stock information
            $products = DB::connection('crm')
                ->table('tblitems')
                ->select('id', 'description', 'long_description', 'rate', 'unit')
                // ->where('description', 'like', '%' . $message . '%')
                ->limit(10)
                ->get();
            
            if ($products->isEmpty()) {
                return "No products found in the inventory.";
            }

            $response = "Here are some of our products:\n\n";
            foreach ($products as $product) {
                $response .= "• {$product->description} - $" . number_format($product->rate, 2) . "\n";
            }

            
            return $response;

        } catch (\Exception $e) {
            Log::error('CRM Product Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving product information. Please try again.";
        }
    }

    private function handleStockQuery(string $message): string
    {
         try {
            $product = DB::connection('crm')
                ->table('tblitems')
                ->select('id', 'description', 'rate', 'unit')
                ->where('description','like', '%' . $message . '%')
                ->first();

            $warehouse_id = 1;
            $commodity_id = $product->id;

            $inventory_stock = DB::connection('crm')
                ->table('tblinventory_manage')
                ->select('warehouse_id', 'commodity_id', DB::raw('sum(inventory_number) as inventory_number'))
                ->where('warehouse_id', $warehouse_id)
                ->where('commodity_id', $commodity_id)
                ->groupBy('warehouse_id', 'commodity_id')
                ->first();

            
            if (!$product) {
                return "No products found in the catalog.";
            }

            $response = "Here is the product stock information:\n\n";
            $response .= "Product in stock: " . $inventory_stock->inventory_number . "\n\n";
            $response .= "Product: {$product->description}\n\n";
            $response .= "Price: $" . number_format($product->rate, 2) . "\n\n";
            $response .= "Unit: {$product->unit}\n\n";

            return $response . "\n\nHere is the details of this productFor more details about a specific product, please provide the product name.";

        } catch (\Exception $e) {
            Log::error('CRM Product Query Error: ' . $e->getMessage());
            return "Sorry, I encountered an error while retrieving product information. Please try again.";
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
                    'showContactInfo' => false // Will be determined by frontend based on message content
                ];
            })
        ]);
    }

    // public function getBroadcastConfig(): JsonResponse
    // {
    //     $driver = config('broadcasting.default');
        
    //     if ($driver === 'reverb') {
    //         return response()->json([
    //             'enabled' => true,
    //             'driver' => 'reverb',
    //             'key' => config('reverb.apps.apps.0.key') ?? config('reverb.app_key') ?? env('REVERB_APP_KEY'),
    //             'host' => env('VITE_REVERB_HOST') ?? env('REVERB_HOST'),
    //             'port' => (int) (env('VITE_REVERB_PORT') ?? env('REVERB_PORT')),
    //             'scheme' => env('VITE_REVERB_SCHEME') ?? env('REVERB_SCHEME'),
    //         ]);
    //     }
        
    //     return response()->json([
    //         'enabled' => false,
    //     ]);
    // }
}
