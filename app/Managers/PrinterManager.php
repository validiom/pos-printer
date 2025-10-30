<?php
namespace App\Managers;

use App\Enums\PrinterConfigs;

class PrinterManager
{
    const CONFIG_PATH = "printer.";


    public function hasConfig($conf):bool
    {
        return in_array($conf, PrinterConfigs::$allowed);
    }


    public function getConfig($conf)
    {
        return config(self::CONFIG_PATH.$conf);
    }

    public function setConfig($conf, $value): void
    {
        config([self::CONFIG_PATH.$conf => $value]);
    }
}
