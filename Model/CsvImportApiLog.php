<?php
/**
 * @category GauravCape
 * @author Gaurav
 * @copyright Copyright (c) 2023 Gaurav
 * @package GauravCape_CsvImportApi
 */

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model;

use GauravCape\CsvImportApi\Model\ResourceModel\CsvImportApiLog as CsvImportApiLogResourceModel;

class CsvImportApiLog extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Initialize the CsvImportApiLog model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(CsvImportApiLogResourceModel::class);
    }
}
