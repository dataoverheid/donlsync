<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use Doctrine\DBAL\Exception;
use DonlSync\Database\Repository\UnmappedValuesRepository;
use DonlSync\Dataset\Mapping\BlacklistMapper;
use DonlSync\Dataset\Mapping\DefaultMapper;
use DonlSync\Dataset\Mapping\ValueMapper;
use DonlSync\Dataset\Mapping\WhitelistMapper;

/**
 * Class AbstractDCATEntityBuildRule.
 *
 * Base implementation of the IDCATEntityBuildRule interface.
 */
abstract class AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * The property being built.
     */
    protected string $property;

    /**
     * The prefix used for generating notices.
     */
    protected string $prefix;

    /**
     * The mapping implementation for applying default values.
     */
    protected ?DefaultMapper $defaults;

    /**
     * The mapping implementations per field for transforming harvested values.
     *
     * @var ValueMapper[]
     */
    protected array $value_mappers;

    /**
     * The mapping implementations per field for blocking the harvesting of certain metadata values.
     *
     * @var BlacklistMapper[]
     */
    protected array $blacklists;

    /**
     * The mapping implementations per field for only allowing the harvesting of certain metadata
     * values.
     *
     * @var WhitelistMapper[]
     */
    protected array $whitelists;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $property, string $prefix = 'Dataset')
    {
        $this->property      = $property;
        $this->prefix        = $prefix;
        $this->defaults      = null;
        $this->value_mappers = [];
        $this->blacklists    = [];
        $this->whitelists    = [];
    }

    /**
     * {@inheritdoc}
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Checks if a value is present in the harvested data or in the configured defaults.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     *
     * @return bool Whether or not a value is present to process
     */
    public function valueIsPresent(string $property, array &$data, array &$notices): bool
    {
        if (!array_key_exists($property, $data) || '' === trim($data[$property])) {
            if (!$this->defaults->has($property)) {
                return false;
            }

            $data[$property] = $this->defaults->getDefault($property);
            $notices[]       = sprintf('%s: %s: no value found, using default value %s',
                $this->prefix, ucfirst($property), $data[$property]
            );
        }

        return true;
    }

    /**
     * Checks if a (multi valued) value is present in the harvested data or in the configured
     * defaults.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     *
     * @return bool Whether or not a value is present to process
     */
    public function multiValuedValueIsPresent(string $property, array &$data, array &$notices): bool
    {
        if (!array_key_exists($property, $data) || 0 == count($data[$property])) {
            if (!$this->defaults->has($property)) {
                return false;
            }

            $data[$property]   = [$this->defaults->getDefault($property)];
            $notices[]         = sprintf('%s: %s: no values found, using default value %s',
                $this->prefix, ucfirst($property), $this->defaults->getDefault($property)
            );
        }

        return true;
    }

    /**
     * Checks if a given value is blacklisted.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     *
     * @return bool Whether or not a value is blacklisted
     */
    public function valueIsBlacklisted(string $property, array $data, array &$notices): bool
    {
        if (array_key_exists($property, $this->blacklists)) {
            if ($this->blacklists[$property]->isBlacklisted($data[$property])) {
                $notices[] = sprintf('%s: %s: value %s is blacklisted, removing',
                    $this->prefix, ucfirst($property), $data[$property]
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a given value is blacklisted.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     * @param int                  $index    The index holding the value to check
     *
     * @return bool Whether or not a value is blacklisted
     */
    public function multiValuedValueIsBlacklisted(string $property, array $data, array &$notices,
                                                  int $index): bool
    {
        if (array_key_exists($property, $this->blacklists)) {
            if ($this->blacklists[$property]->isBlacklisted($data[$property][$index])) {
                $notices[] = sprintf('%s: %s: value %s is blacklisted, removing',
                    $this->prefix, ucfirst($property), $data[$property][$index]
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a given value is whitelisted.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     *
     * @return bool Whether or not a value is whitelisted
     */
    public function valueIsWhitelisted(string $property, array $data, array &$notices): bool
    {
        if (array_key_exists($property, $this->whitelists)) {
            if (!$this->whitelists[$property]->inWhitelist($data[$property])) {
                $notices[] = sprintf('%s: %s: value %s is not whitelisted, removing',
                    $this->prefix, ucfirst($property), $data[$property]
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a given value is whitelisted.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     * @param int                  $index    The index holding the value to check
     *
     * @return bool Whether or not a value is whitelisted
     */
    public function multiValuedValueIsWhitelisted(string $property, array $data, array &$notices,
                                                  int $index): bool
    {
        if (array_key_exists($property, $this->whitelists)) {
            if (!$this->whitelists[$property]->inWhitelist($data[$property][$index])) {
                $notices[] = sprintf('%s: %s: value %s is not whitelisted, removing',
                    $this->prefix, ucfirst($property), $data[$property][$index]
                );

                return false;
            }
        }

        return true;
    }

    /**
     * If a mapping is defined for a given value, that mapping is applied to that value. No action
     * is taken otherwise.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     */
    public function applyValueMapping(string $property, array &$data, array &$notices): void
    {
        if (array_key_exists($property, $this->value_mappers)) {
            $mapped_value = $this->value_mappers[$property]->map($data[$property]);

            if ($mapped_value != $data[$property]) {
                $notices[] = sprintf('%s: %s: mapped from %s to %s',
                    $this->prefix, ucfirst($property), $data[$property], $mapped_value
                );
                $data[$property] = $mapped_value;
            }
        }
    }

    /**
     * If a mapping is defined for a given value, that mapping is applied to that value. No action
     * is taken otherwise.
     *
     * @param string               $property The property holding the value
     * @param array<string, mixed> $data     The data from which to construct a DCATEntity
     * @param string[]             $notices  The notices generated so far
     * @param int                  $index    The index holding the value to check
     */
    public function applyMultiValuedValueMapping(string $property, array &$data, array &$notices,
                                                 int $index): void
    {
        if (array_key_exists($property, $this->value_mappers)) {
            $mapped_value = $this->value_mappers[$property]->map($data[$property][$index]);

            if ($mapped_value != $data[$property][$index]) {
                $notices[] = sprintf('%s: %s: mapped from %s to %s',
                    $this->prefix, ucfirst($property), $data[$property][$index], $mapped_value
                );
                $data[$property][$index] = $mapped_value;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaults(DefaultMapper $defaults): void
    {
        $this->defaults = $defaults;
    }

    /**
     * {@inheritdoc}
     */
    public function setValueMappers(array $mappers): void
    {
        $this->value_mappers = $mappers;
    }

    /**
     * {@inheritdoc}
     */
    public function setBlacklists(array $mappers): void
    {
        $this->blacklists = $mappers;
    }

    /**
     * {@inheritdoc}
     */
    public function setWhitelists(array $mappers): void
    {
        $this->whitelists = $mappers;
    }

    /**
     * Attempts to create a single DCATEntity based on the given data and the configured Mapping
     * implementations. May return `null` if no DCATEntity can be constructed.
     *
     * @param array<mixed, mixed> $data         The data harvested from the catalog
     * @param string[]            $notices      The notices generated during the dataset building
     *                                          process
     * @param string              $entity_class The DCATEntity to create
     *
     * @return mixed|null The created DCATEntity, or null if one could not be created
     */
    protected function buildSingleProperty(array &$data, array &$notices, string $entity_class)
    {
        if (!$this->valueIsPresent($this->property, $data, $notices)) {
            return null;
        }

        if ($this->valueIsBlacklisted($this->property, $data, $notices)) {
            return null;
        }

        if (!$this->valueIsWhitelisted($this->property, $data, $notices)) {
            return null;
        }

        $original_value = $data[$this->property];

        $this->applyValueMapping($this->property, $data, $notices);

        $dcat_entity = new $entity_class($data[$this->property]);

        if (!$dcat_entity->validate()->validated()) {
            $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                $this->prefix, ucfirst($this->property), $dcat_entity->getData()
            );

            $this->conditionallyRegisterMissingMapping($original_value, $data[$this->property]);

            return null;
        }

        return $dcat_entity;
    }

    /**
     * Register a record in the database detailing that a harvested value was not mapped, but should
     * and/or could have been. The record is only registered if the original value is equal to the
     * value after applying all the mappings.
     *
     * @param mixed $originalValue The original harvested value
     * @param mixed $currentValue  The value after applying all mappings
     */
    protected function conditionallyRegisterMissingMapping($originalValue, $currentValue): void
    {
        if ($originalValue === $currentValue) {
            try {
                UnmappedValuesRepository::insertRecord([
                    'object'    => explode(' ', $this->prefix)[0],
                    'attribute' => $this->property,
                    'value'     => $originalValue,
                ]);
            } catch (Exception $e) {
                // Fail silently.
            }
        }
    }

    /**
     * Creates a fully configured DCATLiteralBuildRule for a given property.
     *
     * @param string $property The property to build
     *
     * @return DCATLiteralBuildRule The created builder
     */
    protected function createLiteralBuildRule(string $property): DCATLiteralBuildRule
    {
        $literal_builder = new DCATLiteralBuildRule($property);
        $literal_builder->setDefaults($this->defaults);
        $literal_builder->setValueMappers($this->value_mappers);
        $literal_builder->setBlacklists($this->blacklists);
        $literal_builder->setWhitelists($this->whitelists);

        return $literal_builder;
    }

    /**
     * Creates a fully configured DCATURIBuildRule for a given property.
     *
     * @param string $property The property to build
     *
     * @return DCATURIBuildRule The created builder
     */
    protected function createURIBuildRule(string $property): DCATURIBuildRule
    {
        $uri_builder = new DCATURIBuildRule($property);
        $uri_builder->setDefaults($this->defaults);
        $uri_builder->setValueMappers($this->value_mappers);
        $uri_builder->setBlacklists($this->blacklists);
        $uri_builder->setWhitelists($this->whitelists);

        return $uri_builder;
    }

    /**
     * Creates a fully configured DCATDateTimeBuildRule for a given property.
     *
     * @param string $property The property to build
     *
     * @return DCATDateTimeBuildRule The created builder
     */
    protected function createDateTimeBuildRule(string $property): DCATDateTimeBuildRule
    {
        $datetime_builder = new DCATDateTimeBuildRule($property);
        $datetime_builder->setDefaults($this->defaults);
        $datetime_builder->setValueMappers($this->value_mappers);
        $datetime_builder->setBlacklists($this->blacklists);
        $datetime_builder->setWhitelists($this->whitelists);

        return $datetime_builder;
    }

    /**
     * Removes any duplicates from the given array.
     *
     * @param array<mixed, mixed> $multi_valued_entity The array which may contain duplicate values
     *
     * @return array<mixed, mixed> The original array, without the duplicate values
     */
    protected function stripDuplicates(array $multi_valued_entity): array
    {
        return array_values(array_unique($multi_valued_entity, SORT_REGULAR));
    }
}
