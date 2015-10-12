#!/bin/bash
<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 2015/10/6
 * Time: 16:12
 */

require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$crontab = new Crontab();
$crontab->start();

class Crontab
{

    /**
     * @var \Jenner\Crontab\AbstractDaemon
     */
    protected $daemon;
    /**
     * @var array
     */
    protected $params;

    protected $config_file;

    protected $port;

    protected $pid_file;

    protected $log;

    protected $missions;

    /**
     * @var array
     */
    protected $args = array(
        'help' => 'h',
        'config:' => 'c:',
        'port:' => 'p:',
        'pid-file:' => 'f:',
        'log:' => 'l:',
    );

    protected function help(){
        echo <<<GLOB_MARK
-c  --config    crontab missions config file
-p  --port      http server port
-f  --pid-file  daemon pid file
-l  --log       crontab log file
GLOB_MARK;
        exit;
    }

    /**
     *
     */
    public function start()
    {
        $this->init();
        $this->checkPidFile();
        $this->daemon = $this->factory();
        $this->daemon->start();
    }

    protected function checkPidFile()
    {
        if (empty($this->pid_file)) return true;
        if (file_exists($this->pid_file)) {
            if (!is_readable($this->pid_file) || !is_writable($this->pid_file)) {
                throw new RuntimeException("the pid file is not readable or writable");
            }
            $pid = file_get_contents($this->pid_file);
            if ($pid != getmypid()) {
                throw new RuntimeException("the crontab is already running. pid:" . $pid);
            }
        } else {
            $touch = touch($this->pid_file);
            if (!$touch) {
                throw new RuntimeException("create pid file failed");
            }
        }

        $put = file_put_contents($this->pid_file, getmypid());
        if (!$put) {
            throw new RuntimeException("write pid file failed");
        }

        return true;
    }

    protected function init()
    {
        $this->params = getopt(implode('', array_values($this->args)), array_keys($this->args));

        if (!$this->argExists('config')) {
            throw new Exception("the config arg is required");
        }

        $this->config_file = $this->arg('config');
        if (!file_exists($this->config_file) || !is_readable($this->config_file)) {
            $message = "config file is not exists or is not readable";
            throw new RuntimeException($message);
        }
        $this->missions = include $this->config_file;

        if ($this->argExists('port')) {
            $this->port = $this->arg('port');
        }

        if ($this->argExists('pid-file')) {
            $this->pid_file = $this->arg('pid-file');
        }

        if ($this->argExists('log')) {
            $this->log = $this->arg('log');
        }
    }

    /**
     * @return \Jenner\Crontab\AbstractDaemon
     */
    public function factory()
    {
        if (!empty($this->port)) {
            return new \Jenner\Crontab\HttpDaemon($this->missions, $this->log, $this->port);
        }
        return new \Jenner\Crontab\Daemon($this->missions, $this->log);
    }

    /**
     * @param $name
     * @return bool
     */
    protected function argExists($name)
    {
        if (array_key_exists($name, $this->params)) {
            return true;
        } elseif (array_key_exists($this->args[$name], $this->params)) {
            return true;
        } elseif (array_key_exists(rtrim($this->args[$name . ':'], ':'), $this->params)) {
            return true;
        }

        return false;
    }

    /**
     * @param $name
     * @return null
     */
    protected function arg($name)
    {
        echo rtrim($this->args[$name . ':'], ':') . PHP_EOL;
        if (array_key_exists($name, $this->params)) {
            return $this->params[$name];
        } elseif (array_key_exists(rtrim($this->args[$name . ':'], ':'), $this->params)) {
            return $this->params[rtrim($this->args[$name . ':'], ':')];
        }

        return null;
    }
}