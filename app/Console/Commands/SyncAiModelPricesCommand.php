<?php

namespace App\Console\Commands;

use App\Actions\Ai\SyncModelsDevPricing;
use Illuminate\Console\Command;

class SyncAiModelPricesCommand extends Command
{
    protected $signature = 'ai:sync-model-prices';

    protected $description = 'Sync AI model token pricing from models.dev and re-price unpriced usage events';

    public function handle(SyncModelsDevPricing $sync): int
    {
        $result = $sync->handle();

        $this->info(sprintf(
            'Synced %d models from models.dev and repriced %d usage events.',
            $result['synced_models'],
            $result['repriced_events'],
        ));

        return self::SUCCESS;
    }
}
