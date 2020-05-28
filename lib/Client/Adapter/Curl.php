<?php /** @noinspection CurlSslServerSpoofingInspection */

namespace JsonAPI\Client\Adapter;


use JsonAPI\Client\Adapter;
use Psr\Http\Message;

class Curl extends Adapter
{
    const MAX_BODY_SIZE = 102400; // > 100k via curl reader

    protected $ch;

    protected $options = [];

    protected $postMultipart = true;

    public function setMultipart($state = true) : self
    {
        $this->postMultipart = $state;

        return $this;
    }

    public function setOptions(array $options)
    {
        foreach ($options as $k => $v) $this->options[$k] = $v;
        $this->curlReset();

        return $this;
    }

    /**
     * @param Message\RequestInterface $request
     * @param null $bodyHandler
     * @param null $headersHandler
     * @return void
     */
    protected function request(Message\RequestInterface $request, $bodyHandler = null, $headersHandler = null)
    {
        $uri = $request->getUri(); $body = $request->getBody();
        $options = $this->options;
        // detect version
        switch ($ver = $request->getProtocolVersion()) {
            case '1.0':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_0;
                break;
            case '1.1':
                $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
                break;
            case '2.0':
                if (\defined('CURL_HTTP_VERSION_2_0')) {
                    $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
                    break;
                }
                throw new Exception('libcurl 7.33 required for HTTP 2.0');
            default:
                throw new Exception('Unexpected HTTP version - '.$ver);
        }

        if ($this->debug) {
            $options[CURLOPT_VERBOSE] = true;
            $verbose = fopen('php://temp', 'wb+');
            $options[CURLOPT_STDERR] = $verbose;
        } else {
            $options[CURLOPT_VERBOSE] = false;
        }

        $options[CURLOPT_URL] = (string)$uri;

        if ($user_info = $uri->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $user_info;
        }

        /*
		 * HTTP methods that cannot have payload:
		 * - GET   => cURL will automatically change method to PUT or POST if we
		 *            set CURLOPT_UPLOAD or CURLOPT_POSTFIELDS.
		 * - HEAD  => cURL treats HEAD as GET request with a same restrictions.
		 * - TRACE => According to RFC7231: a client MUST NOT send a message body
		 *            in a TRACE request.
		 */
        $http_methods = [
            'GET',
            'HEAD',
            'TRACE',
        ];

        $request_method = $request->getMethod();
        if (!\in_array($request_method, $http_methods, true)) {

            $body_size = $body->getSize();
            if ($body_size !== 0) {
                if ($body->isSeekable()) {
                    $body->rewind();
                }
                if ($body_size === null || $body_size > self::MAX_BODY_SIZE) {

                    $options[CURLOPT_UPLOAD] = true;
                    if ($body_size !== null) {
                        $options[CURLOPT_INFILESIZE] = $body_size;
                    }
                    $options[CURLOPT_READFUNCTION] = function ($ch, $fd, $len) use ($body) {
                        return $body->read($len);
                    };
                }
                else {
                    $payload = (string)$body;
                    $options[CURLOPT_POSTFIELDS] = $payload;

                    if ($request_method === 'POST') {
                        $options[CURLOPT_POST] = true;
                    }

                    if ($this->debug && $this->logger) {
                        $this->logger->debug('Payload: '.$payload);
                    }
                }
            }
        }
        if ($request_method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }
        else if ($request_method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }

        if (!array_key_exists(CURLOPT_HTTPHEADER, $options)) $options[CURLOPT_HTTPHEADER] = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (is_scalar($values)) $values = [$values];

            $header = strtoupper($name);

            if ($this->debug && $this->logger) {
                $this->logger->debug('Request Header "'.$header.'": '.implode(', ', $values));
            }

            // cURL does not support 'Expect-Continue', skip all 'EXPECT' headers
            if ($header === 'EXPECT') {
                continue;
            }
            if ($header === 'CONTENT-LENGTH') {
                if (array_key_exists(CURLOPT_POSTFIELDS, $options)) {
                    $values = [\strlen($options[CURLOPT_POSTFIELDS])];
                }
                // Force content length to '0' if body is empty
                else if (!array_key_exists(CURLOPT_READFUNCTION, $options)) {
                    $values = [0];
                }
            }
            foreach ($values as $value) {
                $options[CURLOPT_HTTPHEADER][] = $name.': '.$value;
            }
        }
        // Although cURL does not support 'Expect-Continue', it adds the 'Expect'
        // header by default, so we need to force 'Expect' to empty.
        $options[CURLOPT_HTTPHEADER][] = 'Expect:';

        if (is_callable($headersHandler)) {
            $options[CURLOPT_HEADERFUNCTION] = function ($ch, $data) use($headersHandler) {

                if ($this->debug && $this->logger) {
                    $this->logger->debug('Response header: ' . $data);
                }

                $headersHandler($data);

                return \strlen($data);
            };
        }
        if (is_callable($bodyHandler)) {
            $options[CURLOPT_WRITEFUNCTION] =  function ($ch, $data) use($bodyHandler) {

                $bodyHandler($data);

                return \strlen($data);
            };
        }

        $ch = curl_copy_handle($this->curlInit()); // local options handler

        // Setup the cURL request
        curl_setopt_array($ch, $options);
        // Execute the request
        curl_exec($ch);

        if ($this->debug && $this->logger) {
            rewind($verbose);
            $this->logger->debug('CURL Debug:' . stream_get_contents($verbose));
        }

        // Check for any request errors
        switch (curl_errno($ch)) {
            case CURLE_OK:
                break;
            case CURLE_COULDNT_RESOLVE_PROXY:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_CONNECT:
            case CURLE_OPERATION_TIMEOUTED:
            case CURLE_SSL_CONNECT_ERROR:
                throw new Exception(curl_error($ch));
            default:
                throw new Exception(curl_error($ch));
        }
    }

    /**
     * @param int $timeout
     * @return Curl
     */
    public function setTimeout(int $timeout) : Adapter
    {
        parent::setTimeout($timeout);

        $this->curlReset();

        return $this;
    }

    public function setVerifyHost(bool $verifyHost): Adapter
    {
        parent::setVerifyHost($verifyHost);

        $this->curlReset();

        return $this;
    }

    public function setCertPath(?string $certPath): Adapter
    {
        parent::setCertPath($certPath);

        $this->curlReset();

        return $this;
    }

    public function setCertKeyPath(?string $certKeyPath): Adapter
    {
        parent::setCertKeyPath($certKeyPath);

        $this->curlReset();

        return $this;
    }

    public function setCertKeyPass(?string $certKeyPass): Adapter
    {
        parent::setCertKeyPass($certKeyPass);

        $this->curlReset();

        return $this;
    }

    public function setDebug(bool $debug): Adapter
    {
        parent::setDebug($debug);

        $this->curlReset();

        return $this;
    }

    /**
     * Reset ch instance
     */
    protected function curlReset()
    {
        if ($this->ch !== null) {
            curl_close($this->ch);
            $this->ch = null;
        }
    }

    /**
     * @return resource CURL ch
     */
    protected function curlInit()
    {
        if ($this->ch === null) {
            $this->ch = curl_init();

            if ($this->ch) {

                curl_setopt($this->ch, CURLOPT_HEADER, false);
                curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);

                // X509 authentication
                if ($this->certPath) curl_setopt($this->ch, CURLOPT_SSLCERT, $this->certPath);

                if ($this->certKeyPath) curl_setopt($this->ch, CURLOPT_SSLKEY, $this->certKeyPath);
                if (!empty($this->certKeyPass)) {
                    curl_setopt($this->ch, CURLOPT_SSLKEYPASSWD, $this->certKeyPass);
                }

                if ($this->verifyHost) {
                    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
                } else {
                    curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
                }
            } else {
                throw new \RuntimeException('Can\'t init CURL');
            }
        }

        return $this->ch;
    }
}
