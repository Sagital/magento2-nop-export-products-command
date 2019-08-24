<?php

namespace Sagital\NopProductExporter\Console\Command;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class NopClient
{
    protected $url;

    private $client;
    public $defaultHeaders;
    private $logger;

    public function __construct(
        LoggerInterface $logger,
                                 \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                  OAuth $oauth
                                 ) {
        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Psr\Log\LoggerInterface::class);
        $this->client = new Client();

        $this->url = $scopeConfig->getValue('nop-url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $authorizationCode = $oauth->getAuthorizationCode();
        $refreshToken = $oauth->getRefreshToken($authorizationCode);
        $accessToken = $oauth->getAccessToken($refreshToken);

        $this->logger->debug($accessToken);
        $this->defaultHeaders = ['Authorization' => "Bearer " . $accessToken];
    }

    public function getCategoryMappings()
    {
        $page = 1;
        $mappings = [];

        while ($nop_mappings = $this->getProductCategoryMappings($page)) {
            $page++;
            foreach ($nop_mappings as $product_id => $category_ids) {
                if (array_key_exists($product_id, $mappings)) {
                    $mappings[$product_id] = array_merge($mappings[$product_id], $category_ids);
                } else {
                    $mappings[$product_id] = $category_ids;
                }
            }
        }

        return $mappings;
    }

    public function getProductsBySku()
    {
        $result = [];

        $page = 1;

        while ($products = $this->getProducts($page)) {
            $page++;
            $this->logger->debug("Loading products page $page . ");

            foreach ($products as $product) {
                $result[$product['sku']] = $product;
            }
        }

        return $result;
    }

    public function getProducts($page, $last_update = null)
    {
        $params = [
            'limit' => 200,
            'page' => $page
        ];

        if ($last_update) {
            $params['updated_at_min'] = $last_update;
        }

        $res = $this->client->get(
            $this->url . "/api/products",
            ['headers' => $this->defaultHeaders,
                'query' => $params,
                'connect_timeout' => 30
            ]
        );

        $products = json_decode($res->getBody(), true)['products'];
        return $products;
    }

    public function getProductCategoryMappings($page)
    {
        $params = [
            'limit' => 200,
            'page' => $page
        ];

        $res = $this->client->get(
            $this->url . "/api/product_category_mappings",

            ['headers' => $this->defaultHeaders,
                'connect_timeout' => 30,
                'query' => $params]
        );

        $mappings = json_decode($res->getBody(), true)['product_category_mappings'];

        return array_reduce($mappings, function ($result, $item) {
            $result[$item['product_id']][] = $item['category_id'];
            return $result;
        }, []);
    }

    public function getCategories($page, $last_update = null)
    {
        $params = [
            'limit' => 200,
            'page' => $page
        ];

        if ($last_update) {
            $params['updated_at_min'] = $last_update;
        }

        $res = $this->client->get(
            $this->url . "/api/categories",
            ['headers' => $this->defaultHeaders, 'connect_timeout' => 30, 'query' => $params]
        );

        $categories = json_decode($res->getBody(), true)['categories'];
        return $categories;
    }

    public function loadAllCategories()
    {
        $page = 1;

        $result = [];

        while ($categories = $this->getCategories($page)) {
            $this->logger->debug("Loading categories page $page . ");
            $page++;

            foreach ($categories as $category) {
                $result[$category['id']] = $category;
            }
        }

        return $result;
    }
}
