<?php

namespace DonlSync\Catalog\Source\NGR\BuildRule;

use DCAT_AP_DONL\DCATControlledVocabularyEntry;
use DCAT_AP_DONL\DCATLiteral;
use DCAT_AP_DONL\DCATSpatial;
use DonlSync\Dataset\Builder\BuildRule\AbstractDCATEntityBuildRule;
use DonlSync\Dataset\Builder\BuildRule\IDCATEntityBuildRule;
use Exception;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

/**
 * Class NGRSpatialBuildRule.
 *
 * Creates valid DCATSpatial instances from harvested lat/long coordinates.
 */
class NGRSpatialBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * The URI representing the EPSG:28992 CRS in DCAT-AP-DONL.
     */
    public const EPSG_28992_URI = 'http://standaarden.overheid.nl/owms/4.0/doc/syntax-codeerschemas/overheid.epsg28992';

    /**
     * The vocabulary to use for creating EPSG:28992 DCAT Spatial instances.
     */
    private DCATControlledVocabularyEntry $vocabulary;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $property, string $prefix = 'Dataset')
    {
        parent::__construct($property, $prefix);

        $this->vocabulary = new DCATControlledVocabularyEntry(
            self::EPSG_28992_URI,
            'Overheid:SpatialScheme'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATSpatial|null The created DCATSpatial
     */
    public function build(array &$data, array &$notices): ?DCATSpatial
    {
        // single spatial builder not supported (yet?).

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATSpatial[] The created DCATSpatials
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        $spatial_instances     = [];
        $processed_coordinates = [];

        if (empty($data['coordinates'])) {
            return $spatial_instances;
        }

        if (!is_array($data['coordinates'])) {
            return $spatial_instances;
        }

        foreach ($data['coordinates'] as $lat_long) {
            if (!is_array($lat_long)) {
                continue;
            }

            if (count($lat_long) < 2) {
                continue;
            }

            $coordinate = $this->convertCoordinate($lat_long[0], $lat_long[1]);

            if (null === $coordinate) {
                continue;
            }

            if (in_array($coordinate, $processed_coordinates)) {
                // Can't allow duplicate coordinates.
                continue;
            }

            if (false !== mb_strpos($coordinate, '-')) {
                continue;
            }

            $processed_coordinates[] = $coordinate;

            $spatial = new DCATSpatial();
            $spatial->setScheme($this->vocabulary);
            $spatial->setValue(new DCATLiteral($coordinate));

            $notices[] = sprintf('%s: %s: converted coordinate %s added to dataset',
                $this->prefix, ucfirst($this->property), $coordinate
            );

            $spatial_instances[] = $spatial;
        }

        return $spatial_instances;
    }

    /**
     * Transforms the harvested lat/long coordinate into a coordinate that matches the EPSG:28992
     * coordinate reference system.
     *
     * @param string $x The X-coordinate
     * @param string $y The Y-coordinate
     *
     * @return string|null The converted coordinate, or null if the conversion failed
     */
    private function convertCoordinate(string $x, string $y): ?string
    {
        $projector = new Proj4php();
        $projector::setDebug(false);
        $lat_long_projector = new Proj('WGS84', $projector);
        $rdn_projector      = new Proj('EPSG:28992', $projector);

        try {
            $wgs84_point     = new Point(floatval($x), floatval($y), $lat_long_projector);
            $rdn_point       = $projector->transform($rdn_projector, $wgs84_point);
            $rnd_split_point = explode(' ', $rdn_point->toShortString());

            if (2 !== count($rnd_split_point)) {
                return null;
            }

            $point_x = $this->transformToEPSG28992Format(trim($rnd_split_point[0]));
            $point_y = $this->transformToEPSG28992Format(trim($rnd_split_point[1]));

            if (empty($point_x) || empty($point_y)) {
                return null;
            }

            return sprintf('%s %s', $point_x, $point_y);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Transforms a raw EPSG:28992 coordinate point to ensure that it matches the "Syntax Codeer
     * Schema" overheid:EPSG28992, which is part of OWMS 4.0.
     *
     * @param string $point The raw EPSG:28992 point to transform
     *
     * @return string|null The transformed coordinate, or null on any error
     *
     * @see https://standaarden.overheid.nl/owms/4.0/doc/syntax-codeerschemas/overheid.epsg28992
     */
    private function transformToEPSG28992Format(string $point): ?string
    {
        $parts = explode('.', $point);

        if (empty($parts) || count($parts) > 2) {
            return null;
        }

        $length     = mb_strlen($parts[0]);
        $difference = 6 - $length;

        if ($difference < 0) {
            return null;
        }

        $parts[0] = str_repeat('0', $difference) . $parts[0];

        if (2 === count($parts)) {
            if (mb_strlen($parts[1]) > 3) {
                $parts[1] = mb_substr($parts[1], 0, 3);
            }

            return $parts[0] . '.' . $parts[1];
        }

        return $parts[0];
    }
}
