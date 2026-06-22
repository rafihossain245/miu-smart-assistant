<?php

namespace App\Services;

use OpenAI;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function generateEmbedding(string $text): array
    {
        try {
            // Use OpenRouter's embedding endpoint which routes to OpenAI
            $client = OpenAI::factory()
                ->withApiKey(config('services.openai.api_key'))
                ->withBaseUri(config('services.openai.base_url'))
                ->make();

            $response = $client->embeddings()->create([
                'model' => config('services.openai.embedding_model', 'openai/text-embedding-3-small'),
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding;
        } catch (\Exception $e) {
            Log::error('OpenAI Embedding Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateChatResponse(string $systemPrompt, string $userMessage, array $context = []): string
    {
        try {
            $response = $this->generateChatResponseWithUsage($systemPrompt, $userMessage, $context);
            return $response['content'];
        } catch (\Throwable $e) {
            Log::error('OpenAI Chat Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateChatResponseWithUsage(string $systemPrompt, string $userMessage, array $context = []): array
    {
        try {
            $client = OpenAI::factory()
                ->withApiKey(config('services.openai.api_key'))
                ->withBaseUri(config('services.openai.base_url'))
                ->make();

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            if (!empty($context)) {
                // Ensure all context items are properly converted to strings
                $contextStrings = array_map(function($item) {
                    if (is_string($item)) {
                        return $item;
                    } elseif (is_array($item)) {
                        // For arrays, check if they contain meaningful string content
                        $filtered = array_filter($item, 'is_string');
                        return !empty($filtered) ? implode("\n", $filtered) : '';
                    } else {
                        return (string) $item;
                    }
                }, $context);

                // Filter out empty strings and join
                $validContextStrings = array_filter($contextStrings, function($str) {
                    return !empty(trim($str));
                });

                if (!empty($validContextStrings)) {
                    $contextMessage = "Context from knowledge base:\n\n" . implode("\n\n", $validContextStrings);
                    $messages[] = ['role' => 'system', 'content' => $contextMessage];
                }
            }

            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $response = $client->chat()->create([
                'model' => config('services.openai.chat_model', 'gpt-4o-mini'),
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);

            $content = $response->choices[0]->message->content ?? null;
            if (!is_string($content) || trim($content) === '') {
                Log::warning('OpenAI Chat returned empty content', [
                    'model' => config('services.openai.chat_model', 'gpt-4o-mini'),
                    'finish_reason' => $response->choices[0]->finishReason ?? null,
                ]);

                $content = 'I found relevant information, but I could not generate a complete answer right now. Please try again.';
            }

            return [
                'content' => $content,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens ?? 0,
                    'completion_tokens' => $response->usage->completionTokens ?? 0,
                    'total_tokens' => $response->usage->totalTokens ?? 0,
                ],
                'model' => config('services.openai.chat_model', 'gpt-4o-mini'),
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI Chat Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function chunkText(string $text, int $maxChunkSize = 1000): array
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;

            if (strlen($currentChunk . ' ' . $sentence) <= $maxChunkSize) {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence . '.';
            } else {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence . '.';
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return array_filter($chunks);
    }

    public function extractTextFromImage($imageFile): string
    {
        try {
            $client = OpenAI::factory()
                ->withApiKey(config('services.openai.api_key'))
                ->withBaseUri(config('services.openai.base_url'))
                ->make();

            // Convert image to base64
            $imageData = base64_encode(file_get_contents($imageFile->getPathname()));
            $mimeType = $imageFile->getMimeType();

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract all text from this image, especially focusing on conversations between customers and support representatives. Preserve the structure and format as much as possible.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageData}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 2000,
            ]);

            return $response->choices[0]->message->content;
        } catch (\Exception $e) {
            Log::error('OpenAI Vision Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generateText(string $prompt, int $maxTokens = 1000): string
    {
        try {
            $client = OpenAI::factory()
                ->withApiKey(config('services.openai.api_key'))
                ->withBaseUri(config('services.openai.base_url'))
                ->make();

            $response = $client->chat()->create([
                'model' => config('services.openai.chat_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $maxTokens,
                'temperature' => 0.3,
            ]);

            return $response->choices[0]->message->content;
        } catch (\Exception $e) {
            Log::error('OpenAI Text Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
