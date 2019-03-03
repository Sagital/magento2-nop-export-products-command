### About

This is a CLI  program which allows exporting products from NopCommerce using the API plugin

### Installation

    mkdir -p app/code/Sagital
    cd app/code/Sagital
    git clone https://github.com/sagital/magento2-nop-export-products-command NopProductExporter
    bin/magento setup:upgrade

### Usage

    magento sagital:nop-export-products <filename.csv> (it will be found in the var directory)  
