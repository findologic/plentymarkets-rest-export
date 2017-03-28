<?php

namespace Findologic\Plentymarkets;

class Registry
{
    protected $registry = array();

    public function set($key, $object)
    {
        $key = strtolower($key);
        if (!array_key_exists($key, $this->registry)) {
            $this->registry[$key] = $object;
        }
    }

    public function get($key)
    {
        $key = strtolower($key);
        if (array_key_exists($key, $this->registry)) {
            return $this->registry[$key];
        } else {
            return false;
        }
    }
}