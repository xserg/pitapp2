<?php

namespace App\Console\Commands;

use Carbon\Carbon;

// Set a flag to prevent cron from calling command while in use
class CronFlag
{

    const FLAG_PATH = '/storage/flags/cron.flag';
    const FLAG_TIMEOUT = '6'; // 6 minutes
    const EC2_CURL_URL = 'http://169.254.169.254/latest/meta-data/instance-id';
    protected $flag;
    protected $instanceId;

    public function __construct()
    {
        $this->flag = base_path() . self::FLAG_PATH;
        $this->instanceId = $this->getInstanceId();
    }

    /**
     * @return string
     */
    protected function getInstanceId()
    {
        try {
            $ch = curl_init(self::EC2_CURL_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

            if ($result === false) {
                return 'No Instance ID';
            }

            return $result;
        } catch(\Exception $e) {
            return 'No Instance ID';
        }
    }

    /**
     * @return bool
     */
    public function setFlag()
    {
        if (file_exists($this->flag) && $this->lastModifiedTime() > self::FLAG_TIMEOUT && file_get_contents($this->flag) !== $this->instanceId) {
            unlink($this->flag);
        } 

        if (!file_exists($this->flag)) {
            // set cron flag with instance id
            @file_put_contents($this->flag, $this->instanceId);
            usleep(200000);
            if (file_exists($this->flag) && file_get_contents($this->flag) == $this->instanceId) {
                return true;
            }
        } else if (file_get_contents($this->flag) === $this->instanceId) {
            // renew the timestamp if we're the leader
            touch($this->flag);
            return true;
        }

        return false;
    }

    /**
     * Gets the last modified time of the flag file in minutes
     * @return int
     */
    protected function lastModifiedTime()
    {
        $currentTime = Carbon::now()->timestamp;
        $flagTime = filemtime($this->flag);
        return (int)(($currentTime - $flagTime) / 60);
    }
}