<?php

namespace Sagital\NopProductExporter\Console\Command;


use Symfony\Component\Console\Command\Command;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManagerInterface;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ExportProductsCommand extends Command
{

    /**
     * @var ObjectManagerInterface $objectManager
     */
    protected $objectManager;

    protected $nopClient;

    protected $csvWriter;


    /**
     * @var LoggerInterface $logger ;
     */
    protected $logger;

    /**
     * @var State $state
     */
    protected $state;


    const FILE_ARGUMENT = 'File';
    const IMAGES_PATH_ARGUMENT = 'Images Path';

    public function __construct(ObjectManagerInterface $objectManager,
                                LoggerInterface $logger,
                                State $state,
                                NopClient $nopClient,
                                CsvWriter $csvWriter


)
    {

        $this->logger = $logger;
        $this->state = $state;
        $this->objectManager = $objectManager;
        $this->nopClient = $nopClient;
        $this->csvWriter = $csvWriter;

        parent::__construct();
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sagital:nop-export-products')
            ->setDescription('Export products from Nop Commerce to a CSV file')
            ->setDefinition([
                new InputArgument(
                    self::FILE_ARGUMENT,
                    InputArgument::REQUIRED,
                    'Products CSV file location'
                )
            ]);

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

//        $this->state->setAreaCode('adminhtml');

        $file = $input->getArgument(self::FILE_ARGUMENT);
        $products = $this->nopClient->getProductsBySku();
        $mappings = $this->nopClient->getCategoryMappings();
        $categories = $this->nopClient->loadAllCategories();

        $this->csvWriter->writeCsv($products, $mappings, $categories, $file);


    }


}