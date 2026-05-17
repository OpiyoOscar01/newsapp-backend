<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InspectMediaStackSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Fetching 1 article directly from MediaStack API (no DB storage)...');

        $apiKey = config('mediastack.api_key');
        $apiUrl = config('mediastack.api_url');

        $response = Http::timeout(30)->get($apiUrl, [
            'access_key' => $apiKey,
            'limit' => 1,
            'languages' => 'en',
            'countries' => 'us',
            'sort' => 'published_desc',
        ]);

        if (!$response->successful()) {
            $error = $response->json();
            Log::error('=== MEDIASTACK API ERROR ===', [
                'status' => $response->status(),
                'error' => $error,
            ]);
            $this->command->error('API call failed: ' . ($error['error']['message'] ?? $response->body()));
            return;
        }

        $raw = $response->json();
        $article = $raw['data'][0] ?? null;

        if (!$article) {
            $this->command->error('No article data returned');
            return;
        }

        // ─────────────────────────────────────────────────────────────────────
        // SECTION 1 — API Response Envelope
        // ─────────────────────────────────────────────────────────────────────
        Log::info('═══════════════════════════════════════════════════════');
        Log::info('MEDIASTACK API — RAW ARTICLE INSPECTION');
        Log::info('═══════════════════════════════════════════════════════');

        Log::info('');

        Log::info('─── RESPONSE ENVELOPE ───');
        Log::info('Pagination', $raw['pagination'] ?? []);

        Log::info('');

        // ─────────────────────────────────────────────────────────────────────
        // SECTION 2 — All Fields (incoming API keys)
        // ─────────────────────────────────────────────────────────────────────
        Log::info('─── ARTICLE FIELDS (from API) ───');
        Log::info(sprintf('Total fields returned: %d', count($article)));

        Log::info('');

        $fieldOrder = ['title', 'description', 'author', 'url', 'source', 'image', 'category', 'language', 'country', 'published_at'];

        foreach ($fieldOrder as $key) {
            if (!array_key_exists($key, $article)) {
                continue;
            }
            $value = $article[$key];
            $type = gettype($value);
            $length = is_string($value) ? strlen($value) : null;
            $isNull = is_null($value);

            $label = str_pad($key, 20, ' ', STR_PAD_RIGHT);
            $typeInfo = $isNull ? 'NULL' : "{$type}" . ($length !== null ? " ({$length} chars)" : '');

            Log::info(sprintf('  %s | %s | %s',
                $label,
                str_pad($typeInfo, 22, ' ', STR_PAD_RIGHT),
                $isNull ? '—' : $value
            ));
        }

        Log::info('');

        // ─────────────────────────────────────────────────────────────────────
        // SECTION 3 — Content / Body Check
        // ─────────────────────────────────────────────────────────────────────
        Log::info('─── FULL CONTENT CHECK ───');

        $contentLike = ['content', 'body', 'article_body', 'full_text', 'text', 'summary'];
        foreach ($contentLike as $possibleKey) {
            $found = array_key_exists($possibleKey, $article);
            Log::info(sprintf('  Field "%s": %s', $possibleKey, $found ? '✓ EXISTS' : '✗ NOT PRESENT'));
        }

        Log::info('');

        // ─────────────────────────────────────────────────────────────────────
        // SECTION 4 — Extra / Unknown Fields
        // ─────────────────────────────────────────────────────────────────────
        $expected = ['author', 'title', 'description', 'url', 'source', 'image', 'category', 'language', 'country', 'published_at'];
        $extra = array_diff(array_keys($article), $expected);

        Log::info('─── UNEXPECTED / EXTRA FIELDS ───');
        if (empty($extra)) {
            Log::info('  None — only the 10 standard fields are present.');
        } else {
            foreach ($extra as $key) {
                Log::info(sprintf('  + %s: %s', $key, json_encode($article[$key])));
            }
        }

        Log::info('');

        // ─────────────────────────────────────────────────────────────────────
        // SECTION 5 — Summary
        // ─────────────────────────────────────────────────────────────────────
        Log::info('─── SUMMARY ───');
        Log::info(sprintf('  Title length        : %d chars', strlen($article['title'] ?? '')));
        Log::info(sprintf('  Description length  : %d chars', strlen($article['description'] ?? '')));
        Log::info(sprintf('  Has full content?   : %s', isset($article['content']) ? 'YES' : 'NO — MediaStack is headline/summary only'));
        Log::info(sprintf('  Image provided?     : %s', !empty($article['image']) ? 'YES' : 'NO'));
        Log::info(sprintf('  Source              : %s', $article['source'] ?? 'N/A'));
        Log::info(sprintf('  Category            : %s', $article['category'] ?? 'N/A'));

        Log::info('');
        Log::info('═══════════════════════════════════════════════════════');

        // ─────────────────────────────────────────────────────────────────────
        // Console output
        // ─────────────────────────────────────────────────────────────────────
        $this->command->info('── Article fetched ──');
        $this->command->line("  Title       : {$article['title']}");
        $this->command->line("  Description : " . substr($article['description'] ?? 'N/A', 0, 120) . '...');
        $this->command->line("  Author      : {$article['author']}");
        $this->command->line("  Source      : {$article['source']}");
        $this->command->line("  Category    : {$article['category']}");
        $this->command->line("  URL         : {$article['url']}");
        $this->command->line("");
        $this->command->info('Full structured log written to storage/logs/laravel.log');
    }
}
