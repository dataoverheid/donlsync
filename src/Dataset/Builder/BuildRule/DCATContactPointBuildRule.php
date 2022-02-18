<?php

namespace DonlSync\Dataset\Builder\BuildRule;

use DCAT_AP_DONL\DCATContactPoint;

/**
 * Class DCATContactPointBuildRule.
 *
 * Responsible for constructing a `\DCAT_AP_DONL\DCATContactPoint` object.
 *
 * @see \DCAT_AP_DONL\DCATContactPoint
 */
class DCATContactPointBuildRule extends AbstractDCATEntityBuildRule implements IDCATEntityBuildRule
{
    /**
     * {@inheritdoc}
     *
     * @return DCATContactPoint|null The created DCATContactPoint
     */
    public function build(array &$data, array &$notices): ?DCATContactPoint
    {
        $name    = ($this->createLiteralBuildRule('contact_point_name'))->build($data, $notices);
        $address = ($this->createLiteralBuildRule('contact_point_address'))->build($data, $notices);
        $title   = ($this->createLiteralBuildRule('contact_point_title'))->build($data, $notices);
        $email   = ($this->createLiteralBuildRule('contact_point_email'))->build($data, $notices);
        $webpage = ($this->createURIBuildRule('contact_point_webpage'))->build($data, $notices);
        $phone   = ($this->createLiteralBuildRule('contact_point_phone'))->build($data, $notices);

        if (!$name && !$address && !$title && !$email && !$webpage && !$phone) {
            return null;
        }

        $dcat_contact_point = new DCATContactPoint();

        if ($name) {
            $dcat_contact_point->setFullName($name);
        }

        if ($address) {
            $dcat_contact_point->setAddress($address);
        }

        if ($title) {
            $dcat_contact_point->setTitle($title);
        }

        if ($email) {
            $dcat_contact_point->setEmail($email);
        }

        if ($webpage) {
            $dcat_contact_point->setWebpage($webpage);
        }

        if ($phone) {
            $dcat_contact_point->setPhone($phone);
        }

        if (!$dcat_contact_point->validate()->validated()) {
            foreach ($dcat_contact_point->validate()->getMessages() as $message) {
                $notices[] = sprintf('%s: %s: %s',
                    $this->prefix, ucfirst($this->property), $message
                );
            }

            $notices[] = sprintf('%s: %s: value is not valid, discarding',
                $this->prefix, ucfirst($this->property)
            );

            return null;
        }

        return $dcat_contact_point;
    }

    /**
     * {@inheritdoc}
     *
     * @return DCATContactPoint[] The created DCATContactPoints
     */
    public function buildMultiple(array &$data, array &$notices): array
    {
        // multiple contactPoint builder not supported (yet?).

        return [];
    }
}
