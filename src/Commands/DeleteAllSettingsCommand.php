<?php

namespace BonsaiCms\Settings\Commands;

use Illuminate\Console\Command;
use BonsaiCms\Settings\Contracts\SettingsManager;

class DeleteAllSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:delete-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all settings';

    protected $settingsManager;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(SettingsManager $settingsManager)
    {
        parent::__construct();

        $this->settingsManager = $settingsManager;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->settingsManager->deleteAll();

        $this->info('Settings were successfully deleted.');

        return 0;
    }
}
