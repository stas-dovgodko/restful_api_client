<?php

namespace JsonAPI\Client\Adapter;


use JsonAPI\Client\Adapter;
use Psr\Http\Message;

class Stub extends Adapter
{
    private $callback;
    /**
     * @var callable|null
     */
    private $headersHandler = null;

    /**
     * @var callable|null
     */
    private $bodyHandler = null;

    public function __construct(?callable $callback = null)
    {
        $this->callback = $callback;
    }

    public function writeContent(string $content) : void
    {
        ($this->bodyHandler)($content);
    }

    public function setHeaders(array $headers, $status = 'HTTP/1.1 200 OK') : void
    {
        ($this->headersHandler)($status."\n");

        foreach($headers as $k => $v) {
            ($this->headersHandler)($k.': '.$v."\n");
        }
    }

    /**
     * @param Message\RequestInterface $request
     * @param null $bodyHandler
     * @param null $headersHandler
     * @return void
     */
    protected function request(Message\RequestInterface $request, $bodyHandler = null, $headersHandler = null)
    {
        if (is_callable($bodyHandler)) $this->bodyHandler = $bodyHandler;
        if (is_callable($headersHandler)) $this->headersHandler = $headersHandler;
        if ($this->callback) ($this->callback)($request);
    }
}