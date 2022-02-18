<?php

namespace DonlSync\Test\Unit\Catalog\Target\DONL;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DCAT_AP_DONL\DCATDataset;
use DCAT_AP_DONL\DCATLiteral;
use DCAT_AP_DONL\DCATURI;
use DonlSync\ApplicationInterface;
use DonlSync\Catalog\Target\DONL\DONLTargetCatalog;
use DonlSync\Configuration;
use DonlSync\Dataset\DatasetContainer;
use DonlSync\Dataset\DONLDistribution;
use DonlSync\Exception\CatalogHarvestingException;
use DonlSync\Exception\CatalogInitializationException;
use DonlSync\Exception\CatalogPublicationException;
use DonlSync\Exception\ConfigurationException;
use DonlSync\Exception\DonlSyncRuntimeException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;

class DONLTargetCatalogTest extends TestCase
{
    public function testCatalogWillNotInitializeWhenCatalogNameIsMissing(): void
    {
        $this->expectException(CatalogInitializationException::class);
        $this->expectExceptionMessage(
            'No configuration present with key catalog_name'
        );

        new DONLTargetCatalog(
            new Configuration([]),
            Mockery::mock(ApplicationInterface::class)
        );
    }

    public function testCatalogWillNotInitializeWhenCatalogEndpointIsMissing(): void
    {
        $this->expectException(CatalogInitializationException::class);
        $this->expectExceptionMessage(
            'No configuration present with key catalog_endpoint'
        );

        new DONLTargetCatalog(
            new Configuration([
                'catalog_name' => 'foo',
            ]),
            Mockery::mock(ApplicationInterface::class)
        );
    }

    public function testCatalogWillNotInitializeWhenPersistentPropertiesIsMissing(): void
    {
        $this->expectException(CatalogInitializationException::class);
        $this->expectExceptionMessage(
            'No configuration present with key persistent_properties'
        );

        new DONLTargetCatalog(
            new Configuration([
                'catalog_name'     => 'foo',
                'catalog_endpoint' => 'bar',
            ]),
            Mockery::mock(ApplicationInterface::class)
        );
    }

    public function testCatalogWillNotInitializeWhenCkanIsMissing(): void
    {
        $this->expectException(CatalogInitializationException::class);

        $application = Mockery::mock(ApplicationInterface::class);
        $application->allows('config')->andThrow(ConfigurationException::class);

        new DONLTargetCatalog(
            new Configuration([
                'catalog_name'          => 'foo',
                'catalog_endpoint'      => 'bar',
                'persistent_properties' => [],
            ]),
            $application
        );
    }

    public function testCatalogWillNotInitializeWhenCkanIsCorrupt(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);

        $application = Mockery::mock(ApplicationInterface::class);
        $application->allows('config')->andReturn(new Configuration([]));

        try {
            new DONLTargetCatalog(
                new Configuration([
                    'catalog_name'          => 'foo',
                    'catalog_endpoint'      => 'bar',
                    'persistent_properties' => [],
                ]),
                $application
            );
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException thrown');
        }
    }

    public function testCatalogWillNotInitializeWhenApiBasePathIsMissing(): void
    {
        $this->expectException(CatalogInitializationException::class);
        $this->expectExceptionMessage(
            'No configuration present with key api_base_path'
        );

        $application = Mockery::mock(ApplicationInterface::class);
        $application->allows('guzzle_client');
        $application->allows('config')->andReturn(new Configuration([
            'dataset_mapping'  => [],
            'resource_mapping' => [],
        ]));

        new DONLTargetCatalog(
            new Configuration([
                'catalog_name'          => 'foo',
                'catalog_endpoint'      => 'bar',
                'persistent_properties' => [],
            ]),
            $application
        );
    }

    public function testCatalogInitializesWhenAllPreConditionsAreMet(): void
    {
        try {
            $application = Mockery::mock(ApplicationInterface::class);
            $application->allows('guzzle_client');
            $application->allows('config')->andReturn(new Configuration([
                'dataset_mapping'  => [],
                'resource_mapping' => [],
            ]));

            $catalog = new DONLTargetCatalog(
                new Configuration([
                    'catalog_name'          => 'foo',
                    'catalog_endpoint'      => 'bar',
                    'persistent_properties' => [],
                    'api_base_path'         => 'var',
                ]),
                $application
            );

            $this->assertInstanceOf(DONLTargetCatalog::class, $catalog);
        } catch (CatalogInitializationException | DonlSyncRuntimeException $e) {
            $this->fail('Unexpected exception during catalog initialization.');
        }
    }

    public function testTargetCatalogGetters(): void
    {
        try {
            $application = Mockery::mock(ApplicationInterface::class);
            $application->allows('guzzle_client');
            $application->allows('config')->andReturn(new Configuration([
                'dataset_mapping'  => [],
                'resource_mapping' => [],
            ]));

            $catalog = new DONLTargetCatalog(
                new Configuration([
                    'catalog_name'          => 'foo',
                    'catalog_endpoint'      => 'bar',
                    'persistent_properties' => [],
                    'api_base_path'         => 'var',
                ]),
                $application
            );

            $this->assertEquals('foo', $catalog->getCatalogSlugName());
            $this->assertEquals('bar', $catalog->getCatalogEndpoint());
            $this->assertEquals([], $catalog->getPersistentProperties());
        } catch (CatalogInitializationException | DonlSyncRuntimeException $e) {
            $this->fail('Unexpected exception during catalog initialization.');
        }
    }

    public function testGetDataThrowsExceptionWhenOwnerOrgIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnGetData([
        ], 'Missing configuration key: owner_org');
    }

    public function testGetDataThrowsExceptionWhenUserIdIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnGetData([
            'owner_org' => 'foo',
        ], 'Missing configuration key: user_id');
    }

    public function testGetDataThrowsExceptionWhenApiKeyIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnGetData([
            'owner_org' => 'foo',
            'user_id'   => 'bar',
        ], 'Missing configuration key: api_key');
    }

    public function testGetDataThrowsHarvestingExceptionOnMissingApiRowCount(): void
    {
        $this->expectException(CatalogHarvestingException::class);
        $this->expectExceptionMessage('No configuration present with key api_row_count');

        try {
            $catalog = $this->createCatalog([], false);
            $catalog->getData([
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDataThrowsHarvestingExceptionOnGuzzleException(): void
    {
        $this->expectException(CatalogHarvestingException::class);
        $this->expectExceptionMessage('error message');

        try {
            $catalog = $this->createCatalog([
                new RequestException(
                    'error message',
                    new Request('POST', 'api/3/action/package_search')
                ),
            ]);
            $catalog->getData([
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDataThrowsHarvestingExceptionOnInvalidJsonResponse(): void
    {
        $this->expectException(CatalogHarvestingException::class);
        $this->expectExceptionMessage('Catalog response cannot be parsed as JSON');

        try {
            $catalog = $this->createCatalog([
                new Response(200, [], null),
            ]);
            $catalog->getData([
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDataThrowsHarvestingExceptionOnApiFailures(): void
    {
        $this->expectException(CatalogHarvestingException::class);
        $this->expectExceptionMessage('API request to harvest data failed');

        try {
            $catalog = $this->createCatalog([
                new Response(200, [], '{"success":false}'),
            ]);
            $catalog->getData([
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDataReturnsWhenAllDataIsRetrieved(): void
    {
        try {
            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'count'   => 1,
                        'results' => [
                            [],
                        ],
                    ],
                ])),
            ]);
            $data = $catalog->getData([
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->assertCount(1, $data);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        } catch (CatalogHarvestingException $e) {
            $this->fail('Unexpected CatalogHarvestingException while harvesting catalog');
        }
    }

    public function testGetDataKeepsMakingRequestsUntilAllDataIsRetrieved(): void
    {
        try {
            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'count'   => 2,
                        'results' => [
                            [],
                        ],
                    ],
                ])),
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'count'   => 2,
                        'results' => [
                            [],
                        ],
                    ],
                ])),
            ]);
            $data = $catalog->getData([
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->assertCount(2, $data);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        } catch (CatalogHarvestingException $e) {
            $this->fail('Unexpected CatalogHarvestingException while harvesting catalog');
        }
    }

    public function testPublishDatasetThrowsExceptionWhenOwnerOrgIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnPublishDataset([
        ], 'Missing configuration key: owner_org');
    }

    public function testPublishDatasetThrowsExceptionWhenUserIdIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnPublishDataset([
            'owner_org' => 'foo',
        ], 'Missing configuration key: user_id');
    }

    public function testPublishDatasetThrowsExceptionWhenApiKeyIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnPublishDataset([
            'owner_org' => 'foo',
            'user_id'   => 'bar',
        ], 'Missing configuration key: api_key');
    }

    public function testUpdateDatasetThrowsExceptionWhenOwnerOrgIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnUpdateDataset([
        ], 'Missing configuration key: owner_org');
    }

    public function testUpdateDatasetThrowsExceptionWhenUserIdIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnUpdateDataset([
            'owner_org' => 'foo',
        ], 'Missing configuration key: user_id');
    }

    public function testUpdateDatasetThrowsExceptionWhenApiKeyIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnUpdateDataset([
            'owner_org' => 'foo',
            'user_id'   => 'bar',
        ], 'Missing configuration key: api_key');
    }

    public function testUpdateDatasetThrowsExceptionWhenPropertiesCannotBePersisted(): void
    {
        $this->markTestSkipped('Test disabled until PersistentProperties are fixed in target catalog implementation.');

        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage(
            'Unable to persist properties; failed to retrieve dataset from catalog'
        );

        try {
            $container = new DatasetContainer();
            $container->setDataset(new DCATDataset());
            $container->setTargetIdentifier('bar');

            $catalog = $this->createCatalog([
                new RequestException(
                    'foobar',
                    new Request('POST', 'api/3/action/package_show')
                ),
            ]);
            $catalog->updateDataset($container, [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testUpdateDatasetPersistsDatasetProperties(): void
    {
        $this->markTestSkipped('Test disabled until PersistentProperties are fixed in target catalog implementation.');

        try {
            $history = [];

            $dataset = new DCATDataset();
            $dataset->setTitle(new DCATLiteral('baz'));

            $container = new DatasetContainer();
            $container->setDataset($dataset);
            $container->setTargetIdentifier('bar');

            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'title'     => 'bar',
                        'resources' => [],
                    ],
                ])),
                new RequestException(
                    'foobar',
                    new Request('POST', 'api/3/action/package_update')
                ),
            ], true, true, $history);
            $catalog->updateDataset($container, [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->fail();
        } catch (CatalogPublicationException $e) {
            $this->assertCount(2, $history);

            $request = json_decode($history[1]['request']->getBody()->getContents(), true);

            $this->assertArrayHasKey('title', $request);
            $this->assertEquals('bar', $request['title']);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testUpdateDatasetDoesNotPersistsResourcesWithoutId(): void
    {
        $this->markTestSkipped('Test disabled until PersistentProperties are fixed in target catalog implementation.');

        try {
            $history = [];

            $dataset = new DCATDataset();
            $dataset->setTitle(new DCATLiteral('baz'));
            $dataset->addDistribution(new DONLDistribution());

            $distribution = new DONLDistribution();
            $distribution->setId('bar');
            $distribution->setTitle(new DCATLiteral('foobarbaz'));
            $distribution->setAccessURL(new DCATURI('https://example.com'));
            $distribution->setFormat(new DCATControlledVocabularyEntry('https://example.com', 'MDR:FiletypeNAL'));

            $dataset->addDistribution($distribution);

            $container = new DatasetContainer();
            $container->setDataset($dataset);
            $container->setTargetIdentifier('bar');

            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'title'     => 'bar',
                        'resources' => [
                            [
                                'id'     => 'bar',
                                'name'   => 'baz',
                                'url'    => 'https://example.com',
                                'format' => 'https://example.com',
                            ],
                        ],
                    ],
                ])),
                new RequestException(
                    'foobar',
                    new Request('POST', 'api/3/action/package_update')
                ),
            ], true, true, $history);
            $catalog->updateDataset($container, [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->fail();
        } catch (CatalogPublicationException $e) {
            $this->assertCount(2, $history);

            $request = json_decode($history[1]['request']->getBody()->getContents(), true);

            $this->assertArrayHasKey('title', $request);
            $this->assertEquals('bar', $request['title']);
            $this->assertArrayHasKey('resources', $request);
            $this->assertCount(2, $request['resources']);
            $this->assertArrayHasKey('name', $request['resources'][1]);
            $this->assertEquals('baz', $request['resources'][1]['name']);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testUpdateDatasetThrowsExceptionOnInvalidJsonResponse(): void
    {
        $this->markTestSkipped('Test disabled until PersistentProperties are fixed in target catalog implementation.');

        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage(
            'Unknown result of package_update operation; Invalid JSON response'
        );

        try {
            $history   = [];
            $container = new DatasetContainer();
            $container->setDataset(new DCATDataset());
            $container->setTargetIdentifier('bar');

            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'resources' => [],
                    ],
                ])),
                new Response(200, [], null),
            ], true, true, $history);
            $catalog->updateDataset($container, [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->fail();
        } catch (CatalogInitializationException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testUpdateDatasetThrowsExceptionOnInvalidApiResponse(): void
    {
        $this->markTestSkipped('Test disabled until PersistentProperties are fixed in target catalog implementation.');

        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage('bar');

        try {
            $history   = [];
            $container = new DatasetContainer();
            $container->setDataset(new DCATDataset());
            $container->setTargetIdentifier('bar');

            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'resources' => [],
                    ],
                ])),
                new Response(200, [], json_encode([
                    'success' => false,
                    'error'   => [
                        'message' => 'bar',
                    ],
                ])),
            ], true, true, $history);
            $catalog->updateDataset($container, [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->fail();
        } catch (CatalogInitializationException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testUpdateDatasetPassesWhenAllPreConditionsAreMet(): void
    {
        try {
            $history   = [];
            $container = new DatasetContainer();
            $container->setDataset(new DCATDataset());
            $container->setTargetIdentifier('bar');

            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'resources' => [],
                    ],
                ])),
                new Response(200, [], json_encode([
                    'success' => true,
                ])),
            ], true, true, $history);
            $catalog->updateDataset($container, [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->assertTrue(true);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testDeleteDatasetThrowsExceptionWhenOwnerOrgIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnDeleteDataset([
        ], 'Missing configuration key: owner_org');
    }

    public function testDeleteDatasetThrowsExceptionWhenUserIdIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnDeleteDataset([
            'owner_org' => 'foo',
        ], 'Missing configuration key: user_id');
    }

    public function testDeleteDatasetThrowsExceptionWhenApiKeyIsMissingFromCredentials(): void
    {
        $this->checkCredentialsOnDeleteDataset([
            'owner_org' => 'foo',
            'user_id'   => 'bar',
        ], 'Missing configuration key: api_key');
    }

    public function testDeleteDatasetThrowsPublicationExceptionOnGuzzleException(): void
    {
        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage('foobar');

        try {
            $catalog = $this->createCatalog([
                new RequestException(
                    'foobar',
                    new Request('POST', 'api/3/action/dataset_purge')
                ),
            ]);
            $catalog->deleteDataset('foo', [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testDeleteDatasetThrowsExceptionOnInvalidJsonResponse(): void
    {
        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage(
            'Unknown result of dataset_purge operation; Invalid JSON response'
        );

        try {
            $catalog = $this->createCatalog([
                new Response(200, [], null),
            ]);
            $catalog->deleteDataset('foo', [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testDeleteDatasetThrowsPublicationExceptionOnBadApiResponse(): void
    {
        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage('foobar');

        try {
            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => false,
                    'error'   => [
                        'message' => 'foobar',
                    ],
                ])),
            ]);
            $catalog->deleteDataset('foo', [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testDeleteDatasetPassesWhenAllPreConditionsAreMet(): void
    {
        try {
            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                ])),
            ]);
            $catalog->deleteDataset('foo', [
                'owner_org' => 'foo',
                'user_id'   => 'bar',
                'api_key'   => 'baz',
            ]);

            $this->assertTrue(true);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testGetDatasetThrowsPublicationExceptionOnGuzzleException(): void
    {
        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage('foobar');

        try {
            $catalog = $this->createCatalog([
                new RequestException(
                    'foobar',
                    new Request('POST', 'api/3/action/package_show')
                ),
            ]);
            $catalog->getDataset('foo');
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDatasetThrowsPublicationExceptionOnInvalidJsonResponse(): void
    {
        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage(
            'Unknown result of package_show operation; Invalid JSON response'
        );

        try {
            $catalog = $this->createCatalog([
                new Response(200, [], null),
            ]);
            $catalog->getDataset('foo');
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDatasetThrowsPublicationExceptionOnBadApiResponse(): void
    {
        $this->expectException(CatalogPublicationException::class);
        $this->expectExceptionMessage('foobar');

        try {
            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => false,
                    'error'   => [
                        'message' => 'foobar',
                    ],
                ])),
            ]);
            $catalog->getDataset('foo');
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        }
    }

    public function testGetDatasetReturnsDataUnderResultKeyOnSuccess(): void
    {
        try {
            $catalog = $this->createCatalog([
                new Response(200, [], json_encode([
                    'success' => true,
                    'result'  => [
                        'foo' => 'bar',
                    ],
                ])),
            ]);
            $dataset = $catalog->getDataset('foo');

            $this->assertCount(1, $dataset);
            $this->assertArrayHasKey('foo', $dataset);
            $this->assertEquals('bar', $dataset['foo']);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    private function checkCredentialsOnGetData(array $credentials, string $expected_message): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage($expected_message);

        try {
            $catalog = $this->createCatalog();
            $catalog->getData($credentials);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        } catch (CatalogHarvestingException $e) {
            $this->fail('Unexpected CatalogHarvestingException while harvesting catalog');
        }
    }

    private function checkCredentialsOnPublishDataset(array $credentials, string $expected_message): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage($expected_message);

        try {
            $catalog = $this->createCatalog();
            $catalog->publishDataset(new DatasetContainer(), $credentials);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        } catch (CatalogPublicationException $e) {
            $this->fail('Unexpected CatalogPublicationException while harvesting catalog');
        }
    }

    private function checkCredentialsOnUpdateDataset(array $credentials, string $expected_message): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage($expected_message);

        try {
            $catalog = $this->createCatalog();
            $catalog->updateDataset(new DatasetContainer(), $credentials);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        } catch (CatalogPublicationException $e) {
            $this->fail('Unexpected CatalogPublicationException while harvesting catalog');
        }
    }

    private function checkCredentialsOnDeleteDataset(array $credentials, string $expected_message): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage($expected_message);

        try {
            $catalog = $this->createCatalog();
            $catalog->deleteDataset('foo', $credentials);
        } catch (CatalogInitializationException $e) {
            $this->fail('Unexpected CatalogInitializationException while initializing catalog');
        } catch (CatalogPublicationException $e) {
            $this->fail('Unexpected CatalogPublicationException while deleting dataset');
        }
    }

    /**
     * Creates a `DONLTargetCatalog` with mocked dependencies injected.
     *
     * @param array $handlers          The mocked requests to use
     * @param bool  $add_api_row_count Whether or not to expose 'api_row_count'
     * @param bool  $add_base_path     Whether or not to expose 'api_base_path'
     * @param array $request_history   The array to hold the Guzzle request history in
     *
     * @throws CatalogInitializationException Thrown on any initialization error
     *
     * @return DONLTargetCatalog The created ITargetCatalog
     */
    private function createCatalog(array $handlers = [], bool $add_api_row_count = true,
                                   bool $add_base_path = true, array &$request_history = []): DONLTargetCatalog
    {
        $ckan_config = [
            'dataset_mapping'  => [
                'title' => [
                    'target' => 'title',
                    'multi'  => false,
                ],
            ],
            'resource_mapping' => [
                'title' => [
                    'target' => 'name',
                    'multi'  => false,
                ],
                'accessURL' => [
                    'target' => 'url',
                    'multi'  => false,
                ],
                'format' => [
                    'target' => 'format',
                    'multi'  => false,
                ],
            ],
        ];

        if ($add_api_row_count) {
            $ckan_config['api_row_count'] = 1;
        }

        if ($add_base_path) {
            $ckan_config['api_base_path'] = 'var';
        }

        $history = Middleware::history($request_history);
        $mock    = new MockHandler($handlers);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $application = Mockery::mock(ApplicationInterface::class);
        $application->allows('guzzle_client')
            ->andReturn(new Client(['handler' => $handler]));
        $application->allows('config')
            ->andReturn(new Configuration($ckan_config));

        return new DONLTargetCatalog(
            new Configuration([
                'catalog_name'          => 'foo',
                'catalog_endpoint'      => 'https://data.overheid.nl/data',
                'persistent_properties' => [
                    'dataset' => [
                        'title',
                    ],
                    'resource' => [
                        'title',
                    ],
                ],
                'api_base_path'         => 'var',
            ]),
            $application
        );
    }
}
