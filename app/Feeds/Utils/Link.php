<?php

namespace App\Feeds\Utils;

class Link
{
    /**
     * @var string The URL of the link to which the request will be sent
     */
    private string $url;
    /**
     * @var string Method for sending the request
     */
    private string $method;
    /**
     * @var array Request parameters
     */
    private array $params;
    /**
     * @var string Type of sending request parameters
     * For a GET request, this is a query_string
     * For a POST request, this is form_data or request_payload (the RESTAPI request body)
     * If the value is "default", the POST request parameters will be sent as form_data
     * GET request parameters with any value will be sent as query_string
     */
    private string $type_params;
    /**
     * @var bool Link visit status. False if the link was not visited by the loader
     */
    private bool $visited = false;

    public function __construct( string $url, string $method = 'GET', array $params = [], string $type_params = 'default' )
    {
        $this->method = strtoupper( $method );
        $this->type_params = $type_params;

        if ( $this->method === 'GET' && ( $params_start_with = strpos( $url, '?' ) ) !== false ) {
            $query_string = substr( $url, $params_start_with + 1 );

            $url = substr( $url, 0, $params_start_with );
            preg_match_all( '/([^&=]*)=([^&=]*)/', $query_string, $matches );
            $get_params = array_combine( $matches[ 1 ], $matches[ 2 ] );
            $params = array_merge( $params ?? [], $get_params );
        }

        $this->url = trim( $url );
        $this->params = $params;
    }

    /**
     * Sets a new url
     * @param string $url
     * @return Link
     */
    public function setUrl( string $url ): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Returns the current url
     * @return string
     */
    public function getUrl(): string
    {
        $url = $this->url;
        if ( $this->method === 'GET' && count( $this->params ) ) {
            $get_params = array_map(
                static fn( $k, $v ) => "$k=$v",
                array_keys( $this->params ),
                array_values( $this->params )
            );
            $url .= '?' . implode( '&', $get_params );
        }
        return $url;
    }

    /** Sets a new method for sending the request
     * @param string $method
     * @return Link
     */
    public function setMethod( string $method ): self
    {
        if ( $this->method === 'GET' ) {
            $this->setUrl( $this->getUrl() );
        }
        $this->method = $method;
        return $this;
    }

    /**
     * @return string Returns the current method of sending the request
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Sets a new set of parameters
     * @param array $params
     * @return Link
     */
    public function setParams( array $params ): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return array Returns a set of parameters
     */
    public function getParams(): array
    {
        if ( !count( $this->params ) ) {
            return [];
        }
        return $this->params;
    }

    /**
     * @param string $type Sets a new type for sending request parameters
     * @return $this
     */
    public function setTypeParams( string $type ): self
    {
        $this->type_params = $type;
        return $this;
    }

    /**
     * @return string Returns the current type of sending request parameters
     */
    public function getTypeParams(): string
    {
        return $this->type_params;
    }

    /**
     * @return bool Returns the status of the link visit
     */
    public function isVisited(): bool
    {
        return $this->visited;
    }

    /**
     * @param bool $visited Sets the status of the link visit
     * @return Link
     */
    public function setVisited( bool $visited = true ): self
    {
        $this->visited = $visited;
        return $this;
    }
}