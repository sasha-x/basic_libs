<?php

class DummyLogger
{
    public function __call($name, $args = [])
    {
        echo "$name: " . print_r($args) . "\n";
    }
}