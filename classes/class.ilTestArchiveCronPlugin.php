<?php

// Copyright (c) 2018 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

include_once("./Services/Cron/classes/class.ilCronHookPlugin.php");

class ilTestArchiveCronPlugin extends ilCronHookPlugin
{
    public function getPluginName(): string
    {
        return "TestArchiveCron";
    }

    public function getCronJobInstances(): array
    {
        return array($this->getCronJobInstance('test_archive_cron'));
    }

    public function getCronJobInstance($a_job_id): ilCronJob
    {
        return new ilTestArchiveCronJob($this);
    }

    /**
     * Do checks bofore activating the plugin
     * @return bool
     * @throws ilPluginException
     */
    public function beforeActivation(): bool
    {
        global $DIC;

        if ($this->isActive()) {
            return false;
        }

        if (!$this->checkCreatorPluginActive()) {
            throw new ilPluginException($this->txt("message_creator_plugin_missing"));
            return false;
        }

        return parent::beforeActivation();
    }

    /**
     * Check if the player plugin is active
     * @return bool
     */
    public function checkCreatorPluginActive()
    {
        if (!empty($plugin = $this->getCreatorPlugin())) {
            return $plugin->isActive();
        }
        return false;
    }

    /**
     * Get the creator plugin object
     * @return ilPlugin|null
     */
    public function getCreatorPlugin(): ?ilPlugin
    {
        /** @var \ILIAS\DI\Container $DIC */
        global $DIC;

        try {
            /** @var ilComponentFactory $factory */
            $factory = $DIC["component.factory"];

            /** @var ilPlugin $plugin */
            foreach ($factory->getActivePluginsInSlot('uihk') as $plugin) {
                if ($plugin->getPluginName() == 'TestArchiveCreator') {
                    return $plugin;
                }
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }
}
