<?php

namespace Sagital\NopProductExporter\Console\Command;

use GuzzleHttp\Client;

class OAuth
{
    protected $url;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    /**
     * OAuth constructor.
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->url = $scopeConfig->getValue('nop-url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->clientId = $scopeConfig->getValue('nop-client-id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->clientSecret = $scopeConfig->getValue('nop-client-secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->redirectUri = $scopeConfig->getValue('nop-redirect-uri', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getRefreshToken($authorizationCode)
    {
        $client = new Client();
        $res = $client->post(
            $this->url . "/api/token",
            [
                'form_params' => [
                    'code' => $authorizationCode,
                    'client_id' => $this->clientId,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri,
                    'client_secret' => $this->clientSecret,
                ]
            ]
        );
        return json_decode($res->getBody(), true)["refresh_token"];
    }

    public function getAccessToken($refreshToken)
    {
        $client = new Client();

        $res = $client->post(
            $this->url . '/api/token',
            [
                'form_params' => [
                    'refresh_token' => $refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token'
                ]
            ]
        );

        return json_decode($res->getBody(), true)["access_token"];
    }

    public function getAuthorizationCode()
    {
        $client = new Client();

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri
        ];

        $res = $client->get(
            $this->url . "/oauth/authorize",
            [

                'query' => $params,
                # we are just interested in the location header
                'allow_redirects' => false
            ]
        );

        $locationHeader = $res->getHeader('Location');

        parse_str(parse_url($locationHeader[0], PHP_URL_QUERY), $array);

        return $array['code'];
    }
}
