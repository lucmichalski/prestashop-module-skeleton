<?php

namespace MyModule\Controller;

use PrestaShop\PrestaShop\Core\Search\Filters\CmsPageCategoryFilters;
use PrestaShop\PrestaShop\Core\Search\Filters\CmsPageFilters;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Controller\Admin\Improve\Design\CmsPageController;
use Symfony\Component\HttpFoundation\Request;

class AdminCmsController extends FrameworkBundleAdminController
{
    /**
     * @var CmsPageController
     */
    private $decoratedController;

    public function __construct(CmsPageController $decoratedController)
    {
        $this->decoratedController = $decoratedController;
    }

    public function indexAction(CmsPageCategoryFilters $categoryFilters, CmsPageFilters $cmsFilters, Request $request)
    {
        return $this->decoratedController->indexAction($categoryFilters, $cmsFilters, $request);
    }
}