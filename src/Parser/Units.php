<?php

namespace Findologic\Plentymarkets\Parser;

use Findologic\Plentymarkets\Config;

/**
 * Class Units
 * @package Findologic\Plentymarkets\Parser
 */
class Units extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing units.');
            return $this->results;
        }

        foreach ($data['entries'] as $unit) {
            $this->results[$unit['id']] = $unit['unitOfMeasurement'];
        }

        return $this->results;
    }

    /**
     * Map unit id to actual value
     *
     * @param int $id
     * @return mixed|bool
     */
    public function getUnitValue($id)
    {
        if (isset($this->results[$id])) {
            return $this->results[$id];
        }

        return Config::DEFAULT_EMPTY_VALUE;
    }
}