<?php

namespace JsonAPI\Client;

use Psr\Http\Message\RequestInterface;
use Throwable;

class Exception extends \Exception
{
    /**
     * @var RequestInterface|null
     */
    protected $request;

    public function __construct($message, RequestInterface $request = null, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->request = $request;
    }

    /**
     * @return RequestInterface|null
     */
    public function getRequest()
    {
        return $this->request;
    }
}