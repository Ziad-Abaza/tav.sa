<?php

namespace App\Console\Commands;

use App\Services\ModuleAssetManager;
use Illuminate\Console\Command;

class RebuildModuleManifest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:rebuild-manifest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the merged module asset manifest from all active modules';

    /**
     * Execute the console command.
     */
    public function handle(ModuleAssetManager $manager): int
    {
        $this->info('Rebuilding module manifest...');

        try {
            $manager->rebuildManifest();

            $this->info('✓ Module manifest rebuilt successfully!');
            $this->line('   Location: public/build/modules-manifest.json');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to rebuild manifest: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
