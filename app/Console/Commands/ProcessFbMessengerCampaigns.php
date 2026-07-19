<?php

namespace App\Console\Commands;

use App\Facades\Tenant;
use App\Jobs\SendFbMessengerCampaignJob;
use App\Models\Tenant\FbMessengerCampaign;
use App\Models\Tenant\FbMessengerCampaignDetail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ProcessFbMessengerCampaigns extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fb-campaigns:process-scheduled {--tenant=*} {--batch-size=100} {--delay=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Facebook Messenger campaigns with scheduled_send_time';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $now = Carbon::now();

        $batchSize = (int) $this->option('batch-size');
        $delayBetweenMessages = (int) $this->option('delay');

        $this->info("Starting FB Messenger campaign processing at {$now->toDateTimeString()}");
        $this->info("Batch size: {$batchSize}, Delay between batches: {$delayBetweenMessages}s");

        // Get campaigns that need to be sent now
        $campaigns = FbMessengerCampaign::readyToSend()->get();

        if ($campaigns->isEmpty()) {
            $this->info('No scheduled FB Messenger campaigns to process.');

            return self::SUCCESS;
        }

        $totalProcessed = 0;

        foreach ($campaigns as $campaign) {
            $this->info("Processing FB Messenger campaign: {$campaign->name} (ID: {$campaign->id})");

            try {
                // Count messages to queue
                $messageCount = FbMessengerCampaignDetail::where('campaign_id', $campaign->id)
                    ->where('status', FbMessengerCampaignDetail::STATUS_PENDING)
                    ->count();

                if ($messageCount === 0) {
                    $this->warn("No messages to send for campaign: {$campaign->name}");
                    $campaign->update(['is_sent' => true]);

                    continue;
                }

                $this->info("Found {$messageCount} messages to queue");

                $batchNumber = 0;

                // Process campaign details in chunks
                FbMessengerCampaignDetail::where('campaign_id', $campaign->id)
                    ->where('status', FbMessengerCampaignDetail::STATUS_PENDING)
                    ->chunk($batchSize, function ($chunk) use ($campaign, $now, &$totalProcessed, &$batchNumber, $delayBetweenMessages) {
                        foreach ($chunk as $index => $detail) {
                            // Calculate delay for rate limiting (100/batch with 1s delay between)
                            $messageDelay = ($batchNumber * $delayBetweenMessages);

                            // Additional delay for scheduled campaigns
                            if (! $campaign->send_now && $campaign->scheduled_send_time && $now->lessThan($campaign->scheduled_send_time)) {
                                $messageDelay += $now->diffInSeconds($campaign->scheduled_send_time);
                            }

                            // Create and dispatch job
                            $job = new SendFbMessengerCampaignJob(
                                $detail->id,
                                $campaign->id,
                                Tenant::current()->id
                            );

                            if ($messageDelay > 0) {
                                $job->delay($messageDelay);
                            }

                            dispatch($job);

                            $totalProcessed++;
                        }

                        $batchNumber++;

                        // Clear memory after each chunk
                        DB::connection()->disableQueryLog();
                        gc_collect_cycles();
                    });

                // Mark campaign as sent
                $campaign->update(['is_sent' => true]);

                $this->info("Successfully queued campaign: {$campaign->name}");
            } catch (\Throwable $e) {
                $this->error("Error processing campaign {$campaign->name}: {$e->getMessage()}");
                app_log('FB Messenger campaign scheduling error', 'error', $e, [
                    'campaign_id' => $campaign->id,
                ]);
            }
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info("Completed! Total messages queued: {$totalProcessed} in {$executionTime}s");

        return self::SUCCESS;
    }
}
