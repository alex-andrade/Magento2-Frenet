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
				$itemDimension = $this->getItemDimension($data["product_id"]);

				/**
				 * Testa se de fato as dimensoes estao cadastradas
				 */
				if (!$itemDimension["height"] || !$itemDimension["length"] || !$itemDimension["width"]) {
					return false;
				}

				$ShippingItemArray[] = ["Weight" => $item->getWeight(),
				"Length" => $itemDimension["length"],
				"Height" => $itemDimension["height"],
				"Width" => $itemDimension["width"],
				"Price" => $item->getPrice(),
				"Quantity" => $item->getQty()
				];				
			}

			return $ShippingItemArray;
		}

		private function getItemDimension($productId) // parado aqui
		{			
			$height = $this->getAttributeValue($productId, $this->getAttributeIdByCode('ts_dimensions_height'));		
			$length = $this->getAttributeValue($productId, $this->getAttributeIdByCode('ts_dimensions_length'));
			$width = $this->getAttributeValue($productId, $this->getAttributeIdByCode('ts_dimensions_width'));

			$itemDimension= array("height" => floatval($height),
				"length" => floatval($length),
				"width" => floatval($width));			

			return $itemDimension;

		}

		private function getConnection(){

			$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
			$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
			$connection = $resource->getConnection();

			return $connection;
		}

		private function getAttributeValue($productId, $attributeId){

			$connection = $this->getConnection();

			$sql = "SELECT value FROM catalog_product_entity_decimal where attribute_id=$attributeId and entity_id=$productId";
			$result = $connection->fetchAll($sql);
			return $result[0]["value"];
		}
		
		private function getAttributeIdByCode($attributeCode){

			$connection = $this->getConnection();
			
			$sql = "Select attribute_id FROM eav_attribute WHERE attribute_code = '" . $attributeCode . "'";
			$result = $connection->fetchAll($sql); // gives associated array, table fields as key in array.
			
			return $result[0]["attribute_id"];
		}
		
		public function getOptions($cepOrigem, $cepDestino, $allItems, $productsPrice){
			$Items = $this->getItemsInfo($allItems);

			$dados = array('SellerCEP' => $cepOrigem,
			'RecipientCEP' => $cepDestino,
			'ShipmentInvoiceValue' => "$productsPrice",
			'ShippingItemArray' => $Items,
			'RecipientCountry' => 'BR');

			$json = json_encode($dados);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://api.frenet.com.br/shipping/quote");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_POST, TRUE);
			
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"token: " . $this->token
			));
			$response = curl_exec($ch);

 			$response = json_decode($response);

			return $response;
		}

		/**
		 * gets the method number, requested to get trancking info from Frenet's API
		 */

		private function getMethod($number){
			$connection = $this->getConnection();
			$sql = "SELECT shipping_method FROM sales_order inner join sales_shipment_track on sales_order.entity_id = sales_shipment_track.order_id where sales_shipment_track.track_number = '" . $number . "'";
			$result = $connection->fetchAll($sql);
			return $result[0]['shipping_method'];
		}

		public function getTracking($number){
						$method = $this->getMethod($number);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://api.frenet.com.br/tracking/trackinginfo");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);

			curl_setopt($ch, CURLOPT_POST, TRUE);

			$dados = array("ShippingServiceCode" => $method,
			"TrackingNumber" => $number);
			$dados = json_encode($dados);

			curl_setopt($ch, CURLOPT_POSTFIELDS, $dados);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json",
			"token: $this->token"
			));

			$response = curl_exec($ch);
			$response = json_decode($response);

			curl_close($ch);

			$progress = array();

			if (!isset($response->TrackingEvents)) { //When API returns an error or an empty JSON
				return null;
			}

			foreach ($response->TrackingEvents as $event) {
				$dateTime = $this->separateDateTime($event->EventDateTime); // [0] date, [1] time

				$progress[] = ["deliverydate" => $dateTime[0],
				"deliverytime" => $dateTime[1],
				"deliverylocation" => $event->EventLocation,
				"activity" => $event->EventDescription];
			}



			return $progress;
			
		}

		private function separateDateTime($dateTime){
			$dateTime = explode(" ", $dateTime);
			$dateTime[0] = str_replace("/", "-", $dateTime[0]);
			return $dateTime;
		}
	}