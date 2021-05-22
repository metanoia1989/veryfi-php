<?php
namespace veryfi\errors;

use Exception;
use Veryfi\errors\NotImplemented;

class VeryfiClientError extends Exception
{

    /**
     * error message
     *
     * @var string
     */
    protected $error;

    /**
     * error code
     *
     * @var integer
     */
    protected $code;

    /**
     * error status
     *
     * @var integer
     */
    protected $status;

    /**
     * Guzzle Response
     *
     * @var \Psr\Http\Message\ResponseInterface 
     */
    protected $raw_response;
    
    public function __construct($raw_response, $error, $code)
    {
        $this->$error = $error;
        $this->$code = $code;

        $this->raw_response = $raw_response;
        $this->status = $raw_response->status_code;

        parent::__construct($error, $code); 
    } 

    /**
     * Veryfi API returns error messages with a json body
     *  like:
     *  {
     *      'status': 'fail',
     *      'error': 'Human readable error description.'
     *  }
     *
     * @param \Psr\Http\Message\ResponseInterface $raw_response
     * @return $this
     */
    public static function fromResponse($raw_response) 
    {
        $json_response = json_decode($raw_response->getBody()->getContents(), true);

        // TODO Add Error Codes to API response
        // code = error_info["code"] ?? "";
        
        try {
            return error_map(
                $raw_response->status_code, 
                $json_response["error"], 
                $json_response["code"]
            );
        } catch (\Throwable $th) {
            throw new NotImplemented(
                "Unknown error Please contact customer support at support@veryfi."
            );
        }
    }
}
