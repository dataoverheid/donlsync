<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DCAT_AP_DONL\DCATEntity;
use DCAT_AP_DONL\DCATException;

/**
 * Class DCATControlledVocabularyEntryBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATControlledVocabularyEntry` object.
 *
 * @see \DCAT_AP_DONL\DCATControlledVocabularyEntry
 */
class DCATControlledVocabularyEntryBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /** @var string */
    protected $vocabulary;

    /**
     * {@inheritdoc}
     *
     * @param string $vocabulary The vocabulary for which to create an entry
     */
    public function __construct(string $property, string $prefix = 'Dataset',
                                string $vocabulary = '')
    {
        parent::__construct($property, $prefix);

        $this->vocabulary = $vocabulary;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array &$data, array &$notices): ?DCATEntity
    {
        $vocabulary_match = 'DONL:License' === $this->vocabulary;

        if (!$this->valueIsPresent($this->property, $data, $notices) && !$vocabulary_match) {
            return null;
        }

        if ($this->valueIsBlacklisted($this->property, $data, $notices)) {
            return null;
        }

        if (!$this->valueIsWhitelisted($this->property, $data, $notices)) {
            return null;
        }

        $this->applyValueMapping($this->property, $data, $notices);

        $dcat_controlled_vocabulary_entry = new DCATControlledVocabularyEntry(
            $data[$this->property], $this->vocabulary
        );

        try {
            if (!$dcat_controlled_vocabulary_entry->validate()->validated()) {
                $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                    $this->prefix, ucfirst($this->property),
                    $dcat_controlled_vocabulary_entry->getData()
                );

                return null;
            }
        } catch (DCATException $e) {
            $notices[] = sprintf('%s: %s: invalid vocabulary %s defined for %s',
                $this->prefix, ucfirst($this->property), $this->vocabulary,
                $dcat_controlled_vocabulary_entry->getData()
            );

            return null;
        }

        return $dcat_controlled_vocabulary_entry;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $dcat_controlled_vocabularies = [];

        if (!$this->multiValuedValueIsPresent($this->property, $data, $notices)) {
            return $dcat_controlled_vocabularies;
        }

        for ($i = 0; $i < count($data[$this->property]); ++$i) {
            if ($this->multiValuedValueIsBlacklisted($this->property, $data, $notices, $i)) {
                continue;
            }

            if (!$this->multiValuedValueIsWhitelisted($this->property, $data, $notices, $i)) {
                continue;
            }

            $this->applyMultiValuedValueMapping($this->property, $data, $notices, $i);

            $dcat_controlled_vocabulary_entry = new DCATControlledVocabularyEntry(
                $data[$this->property][$i], $this->vocabulary
            );

            try {
                if (!$dcat_controlled_vocabulary_entry->validate()->validated()) {
                    $notices[] = sprintf('%s: %s: value %s is not valid, discarding',
                        $this->prefix, ucfirst($this->property),
                        $dcat_controlled_vocabulary_entry->getData()
                    );

                    continue;
                }
            } catch (DCATException $e) {
                $notices[] = sprintf('%s: %s: invalid vocabulary %s defined for %s',
                    $this->prefix, ucfirst($this->property), $this->vocabulary,
                    $dcat_controlled_vocabulary_entry->getData()
                );

                continue;
            }

            $dcat_controlled_vocabularies[] = $dcat_controlled_vocabulary_entry;
        }

        return $this->stripDuplicates($dcat_controlled_vocabularies);
    }
}
