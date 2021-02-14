<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\Manticore\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Controller\Admin\Sell\Order\OrderController;
use PrestaShop\PrestaShop\Core\Search\Filters\OrderFilters;
use Symfony\Component\HttpFoundation\Request;
use PrestaShopBundle\Service\Grid\ResponseBuilder;

class AdminOrderController extends FrameworkBundleAdminController
{
    /**
     * @var CmsPageController
     */
    private $decoratedController;

    public function __construct(OrderController $decoratedController)
    {
        $this->decoratedController = $decoratedController;
    }

    public function indexAction(Request $request, OrderFilters $filters)
    {
        return $this->decoratedController->indexAction($request, $filters);
    }    

}