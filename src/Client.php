<?php

namespace veryfi;

use veryfi\errors\VeryfiClientError;

class Client
{
    const API_VERSION = "v7";
    const API_TIMEOUT = 120;
    const MAX_FILE_SIZE_MB = 20;
    const BASE_URL = "https://api.veryfi.com/api/";
    const CATEGORIES = [
        "Advertising & Marketing",
        "Automotive",
        "Bank Charges & Fees",
        "Legal & Professional Services",
        "Insurance",
        "Meals & Entertainment",
        "Office Supplies & Software",
        "Taxes & Licenses",
        "Travel",
        "Rent & Lease",
        "Repairs & Maintenance",
        "Payroll",
        "Utilities",
        "Job Supplies",
        "Grocery",
    ];

    protected $client_id;
    protected $client_secret;
    protected $username;
    protected $api_key;
    protected $base_url;
    protected $timeout;
    protected $api_version;
    protected $headers;
    protected $_session;

    public function __construct(
        $client_id, 
        $client_secret,
        $username = null,
        $api_key = null,
        $base_url = static::BASE_URL,
        $api_version = static::API_VERSION,
        int $timeout = static::API_TIMEOUT
    )
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->username = $username;
        $this->api_key = $api_key;
        $this->base_url = $base_url;
        $this->timeout = $timeout;
        $this->api_version = $api_version;
        $this->headers = [];
        $this->_session = new \GuzzleHttp\Client([
            'cookies' => true,
        ]);
    }

    /**
     * Prepares the headers needed for a request.
     *
     * @param boolean $has_files Are there any files to be submitted as binary
     * @return array Dictionary with headers
     */
    protected function get_headers($has_files = false)
    {
        $final_headers = [
            "User-Agent" => "Python Veryfi-Python/0.1",
            "Accept" => "application/json",
            "Content-Type" => "application/json",
            "Client-Id" => $this->client_id,
        ]; 

        if ($this->username) {
            $final_headers["Authorization"] = "apikey {$this->username}: {$this->api_key}";
        }

        if ($has_files) {
            unset($final_headers["Content-Type"]); 
        }

        return $final_headers;
    }

    /**
     * Get API Base URL with API Version
     *
     * @return string Base URL to Veryfi API
     */
    protected function get_url()
    {
        return $this->base_url + $this->api_version; 
    }

    /**
     * Get Request Guzzle Data key
     *
     * @param string $http_verb 
     * @param boolean $has_files 
     * @return string
     */
    protected function get_request_date_key($http_verb, $has_files)
    {
        $data_key = strtoupper($http_verb) == 'GET' ? 'query' : 'json';
        if ($has_files) {
            $data_key = 'multipart';
        }
        return $data_key;
    }

    /**
     * Submit the HTTP request.
     *
     * @param string $http_verb HTTP Method
     * @param string $endpoint_name Endpoint name such as 'documents', 'users', etc.
     * @param array $request_arguments JSON payload to send to Veryfi
     * @param resource $file_stream A JSON of the response data.
     * @return array
     */
    protected function request($http_verb, $endpoint_name, $request_arguments, $file_stream=null)
    {
        $has_files = !is_null($file_stream);
        $headers = $this->get_headers($has_files);
        $api_url = "{$this->get_url()}partner{$endpoint_name}";

        if ($this->client_secret) {
            $timestamp = time();
            $signature = $this->generate_signature($request_arguments, $timestamp);
            $headers["X-Veryfi-Request-Timestamp"] = (string) $timestamp;
            $headers["X-Veryfi-Request-Signature"] = $signature;
        }

        $data_key = $this->get_request_date_key($http_verb, $has_files);
        $response = $this->_session->request(
            $http_verb, $api_url, 
            [ 
                "headers" => $headers,
                "timeout" => $this->timeout,
                $data_key => $request_arguments,
            ]
        );
        if (!in_array($response->getStatusCode(), [200, 201, 202, 204])) {
            throw VeryfiClientError::fromResponse($response);
        }

        return json_decode($response->getBody()->getContents(), true); 
    }

    protected function generate_signature($payload_params, $timestamp)
    {
        
    }
}