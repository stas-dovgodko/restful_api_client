<?php

namespace JsonAPI\Model;

use JsonAPI\Model;
use Throwable;

class Exception extends \Exception
{
    /**
     * @var Model|null
     */
    protected $model;

    public function __construct($message, Model $model = null, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->model = $model;
    }

    /**
     * @return Model|null
     */
    public function getModel() : ?Model
    {
        return $this->model;
    }
}