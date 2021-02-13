<?php
/**
 * NOTICE OF LICENSE
 *
 * @author    Evolutive Group, evolutive-group.com <lmichalski@evolutive-business.com>
 * @copyright Copyright (c) permanent, Evolutive Group 2021
 * @license   MIT
 * @see       /LICENSE
 *
 */

namespace Evolutive\Manticore\Install;

/**
 * Class Tab - module admin tab settings
 */
class Tab
{
    /**
     * @var string info controller name
     */
    private $controllerInfo = 'AdminManticoreInfo';

    /**
     * @var array
     */
    private $configuration;

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return string
     */
    public function getControllerInfo()
    {
        return $this->controllerInfo;
    }

    /**
     * @return array
     */
    public function getTabs()
    {
        return isset($this->configuration['tabs']) ? $this->configuration['tabs'] : [];
    }
}
