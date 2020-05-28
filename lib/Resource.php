<?php

namespace JsonAPI;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

use JsonAPI\Model\Stub;
use Psr\Http\Message;

trait Resource {

    /**
     * @var Client\Adapter|null
     */
    protected $adapter;

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @return Client\Adapter|null
     */
    public function getAdapter() : ?Client\Adapter
    {
        return $this->adapter;
    }

    /**
     * @param Client\Adapter $client
     * @return self
     */
    public function setAdapter(Client\Adapter $adapter) : self
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param $method
     * @return string
     */
    protected function getEndpointCall($method)
    {
        return $this->endpoint . '/' . $method;
    }

    /**
     * @param $action
     * @param $method
     * @param array $hashmap
     * @return Message\RequestInterface
     */
    protected function prepareRequest($action, $method, $hashmap) : Message\RequestInterface
    {
        $url = $this->getEndpointCall($action);
        if ($method === 'GET') {
            $url .= '?'.http_build_query($hashmap, null, '&');
            $payload = '';
        } elseif (!is_string($hashmap)) {
            $payload = http_build_query($hashmap, null, '&');
        } else {
            $payload = $hashmap;
        }

        $request = new Request($method, $url, [], $payload);

        if (is_string($hashmap)) {

        }

        return $request;
    }

    /**
     * @return Message\ResponseInterface
     */
    protected function createResponse() : Message\ResponseInterface
    {
        return new Response();
    }

    /**
     * @param $action
     * @param $method
     * @param array $data
     * @param string $modelClassOrCallback
     * @return Model
     * @throws Client\Exception client exception
     */
    protected function request($action, $method, $data, $modelClassOrCallback = Stub::class) : Model
    {
        $adapter = $this->getAdapter();

        if (!$adapter) throw new Client\Exception('Adapter missed');

        $response = $this->createResponse(); $request = $this->prepareRequest($action, $method, $data);

        $content = $adapter->sendRequest($request, $response);

        $options = 0;
        $data = \json_decode($content, true, 512, $options);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Client\Exception(
                'Response error: ' . json_last_error_msg()
                , $request);
        }

        if ($response->getStatusCode() !== 200) {

            throw new Client\Exception(($response instanceof Response) ? $response->getReasonPhrase() : 'Wrong HTTP Code - '.$response->getStatusCode(), $request);
        }

        if (is_callable($modelClassOrCallback)) return $modelClassOrCallback($data);
        else return $modelClassOrCallback::FromArray($data);
    }
}
