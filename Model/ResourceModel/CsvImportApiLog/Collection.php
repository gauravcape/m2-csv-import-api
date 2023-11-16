<?php

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model\ResourceModel\CsvImportApiLog;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('GauravCape\CsvImportApi\Model\CsvImportApiLog', 'GauravCape\CsvImportApi\Model\ResourceModel\CsvImportApiLog');
    }
}
