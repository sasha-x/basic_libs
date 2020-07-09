<?php

/**
 * Trait LoggerTrait
 *
 * @package Common\Log
 *
 * Краткие алиасы функций логирования PSR Logger
 *         - делаем use в базовом классе
 *         - вызываем $this->info(<message string>, <params>) , как в PSR
 *         - вызывающий __METHOD__ добавляется перед <message string>
 */
trait LoggerTrait {

    protected $srcClassShortName = true;

    abstract public function getLogger();

    //dark magic
    private function _log($arguments)
    {
        $deep = 3;
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $deep);

        $logFn = $dbt[1]['function'];
        //$arguments = $dbt[1]['args'];

        $srcClass = $dbt[2]['class'];
        if($this->srcClassShortName){
            $srcClass = substr(strrchr($srcClass, '\\'), 1);
        }
        $srcFunc = $dbt[2]['function'];

        $arguments[0] = $srcClass .'::'. $srcFunc .' '. $arguments[0];

        return call_user_func_array([$this->getLogger(), $logFn], $arguments);
    }

    public function emergency()
    {
        return $this->_log(func_get_args());
    }

    public function critical()
    {
        return $this->_log(func_get_args());
    }

    public function alert()
    {
        return $this->_log(func_get_args());
    }

    public function error()
    {
        return $this->_log(func_get_args());
    }

    public function notice()
    {
        return $this->_log(func_get_args());
    }

    public function warning()
    {
        return $this->_log(func_get_args());
    }

    public function info()
    {
        return $this->_log(func_get_args());
    }

    public function debug()
    {
        return $this->_log(func_get_args());
    }

}