<?php

namespace App\Feeds\Downloader;

use App\Feeds\Utils\Data;
use App\Feeds\Utils\HttpClient;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Feeds\Utils\ProxyConnector;
use App\Helpers\HttpHelper;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;
use JetBrains\PhpStorm\Pure;

class HttpDownloader
{
    /**
     * @var HttpClient Async HTTP client
     */
    private HttpClient $client;
    /**
     * @var bool Defines how to use the user agent, change it on each request, or use a static value
     */
    private bool $static_user_agent;
    /**
     * @var string Current user agent
     */
    private string $user_agent;
    /**
     * @var float Waiting time between sending a request
     */
    private float $delay_s;
    /**
     * @var array Parameters for authorization
     *
     * 'check_login_text' => 'Log Out' - A verification word that is displayed only to authorized users (Log Out, My account ...)
     * 'auth_url' => 'https://www.authorise_uri.com/login' - The URL to which the authorization request is sent
     * 'auth_form_url' => 'https://www.authorise_uri.com/login' - The URL of the page where the authorization form is located
     * 'auth_info' => [] - An array of parameters for authorization, contains all the fields that were sent by the browser for authorization
     * 'find_fields_form' => true|false - Determines whether to search for additional fields of the authorization form before sending the request or not
     * If this parameter is omitted, the system will consider its value as "true"
     * 'api_auth' => true|false - Specifies in what form to send the parameters of the authorization form ("request_payload" or "form_data")
     * If this parameter is omitted, the system will consider its value as "false".
     * By default, the parameters are sent as normal form fields
     *
     * Example of the content auth_info:
     * 'auth_info' => [
     *     'login[username]' => 'user@my-email.com',
     *     'login[password]' => 'My-Password',
     * ],
     */
    private array $params;
    /**
     * @var bool Determines whether to use a proxy or not
     */
    private bool $use_proxy;
    /**
     * @var bool Determines whether the connection to the proxy server is established or not
     */
    private bool $connect = false;
    /**
     * @var int Defines the waiting time for processing the request in seconds
     */
    public int $timeout_s;

    public function __construct( array $headers = [], array $params = [], float $timeout_s = 15, float $delay_s = 0, bool $static_user_agent = false, bool $use_proxy = false, string $source = 'https://google.com' )
    {
        $this->timeout_s = $timeout_s;

        $this->setDelay( $delay_s );
        $this->setParams( $params );
        $this->setUseProxy( $use_proxy );

        $this->setStaticUserAgent( $static_user_agent );
        $this->setUserAgent( HttpHelper::getUserAgent() );

        $this->client = new HttpClient( $source );
        $this->client->setHeaders( $headers );
        $this->setTimeOut( $this->timeout_s );

        $this->processAuth();
    }

    /**
     * @param bool $static_agent Sets whether to use a static user-agent or not
     */
    public function setStaticUserAgent( bool $static_agent ): void
    {
        $this->static_user_agent = $static_agent;
    }

    /**
     * @param string $user_agent Sets the user-agent value
     */
    public function setUserAgent( string $user_agent ): void
    {
        $this->user_agent = $user_agent;
    }

    /**
     * @param float $timeout Sets the waiting time for a response to a request
     */
    public function setTimeOut( float $timeout ): void
    {
        $this->getClient()->setRequestTimeOut( $timeout * 1000 );
    }

    /**
     * @param float $delay Sets the delay between requests
     */
    public function setDelay( float $delay ): void
    {
        $this->delay_s = $delay * 1000000;
    }

    /**
     * @param bool $use_proxy Sets whether to use a proxy or not
     */
    public function setUseProxy( bool $use_proxy ): void
    {
        $this->use_proxy = $use_proxy;
    }

    /**
     * @param array $params Sets the authorization parameters
     */
    public function setParams( array $params ): void
    {
        $this->params = $params;
    }

    /**
     * Sending multiple requests async
     * @param array $links An array of references or an array of objects app/Feeds/Utils/Link
     * @param bool $assoc Specifies in what form to return an array of responses to requests
     * A normal array, where the key is the address of the link to which the request was sent, and the value is the content of the response to the request
     * An associative array, of the form:
     * 'data' => new Data() - The content of the response to the request
     * 'link' => [
     * 'url' => $link->getUrl() - The address of the link to which the request was sent
     * 'params' => $link->getParams() - Array of request parameters
     * ]
     * @return array An array of responses to requests, each response is placed in an object app/Feeds/Utils/Data
     */
    public function fetch( array $links, bool $assoc = false ): array
    {
        $data = [];
        $errors_links = [];

        $requests = function ( $links ) use ( &$data, &$errors_links, $assoc ) {
            foreach ( $links as $link ) {
                if ( !$link instanceof Link ) {
                    $link = new Link( $link );
                }

                if ( $this->use_proxy && !$this->connect ) {
                    print PHP_EOL . 'Check proxies' . PHP_EOL;
                    $this->initProxy( $link );
                }

                if ( $this->static_user_agent ) {
                    $this->setHeader( 'User-Agent', $this->user_agent );
                }
                else {
                    $this->setHeader( 'User-Agent', HttpHelper::getUserAgent() );
                }

                yield function () use ( $link, &$data, &$errors_links, $assoc ) {
                    return $this->getClient()->request( $link->getUrl(), $link->getParams(), $link->getMethod(), $link->getTypeParams() )->
                    then(
                        function ( Response $response ) use ( $link, &$data, $assoc ) {
                            if ( $body = $response->getBody() ) {
                                $response_body = new Data( $body->getContents() );
                            }
                            $data = $this->prepareRequestData( $response_body ?? null, $link, $assoc, $data );
                        },
                        function ( RequestException $exception ) use ( $link, &$data, &$errors_links, $assoc ) {
                            if ( $response = $exception->getResponse() ) {
                                $status = $response->getStatusCode();
                                if ( $status === 403 ) {
                                    if ( $this->use_proxy ) {
                                        $this->connect = false;
                                    }
                                    $errors_links[] = $this->prepareErrorLinks( $link, 5 );
                                }
                                elseif ( $status >= 500 ) {
                                    $errors_links[] = $this->prepareErrorLinks( $link, 0 );
                                }
                                elseif ( in_array( $status, [ 200, 404 ], true ) ) {
                                    $data = $this->prepareRequestData( new Data( $response->getBody()->getContents() ), $link, $assoc, $data );
                                }
                                else {
                                    $this->printParseError( $link, $exception );
                                    $data = $this->prepareRequestData( new Data( $response->getBody()->getContents() ), $link, $assoc, $data );
                                }
                            }
                            else if ( $this->use_proxy ) {
                                $this->connect = false;
                                $errors_links[] = $this->prepareErrorLinks( $link, 0 );
                            }
                            else {
                                $this->printParseError( $link, $exception );
                                $data = $this->prepareRequestData( null, $link, $assoc, $data );
                            }
                        }
                    );
                };
                usleep( $this->delay_s );
            }
        };
        $pool = new Pool( $this->getClient()->getHttpClient(), $requests( $links ) );
        $promise = $pool->promise();
        $promise->wait();

        if ( $errors_links ) {
            $data = array_merge( $data, $this->processErrorLinks( $errors_links, $assoc ) );
        }
        return $data;
    }

    /**
     * Attempt to load links with 403 or 500 errors when loading them
     * @param array $errors_links Array of the form ['link' => Link, 'delay' => delay_s]
     * @param bool $assoc In what form should I return an array of responses to requests
     * @return array Array of responses to requests
     */
    private function processErrorLinks( array $errors_links, bool $assoc ): array
    {
        $data = [];
        foreach ( $errors_links as $error_link ) {
            $link = $error_link[ 'link' ];

            if ( $this->static_user_agent ) {
                $this->setHeader( 'User-Agent', $this->user_agent );
            }
            else {
                $this->setHeader( 'User-Agent', HttpHelper::getUserAgent() );
            }

            for ( $i = 1; $i <= 5; $i++ ) {
                if ( $this->use_proxy && !$this->connect ) {
                    print PHP_EOL . 'Check proxies' . PHP_EOL;
                    $this->initProxy( $link );
                }
                else {
                    sleep( $error_link[ 'delay' ] );
                }

                $response = null;
                $exception = null;

                $promise = $this->getClient()->request( $link->getUrl(), $link->getParams(), $link->getMethod(), $link->getTypeParams() )->then(
                    function ( Response $res ) use ( &$response ) {
                        $response = $res;
                    },
                    function ( RequestException $exc ) use ( &$exception ) {
                        $exception = $exc;
                    }
                );
                $promise->wait();
                if ( $response && $body = $response->getBody() ) {
                    $response_body = new Data( $body->getContents() );
                    break;
                }

                $this->connect = false;
            }

            if ( !isset( $response_body ) && isset( $exception ) && $response = $exception->getResponse() ) {
                $response_body = new Data( $response->getBody()->getContents() );
            }

            if ( isset( $exception ) && $exception ) {
                if ( $this->use_proxy ) {
                    $this->connect = false;
                }
                $this->printParseError( $link, $exception );
            }
            $data = $this->prepareRequestData( $response_body ?? null, $link, $assoc, $data );
        }
        return $data;
    }

    /**
     * Sends a single request using the GET method
     * @param string|Link $link A link or an app/Feeds/Utils/Link object is accepted
     * @param array $params Array of parameters to be converted to a query string
     * @return Data Object app/Feeds/Utils/Data which contains the response to the request
     */
    public function get( string|Link $link, array $params = [] ): Data
    {
        if ( !$link instanceof Link ) {
            $link = new Link( $link, 'GET', $params );
        }
        $data = $this->fetch( [ $link ] );
        return array_shift( $data );
    }

    /**
     * Sends a single request using the POST method
     * @param string|Link $link A link or an app/Feeds/Utils/Link object is accepted
     * @param array $params Array of parameters
     * @param string $type_params The type of sending request parameters - as the body of the html form or as a json string (the body of the API request)
     * @return Data The app/Feeds/Utils/Data object that contains the response to the request
     */
    public function post( string|Link $link, array $params = [], string $type_params = 'form_data' ): Data
    {
        if ( !$link instanceof Link ) {
            $link = new Link( $link, 'POST', $params );
        }
        $link->setTypeParams( $type_params );
        $data = $this->fetch( [ $link ] );
        return array_shift( $data );
    }

    /**
     * @return HttpClient Returns an object of an asynchronous http client
     */
    public function getClient(): HttpClient
    {
        return $this->client;
    }

    /**
     * Sets the http request header
     * @param string $name Title name
     * @param string $value Header value
     */
    public function setHeader( string $name, string $value ): void
    {
        $this->getClient()->setHeader( $name, $value );
    }

    /**
     * @param array $headers Sets a set of http request headers
     */
    public function setHeaders( array $headers ): void
    {
        $this->getClient()->setHeaders( $headers );
    }

    /**
     * Returns the value of the specified header
     * @param string $name Title name
     * @return string|null Header value
     */
    public function getHeader( string $name ): ?string
    {
        return $this->getClient()->getHeader( $name );
    }

    /**
     * @return array Returns an array of headers
     */
    public function getHeaders(): array
    {
        return $this->getClient()->getHeaders();
    }

    /**
    * Sets a new cookie
    * @param string $name Cookie name
    * @param string $value Cookie value
    */
    public function setCookie( string $name, string $value ): void
    {
        try {
            $this->getClient()->setCookie( $name, $value );
        } catch ( Exception $e ) {
            print $e->getMessage();
        }
    }

    /**
     * Returns the value of the specified cookie
     * @param string $name Cookie name
     * @return string cookie value
     */
    public function getCookie( string $name ): string
    {
        return $this->getClient()->getCookie( $name );
    }

    /**
     * Deletes all cookies
     */
    public function clearCookie(): void
    {
        $this->getClient()->clearCookie();
    }

    private function printParseError( Link $link, RequestException $exception ): void
    {
        if ( $response = $exception->getResponse() ) {
            if ( $response->getStatusCode() !== 404 ) {
                echo PHP_EOL . "Parser Error: " . $response->getReasonPhrase() .
                    PHP_EOL . "Status code: " . $response->getStatusCode() .
                    PHP_EOL . "URI: " . $link->getUrl() . PHP_EOL;
            }
            else {
                return;
            }
        }
        else {
            echo PHP_EOL . "Parser Error: " . $exception->getMessage() . PHP_EOL . "URI: " . $link->getUrl() . PHP_EOL;
        }
    }

    private function prepareErrorLinks( Link $link, int $delay ): array
    {
        return [
            'link' => $link,
            'delay' => $delay
        ];
    }

    private function prepareRequestData( ?Data $response_body, Link $link, bool $assoc, array $data ): array
    {
        if ( $assoc ) {
            $data[] = [
                'data' => $response_body ?? new Data(),
                'link' => [
                    'url' => $link->getUrl(),
                    'params' => $link->getParams()
                ]
            ];
        }
        else {
            $data[ $link->getUrl() ] = $response_body ?? new Data();
        }
        return $data;
    }

    /**
     * @return string|null Returns the url to which the authorization request will be sent
     */
    public function getAuthUrl(): ?string
    {
        return $this->params[ 'auth_url' ] ?? null;
    }

    /**
     * @return string|null Returns the url of the page where the authorization form is located
     */
    public function getAuthFormUrl(): ?string
    {
        return $this->params[ 'auth_form_url' ] ?? null;
    }

    /**
     * @return array Returns an array of parameters for authorization
     */
    public function getAuthInfo(): array
    {
        return $this->params[ 'auth_info' ] ?? [];
    }

    /**
     * @return bool Returns a value, depending on which the authorization form parameters will be sent as "form_data" or " request_payload"
     */
    public function getApiAuth(): bool
    {
        return isset( $this->params[ 'api_auth' ] ) && $this->params[ 'api_auth' ];
    }

    /**
     * @return string|null Returns the authorization verification word
     */
    public function getCheckLoginText(): ?string
    {
        return $this->params[ 'check_login_text' ] ?? null;
    }

    /**
     * Authorization process
     * @param null $callback
     * @return bool
     */
    public function processAuth( $callback = null ): bool
    {
        if ( $this->getAuthUrl() && $this->getAuthInfo() ) {
            if ( !isset( $this->params[ 'find_fields_form' ] ) || $this->params[ 'find_fields_form' ] ) {
                $this->params[ 'auth_info' ] = $this->getFieldsFormOnLink( $this->getAuthFormUrl() ?? $this->getAuthUrl(), array_key_first( $this->getAuthInfo() ), $this->getAuthInfo() );
            }

            $data_n = $this->post( $this->getAuthUrl(), $this->getAuthInfo(), $this->getApiAuth() ? 'request_payload' : 'form_data' );
            $crawler_n = new ParserCrawler( $data_n->getData() );
            if ( $crawler_n->count() && stripos( $crawler_n->html(), 'sucuri_cloudproxy_js' ) !== false ) {
                $cookies = HttpHelper::sucuri( $crawler_n->html() );
                if ( $cookies ) {
                    $this->setCookie( $cookies[ 0 ], $cookies[ 1 ] );

                    $data_n = $this->post( $this->getAuthUrl(), $this->getAuthInfo() );
                    $crawler_n = new ParserCrawler( $data_n->getData() );
                }
            }

            if ( $callback ) {
                $callback( $crawler_n, $this );
            }

            return $this->checkLogin( $crawler_n );
        }
        return false;
    }

    /**
     * Used to get form fields
     * @param string|Link $link Link to the page where the form is located
     * @param string $field_name The name of the field located inside the desired form. The form will be searched by the name of this field
     * To get all fields from any form, you must pass an empty value
     * @param array $params Array of parameters with the original values, if any
     * @ * @param bool $only_hidden Parameter allows you to collect all the fields of the form or only hidden ones. By default, all fields of the form are collected
     * @return array An associative array in which the keys are the names of the form fields, the values are the values of the form fields
     */
    public function getFieldsFormOnLink( string|Link $link, string $field_name = '', array $params = [], bool $only_hidden = false ): array
    {
        return $this->findFieldsForm( new ParserCrawler( $this->get( $link )->getData() ), $field_name, $params, $only_hidden );
    }

    /**
     * Used to get form fields
     * @param ParserCrawler $crawler Html content of the page where the form is located
     * @param string $field_name The name of the field located inside the desired form. The form will be searched by the name of this field
     * To get all fields from any form, you must pass an empty value
     * @param array $params Array of parameters with the original values, if any
     * @ * @param bool $only_hidden Parameter allows you to collect all the fields of the form or only hidden ones. By default, all fields of the form are collected
     * @return array An associative array in which the keys are the names of the form fields, the values are the values of the form fields
     */
    public function getFieldsFormOnCrawler( ParserCrawler $crawler, string $field_name = '', array $params = [], bool $only_hidden = false ): array
    {
        return $this->findFieldsForm( $crawler, $field_name, $params, $only_hidden );
    }

    /**
     * @param ParserCrawler $crawler
     * @param string $field_name
     * @param array $params
     * @param bool $only_hidden
     * @return array
     */
    private function findFieldsForm( ParserCrawler $crawler, string $field_name = '', array $params = [], bool $only_hidden = false ): array
    {
        $selector = 'input';
        if ( $only_hidden ) {
            $selector = 'input[type="hidden"]';
        }

        if ( empty( $field_name ) ) {
            if ( $crawler->filter( 'form' )->count() ) {
                $crawler->filter( "form $selector" )->each( static function ( ParserCrawler $c ) use ( &$params ) {
                    $name = $c->attr( 'name' );
                    $value = $c->attr( 'value' );
                    if ( ( !empty( $params[ $name ] ) ) ) {
                        return;
                    }
                    $params[ $name ] = $value ?? '';
                } );
            }
            return $params;
        }

        if ( $crawler->filter( 'input[name="' . $field_name . '"]' )->count() ) {
            $parents = $crawler->filter( 'input[name="' . $field_name . '"]' )->parents();
            $parents->filter( 'form' )->first()->filter( $selector )->each( function ( ParserCrawler $c ) use ( &$params ) {
                $name = $c->attr( 'name' );
                $value = $c->attr( 'value' );
                if ( ( !empty( $params[ $name ] ) ) ) {
                    return;
                }
                $params[ $name ] = $value ?? '';
            } );
        }
        return $params;
    }

    /**
     * Verification of authorization on the site by the verification word
     * @Parameter Analyzer crawler $crawler
     * @param ParserCrawler $crawler
     * @return bool
     */
    public function checkLogin( ParserCrawler $crawler ): bool
    {
        if ( !$crawler ) {
            return false;
        }

        if ( $this->getCheckLoginText() && $crawler->count() ) {
            if ( stripos( $crawler->text(), $this->getCheckLoginText() ) !== false ) {
                print PHP_EOL . 'Authorization successful!' . PHP_EOL;
                return true;
            }
        }
        else {
            return true;
        }

        print PHP_EOL . 'Authorization fail!' . PHP_EOL;
        return false;
    }

    private function initProxy( Link $link ): void
    {
        ( new ProxyConnector() )->connect( $this, $link );
        $this->connect = true;
    }
}