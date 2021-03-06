<?php

namespace Keesschepers\DhlParcelApi;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use kamermans\OAuth2\OAuth2Middleware;

class DhlClient
{
    private $apiUserId;
    private $apiKey;
    private $apiTimeout;
    private $httpClient;

    const ENDPOINT = 'https://api-gw.dhlparcel.nl';

    public function __construct(string $userId = null, string $key = null, float $apiTimeout = 0.5)
    {
        $this->apiUserId = $userId;
        $this->apiKey = $key;
        $this->apiTimeout = $apiTimeout;
    }

    private function setupClient()
    {
        if (null !== $this->httpClient) {
            return;
        }

        $config = ['base_uri' => self::ENDPOINT];

        if (!empty($this->apiUserId) && !empty($this->apiKey)) {
            $oAuthClient = new Client(
                [
                    'base_uri' => sprintf('%s/%s', self::ENDPOINT, 'authenticate/api-key'),
                ]
            );
            $grantType = new OAuthDhlGrantType(
                $oAuthClient,
                [
                    'client_id' => $this->apiUserId,
                    'client_secret' => $this->apiKey,
                ]
            );
            $oauth = new OAuth2Middleware($grantType);

            $stack = HandlerStack::create();
            $stack->push($oauth);

            $config['handler'] = $stack;
            $config['auth'] = 'oauth';
        }

        $this->httpClient = new Client($config);
    }

    public function timeWindows($countryCode, $postalCode)
    {
        $this->setupClient();

        $response = $this->httpClient->get(
            '/time-windows',
            [
                'timeout' => ($this->apiTimeout / 1000),
                'query' => ['countryCode' => $countryCode, 'postalCode' => $postalCode],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new DhlApiException('Could not retrieve time window information due to API server error.');
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param array $parameters
     * @return mixed
     * @throws \Keesschepers\DhlParcelApi\DhlApiException
     */
    public function createPickupRequest(array $parameters)
    {
        $this->setupClient();

        try {
            /** @var Response $response */
            $response = $this->httpClient->post(
                '/pickup-requests',
                [
                    'timeout' => ($this->apiTimeout / 1000),
                    'json' => $parameters,
                ]
            );
        } catch (BadResponseException $e) {
            throw new DhlApiException(
                sprintf(
                    'Could not could not create a label due to API server error: %s',
                    $e->getResponse()->getBody()
                )
            );
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function createLabel(array $parameters)
    {
        $this->setupClient();

        try {
            $response = $this->httpClient->post(
                '/labels',
                [
                    'timeout' => ($this->apiTimeout / 1000),
                    'json' => $parameters,
                ]
            );
        } catch (BadResponseException $e) {
            throw new DhlApiException(
                sprintf(
                    'Could not could not create a label due to API server error: %s',
                    $e->getResponse()->getBody()
                )
            );
        }

        return new DhlParcel(json_decode($response->getBody()->getContents(), true));
    }
  
    public function trackAndTrace(array $orderReferences)
    {
        $this->setupClient();

        $response = $this->httpClient->get(
            '/track-trace',
            [
                'timeout' => ($this->apiTimeout / 1000),
                'query' => ['key' => implode(',', $orderReferences)],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new DhlApiException('Could not retrieve track trace information due to API server error.');
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function findParcelShopLocations($postalCode, $country)
    {
        $this->setupClient();

        $response = $this->httpClient->get(
            sprintf('/parcel-shop-locations/%s', strtolower($country)),
            [
                'timeout' => ($this->apiTimeout / 1000),
                'query' => ['limit' => 10, 'zipCode' => $postalCode],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new DhlApiException('Could not retrieve track trace information due to API server error.');
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function findParcelShop($country, $id)
    {
        $this->setupClient();

        $response = $this->httpClient->get(
            sprintf('/parcel-shop-locations/%s/%s', strtolower($country), $id),
            [
                'timeout' => ($this->apiTimeout / 1000),
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new DhlApiException('Could not retrieve track trace information due to API server error.');
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function findLabel(string $labelId)
    {
        $this->setupClient();

        $response = $this->httpClient->get(
            sprintf('/labels/%s', $labelId),
            [
                'timeout' => ($this->apiTimeout / 1000),
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new DhlApiException('Could not retrieve label information due to API server error.');
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function pieces(string $pieceId, string $zipCode)
    {
        $this->setupClient();

        $response = $this->httpClient->get(
            sprintf('/pieces/%s/pod', $pieceId),
            [
                'timeout' => ($this->apiTimeout / 1000),
                'query' => [
                    'receiver.address.postalCode' => $zipCode,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new DhlApiException('Could not retrieve pieces information due to API server error.');
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
