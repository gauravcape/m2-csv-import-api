<?php

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model;

use GauravCape\CsvImportApi\Model\ResourceModel\CsvImportApiLog as CsvImportApiLogResourceModel;

class CsvImportApiLog extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init(CsvImportApiLogResourceModel::class);
    }
}
