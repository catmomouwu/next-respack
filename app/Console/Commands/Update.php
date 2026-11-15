<?php

namespace App\Console\Commands;

use App\Services\Installation\InstallState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update extends Command
{
    protected $signature = 'nr:update
        {--sync-formats : Synchronize the bundled Minecraft pack format dataset after updating}';

    protected $description = 'Run database migrations and clear caches on an installed Next Respack instance';

    public function handle(InstallState $state): int
    {
        if (! $state->installed()) {
            $this->error(__('Next Respack is not installed yet. Run `php artisan nr:install` first.'));

            return self::FAILURE;
        }

        $this->info(__('Applying database migrations…'));

        if (! $this->runArtisan('migrate', ['--force' => true])) {
            return self::FAILURE;
        }

        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            if (! $this->runArtisan($command)) {
                return self::FAILURE;
            }
        }

        if ($this->option('sync-formats')) {
            $this->info(__('Synchronizing Minecraft pack format data…'));

            if (! $this->runArtisan('pack-formats:sync', ['--trigger' => 'update'])) {
                return self::FAILURE;
            }
        }

        $this->info(__('Next Respack was updated successfully.'));

        return self::SUCCESS;
    }

    /**
     * Run a nested artisan command, streaming its output to the console.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(string $command, array $parameters = []): bool
    {
        try {
            $exitCode = Artisan::call($command, $parameters, $this->output->getOutput());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return false;
        }

        if ($exitCode !== 0) {
            $this->error(__('The :command command failed with exit code :code.', ['command' => $command, 'code' => $exitCode]));

            return false;
        }

        return true;
    }
}
