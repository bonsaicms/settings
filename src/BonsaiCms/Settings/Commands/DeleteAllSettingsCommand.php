<?php

namespace BonsaiCms\Settings\Commands;

use Illuminate\Console\Command;
use BonsaiCms\Settings\Contracts\SettingsManager;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;

class DeleteAllSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:delete-all
                            {--driver= : Delete from this driver instead of the default one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all settings';

    public function __construct(
        protected readonly SettingsManager $settingsManager,
        protected readonly SettingsRepositoryFactory $repositoryFactory
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $driver = $this->option('driver');

        if (is_string($driver) && $driver !== '') {
            /*
             * Named driver: go straight to the repository. The manager holds
             * the default driver, so this command has no business emptying
             * its in memory cache.
             */
            $this->repositoryFactory->driver($driver)->deleteAll();
        } else {
            $this->settingsManager->deleteAll();
        }

        $this->info('Settings were successfully deleted.');

        return static::SUCCESS;
    }
}
