<?php

namespace Magecommerce\Frenet\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;
use Magecommerce\Frenet\Helper\FrenetHelper;

class Frenet extends AbstractCarrier implements CarrierInterface
{
	protected $_code = 'magecommerce_frenet';
	protected $_isFixed = true;
	protected $_rateResultFactory;
	protected $_rateMethodFactory;
	protected $frenetHelper;

	public function __construct(
	ScopeConfigInterface $scopeConfig,
	ErrorFactory $rateErrorFactory,
	LoggerInterface $logger,
	ResultFactory $rateResultFactory,
	MethodFactory $rateMethodFactory,
	FrenetHelper $frenetHelper,
	array $data = []
	)

	{		
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
		$cepOrigem = $this->getConfigData('cep_origem'); //get the post code from the admin configuration
		$this->frenetHelper->setToken($token);	//sets the token to the frenet helper, where the module will connect to the Frenet API

		$availableOptions = $this->frenetHelper->getAvailableOptions(); 
		
		$options = $this->frenetHelper->getOptions($cepOrigem, $cepDestino, $allItems, $productsPrice); // get the prices and options from Frenet API
	

		$result = $this->_rateResultFactory->create(); // array de metodos

		foreach ($options->ShippingSevicesArray as $option) {
			$method = $this->_rateMethodFactory->create();
			$method->setCarrier($this->getCarrierCode()); // Info que esta em etc/adminhtml/system.xml
			$method->setCarrierTitle($option->Carrier);
			$method->setMethod($option->Carrier . " " . $option->ServiceCode);
			$method->setMethodTitle($option->ServiceDescription);
			$method->setPrice($option->ShippingPrice);
			$method->setCost($option->ShippingPrice);
			$result->append($method);
		}																		
		return $result;
	}
}