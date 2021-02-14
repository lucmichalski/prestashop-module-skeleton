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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use PrestaShopBundle\Routing\Converter;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Controller\Admin\ProductController;
use PrestaShopBundle\Form\Admin\Product\ProductCategories;
use PrestaShopBundle\Security\Voter\PageVoter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminProductController extends FrameworkBundleAdminController
{

    /**
     * Used to validate connected user authorizations.
     */
    public const PRODUCT_OBJECT = 'ADMINPRODUCTS_';

    /**
     * @var AdminProductController
     */
    private $decoratedController;

    public function __construct(ProductController $decoratedController)
    {
        $this->decoratedController = $decoratedController;
    }

    /**
     * Get the Catalog page with KPI banner, product list, bulk actions, filters, search, etc...
     *
     * URL example: /product/catalog/40/20/id_product/asc
     *
     * @Template("@PrestaShop/Admin/Product/CatalogPage/catalog.html.twig")
     *
     * @param Request $request
     * @param int $limit The size of the listing
     * @param int $offset The offset of the listing
     * @param string $orderBy To order product list
     * @param string $sortOrder To order product list
     *
     * @return array|Template|RedirectResponse|Response
     *
     * @throws \Symfony\Component\Translation\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \LogicException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     * @throws \Symfony\Component\Form\Exception\LogicException
     * @throws \Symfony\Component\Form\Exception\AlreadySubmittedException
     */
    public function catalogAction(
        Request $request,
        $limit = 10,
        $offset = 0,
        $orderBy = 'id_product',
        $sortOrder = 'desc'
    )
    {
        return $this->decoratedController->catalogAction($request, $limit, $offset, $orderBy, $sortOrder);
    }    

    public function catalogAction2(
        Request $request,
        $limit = 10,
        $offset = 0,
        $orderBy = 'id_product',
        $sortOrder = 'desc'
    )
    {
        if (!$this->decoratedController->isGranted([PageVoter::READ, PageVoter::UPDATE, PageVoter::CREATE], $this->decoratedController::PRODUCT_OBJECT)) {
            return $this->decoratedController->redirect('admin_dashboard');
        }

        // override with sphinxql

        $language = $this->decoratedController->getContext()->language;
        $request->getSession()->set('_locale', $language->locale);
        $request = $this->decoratedController->get('prestashop.adapter.product.filter_categories_request_purifier')->purify($request);

        /** @var ProductInterfaceProvider $productProvider */
        $productProvider = $this->decoratedController->get('prestashop.core.admin.data_provider.product_interface');

        // Set values from persistence and replace in the request
        $persistedFilterParameters = $productProvider->getPersistedFilterParameters();
        /** @var ListParametersUpdater $listParametersUpdater */
        $listParametersUpdater = $this->decoratedController->get('prestashop.adapter.product.list_parameters_updater');
        $listParameters = $listParametersUpdater->buildListParameters(
            $request->query->all(),
            $persistedFilterParameters,
            compact('offset', 'limit', 'orderBy', 'sortOrder')
        );
        $offset = $listParameters['offset'];
        $limit = $listParameters['limit'];
        $orderBy = $listParameters['orderBy'];
        $sortOrder = $listParameters['sortOrder'];

        //The product provider performs the same merge internally, so we do the same so that the displayed filters are
        //consistent with the request ones
        $combinedFilterParameters = array_replace($persistedFilterParameters, $request->request->all());

        $toolbarButtons = $this->getToolbarButtons();

        // Fetch product list (and cache it into view subcall to listAction)
        $products = $productProvider->getCatalogProductList(
            $offset,
            $limit,
            $orderBy,
            $sortOrder,
            $request->request->all()
        );
        $lastSql = $productProvider->getLastCompiledSql();

        $hasCategoryFilter = $productProvider->isCategoryFiltered();
        $hasColumnFilter = $productProvider->isColumnFiltered();
        $totalFilteredProductCount = (count($products) > 0) ? $products[0]['total'] : 0;
        // Alternative layout for empty list
        if ((!$hasCategoryFilter && !$hasColumnFilter && $totalFilteredProductCount === 0)
            || ($totalProductCount = $productProvider->countAllProducts()) === 0
        ) {
            // no filter, total filtered == 0, and then total count == 0 too.
            $legacyUrlGenerator = $this->decoratedController->get('prestashop.core.admin.url_generator_legacy');

            return $this->decoratedController->render(
                '@PrestaShop/Admin/Product/CatalogPage/catalog_empty.html.twig',
                [
                    'layoutHeaderToolbarBtn' => $toolbarButtons,
                    'import_url' => $legacyUrlGenerator->generate('AdminImport'),
                ]
            );
        }

        // Pagination
        $paginationParameters = $request->attributes->all();
        $paginationParameters['_route'] = 'admin_product_catalog';
        $categoriesForm = $this->decoratedController->createForm(ProductCategories::class);
        if (!empty($persistedFilterParameters['filter_category'])) {
            $categoriesForm->setData(
                [
                    'categories' => [
                        'tree' => [0 => $combinedFilterParameters['filter_category']],
                    ],
                ]
            );
        }

        $cleanFilterParameters = $listParametersUpdater->cleanFiltersForPositionOrdering(
            $combinedFilterParameters,
            $orderBy,
            $hasCategoryFilter
        );

        $permissionError = null;
        if ($this->decoratedController->get('session')->getFlashBag()->has('permission_error')) {
            $permissionError = $this->decoratedController->get('session')->getFlashBag()->get('permission_error')[0];
        }

        $categoriesFormView = $categoriesForm->createView();
        $selectedCategory = !empty($combinedFilterParameters['filter_category']) ? new Category($combinedFilterParameters['filter_category']) : null;

        //Drag and drop is ONLY activated when EXPLICITLY requested by the user
        //Meaning a category is selected and the user clicks on REORDER button
        $activateDragAndDrop = 'position_ordering' === $orderBy && $hasCategoryFilter;

        //
        // $output = $this->decoratedController->indexAction($categoryFilters, $cmsFilters, $request);
        // $myService = $this->getMyPaymentService();
        // $output = $this->injectMyData($myService, $output);

        // return $output;

        // Template vars injection
        return array_merge(
            $cleanFilterParameters,
            [
                'limit' => $limit,
                'offset' => $offset,
                'orderBy' => $orderBy,
                'sortOrder' => $sortOrder,
                'has_filter' => $hasCategoryFilter || $hasColumnFilter,
                'has_category_filter' => $hasCategoryFilter,
                'selected_category' => $selectedCategory,
                'has_column_filter' => $hasColumnFilter,
                'products' => $products,
                'last_sql' => $lastSql,
                'product_count_filtered' => $totalFilteredProductCount,
                'product_count' => $totalProductCount,
                'activate_drag_and_drop' => $activateDragAndDrop,
                'pagination_parameters' => $paginationParameters,
                'layoutHeaderToolbarBtn' => $toolbarButtons,
                'categories' => $categoriesFormView,
                'pagination_limit_choices' => $productProvider->getPaginationLimitChoices(),
                'import_link' => $this->decoratedController->generateUrl('admin_import', ['import_type' => 'products']),
                'sql_manager_add_link' => $this->decoratedController->generateUrl('admin_sql_requests_create'),
                'enableSidebar' => true,
                'help_link' => $this->decoratedController->generateSidebarLink('AdminProducts'),
                'is_shop_context' => $this->decoratedController->get('prestashop.adapter.shop.context')->isShopContext(),
                'permission_error' => $permissionError,
                'layoutTitle' => $this->decoratedController->trans('Products', 'Admin.Global'),
            ]
        );
    }

    /**
     * Get only the list of products to display on the main Admin Product page.
     * The full page that shows products list will subcall this action (from catalogAction).
     * URL example: /product/list/html/40/20/id_product/asc.
     *
     * @Template("@PrestaShop/Admin/Product/CatalogPage/Lists/list.html.twig")
     *
     * @param Request $request
     * @param int $limit The size of the listing
     * @param int $offset The offset of the listing
     * @param string $orderBy To order product list
     * @param string $sortOrder To order product list
     * @param string $view full|quicknav To change default template used to render the content
     *
     * @return array|Template|Response
     */
    public function listAction(
        Request $request,
        $limit = 10,
        $offset = 0,
        $orderBy = 'id_product',
        $sortOrder = 'asc',
        $view = 'full'
    ) {
        if (!$this->isGranted([PageVoter::READ], self::PRODUCT_OBJECT)) {
            return $this->redirect('admin_dashboard');
        }

        /** @var ProductInterfaceProvider $productProvider */
        $productProvider = $this->decoratedController->get('prestashop.core.admin.data_provider.product_interface');
        $adminProductWrapper = $this->decoratedController->get('prestashop.adapter.admin.wrapper.product');
        $totalCount = 0;

        $this->decoratedController->get('prestashop.service.product')->cleanupOldTempProducts();

        $products = $request->attributes->get('products', null); // get from action subcall data, if any
        $lastSql = $request->attributes->get('last_sql', null); // get from action subcall data, if any

        if ($products === null) {
            // get old values from persistence (before the current update)
            $persistedFilterParameters = $productProvider->getPersistedFilterParameters();
            /** @var ListParametersUpdater $listParametersUpdater */
            $listParametersUpdater = $this->decoratedController->get('prestashop.adapter.product.list_parameters_updater');
            $listParameters = $listParametersUpdater->buildListParameters(
                $request->query->all(),
                $persistedFilterParameters,
                compact('offset', 'limit', 'orderBy', 'sortOrder')
            );
            $offset = $listParameters['offset'];
            $limit = $listParameters['limit'];
            $orderBy = $listParameters['orderBy'];
            $sortOrder = $listParameters['sortOrder'];

            /**
             * 2 hooks are triggered here:
             * - actionAdminProductsListingFieldsModifier
             * - actionAdminProductsListingResultsModifier.
             */
            $products = $productProvider->getCatalogProductList($offset, $limit, $orderBy, $sortOrder);
            $lastSql = $productProvider->getLastCompiledSql();
        }

        $hasCategoryFilter = $productProvider->isCategoryFiltered();

        // Adds controller info (URLs, etc...) to product list
        foreach ($products as &$product) {
            $totalCount = isset($product['total']) ? $product['total'] : $totalCount;
            $product['url'] = $this->decoratedController->generateUrl(
                'admin_product_form',
                ['id' => $product['id_product']]
            );
            $product['unit_action_url'] = $this->decoratedController->generateUrl(
                'admin_product_unit_action',
                [
                    'action' => 'duplicate',
                    'id' => $product['id_product'],
                ]
            );
            $product['preview_url'] = $adminProductWrapper->getPreviewUrlFromId($product['id_product']);
        }

        //Drag and drop is ONLY activated when EXPLICITLY requested by the user
        //Meaning a category is selected and the user clicks on REORDER button
        $activateDragAndDrop = 'position_ordering' === $orderBy && $hasCategoryFilter;

        // Template vars injection
        $vars = [
            'activate_drag_and_drop' => $activateDragAndDrop,
            'products' => $products,
            'product_count' => $totalCount,
            'last_sql_query' => $lastSql,
            'has_category_filter' => $productProvider->isCategoryFiltered(),
            'is_shop_context' => $this->get('prestashop.adapter.shop.context')->isShopContext(),
        ];
        if ($view !== 'full') {
            return $this->decoratedController->render(
                '@Product/CatalogPage/Lists/list_' . $view . '.html.twig',
                array_merge(
                    $vars,
                    [
                        'limit' => $limit,
                        'offset' => $offset,
                        'total' => $totalCount,
                    ]
                )
            );
        }

        return $vars;
    }

    /**
     * Gets the header toolbar buttons.
     *
     * @return array
     */
    public function getToolbarButtons()
    {
        $toolbarButtons = [];
        // $toolbarButtons['add'] = [
        //     'href' => $this->generateUrl('admin_product_new'),
        //     'desc' => $this->trans('New product', 'Admin.Actions'),
        //     'icon' => 'add_circle_outline',
        //     'help' => $this->trans('Create a new product: CTRL+P', 'Admin.Catalog.Help'),
        // ];
        // $toolbarButtons['add_v2'] = [
        //     'href' => $this->generateUrl('admin_products_v2_create'),
        //     'desc' => $this->trans('New product v2', 'Admin.Actions'),
        //     'icon' => 'add_circle_outline',
        // ];

        return $toolbarButtons;
    }

}