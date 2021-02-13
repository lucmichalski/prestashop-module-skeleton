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

namespace Evolutive\Manticore\Controller;

/**
 * Class AdminController - an abstraction for all admin controllers
 */
class AdminController extends \ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }
}
