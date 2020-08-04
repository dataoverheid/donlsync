<?php

namespace DonlSync\Dataset;

use DCAT_AP_DONL\DCATDataset;
use DCAT_AP_DONL\DCATDistribution;
use DCAT_AP_DONL\DCATEntity;
use DonlSync\Configuration;

/**
 * Class DatasetTransformer.
 *
 * Allows the transforming of DCAT-AP-DONL datasets into a CKAN acceptable format.
 */
class DatasetTransformer
{
    /** @var string[] */
    private $dataset_mapping;

    /** @var string[] */
    private $resource_mapping;

    /**
     * DatasetTransformer constructor.
     *
     * @param Configuration $ckan_config The CKAN configuration holding the field mapping
     */
    public function __construct(Configuration $ckan_config)
    {
        $config = $ckan_config->all();

        $this->dataset_mapping  = $config['dataset_mapping'];
        $this->resource_mapping = $config['resource_mapping'];
    }

    /**
     * Transforms the given DCATDataset into a key/value array for the CKAN API.
     *
     * @param DCATDataset $dataset The dataset to transform
     *
     * @return array The DCATDataset as a key/value array
     */
    public function transform(DCATDataset $dataset): array
    {
        $transformed = [];

        foreach ($this->dataset_mapping as $dcat_field => $mapping) {
            if (true === $mapping['multi']) {
                $this->transformMultiValueProperty(
                    $dcat_field, $mapping['target'], $transformed, $dataset
                );
            } else {
                $this->transformProperty(
                    $dcat_field, $mapping['target'], $transformed, $dataset
                );
            }
        }

        $this->transformKeyword($transformed, $dataset);
        $this->transformContactPoint($transformed, $dataset);
        $this->transformSpatial($transformed, $dataset);
        $this->transformTemporal($transformed, $dataset);
        $this->transformLegalFoundation($transformed, $dataset);
        $this->transformDistribution($transformed, $dataset);

        return $transformed;
    }

    /**
     * Transforms a given property present in the given dataset into a key/value pair placed in the
     * transformed array.
     *
     * @param string      $property    The property to retrieve from the dataset
     * @param string      $target_key  The key on which the value should be set
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformProperty(string $property, string $target_key, array &$transformed,
                                         DCATDataset $dataset): void
    {
        $method = 'get' . ucfirst($property);
        $value  = $dataset->$method();

        /** @var $value DCATEntity */
        if (null !== $value) {
            $transformed[$target_key] = $value->getData();
        }
    }

    /**
     * Transforms a given multi valued property present in the given dataset into a key/value pair
     * placed in the transformed array.
     *
     * @param string      $property    The property to retrieve from the dataset
     * @param string      $target_key  The key on which the value should be set
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformMultiValueProperty(string $property, string $target_key,
                                                   array &$transformed, DCATDataset $dataset): void
    {
        $method = 'get' . ucfirst($property);

        foreach ($dataset->$method() as $value) {
            /* @var $value DCATEntity */
            $transformed[$target_key][] = $value->getData();
        }
    }

    /**
     * Transforms the keywords of the dataset into a valid CKAN tag_string.
     *
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformKeyword(array &$transformed, DCATDataset $dataset): void
    {
        foreach ($dataset->getKeyword() as $keyword) {
            $transformed['tags'][] = [
                'name' => iconv('UTF-8', 'UTF-8//IGNORE', $keyword->getData()),
            ];
        }
    }

    /**
     * Transforms the contactPoint of a dataset into the transformed container.
     *
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformContactPoint(array &$transformed, DCATDataset $dataset): void
    {
        $contact_point = $dataset->getContactPoint();

        if (null == $contact_point) {
            return;
        }

        $transformed['contact_point_name'] = $contact_point->getFullName()->getData();

        if (null !== $contact_point->getEmail()) {
            $transformed['contact_point_email'] = $contact_point->getEmail()->getData();
        }

        if (null !== $contact_point->getPhone()) {
            $transformed['contact_point_phone'] = $contact_point->getPhone()->getData();
        }

        if (null !== $contact_point->getAddress()) {
            $transformed['contact_point_address'] = $contact_point->getAddress()->getData();
        }

        if (null !== $contact_point->getWebpage()) {
            $transformed['contact_point_website'] = $contact_point->getWebpage()->getData();
        }

        if (null !== $contact_point->getTitle()) {
            $transformed['contact_point_title'] = $contact_point->getTitle()->getData();
        }
    }

    /**
     * Transforms the spatials of the dataset into key/value pairs.
     *
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformSpatial(array &$transformed, DCATDataset $dataset): void
    {
        foreach ($dataset->getSpatial() as $spatial) {
            $transformed['spatial_scheme'][] = $spatial->getScheme()->getData();
            $transformed['spatial_value'][]  = $spatial->getValue()->getData();
        }
    }

    /**
     * Transforms the temporal of the dataset into key/value pairs.
     *
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformTemporal(array &$transformed, DCATDataset $dataset): void
    {
        $temporal = $dataset->getTemporal();

        if (null == $temporal) {
            return;
        }

        if (null !== $temporal->getLabel()) {
            $transformed['temporal_label'] = $temporal->getLabel()->getData();
        }

        if (null !== $temporal->getStart()) {
            $transformed['temporal_start'] = $temporal->getStart()->getData();
        }

        if (null !== $temporal->getEnd()) {
            $transformed['temporal_end'] = $temporal->getEnd()->getData();
        }
    }

    /**
     * Transforms the legalFoundation of the dataset into key/value pairs.
     *
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     */
    protected function transformLegalFoundation(array &$transformed, DCATDataset $dataset): void
    {
        $legal_foundation = $dataset->getLegalFoundation();

        if (null == $legal_foundation) {
            return;
        }

        $transformed['legal_foundation_label'] = $legal_foundation->getLabel()->getData();
        $transformed['legal_foundation_ref']   = $legal_foundation->getReference()->getData();
        $transformed['legal_foundation_uri']   = $legal_foundation->getUri()->getData();
    }

    /**
     * Transforms the distributions of the dataset into key/value pairs.
     *
     * @param array       $transformed The container holding the transformed dataset
     * @param DCATDataset $dataset     The dataset to transform
     *
     * @return array The updated transformed container
     */
    protected function transformDistribution(array &$transformed, DCATDataset $dataset): array
    {
        foreach ($dataset->getDistributions() as $distribution) {
            $resource = [];

            foreach ($this->resource_mapping as $dcat_field => $mapping) {
                if (true === $mapping['multi']) {
                    $this->transformDistributionMultiValuedProperty(
                        $dcat_field, $mapping['target'], $resource, $distribution
                    );
                } else {
                    $this->transformDistributionProperty(
                        $dcat_field, $mapping['target'], $resource, $distribution
                    );
                }
            }

            $this->transformDistributionChecksum($resource, $distribution);

            $transformed['resources'][] = $resource;
        }

        return $transformed;
    }

    /**
     * Transforms a given property present in the given distribution into a key/value pair placed in
     * the transformed array.
     *
     * @param string           $property     The property to retrieve from the dataset
     * @param string           $target_key   The key behind which to place the transformed data
     * @param array            $transformed  The container holding the transformed distribution
     * @param DCATDistribution $distribution The distribution to transform
     */
    protected function transformDistributionProperty(string $property, string $target_key,
                                                     array &$transformed,
                                                     DCATDistribution $distribution): void
    {
        $method = 'get' . ucfirst($property);
        $value  = $distribution->$method();

        /** @var $value DCATEntity */
        if (null !== $value) {
            $transformed[$target_key] = $value->getData();
        }
    }

    /**
     * Transforms a given multi valued property present in the given distribution into a key/value
     * pair placed in the transformed array.
     *
     * @param string           $property     The property to retrieve from the dataset
     * @param string           $target_key   The key behind which to place the transformed data
     * @param array            $transformed  The container holding the transformed distribution
     * @param DCATDistribution $distribution The distribution to transform
     */
    protected function transformDistributionMultiValuedProperty(string $property,
                                                                string $target_key,
                                                                array &$transformed,
                                                                DCATDistribution $distribution): void
    {
        $method = 'get' . ucfirst($property) . 's';

        foreach ($distribution->$method() as $value) {
            /* @var $value DCATEntity */
            $transformed[$target_key][] = $value->getData();
        }
    }

    /**
     * Transforms the checksum of the distribution into key/value pairs.
     *
     * @param array            $resource     The container holding the transformed distribution
     * @param DCATDistribution $distribution The distribution to transform
     */
    protected function transformDistributionChecksum(array &$resource,
                                                     DCATDistribution $distribution): void
    {
        $checksum = $distribution->getChecksum();

        if (null == $checksum) {
            return;
        }

        $resource['hash']           = $checksum->getHash()->getData();
        $resource['hash_algorithm'] = $checksum->getAlgorithm()->getData();
    }
}
