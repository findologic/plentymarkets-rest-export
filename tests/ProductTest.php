<?php

namespace Findologic\PlentymarketsTest;

use Findologic\Plentymarkets\Parser\Attributes;
use Findologic\Plentymarkets\Parser\Categories;
use Findologic\Plentymarkets\Parser\Properties;
use Findologic\Plentymarkets\Parser\Units;
use Findologic\Plentymarkets\Parser\Vat;
use Findologic\Plentymarkets\Product;
use Findologic\Plentymarkets\Registry;
use Findologic\Plentymarkets\Tests\MockResponseHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    use MockResponseHelper;

    /**
     * @var \Findologic\Plentymarkets\Product
     */
    protected $product;

    protected $defaultEmptyValue = '';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var Registry|MockObject $registry */
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

    public function getAttributeFieldProvider()
    {
        return array(
            array(
                'Test Attribute',
                'Test Attribute 2',
                array('Test Value'),
                ''
            ),
            array(
                'Test Attribute',
                'Test Attribute',
                array('Test Value'),
                array('Test Value')
            )
        );
    }

    /**
     * @dataProvider getAttributeFieldProvider
     */
    public function testGetAttributeField($setAttribute, $getAttribute, $values, $expectedResult)
    {
        foreach ($values as $value) {
            $this->product->setAttributeField($setAttribute, $value);
            $this->assertSame($expectedResult, $this->product->getAttributeField($getAttribute));
        }
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
        return [
            // No data given, item object should not have any information
            ['', '1', [], [], ''],
            // Product initial data provided but the texts array is empty
            [
                1,
                '1',
                [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45'
                ],
                [],
                ''
            ],
            // Product initial data provided but the texts data is not in language configured in export config,
            // texts should be null
            [
                1,
                '1',
                [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => [
                        [
                            'lang' => 'lt',
                            'name1' => 'Test',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        ]
                    ]
                ],
                [
                    'name' => '',
                    'summary' => '',
                    'description' => '',
                    'keywords' => ''
                ],
                ''
            ],
            // Product initial data provided, item should have an id and appropriate texts fields (description, meta description, etc.)
            [
                1,
                2,
                [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => [
                        [
                            'lang' => 'en',
                            'name1' => 'Test',
                            'name2' => 'Test 2',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        ]
                    ],
                    'free1' => null,
                    'free2' => '',
                    'free3' => '0',
                    'free4' => 100,
                    'free5' => 'value'
                ],
                [
                    'name' => 'Test 2',
                    'summary' => 'Short Description',
                    'description' => 'Description',
                    'keywords' => 'Keyword'
                ],
                ['free3' => ['0'], 'free4' => [100], 'free5' => ['value']]
            ]
        ];
    }

    /**
     * Test initial data parsing
     *
     * @dataProvider processInitialDataProvider
     */
    public function testProcessInitialData($itemId, $productNameId, $data, $texts, $attributes)
    {
        $productMock = $this->getProductMock();
        $productMock->setProductNameFieldId($productNameId);
        $productMock->processInitialData($data);

        $this->assertSame($attributes, $productMock->getField('attributes'));
        $this->assertSame($itemId, $productMock->getItemId());
        $this->assertSame($itemId, $productMock->getField('id'));
        foreach ($texts as $field => $value) {
            $this->assertSame($value, $productMock->getField($field));
        }
    }

    public function mergeKeywordsAndTagsProvider()
    {
        return [
            'Texts keywords is set;Tag is of type variation and is set' => [
                'initialData' => [
                    'id' => 1,
                    'createdAt' => '2001-12-12 14:12:45',
                    'texts' => [
                        [
                            'lang' => 'en',
                            'name1' => 'Test',
                            'name2' => 'Test 2',
                            'shortDescription' => 'Short Description',
                            'description' => 'Description',
                            'keywords' => 'Keyword'
                        ]
                    ]
                ],
                'variationData' => $this->getMockResponse('/pim/variations/variation_with_tags.json')['entries'][0],
                'expectedResult' => 'Keyword,I am a Tag'
            ]
        ];
    }

    /**
     * @dataProvider mergeKeywordsAndTagsProvider
     *
     * @param array $initialData
     * @param array $variationData
     * @param string $expectedResult
     * @throws \ReflectionException
     */
    public function testProcessVariationKeywordsAndTagsFromRequestAreMergedToKeywordsField(array $initialData, array $variationData, $expectedResult)
    {
        $attributesMock = $this->getMockBuilder(Attributes::class)
            ->disableOriginalConstructor()
            ->setMethods(['attributeValueExists', 'getAttributeName', 'getAttributeValueName'])
            ->getMock();

        $attributesMock->expects($this->any())->method('attributeValueExists')->willReturn(true);
        $attributesMock->expects($this->any())->method('getAttributeName')->willReturn('Test');
        $attributesMock->expects($this->any())->method('getAttributeValueName')->willReturn('Test');

        $vatMock = $this->getMockBuilder(Vat::class)
            ->disableOriginalConstructor()
            ->setMethods(['getVatRateByVatId'])
            ->getMock();

        $vatMock->expects($this->any())->method('getVatRateByVatId')->willReturn('19.00');

        $unitsMock = $this->getMockBuilder(Units::class)
            ->disableOriginalConstructor()
            ->setMethods(['getUnitValue'])
            ->getMock();

        $unitsMock->expects($this->any())->method('getUnitValue')->willReturn('C62');
        $categoriesMock = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->getMock();
        $categoriesMock->expects($this->any())->method('getCategoryFullNamePath')->willReturn('');

        $registry = $this->getRegistryMock();
        $registry->set('Attributes', $attributesMock);
        $registry->set('Vat', $vatMock);
        $registry->set('Units', $unitsMock);
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(['getItemId', 'getLanguageCode', 'getStorePlentyId', 'processVariationGroups'], [$registry]);
        $productMock->expects($this->any())->method('processVariationGroups')->willReturn($productMock);
        $productMock->expects($this->any())->method('getStorePlentyId')->willReturn(1);
        $productMock->setExportSalesFrequency(true);
        $productMock->setPriceId(1);
        $productMock->setRrpPriceId(4);
        $productMock->processInitialData($initialData);

        $productMock->processVariation($variationData);

        $this->assertSame($productMock->getField('keywords'), $expectedResult);
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
            ->disableOriginalConstructor()
            ->setMethods(array('getManufacturerName'))
            ->getMock();

        $manufacturersMock->expects($this->any())->method('getManufacturerName')->willReturn($manufacturerName);

        $registry = $this->getRegistryMock();
        $registry->set('manufacturers', $manufacturersMock);

        $productMock = $this->getProductMock(array(), array($registry));
        $productMock->processManufacturer($manufacturerId);
        $this->assertSame($productMock->getField('attributes'), $expectedResult);
    }

    public function processVariationProvider()
    {
        return [
            'No variation data provided' => [
                'data' => [],
                'expectedAttributes' => '',
                'expectedIdentifiers' => '',
                'expectedFields' => []
            ],
            // Variation attributes, units and identifiers (barcodes not included) data provided but the prices is
            // missing, second variation will be ignored as it is not active
            'Prices is missing' => [
                'data' => $this->getMockResponse('/pim/variations/variations_without_price.json')['entries'],
                'expectedAttributes' => [
                    'Test' => ['Test']
                ],
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => [
                    'price' => 0.00,
                    'maxprice' => '',
                    'instead' => 0.00,
                    'base_unit' => 'C62',
                    'taxrate' => '19.00',
                    'sales_frequency' => 0,
                    'variation_id' => 'Test Id'
                ]
            ],
            // Variation prices includes price with configured sales price id and configured rrp price id
            // Variation has duplicate identifier id => 'Test Id' so it should be ignored when adding
            // to 'ordernumber' field.
            'Variation has duplicate identifier id' => [
                'data' => $this->getMockResponse('/pim/variations/variations_with_same_ids.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Test Id',
                    'Test Number 2',
                    'Test Model 2',
                    'Barcode'
                ],
                'expectedFields' => [
                    'price' => 14,
                    'maxprice' => '',
                    'instead' => 17,
                    'variation_id' => 'Test Id',
                    'sort' => 2
                ]
            ],
            'Variation is hidden in category list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_hidden_in_category_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => []
            ],
            'Variation is hidden in item list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_hidden_in_item_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => []
            ],
            'Variation is visible in category list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_visible_in_category_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Test Id'],
                'expectedFields' => []
            ],
            'Variation is visible in item list' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variation_visible_in_item_list.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Test Id',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id 2'
                ],
                'expectedFields' => []
            ],
            'Main variation is inactive, use fallback variation ID' => [
                'data' => $this->getMockResponse('/pim/variations/main_variation_is_inactive.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => ['Test Number', 'Test Model', 'Not the main variation'],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Not the main variation',
                    'sort' => 1
                ]
            ],
            'Use the main variation ID for field, although already set' => [
                'data' => $this->getMockResponse('/pim/variations/last_variation_is_main_variation.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2
                ]
            ],
            'Tag list is empty; field for keywords is empty' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variations_without_tags_and_keywords.json')['entries'],
                'expectedAttributes' => '',
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => ''
                ]
            ],
            'Variations with tags; "keywords" is set with tag value and "cat_id" is set with tag id' => [
                'data' => $this->getMockResponse('/pim/variations/variations_with_tags.json')['entries'],
                'expectedAttributes' => [
                    'cat_id' => [1]
                ],
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => 'I am a Tag'
                ]
            ],
            'Tag list contains tags for irrelevant language; field for keywords is set with main tag name' => [
                'data' =>
                    $this->getMockResponse('/pim/variations/variations_with_tags_of_another_lang.json')['entries'],
                'expectedAttributes' => [
                    'cat_id' => [1]
                ],
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => 'Main Tag'
                ]
            ],
            'Multiple tags are set' => [
                'data' => $this->getMockResponse('/pim/variations/variation_with_multiple_tags.json')['entries'],
                'expectedAttributes' => [
                    'cat_id' => [1, 2, 5]
                ],
                'expectedIdentifiers' => [
                    'Test Number',
                    'Test Model',
                    'Not the main variation',
                    'Test Number 2',
                    'Test Model 2',
                    'Test Id'
                ],
                'expectedFields' => [
                    'price' => 0.0,
                    'maxprice' => '',
                    'instead' => 0.0,
                    'variation_id' => 'Test Id',
                    'sort' => 2,
                    'keywords' => 'Main Tag,Another Main Tag,Third Tag'
                ]
            ],
        ];
    }

    /**
     * @dataProvider processVariationProvider
     *
     * @param array $data
     * @param array|string $expectedAttributes
     * @param array|string $expectedIdentifiers
     * @param array $expectedFields
     */
    public function testProcessVariation(array $data, $expectedAttributes, $expectedIdentifiers, array $expectedFields)
    {
        $attributesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Attributes')
            ->disableOriginalConstructor()
            ->setMethods(array('attributeValueExists', 'getAttributeName', 'getAttributeValueName'))
            ->getMock();

        $attributesMock->expects($this->any())->method('attributeValueExists')->willReturn(true);
        $attributesMock->expects($this->any())->method('getAttributeName')->willReturn('Test');
        $attributesMock->expects($this->any())->method('getAttributeValueName')->willReturn('Test');

        $vatMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Vat')
            ->disableOriginalConstructor()
            ->setMethods(array('getVatRateByVatId'))
            ->getMock();

        $vatMock->expects($this->any())->method('getVatRateByVatId')->willReturn('19.00');

        $unitsMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Units')
            ->disableOriginalConstructor()
            ->setMethods(array('getUnitValue'))
            ->getMock();

        $unitsMock->expects($this->any())->method('getUnitValue')->willReturn('C62');

        $categoriesMock = $this->getMockBuilder(Categories::class)
            ->disableOriginalConstructor()
            ->getMock();
        $categoriesMock->expects($this->any())->method('getCategoryFullNamePath')->willReturn('');

        $registry = $this->getRegistryMock();
        $registry->set('Attributes', $attributesMock);
        $registry->set('Vat', $vatMock);
        $registry->set('Units', $unitsMock);
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(array('getItemId', 'getLanguageCode', 'getStorePlentyId', 'processVariationGroups'), array($registry));
        $productMock->expects($this->any())->method('processVariationGroups')->willReturn($productMock);
        $productMock->expects($this->any())->method('getStorePlentyId')->willReturn(1);
        $productMock->setExportSalesFrequency(true);
        $productMock->setPriceId(1);
        $productMock->setRrpPriceId(4);

        foreach ($data as $variation) {
            $productMock->processVariation($variation);
        }

        $this->assertSame($expectedAttributes, $productMock->getField('attributes'));
        $this->assertSame($expectedIdentifiers, $productMock->getField('ordernumber'));
        foreach ($expectedFields as $field => $expectedValue) {
            $this->assertSame($expectedValue, $productMock->getField($field), sprintf('Expected field "%s" is not identical.', $field));
        }
    }

    public function processVariationWhenVariationIsNotActiveProvider()
    {
        return [
            'Variation is not active and config is set to not include inactive variations' => [
                'data' => [
                    'base' => [
                        'isActive' => false,
                        'availability' => 1,
                    ]
                ],
                'availabilityId' => []
            ],
            'Variation is active but availability id does not match with the config' => [
                'data' => [
                    'base' => [
                        'isActive' => true,
                        'availability' => 2,
                    ]
                ],
                'availabilityId' => 2
            ],
            'Variation is no longer available' => [
                'data' => [
                    'base' => [
                        'isActive' => true,
                        'availability' => 1,
                        'availableUntil' => '2018-01-01T00:00:00+01:00'
                    ]
                ],
                'availabilityId' => 1
            ]
        ];
    }

    /**
     * Variation should be skipped if product is not active or availability ids is not in config array
     *
     * @dataProvider processVariationWhenVariationIsNotActiveProvider
     */
    public function testProcessVariationWhenVariationIsNotActive($data, $availabilityId)
    {
        $productMock = $this->getProductMock(
            [
                'processVariationIdentifiers',
                'processVariationCategories'
            ]
        );

        $productMock->setAvailabilityIds($availabilityId);

        // Further processing methods should not be called if variation do not pass visibility filtering so it could be
        // used to indicate whether it passes or not
        $productMock->expects($this->never())->method('processVariationIdentifiers');
        $productMock->expects($this->never())->method('processVariationCategories');

        $productMock->processVariation($data);
    }

    public function processVariationCategoriesProvider()
    {
        return array(
            // No data for categories provider, results should be empty
            array(
                array(),
                false,
                ''
            ),
            // Variations belongs to category, but category values is empty so attributes should also be empty
            array(
                array(array('categoryId' => 1)),
                array(
                    array('urlKey' => '', 'fullNamePath' => ''),
                ),
                ''
            ),
            // Variations belongs to two categories, categories names is saved in attributes field
            array(
                array(array('categoryId' => 1), array('categoryId' => 2)),
                array(
                    array('urlKey' => 'test', 'fullNamePath' => 'Test'),
                    array('urlKey' => 'category', 'fullNamePath' => 'Category')
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
     * @dataProvider processVariationCategoriesProvider
     */
    public function testProcessVariationCategories($data, $categories , $expectedResult)
    {
        $categoriesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Categories')
            ->disableOriginalConstructor()
            ->setMethods(array('getCategoryFullNamePath', 'getCategoryFullPath'))
            ->getMock();

        if ($categories) {
            // Mock return method for testing product with multiple categories
            $i = 0;
            foreach ($categories as $category) {
                $categoriesMock->expects($this->at($i))->method('getCategoryFullNamePath')->will($this->returnValue($category['fullNamePath']));
                $i++;
                $categoriesMock->expects($this->at($i))->method('getCategoryFullPath')->will($this->returnValue($category['urlKey']));
                $i++;
            }
        }

        $registry = $this->getRegistryMock();
        $registry->set('categories', $categoriesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array($registry));
        $productMock->processVariationCategories($data);
        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function processVariationGroupsProvider()
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
                array(array('plentyId' => 31776), array('plentyId' => 31777)),
                array(0, 1),
                '0_,1_'
            )
        );
    }

    /**
     * Test setting the categories attribute field
     *
     * @dataProvider processVariationGroupsProvider
     */
    public function testProcessVariationGroups($data, $stores , $expectedResult)
    {
        $storesMock = $this->getMockBuilder('Findologic\Plentymarkets\Parser\Stores')
            ->disableOriginalConstructor()
            ->setMethods(array('getStoreInternalIdByIdentifier'))
            ->getMock();

        if ($stores) {
            // Mock return method for testing product with multiple stores
            $i = 0;
            foreach ($stores as $store) {
                $storesMock->expects($this->at($i))->method('getStoreInternalIdByIdentifier')->will($this->returnValue($store));
                $i++;
            }
        }

        $registry = $this->getRegistryMock();
        $registry->set('stores', $storesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array($registry));
        $productMock->processVariationGroups($data);
        $this->assertSame($expectedResult, $productMock->getField('groups'));
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
     *          'valueTexts' => array (
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
        return [
            'No data provided, results should be empty' => [
                'data' => [],
                'expectedResult' => ''
            ],
            'Variation property is not searchable, results should be empty' => [
                'data' => [
                    [
                        'propertyId' => 1,
                        'property' => [
                            'id' => '1',
                            'isSearchable' => false,
                        ],
                    ],
                    [
                        'propertyId' => 2,
                        'property' => [
                            'id' => '2',
                            'backendName' => 'Test Property',
                            'valueType' => 'empty',
                        ],
                    ]
                ],
                ''
            ],
            'Variation has "text" type property but value is "null", results should be empty' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property',
                            'valueType' => 'text',
                            'propertyGroupId' => null
                        ],
                        'valueTexts' => [
                            ['value' => 'null', 'lang' => 'lt']
                        ]
                    ]
                ],
                ''
            ],
            'Variation with "text" and "selection" properties - lang is not the same as in export config, results should be empty' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property',
                            'valueType' => 'text',
                            'propertyGroupId' => null
                        ],
                        'valueTexts' => [
                            ['value' => 'Test Value', 'lang' => 'lt']
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property Select',
                            'valueType' => 'selection',
                            'propertyGroupId' => null
                        ],
                        'valueTexts' => [],
                        'propertySelection' => [
                            ['name' => 'Select Value', 'lang' => 'lt']
                        ]
                    ],
                ],
                ''
            ],
            'Variation with "text" and "selection" properties - lang is not the same as in export config, should use property names' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property',
                            'valueType' => 'text',
                            'propertyGroupId' => 2
                        ],
                        'valueTexts' => [
                            ['value' => 'Test Value', 'lang' => 'lt']
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property Select',
                            'valueType' => 'selection',
                            'propertyGroupId' => 2
                        ],
                        'valueTexts' => [],
                        'propertySelection' => [
                            ['name' => 'Select Value', 'lang' => 'lt']
                        ]
                    ],
                ],
                ['Test' => ['Test Property', 'Test Property Select']]
            ],
            'Variation has "text" and "float" type properties' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property',
                            'valueType' => 'text'
                        ],
                        'names' => [
                            ['value' => 'Test Value', 'lang' => 'en']
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '999',
                            'backendName' => 'Test Property 2',
                            'valueType' => 'text'
                        ],
                        'names' => [
                            ['value' => 'Test Value 2', 'lang' => 'en']
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Float',
                            'valueType' => 'float'
                        ],
                        'valueFloat' => 3.25
                    ]
                ],
                ['Test Property' => ['Test Value'], 'Test Property Name' => ['Test Value 2'], 'Test Float' => [3.25]]
            ],
            'Variation has "selection" and "int" type properties' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Property Select',
                            'valueType' => 'selection',
                            'propertyGroupId' => null
                        ],
                        'valueTexts' => [],
                        'propertySelection' => [
                            ['name' => 'Select Value', 'lang' => 'en']
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Int',
                            'valueType' => 'int',
                            'propertyGroupId' => null
                        ],
                        'valueInt' => 3
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Default',
                            'valueType' => 'Test',
                            'propertyGroupId' => null
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Value',
                            'valueType' => 'empty',
                            'propertyGroupId' => 2
                        ]
                    ]
                ],
                ['Test Property Select' => ['Select Value'], 'Test Int' => [3], 'Test' => ['Test Value']]
            ],
            'Variation property should use name for provided language instead backend name' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test',
                            'valueType' => 'selection',
                        ],
                        'valueTexts' => [],
                        'propertySelection' => [
                            ['name' => 'Test value en', 'lang' => 'EN'],
                        ],
                        'names' => [
                            ['lang' => 'EN', 'value' => ''],
                        ]
                    ]
                ],
                ['Test' => ['Test value en']]
            ],
            'Multiple properties should use name for provided language instead backend name' => [
                [
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Selection',
                            'valueType' => 'selection',
                        ],
                        'propertySelection' => [
                            ['name' => 'Test value de', 'lang' => 'DE'],
                            ['name' => 'Test value en', 'lang' => 'EN'],
                        ],
                        'names' => [
                            ['lang' => 'DE', 'value' => 'Test DE'],
                            ['lang' => 'EN', 'value' => 'Test EN'],
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test Value',
                            'valueType' => 'text',
                        ],
                        'names' => [
                            ['lang' => 'DE', 'value' => 'Test DE'],
                            ['lang' => 'EN', 'value' => 'Test'],
                        ]
                    ],
                    [
                        'property' => [
                            'id' => '1',
                            'backendName' => 'Test 2',
                            'valueType' => 'empty',
                            'propertyGroupId' => 1
                        ],
                        'names' => [
                            ['lang' => 'EN', 'value' => 'Test EN']
                        ]
                    ]
                ],
                ['Test Selection' => ['Test value en'], 'Test Value' => ['Test'], 'Test' => ['Test 2']]
            ]
        ];
    }

    /**
     * @dataProvider processVariationPropertiesProvider
     */
    public function testProcessVariationsProperties($data, $expectedResult)
    {
        $propertyGroupsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\PropertyGroups')
            ->disableOriginalConstructor()
            ->setMethods(array('getPropertyGroupName'))
            ->getMock();

        $propertyGroupsMock->expects($this->any())->method('getPropertyGroupName')->will($this->returnCallback(function ($gid) {
            return is_null($gid) ? '' : 'Test';
        }));

        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\ItemProperties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->setResults(array('999' => array('names' => array('EN' => array('name' => 'Test Property Name')))));

        $registryMock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $registryMock->set('PropertyGroups', $propertyGroupsMock);
        $registryMock->set('ItemProperties', $propertiesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array('registry' => $registryMock));
        $productMock->processCharacteristics($data);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function providerProcessVariationSpecificProperties()
    {
        return array(
            'No data provided' => array(
                array(),
                array(),
                ''
            ),
            'No item properties provided' => array(
                array(),
                array(
                    array(
                        'property' => array(
                            'id' => '1',
                        ),
                        'relationTypeIdentifier' => 'Test'
                    )
                ),
                ''
            ),
            'Empty type property' => array(
                array(
                    '1' => array(
                        'names' => array(
                            'EN' => 'Test Property EN'
                        ),
                        'propertyGroups' => array(
                            '1' => array('DE' => 'Test DE', 'EN' => 'Test EN')
                        )
                    )
                ),
                array(
                    array(
                        'property' => array(
                            'id' => '1',
                        ),
                        'propertyId' => '1',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => array(
                            'cast' => 'empty'
                        ),
                        'propertyGroups' => array(
                            '1' => array(
                                'DE' => 'Test DE',
                                'EN' => 'Test EN'
                            )
                        )
                    )
                ),
                array('Test EN' => array('Test Property EN')),
            ),
            'Text type property' => array(
                array(
                    '1' => array(
                        'names' => array(
                            'EN' => 'Test Property'
                        )
                    )
                ),
                array(
                    array(
                        'property' => array(
                            'id' => '1',
                        ),
                        'propertyId' => '1',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => array(
                            'cast' => 'shortText'
                        ),
                        'relationValues' => array(
                            array('lang' => 'DE', 'value' => 'Test DE'),
                            array('lang' => 'EN', 'value' => 'Test EN'),
                        )
                    )
                ),
                array('Test Property' => array('Test EN')),
            ),
            'Int type property' => array(
                array(
                    '1' => array(
                        'names' => array(
                            'EN' => 'Test Property'
                        )
                    )
                ),
                array(
                    array(
                        'property' => array(
                            'id' => '1',
                        ),
                        'propertyId' => '1',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => array(
                            'cast' => 'int'
                        ),
                        'relationValues' => array(
                            array('lang' => 0, 'value' => 12),
                        )
                    )
                ),
                array('Test Property' => array(12)),
            ),
            'Selection type property' => array(
                array(
                    '1' => array(
                        'names' => array(
                            'EN' => 'Test Property'
                        ),
                        'selections' => array(
                            '1' => array(
                                'EN' => 'Selecttion Value'
                            )
                        )
                    )
                ),
                array(
                    array(
                        'property' => array(
                            'id' => '1',
                        ),
                        'propertyId' => '1',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => array(
                            'cast' => 'selection'
                        ),
                        'relationValues' => array(
                            array('value' => 1)
                        )
                    )
                ),
                array('Test Property' => array('Selecttion Value')),
            ),
        );
    }

    /**
     * @dataProvider providerProcessVariationSpecificProperties
     */
    public function testProcessVariationSpecificProperties($previouslyParsedData, $data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->setResults($previouslyParsedData);

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Properties', $propertiesMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array('registry' => $registryMock));
        $productMock->processProperties($data);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function providerProcessVariationSpecificMultiselectProperties()
    {
        return [
            'Single selection' => [
                [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                [
                    10 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                        ]
                    ]
                ],
                [
                    [
                        'property' => [
                            'id' => '10',
                        ],
                        'propertyId' => '10',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => [
                            'cast' => 'multiSelection'
                        ],
                        'relationValues' => [
                            ['value' => 100]
                        ]
                    ]
                ],
                ['testMultiselectProperty' => ['enValue1']],
            ],
            'Double selection' => [
                [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                [
                    10 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2'
                            ]
                        ]
                    ]
                ],
                [
                    [
                        'property' => [
                            'id' => '10',
                        ],
                        'propertyId' => '10',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => [
                            'cast' => 'multiSelection'
                        ],
                        'relationValues' => [
                            ['value' => 100],
                            ['value' => 200]
                        ]
                    ]
                ],
                ['testMultiselectProperty' => ['enValue1', 'enValue2']],
            ],
            'No selection' => [
                [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                [
                    11 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2'
                            ]
                        ]
                    ]
                ],
                [
                    [
                        'property' => [
                            'id' => '10',
                        ],
                        'propertyId' => '10',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => [
                            'cast' => 'multiSelection'
                        ],
                        'relationValues' => []
                    ]
                ],
                '',
            ],
            'Two values - one selected' => [
                [
                    10 => [
                        'names' => [
                            'EN' => 'testMultiselectProperty'
                        ]
                    ]
                ],
                [
                    10 => [
                        'selections' => [
                            100 => [
                                'EN' => 'enValue1'
                            ],
                            200 => [
                                'EN' => 'enValue2'
                            ]
                        ]
                    ]
                ],
                [
                    [
                        'property' => [
                            'id' => '10',
                        ],
                        'propertyId' => '10',
                        'relationTypeIdentifier' => 'item',
                        'propertyRelation' => [
                            'cast' => 'multiSelection'
                        ],
                        'relationValues' => [
                            ['value' => 100],
                        ]
                    ]
                ],
                ['testMultiselectProperty' => ['enValue1']],
            ],
        ];
    }

    /**
     * @dataProvider providerProcessVariationSpecificMultiselectProperties
     */
    public function testProcessVariationSpecificMultiselectProperties($parsedPropiertiesData, $parsedSelectionsData, $data, $expectedResult)
    {
        $propertiesMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\Properties')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertiesMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertiesMock->setResults($parsedPropiertiesData);

        $propertySelectionsMock = $this->getMockBuilder('\Findologic\Plentymarkets\Parser\PropertySelections')
            ->disableOriginalConstructor()
            ->setMethods(array('getLanguageCode'))
            ->getMock();

        $propertySelectionsMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');
        $propertySelectionsMock->setResults($parsedSelectionsData);

        $registryMock = $this->getRegistryMock();
        $registryMock->set('Properties', $propertiesMock);
        $registryMock->set('PropertySelections', $propertySelectionsMock);

        $productMock = $this->getProductMock(array('handleEmptyData'), array('registry' => $registryMock));
        $productMock->processProperties($data);

        $this->assertSame($expectedResult, $productMock->getField('attributes'));
    }

    public function testDoesNotProcessVariationSpecificPropertiesWhenDataIsUnavailable()
    {
        $registryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();
        $registryMock->expects($this->once())->method('get')->willReturn(false);

        $productMock = $this->getProductMock(['handleEmptyData'], ['registry' => $registryMock]);
        $productMock->expects($this->once())->method('handleEmptyData')->with('Variation properties are missing');

        $productMock->processProperties(['test' => 'test']);
        $this->assertNotSame($productMock->getAttributeField('test'), 'test');
    }

    public function testDoesNotProcessVariationSpecificPropertiesWhenNoneExist()
    {
        $propertiesMock = $this->getMockBuilder(Properties::class)
            ->disableOriginalConstructor()
            ->setMethods(['getResults'])
            ->getMock();
        $propertiesMock->expects($this->once())->method('getResults')->willReturn([]);

        $registryMock = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();
        $registryMock->expects($this->once())->method('get')->willReturn($propertiesMock);

        $productMock = $this->getProductMock(['handleEmptyData'], ['registry' => $registryMock]);
        $productMock->expects($this->once())->method('handleEmptyData')->with('Variation properties are missing');

        $productMock->processProperties(['test' => 'test']);
        $this->assertNotSame($productMock->getAttributeField('test'), 'test');
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
        return [
            'No image data provided' => [
                false,
                ''
            ],
            'First image is available in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => [
                            [
                                'type' => Product::AVAILABILITY_STORE
                            ],
                            [
                                'type' => 'marketplace'
                            ]
                        ]
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => [
                            [
                                'type' => 'marketplace'
                            ]
                        ]
                    ]
                ],
                'firstPath'
            ],
            'Last image is available in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => []
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => []
                    ],
                    [
                        'itemId' => '3',
                        'urlMiddle' => 'thirdPath',
                        'availabilities' => [
                            [
                                'type' => Product::AVAILABILITY_STORE
                            ]
                        ]
                    ]
                ],
                'thirdPath'
            ],
            'No images are available in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => []
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => []
                    ],
                    [
                        'itemId' => '3',
                        'urlMiddle' => 'thirdPath',
                        'availabilities' => []
                    ]
                ],
                ''
            ],
            'Image is available in a marketplace, but not in shop' => [
                [
                    [
                        'itemId' => '1',
                        'urlMiddle' => 'firstPath',
                        'availabilities' => [
                            [
                                'imageId' => 11,
                                'type' => 'marketplace',
                                'value' => 1234
                            ]
                        ]
                    ],
                    [
                        'itemId' => '2',
                        'urlMiddle' => 'secondPath',
                        'availabilities' => [
                            [
                                'imageId' => 22,
                                'type' => 'marketplace',
                                'value' => 1234
                            ]
                        ]
                    ],
                    [
                        'itemId' => '3',
                        'urlMiddle' => 'thirdPath',
                        'availabilities' => []
                    ]
                ],
                ''
            ]
        ];
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
     * @return \Findologic\Plentymarkets\Product|MockObject
     */
    protected function getProductMock($methods = array(), $constructorArgs = false)
    {
        // Add getters of config values to mock
        if (!in_array('getLanguageCode', $methods)) {
            $methods[] = 'getLanguageCode';
        }

        $productMock = $this->getMockBuilder('\Findologic\Plentymarkets\Product');

        if (is_array($constructorArgs)) {
            $productMock->setConstructorArgs($constructorArgs);
        } else {
            $productMock->disableOriginalConstructor();
        }

        $productMock = $productMock->setMethods($methods)->getMock();

        $productMock->expects($this->any())->method('getLanguageCode')->willReturn('EN');

        return $productMock;
    }

    /**
     * Helper function to get registry mock
     *
     * @return Registry|MockObject
     */
    protected function getRegistryMock(): Registry
    {
        $mock = $this->getMockBuilder('\Findologic\Plentymarkets\Registry')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        return $mock;
    }
}