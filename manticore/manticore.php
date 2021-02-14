<?php
# /modules/manticore/manticore.php

/**
 * Manticore - A Prestashop Module
 * 
 * Manticore
 * 
 * @author Luc Michalski <lmichalski@evolutive-business.com>
 * @version 0.0.1
 */

declare(strict_types=1);

use PrestaShop\Module\Manitcore\Install\Installer;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnInterface;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Exception\ColumnNotFoundException;

if ( !defined('_PS_VERSION_') ) exit;

class Manticore extends Module
{
	public function __construct()
	{
		$this->initializeModule();
	}

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $installer = new Installer();

        return $installer->install($this);
    }

	public function uninstall()
	{
		return
			parent::uninstall()
		;
	}
	
	/** Module configuration page */
	public function getContent()
	{
		return 'Manticore configuration page !';
	}

	/** Initialize the module declaration */
	private function initializeModule()
	{
		$this->name = 'manticore';
		$this->tab = 'others';
		$this->version = '0.0.1';
		$this->author = 'Luc Michalski';
		$this->need_instance = 1;
		$this->ps_versions_compliancy = [
			'min' => '1.6',
			'max' => _PS_VERSION_,
		];
		$this->bootstrap = true;
		
		parent::__construct();

		$this->displayName = $this->l('Manticore');
		$this->description = $this->l('Manticore');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall this module ?');
	}

    /**
     * @param array $params
     */
    public function hookActionOrderGridDefinitionModifier(array $params): void
    {
        /** @var GridDefinitionInterface $orderGridDefinition */
        $orderGridDefinition = $params['definition'];

        /** @var RowActionCollectionInterface $actionsCollection */
        $actionsCollection = $this->getActionsColumn($orderGridDefinition)->getOption('actions');
        $actionsCollection->add(
            // mark order is just an example of some custom action
            (new SubmitRowAction('mark_order'))
                ->setName($this->trans('Mark', [], 'Admin.Actions'))
                ->setIcon('push_pin')
                ->setOptions([
                    'route' => 'demo_admin_orders_mark_order',
                    'route_param_name' => 'orderId',
                    'route_param_field' => 'id_order',
                    // use this if you want to show the action inline instead of adding it to dropdown
                    'use_inline_display' => true,
                ])
        );
        //@todo: actually button is not working yet, because javascript part is missing (SubmitRowActionExtension)
    }

    private function getActionsColumn(GridDefinitionInterface $gridDefinition): ColumnInterface
    {
        try {
            return $gridDefinition->getColumnById('actions');
        } catch (ColumnNotFoundException $e) {
            // It is possible that not every grid will have actions column.
            // In this case you can create a new column or throw exception depending on your needs
            throw $e;
        }
    }
    
}
