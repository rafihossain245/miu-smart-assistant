<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSourceContent;
use App\Models\Chatbot;
use App\Models\Source;
use Illuminate\Console\Command;

class ImportCrawledTextFiles extends Command
{
    protected $signature = 'sources:import-txt {chatbot_id} {path} {--dry-run}';

    protected $description = 'Bulk-import crawled .txt page dumps as text sources, stripping common site boilerplate and skipping 404 pages';

    protected array $boilerplatePatterns = [
        '/Career Webmail Contact MIU Portal Home.*?Quick links/s',
        '/Visitor Views Today\s*:.*?Total views\s*:\s*\d+/s',
        '/\[Sassy_Social_Share[^\]]*\]/',
        '/Latest Social Media Post.*$/s',
    ];

    public function handle(): int
    {
        $chatbot = Chatbot::findOrFail($this->argument('chatbot_id'));
        $path = rtrim($this->argument('path'), '/');
        $dryRun = (bool) $this->option('dry-run');

        $files = glob("{$path}/*.txt");

        if (empty($files)) {
            $this->error("No .txt files found in {$path}");
            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $raw = file_get_contents($file);

            if (str_contains($raw, '404 not found')) {
                $this->warn('Skipping 404 page: ' . basename($file));
                $skipped++;
                continue;
            }

            $content = $this->clean($raw);

            if (strlen($content) < 50) {
                $this->warn('Skipping near-empty file after cleaning: ' . basename($file));
                $skipped++;
                continue;
            }

            $title = $this->titleFromFilename($file);

            $this->line("Importing \"{$title}\" (" . strlen($content) . ' chars)');

            if (!$dryRun) {
                $source = Source::create([
                    'chatbot_id' => $chatbot->id,
                    'type' => 'text',
                    'title' => $title,
                    'content' => $content,
                ]);

                ProcessSourceContent::dispatch($source);
            }

            $imported++;
        }

        $this->newLine();
        $this->info("Imported: {$imported}, Skipped: {$skipped}");

        if (!$dryRun && $imported > 0) {
            $this->info('Run `php artisan queue:work` to process the queued jobs, then `php artisan sources:check-status ' . $chatbot->id . '` to verify.');
        }

        return self::SUCCESS;
    }

    protected function clean(string $text): string
    {
        foreach ($this->boilerplatePatterns as $pattern) {
            $text = preg_replace($pattern, ' ', $text);
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    protected function titleFromFilename(string $file): string
    {
        $name = basename($file, '.txt');
        $name = preg_replace('/^manarat\.ac\.bd_/', '', $name);
        $name = trim($name, '_?=');
        $name = str_replace(['_', '-'], ' ', $name);
        $name = trim($name);

        return $name === '' ? 'MIU Homepage' : ucwords($name);
    }
}
