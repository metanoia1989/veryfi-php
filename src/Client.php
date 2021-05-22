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
        // TODO data upload has issues，要看看 file_stream 是怎么调用的 
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

    /**
     * Generate unique signature for payload params.
     *
     * @param array $payload_params JSON params to be sent to API request
     * @param int $timestamp Unix Long timestamp
     * @return string Unique signature generated using the client_secret and the payload
     */
    protected function generate_signature($payload_params, $timestamp)
    {
        $payload = "timestamp:{$timestamp}";   
        foreach ($payload_params as $key => $value) {
            $payload = "{$payload},{$key}:{$value}";
        }
        $tmp_signature = hash_hmac("sha256", $payload, $this->client_secret);
        $base64_signature = trim(base64_encode($tmp_signature));
        return $base64_signature;
    }

    /**
     * Get list of documents
     *
     * @return array List of previously processed documents
     */
    public function get_documents() 
    {
        $endpoint_name = "/documents/";
        $request_arguments = [];
        $documents = $this->request("GET", $endpoint_name, $request_arguments);
        if (array_key_exists("documents", $documents)) {
            return $documents["documents"];
        }
        return $documents;
    }

    /**
     * Retrieve document by ID
     *
     * @param int $document_id ID of the document you'd like to retrieve
     * @return array Data extracted from the Document
     */
    public function get_document($document_id) 
    {
        $endpoint_name = "/documents/{$document_id}/";
        $request_arguments = [ "id" => $document_id ];
        $document = $this->request("GET", $endpoint_name, $request_arguments);
        return $document;
    }

    /**
     * Process Document and extract all the fields from it
     *
     * @param string $file_path Path on disk to a file to submit for data extraction
     * @param array $categories List of categories Veryfi can use to categorize the document
     * @param boolean $delete_after_processing Delete this document from Veryfi after data has been extracted
     * @return array Data extracted from the document
     */
    public function process_document($file_path, $categories=null, $delete_after_processing=false)
    {
        $endpoint_name = "/documents/";
        if (is_null($categories)) {
            $categories = static::CATEGORIES; 
        }

        $file_name = basename($file_path);
        $image_file = fopen($file_path, "rb");
        $base64_encoded_string = base64_encode(fread($image_file, filesize($image_file))); 
        fclose($image_file);

        $request_arguments = [
            "file_name" => $file_name,
            "file_data" => $base64_encoded_string,
            "categories" => $categories,
            "auto_delete" => $delete_after_processing,
        ];
        $document = $this->request("POST", $endpoint_name, $request_arguments);
        return $document;
    }

    /**
     * Process Document by sending it to Veryfi as multipart form
     *
     * @param string $file_path Path on disk to a file to submit for data extraction
     * @param array $categories List of categories Veryfi can use to categorize the document
     * @param boolean $delete_after_processing Delete this document from Veryfi after data has been extracted
     * @return array Data extracted from the document
     */
    protected function process_document_file($file_path, $categories=null, $delete_after_processing=false)
    {
        $endpoint_name = "/documents/";
        if (is_null($categories)) {
            $categories = static::CATEGORIES; 
        }
        $file_name = basename($file_path);
        $request_arguments = [
            [ "name" =>  "file_name", "contents" => $file_name, "filename" => $file_name ],
            [ "name" =>  "categories", "contents" => $categories],
            [ "name" =>  "auto_delete", "contents" => $delete_after_processing ],
        ];
        $document = $this->request("POST", $endpoint_name, $request_arguments, true);
        return $document;
    }

    /**
     * Process Document from url and extract all the fields from it
     *
     * @param string $file_url Required if file_urls isn't specified. Publicly accessible URL to a file, e.g. "https://cdn.example.com/receipt.jpg"
     * @param array<string> $file_urls Required if file_url isn't specifies. List of publicly accessible URLs to multiple files, e.g. ["https://cdn.example.com/receipt1.jpg", "https://cdn.example.com/receipt2.jpg"]
     * @param array<string> $categories List of categories to use when categorizing the document
     * @param boolean $delete_after_processing Delete this document from Veryfi after data has been extracted
     * @param integer $boost_mode Flag that tells Veryfi whether boost mode should be enabled. When set to 1, Veryfi will skip data enrichment steps, but will process the document faster. Default value for this flag is 0
     * @param string $external_id Optional custom document identifier. Use this if you would like to assign your own ID to documents
     * @param integer $max_pages_to_process When sending a long document to Veryfi for processing, this paremeter controls how many pages of the document will be read and processed, starting from page 1.
     * @return array Data extracted from the document
     */
    public function process_document_url(
        $file_url = null, 
        $categories = null,
        $delete_after_processing = false,
        $boost_mode = 0,
        $external_id = null,
        $max_pages_to_process = null,
        $file_urls = null
    ) {
        $endpoint_name = "/documents/";
        $request_arguments = [
            "auto_delete" => $delete_after_processing,
            "boost_mode" => $boost_mode,
            "categories" => $categories,
            "external_id" => $external_id,
            "file_url" => $file_url,
            "file_urls" => $file_urls,
            "max_pages_to_process" => $max_pages_to_process,
        ];

        return $this->request("POST", $endpoint_name, $request_arguments);
        
    }

    /**
     * Delete Document from Veryfi
     *
     * @param integer $document_id ID of the document you'd like to delete
     * @return void
     */
    public function delete_document($document_id)
    {
        $endpoint_name = "/documents/{$document_id}/";
        $request_arguments = [ "id" => $document_id ];
        $this->request("DELETE", $endpoint_name, $request_arguments);
        
    }

    /**
     * Update data for a previously processed document, including almost any field like `vendor`, `date`, `notes` and etc.
     * ```veryfi_client.update_document(id, date="2021-01-01", notes="look what I did")```
     *
     * @param integer $id ID of the document you'd like to update
     * @param array $params fields to update
     * @return array A document json with updated fields, if fields are writible. Otherwise a document with unchanged fields.
     */
    public function update_document($id, $params)
    {
        $endpoint_name = "/documents/{$id}/";

        return $this->request("PUT", $endpoint_name, $params);
    }
}