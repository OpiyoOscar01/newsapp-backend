<?php

namespace App\Console\Commands;

use App\Services\MediaStackService;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature = 'news:fetch 
                           {--categories= : Comma-separated list of categories}
                           {--sources= : Comma-separated list of sources}
                           {--countries= : Comma-separated list of countries}
                           {--languages= : Comma-separated list of languages}
                           {--limit=100 : Number of articles to fetch}';

    protected $description = 'Fetch news from MediaStack API';

    public function handle(MediaStackService $mediaStackService): int
    {
        $this->info('Starting news fetch from MediaStack...');

        $params = array_filter([
            'categories' => $this->option('categories'),
            'sources' => $this->option('sources'),
            'countries' => $this->option('countries'),
            'languages' => $this->option('languages'),
            'limit' => $this->option('limit'),
        ]);

        try {
            $result = $mediaStackService->fetchNews($params);

            $this->info("✅ Success! Fetched {$result['articles_fetched']} articles, processed {$result['articles_processed']}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
