<?php
/**
 * Created by PhpStorm.
 * User: cip
 * Date: 16.12.2018
 * Time: 12:03
 */

namespace Sagital\NopProductExporter\Console\Command;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class NopClient
{

    protected $url;

    private $client;
    private $defaultHeaders;
    private $logger;

    public function __construct( LoggerInterface $logger,
                                 \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                  OAuth $oauth
                                 )
    {
        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Psr\Log\LoggerInterface::class);
        $this->client = new Client();

        $this->url = $scopeConfig->getValue('nop-url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $authorizationCode = $oauth->getAuthorizationCode();
        $refreshToken = $oauth->getRefreshToken($authorizationCode);
        $accessToken = $oauth->getAccessToken($refreshToken);


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


//    private function loadProducts($page, $last_update = null)
//    {
//
//        $params = [
//            'limit' => 200,
//            'page' => $page
//        ];
//
//        if ($last_update) {
//            $params['updated_at_min'] = $last_update;
//        }
//
//
//        $res = $this->client->get($this->url . "/api/products",
//            ['headers' => $this->defaultHeaders,
//                'query' => $params,
//                'connect_timeout' => 30
//            ]);
//
//        $products = json_decode($res->getBody(), true)['products'];
//
//        return array_map(function ($c) {
//            return Product::fromNopCommerce($c);
//        }, $products);
//
//    }


    public function getProductsBySku()
    {

        $result = array();

        $page = 1;

        //while ( $products = $this->getProducts( $page) and $page < 6) {

        while ( $products = $this->getProducts( $page)) {
//        $products = $this->getProducts(4);
            $page++;
            $this->logger->debug("Loading products page $page . ");

            foreach ($products as $product) {
//                $this->logger->debug(json_encode($product));
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

        $res = $this->client->get($this->url . "/api/products",
            ['headers' => $this->defaultHeaders,
                'query' => $params,
                'connect_timeout' => 30
            ]);

        $products = json_decode($res->getBody(), true)['products'];
        return $products;

    }



    public function getProductCategoryMappings($page)
    {

        $params = [
            'limit' => 200,
            'page' => $page
        ];

        $res = $this->client->get($this->url . "/api/product_category_mappings",

            ['headers' => $this->defaultHeaders,
                'connect_timeout' => 30,
                'query' => $params]);

        $mappings = json_decode($res->getBody(), true)['product_category_mappings'];

     //   $this->logger->debug(json_encode($mappings));

        return array_reduce($mappings, function ($result, $item) {
            $result[$item['product_id']][] = $item['category_id'];
            return $result;
        }, array());
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

        $res = $this->client->get($this->url . "/api/categories",
            ['headers' => $this->defaultHeaders, 'connect_timeout' => 30, 'query' => $params]);

        $categories = json_decode($res->getBody(), true)['categories'];
        return $categories;

    }


    public function loadAllCategories()
    {

        $page = 1;

        $result = array();

        while ( $categories = $this->getCategories( $page ) ) {

            $this->logger->debug("Loading categories page $page . ");
            $page++;

            foreach ($categories as $category) {
                $result[$category['id']] = $category;
            }

        }

        return $result;


    }





}

