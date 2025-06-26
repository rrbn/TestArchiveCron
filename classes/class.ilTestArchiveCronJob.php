<?php

// Copyright (c) 2018 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\Cron\Schedule\CronJobScheduleType;

class ilTestArchiveCronJob extends ilCronJob
{
    private ilCronJobRepository $repository;
    private ilTestArchiveCronPlugin $plugin;
    private ilObjUser $user;
    private ?ilDateTime $last_run = null;
    private bool $is_active = false;
    private $loaded = false;

    public function __construct($plugin)
    {
        global $DIC;

        $this->plugin = $plugin;
        $this->user = $DIC->user();
        $this->repository = $DIC->cron()->repository();
    }

    public function getId(): string
    {
        return "test_archive_cron";
    }

    public function getTitle(): string
    {
        return $this->plugin->txt('job_title');
    }

    public function getDescription(): string
    {
        if (!$this->plugin->checkCreatorPluginActive()) {
            return $this->plugin->txt('message_creator_plugin_missing');
        }
        return $this->plugin->txt('job_description');
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return  CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS;
    }

    public function getDefaultScheduleValue(): int
    {
        return 1;
    }

    public function hasAutoActivation(): bool
    {
        return true;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function hasCustomSettings(): bool
    {
        return true;
    }

    public function isManuallyExecutable(): bool
    {
        if (!$this->plugin->checkCreatorPluginActive()) {
            return false;
        }
        return parent::isManuallyExecutable();
    }

    public function run(): ilCronJobResult
    {
        $result = new ilCronJobResult();

        if (!$this->plugin->checkCreatorPluginActive()) {
            $result->setStatus(ilCronJobResult::STATUS_INVALID_CONFIGURATION);
            $result->setMessage($this->plugin->txt('message_creator_plugin_missing'));
            return $result;
        } else {
            /** @var ilTestArchiveCreatorPlugin $creatorPlugin */
            $creatorPlugin = $this->plugin->getCreatorPlugin();
            $number = $creatorPlugin->handleCronJob();
            if ($number == 0) {
                $result->setStatus(ilCronJobResult::STATUS_NO_ACTION);
                $result->setMessage($this->plugin->txt('no_archive_created'));
            } elseif ($number == 1) {
                $result->setStatus(ilCronJobResult::STATUS_OK);
                $result->setMessage($this->plugin->txt('one_archive_created'));

            } else {
                $result->setStatus(ilCronJobResult::STATUS_OK);
                $result->setMessage(sprintf($this->plugin->txt('x_archives_created'), $number));
            }

            return $result;
        }
    }

    public function addCustomSettingsToForm(ilPropertyFormGUI $a_form): void
    {
        $setrun = new ilCheckboxInputGUI($this->plugin->txt('set_last_run'), 'set_last_run');
        $setrun->setInfo($this->plugin->txt('set_last_run_info'));
        $a_form->addItem($setrun);

        $lastrun = new ilDateTimeInputGUI($this->plugin->txt('last_run'), 'last_run');
        $lastrun->setShowTime(true);
        $lastrun->setShowSeconds(false);
        $lastrun->setMinuteStepSize(10);
        $lastrun->setDate($this->getLastRun());
        $setrun->addSubItem($lastrun);
    }

    public function saveCustomSettings(ilPropertyFormGUI $a_form): bool
    {
        if ($a_form->getInput('set_last_run')) {
            /** @var ilDateTimeInputGUI $last_run */
            $last_run = $a_form->getItemByPostVar('last_run');

            /** @var ilDateTime $date */
            $date = $last_run->getDate();

            if (isset($date)) {
                $when = DateTimeImmutable::createFromFormat('U', (string) $date->getUnixTime());

                $result = new ilCronJobResult();
                $result->setStatus(ilCronJobResult::STATUS_RESET);
                $result->setCode(ilCronJobResult::CODE_MANUAL_RESET);
                $result->setMessage('');
                $result->setDuration(0);

                $this->repository->updateJobResult($this, $when, $this->user, $result);
            }
        }

        return true;
    }

    public function loadData(): void
    {
        if (!$this->loaded) {
            $rows = $this->repository->getCronJobData($this->getId());
            if (isset($rows[0])) {
                $data = $rows[0];

                $this->is_active = (bool) ($data['job_status'] ?? false);

                $this->setSchedule(
                    CronJobScheduleType::tryFrom((int) ($data['schedule_type'] ?? 0)),
                    (int) ($data['schedule_value'] ?? 0)
                );

                $ts = $data['job_result_ts'] ?? 0;
                if ($ts > 0) {
                    $this->last_run = new ilDateTime($ts, IL_CAL_UNIX, $this->user->getTimeZone());
                }
            }
            $this->loaded = true;
        }
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getLastRun(): ?ilDateTime
    {
        $this->loadData();
        return $this->last_run;
    }

    public function getScheduleType(): ?CronJobScheduleType
    {
       $this->loadData();
       return parent::getScheduleType();
    }

    public function getScheduleValue(): ?int
    {
        $this->loadData();
        return parent::getScheduleValue();
    }
}
