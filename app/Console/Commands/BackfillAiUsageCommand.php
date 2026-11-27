<?php

namespace App\Console\Commands;

use App\Actions\Ai\BackfillAiUsageEvents;
use Illuminate\Console\Command;

class BackfillAiUsageCommand extends Command
{
    protected $signature = 'ai:backfill-usage';

    protected $description = 'Backfill AI usage events from stored assistant conversation messages';

    public function handle(BackfillAiUsageEvents $backfill): int
    {
        $result = $backfill->handle();

        $this->info(sprintf(
            'Imported %d historical AI usage events (%d skipped).',
            $result['imported'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
