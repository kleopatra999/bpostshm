<?php
/**
 * 2014 Stigmi
 *
 * @author    Stigmi <www.stigmi.eu>
 * @copyright 2014 Stigmi
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit();

require_once(_PS_MODULE_DIR_.'bpostshm/bpostshm.php');
require_once(_PS_MODULE_DIR_.'bpostshm/classes/Service.php');

class AdminBpostOrdersController extends ModuleAdminController
{
	protected $identifier = 'reference';

	public $statuses = array(
		'OPEN',
		'PENDING',
		'CANCELLED',
		'COMPLETED',
		'ON-HOLD',
		'PRINTED',
	);

	private $tracking_url = 'http://track.bpost.be/etr/light/performSearch.do';
	private $tracking_params = array(
		'searchByCustomerReference' => true,
		'oss_language' => '',
		'customerReference' => '',
	);

	public function __construct()
	{
		$this->table = 'order_label';
		$this->lang = false;
		$this->explicitSelect = true;
		$this->deleted = false;
		$this->context = Context::getContext();

		$this->module = new BpostShm();
		$this->service = Service::getInstance($this->context);

		$this->actions = array(
			'addLabel',
			'printLabels',
			'markTreated',
			'sendTTEmail',
			'createRetour',
			'view',
			'cancel',
		);

		$this->bulk_actions = array(
			'markTreated' => array('text' => $this->l('Mark treated'), 'confirm' => $this->l('Mark order as treated?')),
			'printLabels' => array('text' => $this->l('Print labels')),
			'sendTTEmail' => array('text' => $this->l('Send T&T e-mail'), 'confirm' => $this->l('Send track & trace email to sender?')),
		);

		$this->delivery_methods_list = array(
			(int)Configuration::get('BPOST_SHIP_METHOD_'.BpostShm::SHIPPING_METHOD_AT_HOME.'_ID_CARRIER_'
					.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id))
				=> $this->module->shipping_methods[BpostShm::SHIPPING_METHOD_AT_HOME]['slug'],
			(int)Configuration::get('BPOST_SHIP_METHOD_'.BpostShm::SHIPPING_METHOD_AT_SHOP.'_ID_CARRIER_'
					.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id))
				=> $this->module->shipping_methods[BpostShm::SHIPPING_METHOD_AT_SHOP]['slug'],
			(int)Configuration::get('BPOST_SHIP_METHOD_'.BpostShm::SHIPPING_METHOD_AT_24_7.'_ID_CARRIER_'
					.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id))
				=> $this->module->shipping_methods[BpostShm::SHIPPING_METHOD_AT_24_7]['slug'],
		);

		$this->_select = '
		a.`reference` as print,
		a.`reference` as recipient,
		a.`reference` as t_t,
		COUNT(a.`reference`) as count
		';

		$this->_join = '
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (o.`reference` = SUBSTRING(a.`reference`, 8))
		LEFT JOIN `'._DB_PREFIX_.'order_carrier` oc ON (oc.`id_order` = o.`id_order`)
		';

		$this->_where = '
		AND a.status IN("'.implode('", "', $this->statuses).'")
		AND o.current_state IN(2, 12, 22, '
			.(int)Configuration::get('BPOST_ORDER_STATE_TREATED_'.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id)).')
		AND DATEDIFF(NOW(), a.date_add) <= 14
		';

		$id_bpost_carriers = array_keys($this->delivery_methods_list);
		if ($references = Db::getInstance()->executeS('
			SELECT id_reference FROM `'._DB_PREFIX_.'carrier` WHERE id_carrier IN ('.implode(', ', $id_bpost_carriers).')'))
		{
			foreach ($references as $reference)
				$id_bpost_carriers[] = (int)$reference['id_reference'];
		}
		$this->_where .= '
		AND oc.id_carrier IN ("'.implode('", "', $id_bpost_carriers).'")';

		$this->_group = 'GROUP BY(a.`reference`)';
		$this->_orderBy = 'o.id_order';
		$this->_orderWay = 'DESC';

		$this->fields_list = array(
		'print' => array(
			'title' => '',
			'align' => 'center',
			'width'	=> 30,
			'callback' => 'getPrintIcon',
			'search' => false,
			'orderby' => false,
		),
		't_t' => array(
			'title' => '',
			'align' => 'center',
			'width'	=> 30,
			'callback' => 'getTTIcon',
			'search' => false,
			'orderby' => false,
		),
		/*'id_order' => array(
			'title' => $this->l('Order n°'),
			'align' => 'center',
			'width' => 45,
			'filter_key' => 'o!id_order',
		),*/
		'reference' => array(
			'title' => $this->l('Reference'),
			'align' => 'left',
			'width' => 25,
			'filter_key' => 'a!reference',
		),
		'shipping_method' => array(
			'title' => $this->l('Delivery method'),
			'width' => 150,
			'type' => 'select',
			'list' => $this->delivery_methods_list,
			'filter_key' => 'oc!id_carrier',
			'callback' => 'getOrderShippingMethod',
			'search' => false,
		),
		'recipient' => array(
			'title' => $this->l('Recipient'),
			'width' => 450,
			'callback' => 'getOrderRecipient',
			'search' => false,
		),
		'status' => array(
			'title' => $this->l('Status'),
			'width' => 100,
		),
		'date_add' => array(
			'title' => $this->l('Creation date'),
			'width' => 130,
			'align' => 'right',
			'type' => 'datetime',
			'filter_key' => 'a!date_add'
		),
		'count' => array(
			'title' => $this->l('Labels'),
			'width' => 100,
			'align' => 'center',
			'callback' => 'getLabelsCount',
			'search' => false,
			'orderby' => false,
		),
		'current_state' => array(
			'title' => $this->l('Order state'),
			'width' => 100,
			'align' => 'center',
			'class' => 'order_state',
		)
		);

		$this->shopLinkType = 'shop';
		$this->shopShareDatas = Shop::SHARE_ORDER;

		parent::__construct();
	}


	public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
	{
		parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

		if (!Tools::getValue($this->list_id.'_pagination'))
			$this->context->cookie->{$this->list_id.'_pagination'} = 50;
	}

	public function initContent()
	{
		if (!$this->viewAccess())
		{
			$this->errors[] = Tools::displayError('You do not have permission to view this.');
			return;
		}

		$this->getLanguages();
		$this->initToolbar();
		$this->initTabModuleList();

		if ($this->display == 'view')
		{
			// Some controllers use the view action without an object
			if ($this->className)
				$this->loadObject(true);
			$this->content .= $this->renderView();
		}
		else
			parent::initContent();

		$this->addJqueryPlugin(array('idTabs'));
		$this->context->smarty->assign('content', $this->content);
	}

	public function initProcess()
	{
		parent::initProcess();

		if (empty($this->errors))
		{
			$reference 	= (string)Tools::getValue('reference');

			if (Tools::getIsset('addLabel'.$this->table))
			{
				$order = $this->service->bpost->fetchOrder($reference);
				$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
				$id_carrier = $this->getOrderShippingMethod($ps_order->id_carrier, false);

				switch ($id_carrier)
				{
					case (int)Configuration::get('BPOST_SHIP_METHOD_'.BpostShm::SHIPPING_METHOD_AT_HOME.'_ID_CARRIER_'.$this->context->shop->id):
					default:
						$type = BpostShm::SHIPPING_METHOD_AT_HOME;
						break;
					case (int)Configuration::get('BPOST_SHIP_METHOD_'.BpostShm::SHIPPING_METHOD_AT_SHOP.'_ID_CARRIER_'.$this->context->shop->id):
						$type = BpostShm::SHIPPING_METHOD_AT_SHOP;
						break;
					case (int)Configuration::get('BPOST_SHIP_METHOD_'.BpostShm::SHIPPING_METHOD_AT_24_7.'_ID_CARRIER_'.$this->context->shop->id):
						$type = BpostShm::SHIPPING_METHOD_AT_24_7;
						break;
				}

				$boxes = $order->getBoxes();
				$box = $boxes[0];
				$cart = new Cart((int)$ps_order->id_cart);
				if ($national_box = $box->getNationalBox())
				{
					if (method_exists($national_box, 'getReceiver'))
						$receiver = $national_box->getReceiver();
					elseif (method_exists($national_box, 'getPugoAddress'))
					{
						$receiver = new TijsVerkoyenBpostBpostOrderReceiver();
						$pugo_address = $box->getNationalBox()->getPugoAddress();
						$receiver->setAddress($pugo_address);
						$receiver->setName($national_box->getReceiverName());
						if ($company = $national_box->getReceiverCompany())
							if (!empty($company))
								$receiver->setCompany($company);
					}
				}
				elseif ($international_box = $box->getInternationalBox())
					$receiver = $international_box->getReceiver();

				// Remove existing boxes so that they won't get duplicated
				$order->setBoxes(array());
				$response = $this->service->addBox($order, (int)$type, $box->getSender(), $receiver, 0, $cart->service_point_id);
				$response = $response && $this->service->bpost->createOrReplaceOrder($order);
				$response = $response && $this->service->createPSLabel($order->getReference());

				$this->jsonEncode($response);
			}
			elseif (Tools::getIsset('printLabels'.$this->table))
				$this->jsonEncode(array('links' => $this->printLabels($reference)));
			elseif (Tools::getIsset('markTreated'.$this->table))
				$this->jsonEncode($this->markOrderTreated($reference));
			elseif (Tools::getIsset('sendTTEmail'.$this->table))
			{
				$response = $this->sendTTEmail($reference);

				if (!empty($this->errors))
					$response = array('errors' => $this->errors);

				$this->jsonEncode($response);
			}
			elseif (Tools::getIsset('createRetour'.$this->table))
			{
				$response = array(
					'errors' => array(),
					'links' => array(),
				);

				$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();

				foreach (array_keys($this->module->shipping_methods) as $shipping_method)
					if ((int)$ps_order->id_carrier == (int)Configuration::get('BPOST_SHIP_METHOD_'.$shipping_method.'_ID_CARRIER_'
						.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id)))
					{
						if ($this->service->makeOrder($ps_order->id, $shipping_method, true))
						{
							$context_shop_id = (isset($this->context->shop) && !is_null($this->context->shop->id) ? $this->context->shop->id : 1);

							if ($labels = $this->service->createLabelForOrder(
								$reference,
								Configuration::get('BPOST_LABEL_PDF_FORMAT_'.$context_shop_id),
								(bool)Configuration::get('BPOST_LABEL_RETOUR_LABEL_'.$context_shop_id)))
							{
								$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf';
								$i = 1;

								if (!is_dir($pdf_dir))
									mkdir($pdf_dir, 0755);

								$pdf_dir .= '/'.$reference;
								if (!is_dir($pdf_dir))
									mkdir($pdf_dir, 0755);

								$files = scandir($pdf_dir);

								if (!empty($files) && is_array($files))
									foreach ($files as $file)
										if (!in_array($file, array('.', '..')) && !is_dir($pdf_dir.'/'.$file))
										{
											$response['links'][] = _MODULE_DIR_.'bpostshm/pdf/'.$reference.'/retours/'.$i.'.pdf';
											$i++;
										}

								foreach ($labels as $label)
								{
									$this->service->updatePSLabelBarcode($reference, $label->getBarcode());

									$file = $pdf_dir.'/'.$i.'.pdf';
									$fp = fopen($file, 'w');
									fwrite($fp, $label->getBytes());
									fclose($fp);

									$response['links'][] = _MODULE_DIR_.'bpostshm/pdf/'.$reference.'/retours/'.$i.'.pdf';
									$i++;
								}

								$this->service->updatePSLabelStatus($reference, 'PRINTED');
							}
						}

						break;
					}

				$this->jsonEncode($response);
			}
			elseif (Tools::getIsset('view'.$this->table))
			{
				$order = $this->service->bpost->fetchOrder($reference);
				$boxes = $order->getBoxes();

				$this->context->smarty->assign('boxes', array_reverse($boxes));
				$this->context->smarty->assign('order', $order);
				$this->context->smarty->assign('url_get_label', Tools::safeOutput(self::$currentIndex.'&reference='.$reference
					.'&printLabels'.$this->table.'&token='.$this->token));

			}
			elseif (Tools::getIsset('cancel'.$this->table))
			{
				$errors = array();
				$response = true;

				$id_order_state = null;
				$order_states = OrderState::getOrderStates($this->context->language->id);
				foreach ($order_states as $order_state)
					if ('order_canceled' == $order_state['template'])
					{
						$id_order_state = $order_state['id_order_state'];
						break;
					}

				if (is_null($id_order_state))
					$errors[] = Tools::displayError('Please create a "Cancel" order state using "order_canceled" template.');

				$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
				if (is_dir($pdf_dir) && opendir($pdf_dir))
					$errors[] = Tools::displayError('The order has one or more barcodes linked in the bpost Shipping Manager.');

				if (!empty($errors))
					$this->jsonEncode(array('errors' => $errors));

				$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
				$ps_order->current_state = (int)$id_order_state;
				$response = $response && $ps_order->save();

				// Create new OrderHistory
				$history = new OrderHistory();
				$history->id_order = $ps_order->id;
				$history->id_employee = (int)$this->context->employee->id;

				$use_existings_payment = false;
				if (!$ps_order->hasInvoice())
					$use_existings_payment = true;
				$history->changeIdOrderState((int)$id_order_state, $ps_order, $use_existings_payment);

				$carrier = new Carrier($ps_order->id_carrier, $ps_order->id_lang);
				$template_vars = array();
				if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $ps_order->shipping_number)
					$template_vars = array('{followup}' => str_replace('@', $ps_order->shipping_number, $carrier->url));
				// Save all changes
				$response = $response && $history->addWithemail(true, $template_vars);

				$response = $response && $this->service->updateOrderStatus($reference, 'CANCELLED');
				$this->jsonEncode($response);

			}
		}
	}

	/**
	 * Function used to render the list to display for this controller
	 */
	public function renderList()
	{
		if (!($this->fields_list && is_array($this->fields_list)))
			return false;
		$this->getList($this->context->language->id);

		$helper = new HelperList();
		$helper->module = new BpostShm();

		// Empty list is ok
		if (!is_array($this->_list))
		{
			$this->displayWarning($this->l('Bad SQL query', 'Helper').'<br />'.htmlspecialchars($this->_list_error));
			return false;
		}

		$this->tpl_list_vars = array_merge(
			$this->tpl_list_vars,
			array('treated_status' =>
				Configuration::get('BPOST_ORDER_STATE_TREATED_'.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id)))
		);

		$this->setHelperDisplay($helper);
		$helper->tpl_vars = $this->tpl_list_vars;
		$helper->tpl_delete_link_vars = $this->tpl_delete_link_vars;

		// For compatibility reasons, we have to check standard actions in class attributes
		foreach ($this->actions_available as $action)
			if (!in_array($action, $this->actions) && isset($this->$action) && $this->$action)
				$this->actions[] = $action;
		$helper->is_cms = $this->is_cms;
		$list = $helper->generateList($this->_list, $this->fields_list);

		return $list;
	}

	public function processbulkmarktreated()
	{
		$response = true;

		if (empty($this->boxes) || !is_array($this->boxes))
			$response = false;
		else
			foreach ($this->boxes as $reference)
				$response &= $response && $this->markOrderTreated($reference);

		return $response;
	}

	public function processbulkprintlabels()
	{
		$labels = array();

		if (empty($this->boxes) || !is_array($this->boxes))
			return false;
		else
			foreach ($this->boxes as $reference)
				$labels[] = $this->printLabels($reference);

		if (!empty($labels))
			$this->context->smarty->assign('labels', $labels);

		return true;
	}

	public function processbulksendttemail()
	{
		$response = true;

		if (empty($this->boxes) || !is_array($this->boxes))
			$response = false;
		else
			foreach ($this->boxes as $reference)
				$response &= $response && $this->sendTTEmail($reference);

		return $response;
	}

	/**
	 * @param int $id_carrier
	 * @return mixed
	 */
	public function getOrderShippingMethod($id_carrier = 0, $slug = true)
	{
		$shipping_method = '';

		if ($id_reference = Db::getInstance()->getValue('
SELECT
	MAX(occ.`id_carrier`)
FROM
	`'._DB_PREFIX_.'carrier` oc
LEFT JOIN
	`'._DB_PREFIX_.'carrier` occ
ON
	occ.`id_reference` = oc.`id_reference`
WHERE
	oc.`id_carrier` = '.(int)$id_carrier))
		{
			$shipping_method = $this->delivery_methods_list[(int)$id_reference];
			if (!$slug)
				$shipping_method = array_search($this->delivery_methods_list[(int)$id_reference], $this->delivery_methods_list);
		}

		return $shipping_method;
	}

	/**
	 * @param string $reference
	 * @return string
	 */
	public function getOrderRecipient($reference)
	{
		return $this->service->getOrderRecipient($reference);
	}

	/**
	 * @param int $count
	 * @return int
	 */
	public function getLabelsCount($count = 1)
	{
		return $count;
	}

	/**
	 * @param string $reference
	 * @return string
	 */
	public function getPrintIcon($reference = '')
	{
		if (empty($reference))
			return;

		return '<img class="print" src="'._MODULE_DIR_.'bpostshm/views/img/icons/print.png"
			 data-labels="'.Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&printLabels'.$this->table.'&token='.$this->token).'"/>';
	}

	/**
	 * @param string $reference
	 * @return string
	 */
	public function getTTIcon($reference = '')
	{
		if (empty($reference))
			return;

		$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
		$treated_status = Configuration::get('BPOST_ORDER_STATE_TREATED_'.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id));
		// do not display if order is not TREATED
		if ($ps_order->current_state != $treated_status)
			return;

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		// do not display if labels are not PRINTED
		if (!is_dir($pdf_dir) || !opendir($pdf_dir))
			return;

		$tracking_url = $this->tracking_url;
		foreach ($this->tracking_params as $param => $value)
			if (empty($value) && false !== $value)
				switch ($param)
				{
					case 'searchByCustomerReference':
						$this->tracking_params[$param] = true;
						break;
					case 'oss_language':
						if (in_array($this->context->language->iso_code, array('de', 'fr', 'nl', 'en')))
							$this->tracking_params[$param] = $this->context->language->iso_code;
						else
							$this->tracking_params[$param] = 'en';
						break;
					case 'customerReference':
						$this->tracking_params[$param] = $reference;
						break;
					default:
						break;
				}
		$tracking_url .= '?'.http_build_query($this->tracking_params);

		return '<a href="'.$tracking_url.'" target="_blank"><img class="t_t" src="'._MODULE_DIR_.'bpostshm/views/img/icons/track_and_trace.png" /></a>';
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displayAddLabelLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Add label'),
			'href' => Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&addLabel'.$this->table
				.'&token='.($token != null ? $token : $this->token)),
		);

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference.'/retours';
		// disable if labels are not PRINTED
		if (is_dir($pdf_dir))
			$tpl_vars['disabled'] = $this->l('A retour has already been created.');

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displayPrintLabelsLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Print labels'),
			'href' => Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&printLabels'.$this->table
				.'&token='.($token != null ? $token : $this->token))
		);

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displayMarkTreatedLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Mark treated'),
			'href' => Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&markTreated'.$this->table
				.'&token='.($token != null ? $token : $this->token)),
		);

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		// disable if labels are not PRINTED
		if (!is_dir($pdf_dir) || !opendir($pdf_dir))
			$tpl_vars['disabled'] = $this->l('Actions are only available for orders that are printed.');

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displaySendTTEmailLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Send T&T e-mail'),
			'href' => Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&sendTTEmail'.$this->table
				.'&token='.($token != null ? $token : $this->token)),
		);

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		if (!is_dir($pdf_dir) || !opendir($pdf_dir))
		// disable if labels are not PRINTED
			$tpl_vars['disabled'] = $this->l('Actions are only available for orders that are printed.');

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displayCreateRetourLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Create retour'),
			'href' => Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&createRetour'.$this->table
				.'&token='.($token != null ? $token : $this->token)),
		);

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		// disable if labels are not PRINTED
		if (!is_dir($pdf_dir) || !opendir($pdf_dir))
			$tpl_vars['disabled'] = $this->l('Actions are only available for orders that are printed.');

		$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
		$address_delivery = new Address($ps_order->id_address_delivery);
		$id_country = (int)$address_delivery->id_country;
		foreach (array('België', 'Belgique', 'Belgium') as $country)
			if ($id_belgium = Country::getIdByName(null, $country))
				break;
		// disable if no Belgium found or if order has been placed elsewhere
		if (empty($id_belgium) || $id_belgium !== $id_country)
			$tpl_vars['disabled'] = $this->l('The creation of international retour orders are currently not supported.
				Please contact your bpost account manager for more information.');

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference.'/retours';
		// disable if retour labels have already been PRINTED
		if (is_dir($pdf_dir))
			$tpl_vars['disabled'] = $this->l('A retour has already been created.');

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displayViewLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Open order'),
			'target' => '_blank',
		);

		$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
		$tpl_vars['href'] = 'index.php?tab=AdminOrders&vieworder&id_order='.(int)$ps_order->id.'&token='.Tools::getAdminTokenLite('AdminOrders');

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param null|string $token
	 * @param string $reference
	 * @return mixed
	 */
	public function displayCancelLink($token = null, $reference = '')
	{
		if (empty($reference))
			return;

		$tpl_vars = array(
			'action' => $this->l('Cancel order'),
			'href' => Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&cancel'.$this->table
				.'&token='.($token != null ? $token : $this->token)),
		);

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		// disable if labels have already been PRINTED
		if (is_dir($pdf_dir))
			$tpl_vars['disabled'] = $this->l('Only open orders can be cancelled.');

		$tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
		$tpl->assign($tpl_vars);
		return $tpl->fetch();
	}

	/**
	 * @param string $reference
	 * @return bool
	 */
	private function markOrderTreated($reference = '')
	{
		if (empty($reference))
			return false;

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		if (!is_dir($pdf_dir) || !opendir($pdf_dir))
		{
			// disable if labels are not PRINTED
			$this->errors[] = $this->l('Order ref. '.$reference.' was not treated : action is only available for orders that are printed.');
			return false;
		}

		$response = true;
		$treated_status = Configuration::get('BPOST_ORDER_STATE_TREATED_'.(is_null($this->context->shop->id) ? '1' : $this->context->shop->id));
		$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
		$ps_order->current_state = (int)$treated_status;
		$response = $response && $ps_order->save();

		// Create new OrderHistory
		$history = new OrderHistory();
		$history->id_order = $ps_order->id;
		$history->id_employee = (int)$this->context->employee->id;

		$use_existings_payment = false;
		if (!$ps_order->hasInvoice())
			$use_existings_payment = true;
		$history->changeIdOrderState((int)$treated_status, $ps_order, $use_existings_payment);

		$carrier = new Carrier($ps_order->id_carrier, $ps_order->id_lang);
		$template_vars = array();
		if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $ps_order->shipping_number)
			$template_vars = array('{followup}' => str_replace('@', $ps_order->shipping_number, $carrier->url));
		// Save all changes
		$response = $response && $history->addWithemail(true, $template_vars);

		return $response;
	}

	/**
	 * @param string $reference
	 * @return bool
	 */
	private function sendTTEmail($reference = '')
	{
		if (empty($reference))
			return false;

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf/'.$reference;
		if (!is_dir($pdf_dir) || !opendir($pdf_dir))
		{
			// disable if labels are not PRINTED
			$this->errors[] = $this->l('Order ref. '.$reference.' was not treated : action is only available for orders that are printed.');
			return false;
		}

		$response = true;
		$ps_order = Order::getByReference(Tools::substr($reference, 7))->getFirst();
		$tracking_url = $this->tracking_url;

		foreach ($this->tracking_params as $param => $value)
			if (empty($value) && false !== $value)
				switch ($param)
				{
					case 'searchByCustomerReference':
						$this->tracking_params[$param] = true;
						break;
					case 'oss_language':
						if (in_array($this->context->language->iso_code, array('de', 'fr', 'nl', 'en')))
							$this->tracking_params[$param] = $this->context->language->iso_code;
						else
							$this->tracking_params[$param] = 'en';
						break;
					case 'customerReference':
						$this->tracking_params[$param] = $reference;
						break;
					default:
						break;
				}

		$tracking_url .= '?'.http_build_query($this->tracking_params);
		$message = $this->l('Your order').' '.$ps_order->reference.' '.$this->l('can now be tracked here :')
			.' <a href="'.$tracking_url.'">'.$tracking_url.'</a>';

		$customer = new Customer($ps_order->id_customer);
		if (!Validate::isLoadedObject($customer))
			$this->errors[] = Tools::displayError('The customer is invalid.');
		else
		{
			//check if a thread already exist
			$id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $ps_order->id);
			if (!$id_customer_thread)
			{
				$customer_thread = new CustomerThread();
				$customer_thread->id_contact = 0;
				$customer_thread->id_customer = (int)$ps_order->id_customer;
				$customer_thread->id_shop = (int)$this->context->shop->id;
				$customer_thread->id_order = (int)$ps_order->id;
				$customer_thread->id_lang = (int)$this->context->language->id;
				$customer_thread->email = $customer->email;
				$customer_thread->status = 'open';
				$customer_thread->token = Tools::passwdGen(12);
				$customer_thread->add();
			}
			else
				$customer_thread = new CustomerThread((int)$id_customer_thread);

			$customer_message = new CustomerMessage();
			$customer_message->id_customer_thread = $customer_thread->id;
			$customer_message->id_employee = (int)$this->context->employee->id;
			$customer_message->message = $message;
			$customer_message->private = false;

			if (!$customer_message->add())
				$this->errors[] = Tools::displayError('An error occurred while saving the message.');
			else
			{
				$message = $customer_message->message;
				if (Configuration::get('PS_MAIL_TYPE', null, null, $ps_order->id_shop) != Mail::TYPE_TEXT)
					$message = Tools::nl2br($customer_message->message);

				$vars_tpl = array(
					'{lastname}' => $customer->lastname,
					'{firstname}' => $customer->firstname,
					'{id_order}' => $ps_order->id,
					'{order_name}' => $ps_order->getUniqReference(),
					'{message}' => $message
				);

				Mail::Send((int)$ps_order->id_lang, 'order_merchant_comment',
					Mail::l('New message regarding your order', (int)$ps_order->id_lang), $vars_tpl, $customer->email,
					$customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int)$ps_order->id_shop
				);
			}
		}

		if (!empty($this->errors))
			$response = false;

		return $response;
	}

	/**
	 * @param string $reference
	 * @return array
	 */
	private function printLabels($reference = '')
	{
		$links = array();

		if (empty($reference))
			return $links;

		$context_shop_id = (isset($this->context->shop) && !is_null($this->context->shop->id) ? $this->context->shop->id : 1);
		$do_not_open = array('.', '..', 'labels');
		$i = 1;

		$pdf_dir = _PS_MODULE_DIR_.'bpostshm/pdf';
		if (!is_dir($pdf_dir))
			mkdir($pdf_dir, 0755);

		$pdf_dir .= '/'.$reference;
		if (!is_dir($pdf_dir))
			mkdir($pdf_dir, 0755);
		$files = scandir($pdf_dir);

		if (!empty($files) && is_array($files))
			foreach ($files as $file)
				if (!in_array($file, $do_not_open) && !is_dir($pdf_dir.'/'.$file))
				{
					$links[] = _PS_BASE_URL_._MODULE_DIR_.'bpostshm/pdf/'.$reference.'/'.$i.'.pdf';
					$i++;
				}

		if ($labels = $this->service->createLabelForOrder(
			$reference,
			Configuration::get('BPOST_LABEL_PDF_FORMAT_'.$context_shop_id),
			(bool)Configuration::get('BPOST_LABEL_RETOUR_LABEL_'.$context_shop_id)))
		{
			foreach ($labels as $label)
			{
				$this->service->updatePSLabelBarcode($reference, $label->getBarcode());

				$file = $pdf_dir.'/'.$i.'.pdf';
				$fp = fopen($file, 'w');
				fwrite($fp, $label->getBytes());
				fclose($fp);

				$links[] = _MODULE_DIR_.'bpostshm/pdf/'.$reference.'/'.$i.'.pdf';
				$i++;
			}

			$this->service->updatePSLabelStatus($reference, 'PRINTED');
		}

		$pdf_dir .= '/retours';
		if (is_dir($pdf_dir))
		{
			$i = 1;
			$files = scandir($pdf_dir);

			if (!empty($files) && is_array($files))
				foreach ($files as $file)
					if (!in_array($file, $do_not_open) && !is_dir($pdf_dir.'/'.$file))
					{
						$links[] = _PS_BASE_URL_._MODULE_DIR_.'bpostshm/pdf/'.$reference.'/retours/'.$i.'.pdf';
						$i++;
					}
		}

		return $links;
	}

	/**
	 * @param mixed $content
	 */
	private function jsonEncode($content)
	{
		header('Content-Type: application/json');
		die(Tools::jsonEncode($content));
	}
}