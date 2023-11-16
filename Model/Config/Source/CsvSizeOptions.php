<?php
namespace GauravCape\CsvImportApi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class CsvSizeOptions implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => '1mb'],
            ['value' => '2', 'label' => '2mb'],
            ['value' => '3', 'label' => '3mb'],
            ['value' => '4', 'label' => '4mb'],
            ['value' => '5', 'label' => '5mb'],
            ['value' => '6', 'label' => '6mb'],
            ['value' => '7', 'label' => '7mb'],
            ['value' => '8', 'label' => '8mb'],
            ['value' => '9', 'label' => '9mb'],
            ['value' => '10', 'label' => '10mb']
        ];
    }
}
