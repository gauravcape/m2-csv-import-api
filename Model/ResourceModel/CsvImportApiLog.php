<?php

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model\ResourceModel;

class CsvImportApiLog extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('product_apicsvimport_log', 'entity_id');
    }
}
