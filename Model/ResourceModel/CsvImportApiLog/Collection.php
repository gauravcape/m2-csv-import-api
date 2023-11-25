<?php
/**
 * @category GauravCape
 * @author Gaurav
 * @copyright Copyright (c) 2023 Gaurav
 * @package GauravCape_CsvImportApi
 */

declare(strict_types=1);

namespace GauravCape\CsvImportApi\Model\ResourceModel\CsvImportApiLog;

/**
 * Collection for CsvImportApiLog entities
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Initialize resource collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \GauravCape\CsvImportApi\Model\CsvImportApiLog::class,
            \GauravCape\CsvImportApi\Model\ResourceModel\CsvImportApiLog::class
        );
    }
}
