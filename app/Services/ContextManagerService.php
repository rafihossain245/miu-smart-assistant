<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ContextManagerService
{
    protected int $maxTokens = 3000;
    protected float $averageTokensPerWord = 1.3; // Rough estimate for English
    protected array $conversationEntities = []; // Track entities across conversation

    /**
     * Assemble context with proper prioritization and token management
     * Enhanced with entity tracking and conversation continuity
     */
    public function assembleContext(array $sources, array $products = [], string $conversationHistory = '', array $additionalContext = []): array
    {
        $contextParts = [];

        // Extract entities from conversation and products for reference tracking
        $this->updateConversationEntities($conversationHistory, $products);

        // Priority 1: Conversation continuity context (HIGHEST priority for context consistency)
        $entityContext = $this->buildEntityContext();
        if (!empty($entityContext)) {
            $contextParts[] = $entityContext;
        }

        // Priority 2: Recent conversation history (enhanced for better context)
        if (!empty($conversationHistory)) {
            $conversationContext = $this->formatConversationContext($conversationHistory);
            if (!empty($conversationContext)) {
                $contextParts[] = $conversationContext;
            }
        }

        // Priority 3: Most relevant sources (adjust based on conversation context)
        if (!empty($sources)) {
            $sourceContext = $this->formatSourcesContext($sources, 2, $this->conversationEntities);
            if (!empty($sourceContext)) {
                $contextParts[] = $sourceContext;
            }
        }

        // Priority 4: Relevant products/services (entity-aware)
        if (!empty($products)) {
            $productsContext = $this->formatProductsContext($products, 2);
            if (!empty($productsContext)) {
                $contextParts[] = $productsContext;
            }
        }

        // Priority 5: Additional context (learning data, etc.)
        if (!empty($additionalContext)) {
            $additionalContextString = $this->formatAdditionalContext($additionalContext);
            if (!empty($additionalContextString)) {
                $contextParts[] = $additionalContextString;
            }
        }

        // Ensure total context doesn't exceed token limits
        return $this->trimContextToTokenLimit($contextParts);
    }

    /**
     * Update conversation entities for reference tracking
     */
    protected function updateConversationEntities(string $conversationHistory, array $products = []): void
    {
        // Extract product names mentioned in conversation
        if (!empty($products)) {
            foreach ($products as $product) {
                $productName = $product['name'] ?? '';
                if (!empty($productName)) {
                    $this->conversationEntities['products'][$productName] = [
                        'name' => $productName,
                        'type' => $product['type'] ?? 'product',
                        'description' => $product['description'] ?? '',
                        'mentioned_at' => time()
                    ];
                }
            }
        }

        // Extract product names from conversation history
        if (!empty($conversationHistory)) {
            // Look for capitalized product names (common pattern for product mentions)
            preg_match_all('/\b([A-Z][a-z]+(?:[A-Z][a-z]+)*)\b/', $conversationHistory, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $entity) {
                    // Filter out common words that aren't products
                    if (!in_array(strtolower($entity), ['Assistant', 'User', 'Hello', 'How', 'Can', 'Help', 'You', 'I', 'That', 'This', 'The'])) {
                        $this->conversationEntities['mentioned_entities'][$entity] = [
                            'name' => $entity,
                            'mentioned_at' => time(),
                            'context' => 'conversation'
                        ];
                    }
                }
            }
        }

        // Clean up old entities (older than 30 minutes)
        $this->cleanupOldEntities();
    }

    /**
     * Build entity context for reference resolution
     */
    protected function buildEntityContext(): string
    {
        if (empty($this->conversationEntities)) {
            return '';
        }

        $context = "CONVERSATION CONTEXT & ENTITY REFERENCES:\n\n";

        // Add product entities
        if (!empty($this->conversationEntities['products'])) {
            $context .= "Previously mentioned products/services:\n";
            foreach ($this->conversationEntities['products'] as $product) {
                $context .= "- **{$product['name']}** ({$product['type']}): {$product['description']}\n";
            }
            $context .= "\n";
        }

        // Add general entities
        if (!empty($this->conversationEntities['mentioned_entities'])) {
            $context .= "Other entities mentioned in conversation:\n";
            foreach ($this->conversationEntities['mentioned_entities'] as $entity) {
                $context .= "- {$entity['name']}\n";
            }
            $context .= "\n";
        }

        $context .= "IMPORTANT: When the user refers to 'it', 'this', 'that', or 'them', they are likely referring to the most recently mentioned product or topic above.\n\n";

        return $context;
    }

    /**
     * Clean up old entities from conversation tracking
     */
    protected function cleanupOldEntities(): void
    {
        $thirtyMinutesAgo = time() - (30 * 60);

        foreach (['products', 'mentioned_entities'] as $type) {
            if (isset($this->conversationEntities[$type])) {
                $this->conversationEntities[$type] = array_filter(
                    $this->conversationEntities[$type],
                    fn($entity) => ($entity['mentioned_at'] ?? 0) > $thirtyMinutesAgo
                );
            }
        }
    }

    /**
     * Format sources into context string
     */
    protected function formatSourcesContext(array $sources, int $maxSources = 3, array $conversationEntities = []): string
    {
        if (empty($sources)) {
            return '';
        }

        $context = "KNOWLEDGE BASE SOURCES:\n\n";
        $sourcesUsed = 0;

        foreach ($sources as $source) {
            if ($sourcesUsed >= $maxSources) break;

            $similarity = isset($source['similarity']) ? round($source['similarity'] * 100, 1) : 'N/A';
            $content = isset($source['content']) ? $source['content'] : $source['title'] ?? '';

            $context .= "Source: {$source['title']} (Relevance: {$similarity}%)\n";
            $context .= "Type: {$source['type']}\n";
            $context .= "Content: " . substr($content, 0, 800) . (strlen($content) > 800 ? "..." : "") . "\n\n";

            $sourcesUsed++;
        }

        return $context;
    }

    /**
     * Format products into context string
     */
    protected function formatProductsContext(array $products, int $maxProducts = 2): string
    {
        if (empty($products)) {
            return '';
        }

        $context = "RELEVANT PRODUCTS/SERVICES:\n\n";
        $productsUsed = 0;

        foreach ($products as $product) {
            if ($productsUsed >= $maxProducts) break;

            $context .= "**{$product['name']}** ({$product['type']})\n";
            $context .= "Description: " . substr($product['description'], 0, 300) . "\n";

            if (!empty($product['key_benefits'])) {
                $benefits = is_array($product['key_benefits']) ? implode(', ', array_slice($product['key_benefits'], 0, 3)) : $product['key_benefits'];
                $context .= "Key Benefits: {$benefits}\n";
            }

            if (!empty($product['primary_url'])) {
                $context .= "URL: {$product['primary_url']}\n";
            }

            $context .= "\n";
            $productsUsed++;
        }

        return $context;
    }

    /**
     * Format conversation history with enhanced context awareness
     */
    protected function formatConversationContext(string $conversationHistory): string
    {
        if (empty(trim($conversationHistory))) {
            return '';
        }

        // Increase history length to capture more context
        $maxHistoryLength = 800;
        if (strlen($conversationHistory) > $maxHistoryLength) {
            $conversationHistory = "..." . substr($conversationHistory, -$maxHistoryLength);
        }

        $context = "RECENT CONVERSATION HISTORY:\n\n{$conversationHistory}\n\n";

        // Add context continuity instruction
        $context .= "CONVERSATION CONTINUITY RULES:\n";
        $context .= "- Maintain context from the conversation above\n";
        $context .= "- When user says 'it', 'this', 'that' refer to the most recently mentioned topic/product\n";
        $context .= "- Build upon previous discussion points rather than starting fresh\n";
        $context .= "- Keep the same topic flow unless user explicitly changes subject\n\n";

        return $context;
    }

    /**
     * Format additional context (learning data, etc.)
     */
    protected function formatAdditionalContext(array $additionalContext): string
    {
        if (empty($additionalContext)) {
            return '';
        }

        $validStrings = [];

        foreach ($additionalContext as $item) {
            if (is_string($item) && !empty(trim($item))) {
                $validStrings[] = trim($item);
            } elseif (is_array($item)) {
                // Handle array context items
                $stringItems = array_filter($item, 'is_string');
                if (!empty($stringItems)) {
                    $validStrings[] = implode("\n", $stringItems);
                }
            }
        }

        if (empty($validStrings)) {
            return '';
        }

        return "ADDITIONAL CONTEXT:\n\n" . implode("\n\n", $validStrings) . "\n\n";
    }

    /**
     * Trim context to fit within token limits
     */
    protected function trimContextToTokenLimit(array $contextParts): array
    {
        $totalTokens = 0;
        $finalContext = [];

        foreach ($contextParts as $part) {
            $partTokens = $this->estimateTokens($part);

            if ($totalTokens + $partTokens <= $this->maxTokens) {
                $finalContext[] = $part;
                $totalTokens += $partTokens;
            } else {
                // Try to fit a truncated version
                $remainingTokens = $this->maxTokens - $totalTokens;
                if ($remainingTokens > 100) { // Only if we have at least 100 tokens left
                    $truncatedPart = $this->truncateToTokens($part, $remainingTokens);
                    if (!empty($truncatedPart)) {
                        $finalContext[] = $truncatedPart;
                    }
                }
                break; // Stop adding more context
            }
        }

        Log::info('Context assembled', [
            'parts_count' => count($finalContext),
            'estimated_tokens' => $totalTokens,
            'max_tokens' => $this->maxTokens
        ]);

        return $finalContext;
    }

    /**
     * Estimate tokens in text (rough approximation)
     */
    protected function estimateTokens(string $text): int
    {
        $wordCount = str_word_count($text);
        return (int) ceil($wordCount * $this->averageTokensPerWord);
    }

    /**
     * Truncate text to approximately fit within token limit
     */
    protected function truncateToTokens(string $text, int $maxTokens): string
    {
        $maxWords = (int) floor($maxTokens / $this->averageTokensPerWord);
        $words = explode(' ', $text);

        if (count($words) <= $maxWords) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . '...';
    }

    /**
     * Set maximum tokens for context
     */
    public function setMaxTokens(int $tokens): void
    {
        $this->maxTokens = $tokens;
    }

    /**
     * Get current token limit
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }
}