<?php

declare(strict_types=1);

namespace Descope\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Descope\SDK\Exception\AuthException;
use Descope\SDK\EndpointsV1;
use Descope\SDK\Token\Verifier;

class API
{
    private $httpClient;
    private $projectId;
    private $managementKey;

    /**
     * Constructor for API class.
     *
     * @param string      $projectId
     * @param string|null $managementKey Management key for authentication.
     */
    public function __construct(string $projectId, ?string $managementKey)
    {
        $this->httpClient = new Client();

        if (!empty($_ENV['DESCOPE_LOG_PATH'])) {
            $log = new Logger('descope_guzzle_log');
            $log->pushHandler(new StreamHandler($_ENV['DESCOPE_LOG_PATH'], Logger::DEBUG));
            $stack = HandlerStack::create();
            $stack->push(
                Middleware::log(
                    $log,
                    new MessageFormatter(MessageFormatter::DEBUG)
                )
            );
            $this->httpClient = new Client(['handler' => $stack]);
        } else {
            $this->httpClient = new Client();
        }

        $this->projectId = $projectId;
        $this->managementKey = $managementKey ?? '';
    }

    /**
     * Recursively transforms empty arrays to empty objects.
     *
     * This function ensures that empty arrays in the input data are
     * converted to empty objects (stdClass) before being JSON encoded.
     *
     * @param  mixed $data The data to transform, which can be an array or any other type.
     * @return mixed The transformed data with empty arrays replaced by empty objects.
     */
    private function transformEmptyArraysToObjects($data)
    {
        if (is_array($data)) {
            // Check if the array is associative
            $isAssociative = count(array_filter(array_keys($data), 'is_string')) > 0;
    
            // If the array is empty and associative, convert to stdClass object
            if (empty($data) && $isAssociative) {
                return new \stdClass();
            }
    
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    // Recursively handle nested arrays
                    $value = $this->transformEmptyArraysToObjects($value);
                }
            }
        }
        return $data;
    }

    /**
     * Requests JwtResponse from Descope APIs with the given body and auth token.
     *
     * @param  string $uri              URI endpoint.
     * @param  array  $body             Request body.
     * @param  bool   $useManagementKey Whether to use the management key for authentication.
     * @return array JWT response array.
     * @throws AuthException|GuzzleException|\JsonException If the request fails.
     */
    public function doPost(string $uri, array $body, ?bool $useManagementKey = false, ?string $refreshToken = null): array
    {
        $authToken = "";

        if ($refreshToken) {
            $authToken = $this->getAuthToken(false, $refreshToken);
        } else {
            $authToken = $this->getAuthToken($useManagementKey, '');
        }

        $body = $this->transformEmptyArraysToObjects($body);
        $jsonBody = empty($body) ? '{}' : json_encode($body);
        try {
            $response = $this->httpClient->post(
                $uri,
                [
                    'headers' => $this->getHeaders($authToken),
                    'body' => $jsonBody,
                ]
            );
            
            // Ensure the response is an object with getBody method
            if (!is_object($response) || !method_exists($response, 'getBody') || !method_exists($response, 'getHeader')) {
                throw new AuthException(500, 'internal error', 'Invalid response from API');
            }

            // Read Body
            $body = $response->getBody();
            $body->rewind();
            $contents = $body->getContents() ?? [];

            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';

            return [
                'statusCode' => $statusCode,
                'response' => $responseBody,
            ];
        }
    }

    /**
     * Sends a GET request to the specified URI with an optional auth token.
     *
     * @param  string $uri              URI endpoint.
     * @param  bool   $useManagementKey Whether to use the management key for authentication.
     * @return array JWT response array.
     * @throws AuthException|GuzzleException|\JsonException If the request fails.
     */
    public function doGet(string $uri, bool $useManagementKey, ?string $refreshToken = null): array
    {
        $authToken = "";

        if ($refreshToken) {
            $authToken = $this->getAuthToken(false, $refreshToken);
        } else {
            $authToken = $this->getAuthToken($useManagementKey);
        }

        try {
            $response = $this->httpClient->get(
                $uri,
                [
                'headers' => $this->getHeaders($authToken),
                ]
            );

            // Ensure the response is an object with getBody method
            if (!is_object($response) || !method_exists($response, 'getBody') || !method_exists($response, 'getHeader')) {
                throw new AuthException(500, 'internal error', 'Invalid response from API');
            }

            // Read Body
            $body = $response->getBody();
            $body->rewind();
            $contents = $body->getContents() ?? [];

            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';

            return [
                'statusCode' => $statusCode,
                'response' => $responseBody,
            ];
        }
    }

    /**
     * Sends a DELETE request to the specified URI with an auth token.
     *
     * @param  string $uri URI endpoint.
     * @return array JWT response array.
     * @throws AuthException|GuzzleException|\JsonException If the request fails.
     */
    public function doDelete(string $uri): array
    {
        $authToken = $this->getAuthToken(true);

        try {
            $response = $this->httpClient->delete(
                $uri,
                [
                'headers' => $this->getHeaders($authToken),
                ]
            );

            // Ensure the response is an object with getBody method
            if (!is_object($response) || !method_exists($response, 'getBody') || !method_exists($response, 'getHeader')) {
                throw new AuthException(500, 'internal error', 'Invalid response from API');
            }

            // Read Body
            $body = $response->getBody();
            $body->rewind();
            $contents = $body->getContents() ?? [];

            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';

            return [
                'statusCode' => $statusCode,
                'response' => $responseBody,
            ];
        }
    }

    /**
     * Generates a JWT response array with the given parameters.
     *
     * @param  array       $responseBody
     * @param  string|null $refreshToken Refresh token.
     * @param  string|null $audience     Audience.
     * @return array JWT response array.
     */
    public function generateJwtResponse(array $responseBody, ?string $refreshToken = null, ?string $audience = null): array
    {
        $jwtResponse = $this->generateAuthInfo($responseBody, $refreshToken, true, $audience);

        $jwtResponse['user'] = $responseBody['user'] ?? [];
        $jwtResponse['firstSeen'] = $responseBody['firstSeen'] ?? true;

        return $jwtResponse;
    }

    /**
     * Generates headers for the HTTP request.
     *
     * @param  string|null $authToken Authentication token.
     * @return array Headers array.
     */
    private function getHeaders(string $authToken): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $headers['Authorization'] = "Bearer $authToken";

        return $headers;
    }

    /**
     * Constructs the auth token based on whether the management key is used.
     *
     * @param  bool $useManagementKey Whether to use the management key for authentication.
     * @return string The constructed auth token.
     */
    private function getAuthToken(bool $useManagementKey, ?string $refreshToken = null): string
    {
        if ($useManagementKey && !empty($this->managementKey)) {
            return $this->projectId . ':' . $this->managementKey;
        }

        if ($refreshToken) {
            return $this->projectId . ':' . $refreshToken;
        }

        return $this->projectId;
    }

    /**
     * Generates authentication information from the response body.
     *
     * This method processes the response body to extract JWTs, session data,
     * and cookie settings, and adjusts properties based on the token type.
     *
     * @param  array       $responseBody The API response body containing JWTs and user data.
     * @param  string|null $refreshToken Optional refresh token.
     * @param  bool        $userJwt      Indicates if user-related JWT information should be processed.
     * @param  string|null $audience     Optional audience identifier.
     * @return array The structured JWT response array containing session and user data.
     */
    private function generateAuthInfo(array $responseBody, ?string $refreshToken, bool $userJwt, ?string $audience): array
    {
        $jwtResponse = [];
        $stJwt = $responseBody['sessionJwt'] ?? '';

        if ($stJwt) {
            $jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME] = $stJwt;
        }
        
        $rtJwt = $responseBody['refreshJwt'] ?? '';

        if ($refreshToken) {
            $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME] = $refreshToken;
        } elseif ($rtJwt) {
            $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME] = $rtJwt;
        }

        $jwtResponse = $this->adjustProperties($jwtResponse, $userJwt);

        if ($userJwt) {
            $jwtResponse[EndpointsV1::$COOKIE_DATA_NAME] = [
                'exp' => $responseBody['cookieExpiration'] ?? 0,
                'maxAge' => $responseBody['cookieMaxAge'] ?? 0,
                'domain' => $responseBody['cookieDomain'] ?? '',
                'path' => $responseBody['cookiePath'] ?? '/',
            ];
        }

        return $jwtResponse;
    }

    /**
     * Adjusts properties of the JWT response array.
     *
     * This method sets permissions, roles, and tenant data from the JWT
     * and processes the issuer and subject values to extract project and user IDs.
     *
     * @param  array $jwtResponse The JWT response array to adjust.
     * @param  bool  $userJwt     Indicates if user-related JWT information should be processed.
     * @return array The adjusted JWT response array with updated properties.
     */
    private function adjustProperties(array $jwtResponse, bool $userJwt): array
    {
        if (isset($jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME])) {
            $jwtResponse['permissions'] = $jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME]['permissions'] ?? [];
            $jwtResponse['roles'] = $jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME]['roles'] ?? [];
            $jwtResponse['tenants'] = $jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME]['tenants'] ?? [];
        } elseif (isset($jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME])) {
            $jwtResponse['permissions'] = $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME]['permissions'] ?? [];
            $jwtResponse['roles'] = $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME]['roles'] ?? [];
            $jwtResponse['tenants'] = $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME]['tenants'] ?? [];
        } else {
            $jwtResponse['permissions'] = $jwtResponse['permissions'] ?? [];
            $jwtResponse['roles'] = $jwtResponse['roles'] ?? [];
            $jwtResponse['tenants'] = $jwtResponse['tenants'] ?? [];
        }

        $issuer = $jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME]['iss'] ??
                  $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME]['iss'] ??
                  $jwtResponse['iss'] ?? '';

        $issuerParts = explode("/", $issuer);
        $jwtResponse['projectId'] = end($issuerParts);

        $sub = $jwtResponse[EndpointsV1::$SESSION_TOKEN_NAME]['sub'] ??
               $jwtResponse[EndpointsV1::$REFRESH_TOKEN_NAME]['sub'] ??
               $jwtResponse['sub'] ?? '';

        if ($userJwt) {
            $jwtResponse['userId'] = $sub;
        } else {
            $jwtResponse['keyId'] = $sub;
        }

        return $jwtResponse;
    }
}
