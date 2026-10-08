<?php

namespace App\Services\Metrics;

use PDO;
use Prometheus\Storage\PDO as PdoAdapter;
use RuntimeException;

class SqliteStorage extends PdoAdapter
{
    protected function createTables(): void
    {
        if ($this->database->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException(
                'SqliteStorage only supports the sqlite driver; '
                .'got '.$this->database->getAttribute(PDO::ATTR_DRIVER_NAME).'.'
            );
        }

        $this->database->exec(
            "CREATE TABLE IF NOT EXISTS `{$this->prefix}_metadata` (
                `name` varchar(255) NOT NULL,
                `type` varchar(9) NOT NULL,
                `metadata` text NOT NULL,
                PRIMARY KEY (`name`, `type`)
            )"
        );



        $this->database->exec(
            "CREATE TABLE IF NOT EXISTS `{$this->prefix}_values` (
                `name` varchar(255) NOT NULL,
                `type` varchar(9) NOT NULL,
                `labels_hash` varchar(32) NOT NULL,
                `labels` TEXT NOT NULL,
                `value` double DEFAULT 0.0,
                PRIMARY KEY (`name`, `type`, `labels_hash`)
            )"
        );

        $this->database->exec(
            "CREATE TABLE IF NOT EXISTS `{$this->prefix}_summaries` (
                `name` varchar(255) NOT NULL,
                `labels_hash` varchar(32) NOT NULL,
                `labels` TEXT NOT NULL,
                `value` double DEFAULT 0.0,
                `time` timestamp NOT NULL
            )"
        );

        $this->database->exec(
            "CREATE INDEX IF NOT EXISTS `name` ON `{$this->prefix}_summaries`(`name`)"
        );

        $this->database->exec(
            "CREATE TABLE IF NOT EXISTS `{$this->prefix}_histograms` (
                `name` varchar(255) NOT NULL,
                `labels_hash` varchar(32) NOT NULL,
                `labels` TEXT NOT NULL,
                `value` double DEFAULT 0.0,
                `bucket` varchar(255) NOT NULL,
                PRIMARY KEY (`name`, `labels_hash`, `bucket`)
            )"
        );
    }

    }
