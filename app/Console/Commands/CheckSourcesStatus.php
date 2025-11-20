<?php

namespace App\Console\Commands;

use App\Models\Source;
use Illuminate\Console\Command;

class CheckSourcesStatus extends Command
{
    protected $signature = 'sources:check-status {chatbot_id?}';
    protected $description = 'Check the status of sources and their embeddings';

    public function handle()
    {
        $chatbotId = $this->argument('chatbot_id');

        $query = Source::query();
        if ($chatbotId) {
            $query->where('chatbot_id', $chatbotId);
        }

        $sources = $query->get();

        $this->info("Found {$sources->count()} sources:");
        $this->newLine();

        $statusCounts = [];

        foreach ($sources as $source) {
            $hasEmbedding = !empty($source->embedding) ? 'Yes' : 'No';
            $this->line("ID: {$source->id}, Chatbot: {$source->chatbot_id}, Status: {$source->status}, Has Embedding: {$hasEmbedding}, Title: {$source->title}");

            if ($source->error_message) {
                $this->error("  Error: {$source->error_message}");
            }

            $statusCounts[$source->status] = ($statusCounts[$source->status] ?? 0) + 1;
        }

        $this->newLine();
        $this->info("Status Summary:");
        foreach ($statusCounts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
    }
}