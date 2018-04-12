<?php

namespace Findologic\Plentymarkets\Parser;

/**
 * Class Properties
 * @package Findologic\Plentymarkets\Parser
 */
class Properties extends ParserAbstract implements ParserInterface
{
    /**
     * @inheritdoc
     */
    public function parse($data)
    {
        if (!isset($data['entries'])) {
            $this->handleEmptyData('No data provided for parsing item properties.');
            return $this->results;
        }

        foreach ($data['entries'] as $property) {
            $this->results[$property['id']] = [
                'backendName' => $property['backendName'],
                'propertyGroupId' => $property['propertyGroupId']
            ];

            if (isset($property['names'])) {
                foreach ($property['names'] as $propertyName) {
                    $this->results[$property['id']]['names'][strtoupper($propertyName['lang'])] = [
                        'name' => $propertyName['name'],
                        'description' => $propertyName['description']
                    ];
                }
            }
        }

        return $this->results;
    }

    /**
     * @param int $propertyId
     * @return string
     */
    public function getPropertyName($propertyId)
    {
        if (isset($this->results[$propertyId]['names'][strtoupper($this->getLanguageCode())])) {
            return $this->results[$propertyId]['names'][strtoupper($this->getLanguageCode())]['name'];
        } else if (isset($this->results[$propertyId]['backendName'])) {
            return $this->results[$propertyId]['backendName'];
        }

        return $this->getDefaultEmptyValue();
    }
}