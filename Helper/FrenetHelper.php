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

		private function getAttributeValue($productId, $attributeId){
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
			$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
			$connection = $resource->getConnection();
			//SELECT value From catalog_product_entity_decimal where attribute_id=136 and entity_id=1
			$tableName = $resource->getTableName('catalog_product_entity_decimal');
			$sql = "SELECT value FROM " . $tableName . " where attribute_id=$attributeId and entity_id=$productId";
			$result = $connection->fetchAll($sql);
			return $result[0]["value"];
		}
		
		private function getAttributeIdByCode($attributeCode){
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
			$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
			$connection = $resource->getConnection();
			$tableName = $resource->getTableName('eav_attribute'); //gives table name with prefix
			$sql = "Select attribute_id FROM " . $tableName . " WHERE attribute_code = '" . $attributeCode . "'";
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
	}