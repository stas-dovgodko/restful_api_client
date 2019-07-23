<?php /** @noinspection CurlSslServerSpoofingInspection */

/**
 * Created by PhpStorm.
 * User: Stas
 * Date: 6/28/2018
 * Time: 12:33 PM
 */
namespace JsonAPI\Client;

use Psr\Http\Message;
use Psr\Log\LoggerAwareTrait;

abstract class Adapter
{
    use LoggerAwareTrait;
    


    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var string|null
     */
    protected $certPath;

    /**
     * @var string|null
     */
    protected $certKeyPath;

    /**
     * @var string|null
     */
    protected $certKeyPass;

    /**
     * @var int
     */
    protected $timeout = 5;

    /**
     * @var bool
     */
    protected $verifyHost = true;

    public function __construct()
    {

    }

    /**
     * @return bool
     */
    public function isVerifyHost(): bool
    {
        return $this->verifyHost;
    }

    /**
     * @param bool $verifyHost
     * @return Adapter
     */
    public function setVerifyHost(bool $verifyHost)
    {
        $this->verifyHost = $verifyHost;
        return $this;
    }

    

    /**
     * @param int $timeout
     * @return Adapter
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCertPath(): ?string
    {
        return $this->certPath;
    }

    /**
     * @return string|null
     */
    public function getCertKeyPath(): ?string
    {
        return $this->certKeyPath;
    }

    /**
     * @return string|null
     */
    public function getCertKeyPass(): ?string
    {
        return $this->certKeyPass;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }



    /**
     * @param string|null $certPath
     * @return Adapter
     */
    public function setCertPath(?string $certPath): self
    {
        $this->certPath = $certPath;

        return $this;
    }

    /**
     * @param string|null $certKeyPath
     * @return Adapter
     */
    public function setCertKeyPath(?string $certKeyPath): self
    {
        $this->certKeyPath = $certKeyPath;
        return $this;
    }

    /**
     * @param string|null $certKeyPass
     * @return Adapter
     */
    public function setCertKeyPass(?string $certKeyPass): self
    {
        $this->certKeyPass = $certKeyPass;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDebug() : bool
    {
        return $this->debug;
    }

    /**
     * @param mixed $debug
     * @return Adapter Fluent api
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }


    /**
     * @param callable|null $bodyHandler
     * @param callable|null $headersHandler
     * @return Adapter Fluent api
     */
    /*public function setHandlers(callable $bodyHandler = null, callable $headersHandler = null) : self
    {
        $this->headersHandler = $headersHandler;
        $this->bodyHandler = $bodyHandler;

        return $this;
    }*/

    /**
     * @param Message\RequestInterface $request
     * @param null $bodyHandler
     * @param null $headersHandler
     * @return void
     */
    abstract protected function request(Message\RequestInterface $request, $bodyHandler = null, $headersHandler = null);

    /**
     * @param Message\ResponseInterface $response
     * @param $data
     * @return Message\ResponseInterface
     */
    protected function mapResponseHeader(Message\ResponseInterface $response, $data)
    {
        $clean_data = trim($data);
        if ($clean_data !== '') {
            if (stripos($clean_data, 'HTTP/') === 0) {
                $status_parts = explode(' ', $clean_data, 3);
                $parts_count  = \count($status_parts);
                if ($parts_count < 2 || stripos($status_parts[0], 'HTTP/') !== 0) {
                    throw new \DomainException("Response '$clean_data' is not a valid HTTP status line");
                }
                $reason_phrase = ($parts_count > 2 ? $status_parts[2] : '');

                $response = $response->withStatus((int)$status_parts[1], $reason_phrase)->withProtocolVersion(substr($status_parts[0], 5));
            }
            else {
                $header_parts = explode(':', $clean_data, 2);
                if (\count($header_parts) !== 2) {
                    throw new \DomainException("Response '$clean_data' is not a valid HTTP header line");
                }

                $header_name  = trim($header_parts[0]);
                $header_value = trim($header_parts[1]);

                if ($response->hasHeader($header_name)) {
                    $response = $response->withAddedHeader($header_name, $header_value);
                }
                else {
                    $response = $response->withHeader($header_name, $header_value);
                }
            }
        }

        return $response;
    }

    /**
     * Sends a PSR-7 request and map to PSR-7 response.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return string Response content
     * @throws Exception
     */
    public function sendRequest(Message\RequestInterface $request, Message\ResponseInterface $response)
    {
        try {
            $body = $response->getBody(); $content = '';
            
            $this->request($request, function($data) use($body, &$content) {
                if (($content === '') && substr($data, 0, 3) == pack('CCC', 239, 187, 191)) {
                    $content = substr($data, 3);
                } else {
                    $content .= $data;
                }

                $body->write($data);

            }, function($data) use($request, &$response) {
                try {
                    $response = $this->mapResponseHeader($response, $data);
                } catch (\Exception $e) {
                    throw new Exception($e->getMessage(), $request, $e);
                }
            });

            $status_code = $response->getStatusCode();

            if ($this->debug && $this->logger) {
                $this->logger->debug('Response:' . $content);
            }

            return $content;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $request, $e);
        }
    }
}