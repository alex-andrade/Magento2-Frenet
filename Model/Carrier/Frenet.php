<?php

namespace Magecommerce\Frenet\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;
use Magecommerce\Frenet\Helper\FrenetHelper;

class Frenet extends AbstractCarrier implements CarrierInterface
{
	protected $_code = 'frenet';
	protected $_isFixed = true;
	protected $_rateResultFactory;
	protected $_rateMethodFactory;
	protected $frenetHelper;
	private $trackStatusFactory;
	private $token;
	
	public function __construct(
	ScopeConfigInterface $scopeConfig,
	ErrorFactory $rateErrorFactory,
	LoggerInterface $logger,
	StatusFactory $trackStatusFactory,
	ResultFactory $rateResultFactory,
	MethodFactory $rateMethodFactory,
	FrenetHelper $frenetHelper,
	array $data = []
	)

	{		
		$this->trackStatusFactory = $trackStatusFactory;
		$this->_rateResultFactory = $rateResultFactory;
		$this->_rateMethodFactory = $rateMethodFactory;
		$this->frenetHelper = $frenetHelper;
		parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
	}

	public function getAllowedMethods()
	{
		return [$this->getCarrierCode() => __($this->getConfigData('name'))];
	}
	
	public function collectRates(RateRequest $request)
	{		
		$cepDestino = $request->_data["dest_postcode"]; // get the shipping post code from the checkout page		
		if (!$this->isActive() || !$cepDestino)
		{
			return false;
		}

		$allItems = $request->getAllItems(); // get all items from the cart
		$productsPrice = $request->_data["package_value_with_discount"];				 
		$token = $this->getConfigData('token'); // get the token from the admin configuration
		$cepOrigem = $request->_data['postcode']; //get the post code from the admin configuration

		$this->frenetHelper->setToken($token);	//sets the token to the frenet helper, where the module will connect to the Frenet API
	
		
		$options = $this->frenetHelper->getOptions($cepOrigem, $cepDestino, $allItems, $productsPrice); // get the prices and options from Frenet API
	

		
		$extraDeliveryDays = $this->getConfigData('extra_days');
		$extraDeliveryCost = $this->getConfigData('extra_cost');		

		$result = $this->_rateResultFactory->create(); // array de metodos

		foreach ($options->ShippingSevicesArray as $option) {
			$deliveryText = $this->DeliveryString($option, $extraDeliveryDays); //Gera o texto de tempo de entrea

			$method = $this->_rateMethodFactory->create();
			$method->setCarrier($this->getCarrierCode()); // Info que esta em etc/adminhtml/system.xml
			$method->setCarrierTitle($option->Carrier);
			/**
			 * Abaixo, é retirado o '_' e transformado o code em lowercase
			 */
			
			$method->setMethod($option->ServiceCode);
			$method->setMethodTitle($option->ServiceDescription . $deliveryText);
			$method->setPrice($option->ShippingPrice + $extraDeliveryCost);
			$method->setCost($option->ShippingPrice + $extraDeliveryCost);
			$result->append($method);
		}																		
		return $result;
	}

	private function DeliveryString($option, $extraDeliveryDays){
		$days = $option->DeliveryTime + $extraDeliveryDays;
		if ($days == 1) {
			$result = " Prazo de entrega: 1 dia útel";
			return $result;
		}

		$result = " Prazo de entrega: " . $days . " dias úteis";
		return $result;
	}
	public function isTrackingAvailable()
    {
        return true;
	}
	
	public function getTrackingInfo($trackingNumber)
    {
		/** @var \Magento\Shipping\Model\Tracking\Result\Status $tracking */
		$token = $this->getConfigData('token');
		$this->frenetHelper->setToken($token);

        $tracking = $this->trackStatusFactory->create();
        $tracking->setCarrier($this->_code);
		$tracking->setTracking($trackingNumber);
		
		$tracking->setProgressdetail($this->frenetHelper->getTracking($trackingNumber));
        return $tracking;
    }
}