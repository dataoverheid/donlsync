<?php

namespace DonlSync\Test\Unit\Dataset;

use DCAT_AP_DONL\DCATDataset;
use DCAT_AP_DONL\DCATLiteral;
use DonlSync\Dataset\DatasetContainer;
use DonlSync\Dataset\DONLDistribution;
use PHPUnit\Framework\TestCase;

class DatasetContainerTest extends TestCase
{
    public function testEmptyContainerReturnsNoData(): void
    {
        $container = new DatasetContainer();

        $this->assertNull($container->getCatalogName());
        $this->assertNull($container->getCatalogIdentifier());
        $this->assertNull($container->getTargetIdentifier());
        $this->assertNull($container->getDataset());
        $this->assertNull($container->getDatasetHash());
        $this->assertNull($container->getAssignedNumber());
        $this->assertEmpty($container->getConversionNotices());
    }

    public function testWhenStringValueIsSetItIsRetrievable(): void
    {
        $container  = new DatasetContainer();
        $test_value = 'foo';
        $mapping    = [
            ['getCatalogName', 'setCatalogName'],
            ['getCatalogIdentifier', 'setCatalogIdentifier'],
            ['getTargetIdentifier', 'setTargetIdentifier'],
            ['getDatasetHash', 'setDatasetHash'],
        ];

        foreach ($mapping as $item) {
            $this->assertNull($container->{$item[0]}());

            $container->{$item[1]}($test_value);

            $this->assertNotNull($container->{$item[0]}());
            $this->assertEquals($test_value, $container->{$item[0]}());
        }
    }

    public function testWhenIntValueIsSetItIsRetrievable(): void
    {
        $container  = new DatasetContainer();
        $test_value = 1;
        $mapping    = [
            ['getAssignedNumber', 'setAssignedNumber'],
        ];

        foreach ($mapping as $item) {
            $this->assertNull($container->{$item[0]}());

            $container->{$item[1]}($test_value);

            $this->assertNotNull($container->{$item[0]}());
            $this->assertEquals($test_value, $container->{$item[0]}());
        }
    }

    public function testWhenDatasetValueIsSetItIsRetrievable(): void
    {
        $container = new DatasetContainer();
        $dataset   = new DCATDataset();

        $this->assertNull($container->getDataset());

        $container->setDataset($dataset);

        $this->assertNotNull($container->getDataset());
        $this->assertEquals($dataset, $container->getDataset());
    }

    public function testWhenArrayValueIsSetItIsRetrievable(): void
    {
        $container  = new DatasetContainer();
        $test_value = ['foo'];
        $mapping    = [
            ['getConversionNotices', 'setConversionNotices'],
        ];

        foreach ($mapping as $item) {
            $this->assertEmpty($container->{$item[0]}());

            $container->{$item[1]}($test_value);

            $this->assertNotEmpty($container->{$item[0]}());
            $this->assertSameSize($test_value, $container->{$item[0]}());
            $this->assertEquals($test_value, $container->{$item[0]}());
        }
    }

    public function testGeneratedHashIsEqualForEqualDatasets(): void
    {
        $container_1 = new DatasetContainer();
        $dataset_1   = new DCATDataset();
        $container_2 = new DatasetContainer();
        $dataset_2   = new DCATDataset();

        $container_1->setDataset($dataset_1);
        $container_1->generateHash();

        $container_2->setDataset($dataset_2);
        $container_2->generateHash();

        $this->assertEquals($container_1->getDataset(), $container_2->getDataset());
        $this->assertEquals($container_1->getDatasetHash(), $container_2->getDatasetHash());
    }

    public function testGeneratedHashAccountsForNestedChanges(): void
    {
        $container_1 = new DatasetContainer();
        $dataset_1   = new DCATDataset();
        $dataset_1->setTitle(new DCATLiteral('foo'));
        $container_1->setDataset($dataset_1);
        $container_1->generateHash();

        $container_2 = new DatasetContainer();
        $dataset_2   = new DCATDataset();
        $dataset_2->setTitle(new DCATLiteral('foo'));
        $distribution = new DONLDistribution();
        $distribution->setTitle(new DCATLiteral('bar'));
        $dataset_2->addDistribution($distribution);
        $container_2->setDataset($dataset_2);
        $container_2->generateHash();

        $this->assertNotEquals($container_1->getDataset(), $container_2->getDataset());
        $this->assertNotEquals($container_1->getDatasetHash(), $container_2->getDatasetHash());
    }

    public function testGeneratedHashIsNotEqualForNonEqualDatasets(): void
    {
        $container_1 = new DatasetContainer();
        $dataset_1   = new DCATDataset();
        $dataset_1->setTitle(new DCATLiteral('foo'));

        $container_2 = new DatasetContainer();
        $dataset_2   = new DCATDataset();

        $container_1->setDataset($dataset_1);
        $container_1->generateHash();

        $container_2->setDataset($dataset_2);
        $container_2->generateHash();

        $this->assertNotEquals($container_1->getDataset(), $container_2->getDataset());
        $this->assertNotEquals($container_1->getDatasetHash(), $container_2->getDatasetHash());
    }
}
