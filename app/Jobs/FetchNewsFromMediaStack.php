<?php

namespace App\Jobs;

use App\Services\MediaStackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchNewsFromMediaStack implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct(
        private array $params = []
    ) {}

    public function handle(MediaStackService $mediaStackService): void
    {
        try {
            Log::info('Starting automated news fetch from MediaStack', [
                'params' => $this->params
            ]);

            $result = $mediaStackService->fetchNews($this->params);

            Log::info('Automated news fetch completed successfully', [
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Automated news fetch failed', [
                'error' => $e->getMessage(),
                'params' => $this->params,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Exception $exception): void
    {
        Log::error('FetchNewsFromMediaStack job failed permanently', [
            'error' => $exception->getMessage(),
            'params' => $this->params
        ]);
    }
}
