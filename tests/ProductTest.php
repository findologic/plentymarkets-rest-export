<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Config;
use Findologic\Plentymarkets\Product;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Parser\SalesPrices;
use PHPUnit_Framework_TestCase;

class ProductTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Findologic\Plentymarkets\Product
     */
    protected $product;

    protected $defaultEmptyValue = Config::DEFAULT_EMPTY_VALUE;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        $registry = $this->getRegistryMock();
        $this->product = new Product($registry);
    }

    public function setFieldProvider()
    {
        return array(
            // Some value is set but getter is called for value which is not set, results should be null
            array('testKey', 'getKey', 'testValue', $this->defaultEmptyValue, false),
            // Value set and getter returns correct results
            array('testKey', 'testKey', 'testValue', 'testValue', false),
        );
    }

    /**
     * Test setting the 'fields' array setter method
     *
     * @dataProvider setFieldProvider
     */
    public function testSetField($setKey, $getKey, $value, $expectedResult, $arrayFlag)
    {
        $this->product->setField($setKey, $value, $arrayFlag);

        $this->assertSame($expectedResult, $this->product->getField($getKey));
    }

    public function setFieldWithArrayProvider()
    {
        return array(
            // Set attribute without $array flag, result should be plain value
            array('testKey', 'test', 'test', false, 2),
            // Set attribute wit $array flag, result should contain array with values
            array('testKey', 'test', array('test', 'test'), true, 2),
        );
    }

    /**
     * Some fields can have multiple values so it can be saved as array of values
     *
     * @dataProvider setFieldWithArrayProvider
     */
    public function testSetFieldWithArray($key, $value, $expectedResult, $arrayFlag, $times)
    {
        for ($i = 0; $i < $times; $i++) {
            $this->product->setField($key, $value, $arrayFlag);
        }

        $this->assertSame($expectedResult, $this->product->getField($key));
    }

    public function setAttributeFieldProvider()
    {
        return array(
            // Set attribute with one value
            array(
                'Test Attribute',
                array('Test Value'),
                array('Test Attribute' => array('Test Value'))
            ),
            // Set attribute with multiple value
            array(
                'Test Attribute',
                array('Test Value', 'Test Value 2'),
                array('Test Attribute' => array('Test Value', 'Test Value 2'))
            ),
            // Set attribute with multiple values which have duplicates
            array(
                'Test Attribute',
                array('Test Value', 'Test Value 2', 'Test Value 2'),
                array('Test Attribute' => array('Test Value', 'Test Value 2'))
            ),
        );
    }

    /**
     * @dataProvider setAttributeFieldProvider
     */
    public function testSetAttributeField($attribute, $values, $expectedResult)
    {
        foreach ($values as $value) {
            $this->product->setAttributeField($attribute, $value);
        }

        $this->assertSame($expectedResult, $this->product->getField('attributes'));
    }

    /**
     * Test if passed path is not string
     */
    public function getProductFullUrlEmptyPath()
    {
        $productMock = $this->getProductMock(array('handleEmptyData'));
        $productMock->expects($this->once())->method('handleEmptyData')->willReturn('');

        $this->assertSame('', $productMock->getProductFullUrl(false));
    }

    public function getProductFullUrlProvider()
    {
        return array(
            // Trim path
            array('test.com', '/test/', 1 , 'http://test.com/test/a-1'),
            // No trim
            array('test.com', 'test', 1, 'http://test.com/test/a-1'),
        );
    }

    /**
     * @dataProvider getProductFullUrlProvider
     */
    public function testGetProductFullUrl($storeUrl, $path, $productId, $expectedResult)
    {
        $productMock = $this->getProductMock(array('getStoreUrl', 'getItemId'));
        $productMock->expects($this->once())->method('getStoreUrl')->willReturn($storeUrl);
        $productMock->expects($this->once())->method('getItemId')->willReturn($productId);

        $this->assertSame($expectedResult, $productMock->getProductFullUrl($path));
    }

    /**
     *  array (
     *      'id' => 102,
     *      'position' => 0,
     *      'manufacturerId' => 2,
     *      'createdAt' => '2017-01-01T07:47:30+01:00',
     *      'storeSpecial' => 0,
     *      'isActive' => true,
     *      'type' => 'default',
     *      ...
     *      'itemProperties' => array (
     *          ...
     *      ),
     *      'texts' => array (
     *          0 => array (
     *              'lang' => 'en',
     *              'name1' => 'Name',
     *              'name2' => '',
     *              'name3' => '',
     *              'shortDescription' => 'Description.',
     *              'metaDescription' => 'Meta description',
     *              'description' => 'Long description',
     *              'urlPath' => 'Path',
     *              'keywords' => 'Keyword'
     *          ),
     *      ),
     *  )
     */
    public function processInitialDataProvider()
    {
        return array(
            // No data given, item object should not have any information
            array('', array(), array()),
            // Product initial data provided but the texts array is empty
            array(
                '1',
                array(
                    'id' => '1',
                    'createdAt' => '2001-12-12 14:12:45'
                ),
                array()
            ),
            // Product initial data provided but the texts data is not in language configured in export config,
            // texts should be null
            array(
                '1',
                array(
                    'id' => '1',
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => array(
                        array(
                            'lang' => 'lt',
                            'name1' => 'Test',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        )
                    )
                ),
                array(
                    'name' => '',
                    'summary' => '',
                    'description' => '',
                    'keywords' => ''
                )
            ),
            // Product initial data provided, item should have an id and appropriate texts fields (description, meta description, etc.)
            array(
                '1',
                array(
                    'id' => '1',
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => array(
                        array(
                            'lang' => 'en',
                            'name1' => 'Test',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        )
                    )
                ),
                array(
                    'name' => 'Test',
                    'summary' => 'Short Description',
                    'description' => 'Description',
                    'keywords' => 'Keyword'
                )
            ),
        );
    }

    /**
     * Test initial data parsing
     *
     * @dataProvider processInitialDataProvider
     */
    public function testProcessInitialData($itemId, $data, $texts)
    {
        $productMock = $this->getProductMock();
        $productMock->processInitialData($data);

        $this->assertSame($itemId, $productMock->getItemId());
        $this->assertSame($itemId, $productMock->getField('id'));
        foreach ($texts as $field => $value) {
            $this->assertSame($value, $productMock->getField($field));
        }
    }

    public function getManufacturerProvider()
    {
        return array(
            // Check if manufacturer is setted properly
            array(
                1,
                'Test',
                array(Product::MANUFACTURER_ATTRIBUTE_FIELD => array('Test')),
            ),
        );
    }

    /**
     * @dataProvider getManufacturerProvider
     */
    public function testProcessManufacturer($manufacturerId, $manufacturerName, $expectedResult)
    {
        $manufacturersMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Manufacturers')
            ->setMethods(array('getManufacturerName'))
            ->getMock();

        $manufacturersMock->expects($this->any())->method('getManufacturerName')->willReturn($manufacturerName);

        $registry = $this->getRegistryMock();
        $registry->set('manufacturers', $manufacturersMock);

        $productMock = $this->getProductMock(array(), array($registry));
        $productMock->processManufacturer($manufacturerId);
        $this->assertSame($productMock->getField('attributes'), $expectedResult);
    }

    public function processVariationsProvider()
    {
        return array(
            // No variation data provided, item fields should be empty
            array(
                array(),
                '',
                '',
                array()
            ),
            // Variation attributes, units and identifiers (barcodes not included) data provided but the prices is missing
            array(
                array(
                    'entries' => array(
                        array(
                            'position' => '1',
                            'number' => 'Test Number',
                            'model' => 'Test Model',
                            'id' => 'Test Id',
                            'variationSalesPrices' => array(),
                            'vatId' => 2,
                            'variationAttributeValues' => array(
                                array(
                                    'attributeId' => '1',
                                    'valueId' => '2'
                                ),
                            ),
                            'variationBarcodes' => array(),
                            'unit' => array(
                                "unitId"=> 1,
                                "content" => 2
                            )
                        )
                    )
                ),
                array('Test' => array('Test')),
                array('Test Number', 'Test Model', 'Test Id'),
                array('price' => 0.00, 'maxprice' => 0.00, 'instead' => 0.00, 'base_unit' => 'C62', 'taxrate' => '19.00')
            ),
            // Variation prices provided with multiple prices set so the lowest should be used for 'price' field
            // Variation has duplicate identifier id => 'Test Id' so it should be ignored when adding to 'ordernumber' field
            array(
                array(
                    'entries' => array(
                        array(
                            'position' => '1',
                            'number' => 'Test Number',
                            'model' => 'Test Model',
                            'id' => 'Test Id',
                            'vatId' => 2,
                            'variationSalesPrices' => array(
                                array(
                                    'price' => 15,
                                    'salesPriceId' => 'default'
                                ),
                                array(
                                    'price' => 14,
                                    'salesPriceId' => 'default'
                                ),
                                array(
                                    'price' => 18,
                                    'salesPriceId' => 'default'
                                ),
                                array(
                                    'price' => 17,
                                    'salesPriceId' => SalesPrices::RRP_TYPE
                                )
                            ),
                            'variationAttributeValues' => array(),
                            'variationBarcodes' => array()
                        ),
                        array(
                            'position' => '2',
                            'number' => 'Test Number 2',
                            'model' => 'Test Model 2',
                            'id' => 'Test Id',
                            'variationSalesPrices' => array(
                                array(
                                    'price' => 15,
                                    'salesPriceId' => '1'
                                ),
                                array(
                                    'price' => 18,
                                    'salesPriceId' => '1'
                                ),
                                array(
                                    'price' => 17,
                                    'salesPriceId' => '2'
                                )
                            ),
                            'variationAttributeValues' => array(),
                            'variationBarcodes' => array(
                                array(
                                    'code' => 'Barcode'
                                )
                            )
                        )
                    )
                ),
                '',
                array('Test Number', 'Test Model', 'Test Id', 'Test Number 2', 'Test Model 2', 'Barcode'),
                array('price' => 14, 'maxprice' => 18, 'instead' => 17)

            )
        );
    }

    /**
     * @dataProvider processVariationsProvider
     */
    public function testProcessVariations($data, $expectedAttributes, $expectedIdentifiers, $expectedFields)
    {
        $attributesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Attributes')
            ->setMethods(array('attributeValueExists', 'getAttributeName', 'getAttributeValueName'))
            ->getMock();

        $attributesMock->expects($this->any())->method('attributeValueExists')->willReturn(true);
        $attributesMock->expects($this->any())->method('getAttributeName')->willReturn('Test');
        $attributesMock->expects($this->any())->method('getAttributeValueName')->willReturn('Test');

        $salesPricesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\SalesPrices')
            ->setMethods(array('getRRP'))
            ->getMock();

        $salesPricesMock->expects($this->any())->method('getRRP')->willReturn(array('2'));

        $vatMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Vat')
            ->setMethods(array('getVatRateByVatId'))
            ->getMock();

        $vatMock->expects($this->any())->method('getVatRateByVatId')->willReturn('19.00');

        $registry = $this->getRegistryMock();
        $registry->set('Attributes', $attributesMock);
        $registry->set('SalesPrices', $salesPricesMock);
        $registry->set('Vat', $vatMock);

        $productMock = $this->getProductMock(array('getItemId', 'getConfigLanguageCode'), array($registry));

        $productMock->processVariations($data);

        $this->assertSame($expectedAttributes, $productMock->getField('attributes'));
        $this->assertSame($expectedIdentifiers, $productMock->getField('ordernumber'));
        foreach ($expectedFields as $field => $expectedValue) {
            $this->assertSame($expectedValue, $productMock->getField($field));
        }
    }

    public function proccessVariationCategoriesProvider()
    {
        return array(
            // No data for categories provider, results should be empty
            array(
                array(),
                false,
                ''
            ),
            // Variations belongs to two categories, categories names is saved in attributes field
            array(
                array(array('categoryId' => 1), array('categoryId' => 2)),
                array(
                    array('name' => 'Test', 'url' => 'test'),
                    array('name' => 'Category', 'url' => 'category')
                ),
                array(
                    Product::CATEGORY_ATTRIBUTE_FIELD => array('Test', 'Category'),
                    Product::CATEGORY_URLS_ATTRIBUTE_FIELD => array('test', 'category')
                )
            )
        );
    }

    /**
     * Test setting the categories attribute field
     *
     * @dataProvider proccessVariationCategoriesProvider
     */
    public function testProccessVariationCategories($data, $categories , $expectedResult)
    {
        $categoriesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Categories')
            ->setMethods(array('getCategoryName', 'getCategoryFullPath'))
            ->getMock();

        if ($categories) {
            // Mock return method for testing product with multiple categories
            $i = 0;
            foreach ($categories as $category) {
                $categoriesMock->expects($this->at($i))->method('getCategoryName')->will($this->returnValue($category['name']));
                $i++;
                $categoriesMock->expects($this->at($i))->method('getCategoryFullPath')->will($this->returnValue($category['url']));
                $i++;
            }
        }

        $registry = $this->getRegistryMock();
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array($registry));
        $productMock->proccessVariationCategories($data);
        $this->assertSame($productMock->getField('attributes'), $expectedResult);
    }

    /**
     *  Method $data property example:
     *  array (
     *      0 => array (
     *          'id' => 3,
     *          'itemId' => 102,
     *          'propertyId' => 2,
     *          'variationId' => 1076,
     *          ...
     *          'property' => array (
     *              'id' => 2,
     *              'position' => 2,
     *              'unit' => 'LTR',
     *              'backendName' => 'Test Property 2',
     *              'valueType' => 'text',
     *              'isSearchable' => true,
     *              ...
     *          ),
     *          'names' => array (
     *              0 => array (
     *                  'propertyValueId' => 3,
     *                  'lang' => 'en',
     *                  'value' => 'Some Text',
     *              ),
     *          ),
     *          'propertySelection' => array (
     *              0 => array (
     *                  'id' => 1,
     *                  'propertyId' => 3,
     *                  'lang' => 'en',
     *                  'name' => 'Select 1',
     *                  'description' => 'Select 1',
     *              ),
     *          ),
     *      ),
     *  )
     */
    public function processVariationPropertiesProvider()
    {
        return array(
            // No data provided, results should be empty
            array(
                array(),
                ''
            ),
            // Variation has 'text' and 'selection' type properties but the language of those properties is not the same
            // as in export config, results should be empty
            array(
                array(
                    array(
                        'property' => array(
                            'backendName' => 'Test Property',
                            'valueType' => 'text'
                        ),
                        'names' => array(
                            array('value' => 'Test Value', 'lang' => 'lt')
                        )
                    ),
                    array(
                        'property' => array(
                            'backendName' => 'Test Property Select',
                            'valueType' => 'selection'
                        ),
                        'names' => array(),
                        'propertySelection' => array(
                            array('name' => 'Select Value', 'lang' => 'lt')
                        )
                    ),
                ),
                ''
            ),
            // Variation has 'text' and 'float' type properties
            array(
                array(
                    array(
                        'property' => array(
                            'backendName' => 'Test Property',
                            'valueType' => 'text'
                        ),
                        'names' => array(
                            array('value' => 'Test Value', 'lang' => 'en')
                        )
                    ),
                    array(
                        'property' => array(
                            'backendName' => 'Test Float',
                            'valueType' => 'float'
                        ),
                        'valueFloat' => 3.25
                    )
                ),
                array('Test Property' => array('Test Value'), 'Test Float' => array(3.25))
            ),
            // Variation has 'selection' and 'int' type properties
            array(
                array(
                    array(
                        'property' => array(
                            'backendName' => 'Test Property Select',
                            'valueType' => 'selection'
                        ),
                        'names' => array(),
                        'propertySelection' => array(
                            array('name' => 'Select Value', 'lang' => 'en')
                        )
                    ),
                    array(
                        'property' => array(
                            'backendName' => 'Test Int',
                            'valueType' => 'int'
                        ),
                        'valueInt' => 3
                    ),
                    array(
                        'property' => array(
                            'backendName' => 'Test Default',
                            'valueType' => 'Test'
                        )
                    )
                ),
                array('Test Property Select' => array('Select Value'), 'Test Int' => array(3))
            )
        );
    }

    /**
     * @dataProvider processVariationPropertiesProvider
     */
    public function testProcessVariationsProperties($data, $expectedResult)
    {
        $productMock = $this->getProductMock();
        $productMock->processVariationsProperties($data);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    /**
     *  Result for this method could vary depending if product has one or multiple images
     *  If product has multiple images then $data will hold an array with images data arrays
     *  If product has one image the $data will hold that image data array
     *
     *  array (
     *      0 => array (
     *          'id' => 19,
     *          'itemId' => 102,
     *          'type' => 'internal',
     *          'fileType' => 'jpg',
     *          'url' => 'https://test.com/item/images/102/3000x3000/102-gruen.jpg',
     *          'urlMiddle' => 'https://test.com/item/images/102/370x450/102-gruen.jpg',
     *          'urlPreview' => 'https://test.com/item/images/102/150x150/102-gruen.jpg',
     *          'urlSecondPreview' => 'https://test.com/item/images/102/0x0/102-gruen.jpg',
     *          ...
     *      ),
     *   )
     */
    public function processImagesProvider()
    {
        return array(
            // No data provided, 'image' field should be empty
            array(
                false,
                ''
            ),
            // Image has only one image, 'image' field
            array(
                // Image
                array(
                    'itemId' => '1',
                    'urlMiddle' => 'path'
                ),
                'path'
            ),
            // Image has multiple images so $data has array for images
            array(
                array(
                    // First image
                    array('urlMiddle' => 'path'),
                    // Second image
                    array('urlMiddle' => 'path')
                ),
                'path'
            ),
        );
    }

    /**
     * @dataProvider processImagesProvider
     */
    public function testProcessImages($data, $expectedResult)
    {
        $productMock = $this->getProductMock();

        $productMock->processImages($data);
        $this->assertSame($expectedResult, $productMock->getField('image'));
    }

    /**
     * @param array $methods
     * @param array|bool $constructorArgs
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getProductMock($methods = array(), $constructorArgs = false)
    {
        // Add getters of config values to mock
        if (!in_array('getConfigLanguageCode', $methods)) {
            $methods[] = 'getConfigLanguageCode';
        }

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product');

        if (is_array($constructorArgs)) {
            $productMock->setConstructorArgs($constructorArgs);
        } else {
            $productMock->disableOriginalConstructor();
        }

        $productMock = $productMock->setMethods($methods)->getMock();

        $productMock->expects($this->any())->method('getConfigLanguageCode')->willReturn('EN');

        return $productMock;
    }

    /**
     * Helper function to get registry mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRegistryMock()
    {
        $mock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        return $mock;
    }
}