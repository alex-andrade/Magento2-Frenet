<?php 

	namespace Magecommerce\Frenet\Helper;
	use \Magento\Framework\App\Helper\AbstractHelper;
	/**
	 * 
	 */
	class FrenetHelper extends AbstractHelper
	{
		private $token;

		public function setToken($token)
		{
			$this->token = $token;
		}

		public function getAvailableOptions(){

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://private-anon-d8268ba116-frenetapi.apiary-mock.com/shipping/info");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			  "Content-Type: application/json",
			  "token: " + $this->token
			));

			$response = curl_exec($ch);
			curl_close($ch);

			return $response;

		}


		/*
			Funcao nao completa, precisa coletar informacoes de dimensao
		*/
		private function getItemsInfo($allItems)
		{				
			$ShippingItemArray = array();
			foreach($allItems as $item)
			{																
				$data = $item->getData();
				$ShippingItemArray[] = ["Weight" => $item->getWeight(),
				"Length" => "16",
				"Height" => "16",
				"Width" => "16",
				"Price" => $item->getPrice(),
				"Quantity" => $item->getQty()
				];				
			}

			return $ShippingItemArray;
		}

		public function getOptions($cepOrigem, $cepDestino, $allItems, $productsPrice){
			$Items = $this->getItemsInfo($allItems);
			
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "http://private-anon-5d578eb9ca-frenetapi.apiary-mock.com/shipping/quote");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_POST, TRUE);
			$dados = array('SellerCEP' => $cepOrigem,
			'RecipientCEP' => $cepDestino,
			'ShipmentInvoiceValue' => "$productsPrice",
			'ShippingItemArray' => $Items,
			'RecipientCountry' => 'BR');

			$json = json_encode($dados);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

			$test = $this->token;

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"token: " . $this->token
			));
			$response = curl_exec($ch);

			$response = json_decode($response);
			return $response;
		}
	}