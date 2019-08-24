<?php

namespace Sagital\NopProductExporter\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;

class CsvWriter
{

    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

    /**
     * Source file handler.
     *
     * @var \Magento\Framework\Filesystem\File\Write
     */
    protected $fileHandler;

    /**
     * @var \Magento\ImportExport\Model\Export\Adapter\Factory
     */
    protected $exportAdapterFactory;
    private $logger;
    /**
     * CsvWriter constructor.
     */
    public function __construct(
        FileFactory $fileFactory,
                                \Magento\ImportExport\Model\Export\Adapter\Factory $exportAdapterFac
    ) {
        $this->fileFactory = $fileFactory;
        $this->exportAdapterFactory = $exportAdapterFac;
        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Psr\Log\LoggerInterface::class);
    }

    public function writeCsv($products, $mappings, $categories, $fileName)
    {

        /**
         * var \Magento\ImportExport\Model\Export\Adapter\Csv
         */
        $csvWriter = $this->exportAdapterFactory->create(\Magento\ImportExport\Model\Export\Adapter\Csv::class);

        $headers = ['sku', 'attribute_set_code', 'product_type', 'product_websites', 'weight', 'product_online', 'tax_class_name', 'visibility', 'name', 'price', 'qty', 'categories', 'base_image', 'thumbnail_image', 'additional_images', 'small_image'];

        $csvWriter->setHeaderCols($headers);

        foreach ($products as $product) {
            $categoriesText = $this->getCategoriesText($mappings[$product['id']], $categories);

            $row = [];

            $row['sku'] = $product['sku'];
            $row['attribute_set_code'] = 'Default';
            $row['product_type'] = 'simple';
            $row['product_websites'] = 'base';
            $row['name'] = str_replace("+", "plus", $product['name']);
            $row['weight'] = $product['weight'];
            $row['product_online'] = $product['published'];
            $row['tax_class_name'] = 'Taxable Goods';
            $row['visibility'] = "Catalog, Search";
            $row['price'] = $product['price'];
            $row['qty'] = $product['stock_quantity'];
            $row['categories'] = $categoriesText;

            $images = $product['images'];

            $first_image = array_shift($images);

            $row['base_image'] = substr($first_image['src'], 46);
            $row['small_image'] = substr($first_image['src'], 46);
            $row['thumbnail_image'] = substr($first_image['src'], 46);

            $additionalImages = [];

            foreach ($images as $image) {
                $additionalImages[] = substr($image['src'], 46);
            }

            $row['additional_images'] = join(",", $additionalImages);

            $csvWriter->writeRow($row);
        }

        return $this->fileFactory->create(
            $fileName,
            $csvWriter->getContents(),
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }

    private function addToGraph(array & $graph, $categoryId, $categoriesMap)
    {
        if ($categoryId == 0) {
            return;
        }

        $parentCategoryId = $categoriesMap[$categoryId]['parent_category_id'];

        if (!array_key_exists($parentCategoryId, $graph)) {
            $graph[$parentCategoryId] = [];
        }

        if (!in_array($categoryId, $graph[$parentCategoryId])) {
            $graph[$parentCategoryId][] = $categoryId;
        }
    }

    private function getCategoriesText($productCategories, $categoriesMap)
    {
        $graph = [];

        foreach ($productCategories as $categoryId) {
            if (!array_key_exists($categoryId, $categoriesMap)) {
                continue;
            }

            $parentCategoryId = $categoriesMap[$categoryId]['parent_category_id'];

            $this->addToGraph($graph, $categoryId, $categoriesMap);
            $this->addToGraph($graph, $parentCategoryId, $categoriesMap);
        }

        $stack = [];
        $visited = [];

        foreach ($graph as $parent => $children) {
            $this->dfs($graph, $parent, $stack, $visited);
        }

        $categoryNames = [];

        $current  = "";

        array_pop($stack);

        while (!empty($stack)) {
            $id = array_pop($stack);

            if ($categoriesMap[$id]['parent_category_id'] == 0) {
                if ($current) {
                    $categoryNames[] = $current;
                }

                $current = 'Default Category';
            }
            $categoryName =   $categoriesMap[$id]['name'];
            $categoryName = str_replace("/", "-", $categoryName);
            $categoryName = str_replace("+", "plus", $categoryName);
            $current .= "/" . $categoryName;
        }

        $categoryNames[] = $current;

        $categoriesText = join(", ", $categoryNames);

        return $categoriesText;
    }

    private function dfs(array $graph, $node, array & $stack, array & $visited)
    {
        if (array_key_exists($node, $visited)) {
            return;
        }

        if (array_key_exists($node, $graph)) {
            foreach ($graph[$node] as $child) {
                $this->dfs($graph, $child, $stack, $visited);
            }
        }
        array_push($stack, $node);

        $visited[$node] = true;
    }
}
