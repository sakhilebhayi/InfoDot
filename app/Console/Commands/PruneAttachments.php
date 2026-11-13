<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\Attachments\AttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** R-15: uploads that no form ever used expire after a day; this removes them and their files. Scheduled nightly. */
class PruneAttachments extends Command
{
    protected $signature = 'attachments:prune {--dry : Count only; delete nothing}';

    protected $description = 'Delete unbound attachment uploads whose day has passed, and attachments whose owner record is gone (R-15)';

    public function handle(AttachmentService $service): int
    {
        if ($this->option('dry')) {
            $this->info(Attachment::expired()->count().' expired unbound attachment(s) would be deleted.');

            return self::SUCCESS;
        }

        $this->info($service->prune().' expired unbound attachment(s) deleted.');

        $orphans = $service->pruneOrphans();
        $this->info($orphans.' attachment(s) whose owner record is gone deleted.');

        if ($orphans > 0) {
            Log::info('attachments.orphans_pruned', ['count' => $orphans]);
        }

        return self::SUCCESS;
    }
}
