<?php
declare(strict_types=1);

namespace Search\Test\TestCase\Model\Filter;

use Cake\Datasource\RepositoryInterface;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Search\Manager;
use Search\Test\TestApp\Model\Filter\TestFilter;

class BaseTest extends TestCase
{
    /**
     * @var \Search\Manager
     */
    public $Manager;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.Search.Articles',
    ];

    /**
     * setup
     *
     * @return void
     */
    public function setUp(): void
    {
        $table = TableRegistry::get('Articles');
        $this->Manager = new Manager($table);
    }

    /**
     * @return array
     */
    public function emptyDataProvider()
    {
        return [
            [''],
            [null],
            [[]],
            [['']],
        ];
    }

    /**
     * @dataProvider emptyDataProvider
     * @param mixed $emptyValue Empty value.
     * @return void
     */
    public function testConstructEmptyFieldOption($emptyValue)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The `field` option is invalid. Expected a non-empty string or array.');

        new TestFilter(
            'name',
            $this->Manager,
            ['field' => $emptyValue]
        );
    }

    /**
     * @dataProvider emptyDataProvider
     * @param mixed $emptyValue Empty value.
     * @return void
     */
    public function testConstructEmptyNameArgument($emptyValue)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The `$name` argument is invalid. Expected a non-empty string.');

        new TestFilter(
            $emptyValue,
            $this->Manager,
            ['field' => 'field']
        );
    }

    /**
     * @return array
     */
    public function nonEmptyFieldDataProvider()
    {
        return [
            ['0'], ['value'], [['value']],
        ];
    }

    /**
     * @dataProvider nonEmptyFieldDataProvider
     * @param mixed $nonEmptyValue Non empty value.
     * @return void
     */
    public function testConstructNonEmptyFieldOption($nonEmptyValue)
    {
        $filter = new TestFilter(
            'name',
            $this->Manager,
            ['field' => $nonEmptyValue, 'aliasField' => false]
        );
        $this->assertEquals($filter->field(), $nonEmptyValue);
    }

    /**
     * @return array
     */
    public function nonEmptyNameDataProvider()
    {
        return [
            ['0'], ['value'],
        ];
    }

    /**
     * @dataProvider nonEmptyNameDataProvider
     * @param mixed $nonEmptyValue Non empty value.
     * @return void
     */
    public function testConstructNonEmptyNameArgument($nonEmptyValue)
    {
        $filter = new TestFilter(
            $nonEmptyValue,
            $this->Manager,
            ['field' => 'field']
        );
        $this->assertEquals($filter->name(), $nonEmptyValue);
    }

    /**
     * @return void
     */
    public function testSkip()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['alwaysRun' => true, 'filterEmpty' => true]
        );

        $filter->setArgs(['field' => '1']);
        $this->assertFalse($filter->skip());

        $filter->setArgs(['field' => '0']);
        $this->assertFalse($filter->skip());

        $filter->setArgs(['field' => '']);
        $this->assertTrue($filter->skip());

        $filter->setArgs(['field' => []]);
        $this->assertTrue($filter->skip());
    }

    /**
     * @return void
     */
    public function testValue()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['defaultValue' => 'default']
        );

        $filter->setArgs(['field' => 'value']);
        $this->assertEquals('value', $filter->value());

        $filter->setArgs(['other_field' => 'value']);
        $this->assertEquals('default', $filter->value());

        $filter->setArgs(['field' => ['value1', 'value2']]);
        $this->assertEquals('default', $filter->value());
    }

    /**
     * @return void
     */
    public function testValueMultiValue()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['defaultValue' => 'default']
        );

        $filter->setConfig('multiValue', true);
        $filter->setArgs(['field' => ['value1', 'value2']]);
        $this->assertEquals(['value1', 'value2'], $filter->value());
    }

    /**
     * @return void
     */
    public function testValueMultiValueSeparator()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['defaultValue' => 'default']
        );

        $filter->setConfig('multiValueSeparator', '|');

        $filter->setArgs(['field' => 'value1|value2']);
        $this->assertEquals(['value1', 'value2'], $filter->value());
    }

    /**
     * @return void
     */
    public function testValueMultiValueSeparatorInvalid()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['defaultValue' => 'default']
        );

        $filter->setConfig('multiValue', true);

        $filter->setArgs(['field' => 'value1|value2']);
        $this->assertEquals('value1|value2', $filter->value());
    }

    /**
     * @return void
     */
    public function testFieldAliasing()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            []
        );

        $this->assertEquals('Articles.field', $filter->field());

        $filter->setConfig('aliasField', false);
        $this->assertEquals('field', $filter->field());

        $filter = new TestFilter(
            'name',
            $this->Manager,
            ['field' => ['field1', 'field2']]
        );

        $expected = ['Articles.field1', 'Articles.field2'];
        $this->assertEquals($expected, $filter->field());
    }

    /**
     * @return void
     */
    public function testFieldAliasingWithNonSupportingRepository()
    {
        $repo = $this->getMockBuilder(RepositoryInterface::class)
            ->getMock();

        $filter = new TestFilter(
            'field',
            new Manager($repo),
            ['aliasField' => true]
        );

        $this->assertEquals('field', $filter->field());
    }

    /**
     * @return void
     */
    public function testBeforeProcessCallback()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['beforeProcess' => function ($query, $params) {
                $query->where($params);
            }]
        );

        $filter($this->Manager->getRepository()->find(), ['field' => 'bar']);
        $this->assertNotEmpty($filter->getQuery()->clause('where'));
    }

    /**
     * Test that beforeProcess callback returning false prevent process() from running.
     *
     * @return void
     */
    public function testBeforeProcessReturnFalse()
    {
        $filter = $this->getMockBuilder(TestFilter::class)
            ->setMethods(['process'])
            ->setConstructorArgs([
                'field',
                $this->Manager,
                [
                    'beforeProcess' => function ($query, $params) {
                        return false;
                    },
                ],
            ])
            ->getMock();

        $filter
            ->expects($this->never())
            ->method('process');

        $filter($this->Manager->getRepository()->find(), ['field' => 'bar']);
    }

    /**
     * Test that if beforeProcess returns array it's used as filter args.
     *
     * @return void
     */
    public function testBeforeProcessReturnArgsArray()
    {
        $filter = new TestFilter(
            'field',
            $this->Manager,
            ['beforeProcess' => function ($query, $params) {
                $params['extra'] = 'value';

                return $params;
            }]
        );

        $filter($this->Manager->getRepository()->find(), ['field' => 'bar']);
        $this->assertEquals(['field' => 'bar', 'extra' => 'value'], $filter->getArgs());
    }
}
