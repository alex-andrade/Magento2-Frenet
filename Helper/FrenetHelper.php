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

		private function getItemsInfo($allItems)
		{					
	
			foreach($allItems as $item)
			{
				$itemsArray["ShippingItemArray"]=[
					"Weight" => $item->getWeight(),
					"Length" => $item->getLength(),
					"Height" => $item->getHeight(),
					"Width" => $item->getWith(),
					"Quantity" => $item->QtyToAdd()
					];												
				$productExPrice  = $item->getPrice(); // price excluding tax
			}
		}

		public function getOptions($cepOrigem, $cepDestino, $allItems){
			$Items = $this->getItemsInfo($allItems);
			
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "https://private-anon-d8268ba116-frenetapi.apiary-mock.com/shipping/quote");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_POST, TRUE);

			// curl_setopt($ch, CURLOPT_POSTFIELDS, "{
			// \"SellerCEP\": \"13015300\",
			// \"RecipientCEP\": \"04011060\",
			// \"ShipmentInvoiceValue\": 100.87,
			// \"ShippingItemArray\": [
			// 	{
			// 	\"Weight\": 2.1,
			// 	\"Length\": 14,
			// 	\"Height\": 20,
			// 	\"Width\": 15,
			// 	\"Quantity\": 1,
			// 	\"SKU\": \"CWZ_75673_P\",
			// 	\"Category\": \"acessórios\"
			// 	},
			// 	{
			// 	\"Weight\": 3.1,
			// 	\"Length\": 14,
			// 	\"Height\": 26,
			// 	\"Width\": 35,
			// 	\"Quantity\": 1
			// 	}
			// ],
			// \"RecipientCountry\": \"BR\"
			// }");

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"token: (seu token)"
			));

			$response = curl_exec($ch);
			curl_close($ch);

		}
	}