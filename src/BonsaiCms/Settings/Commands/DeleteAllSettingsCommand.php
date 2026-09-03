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

    protected $settingsManager;

    protected $repositoryFactory;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(SettingsManager $settingsManager, SettingsRepositoryFactory $repositoryFactory)
    {
        parent::__construct();

        $this->settingsManager = $settingsManager;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $driver = $this->option('driver');

        if ($driver) {
            /*
             * Named driver: go straight to the repository. The manager holds
             * the default driver, so its in memory cache is none of this
             * command's business.
             */
            $this->repositoryFactory->driver($driver)->deleteAll();
        } else {
            $this->settingsManager->deleteAll();
        }

        $this->info('Settings were successfully deleted.');

        return 0;
    }
}
