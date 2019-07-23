<?php

namespace JsonAPI;



abstract class Model
{
    private $map = [];

    protected $exposed = null;
    protected $ignored = [];

    /**
     * Magic Get Method
     *
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        if ($this->__isset($key)) {
            return $this->map[$key];
        }
        return null;
    }

    /**
     * Magic Set Method
     *
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        if (!is_array($value) && $value === null) {
            $this->__unset($key);
        } else {
            $this->map[$key] = $value;
        }
    }

    /**
     * Magic isSet Method
     *
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->map[$key]);
    }

    /**
     * Magic Unset Method
     *
     * @param $key
     */
    public function __unset($key)
    {
        unset($this->map[$key]);
    }

    /**
     * Returns array representation of object
     *
     * @return array
     */
    public function toArray(array $keys = null)
    {
        $_convertToArray = function($params) use(&$_convertToArray, $keys) {
            $ret = [];
            foreach ($params as $k => $v) {
                if (($keys !== null) && !in_array($k, $keys)) continue;
                if (($this->exposed !== null) && !in_array($k, $this->exposed)) continue;
                if (in_array($k, $this->ignored)) continue;

                if ($v instanceof Model) {
                    $ret[$k] = $v->toArray();
                } elseif (is_array($v)) {
                    $ret[$k] = $_convertToArray($v);
                } else {
                    $ret[$k] = $v;
                }
            }
            
            return $ret;
        };

        return $_convertToArray($this->map);
    }

    /**
     * @param array $data
     * @return Model
     * @throws \Exception
     */
    public static function FromArray(array $data)
    {
        $class =  get_called_class();
        $object = new $class;

        return $object;
    }
}
