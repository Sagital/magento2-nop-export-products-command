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

    /**
     * CsvWriter constructor.
     */
    public function __construct(
        FileFactory $fileFactory,
                                \Magento\ImportExport\Model\Export\Adapter\Factory $exportAdapterFac
    ) {
        $this->fileFactory = $fileFactory;
        $this->exportAdapterFactory = $exportAdapterFac;
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

    private function getCategoriesText($productCategories, $categoriesMap)
    {
        $parentCategoryId = 0;
        $categoryNames = ['Default Category'];

        $remaining = count($productCategories);

        while ($remaining > 0) {
            foreach ($categoriesMap as $categoryId => $category) {
                if ($category['parent_category_id'] == $parentCategoryId) {
                    $categoryName = $category['name'];
                    $categoryName = str_replace("/", "-", $categoryName);
                    $categoryNames[] = str_replace("+", "plus", $categoryName);
                    $parentCategoryId = $categoryId;
                    $remaining--;
                }
            }
        }

        $categoriesText = join("/", $categoryNames);

        return $categoriesText;
    }
}
