<?php

require_once('config.php');
require_once('utils.php');
require_once('counter_utils.php');

class MageUtils
{
	private static $soap = null;
	public static $session = null;
	
	public static $entityTypeIds = null;
	public static $attributeIds = null;
	public static $storeIds = null;
	
	public static function Connect()
	{
		self::$soap = new SoapClient(MAGENTO_API_SERVER);
		self::$session = self::$soap->login(MAGENTO_API_USERNAME, MAGENTO_API_PASSWORD);
	}
	
	public static function GetDbMap($query)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);
		$map = array();
		$rows = mysql_query($query);
		if ($rows === FALSE) die(mysql_error());
		while ($row = mysql_fetch_row($rows))
			$map[$row[0]] = $row[1];
		return $map;
	}
	
	public static function CallDb($call)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);
		mysql_query($call);
	}
	
	public static function QueryDb($query)
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);
		$res = mysql_query($query);
		if ($res === FALSE) die(mysql_error());
		$rows = mysql_fetch_row($res);
		if (!empty($rows)) return $rows[0];
		else return null;
	}
	
	public static function GetEntityTypeIds()
	{
		self::$entityTypeIds = self::GetDbMap("SELECT entity_type_code, entity_type_id FROM eav_entity_type");
	}
	
	public static function GetAttributeIds()
	{
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);
		$map = array();
		$rows = mysql_query("SELECT entity_type_id, attribute_code, attribute_id FROM eav_attribute");
		while ($row = mysql_fetch_row($rows))
			$map[$row[0]][$row[1]] = $row[2];
		self::$attributeIds = $map;
	}
	
	public static function GetStoreIds()
	{
		self::$storeIds = self::GetDbMap("SELECT code, store_id FROM core_store");
	}
	
	public static function SetProps($entity, $type, $entityId, $props)
	{
		if (self::$entityTypeIds == null) self::GetEntityTypeIds();
		if (self::$attributeIds == null) self::GetAttributeIds();
		
		$e = self::$entityTypeIds[$entity];
		$vals = array();
		
		foreach ($props as $key => $val)
			$vals[] = "('$e', '" . self::$attributeIds[$e][$key] . "', '0', '$entityId', "
			. ($val == null ? 'NULL' : "'" . mysql_real_escape_string($val) . "'") . ")";

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);
		
		mysql_query("INSERT INTO `${entity}_entity_${type}` (`entity_type_id`,`attribute_id`,`store_id`,`entity_id`,`value`) VALUES "
		. implode(',', $vals) . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
	}
	
	private static function _CreateCategory($name, $parent, $level)
	{
		if (self::$entityTypeIds == null) self::GetEntityTypeIds();
		$e = 'catalog_category';
		$eId = self::$entityTypeIds[$e];
		
		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);

		mysql_query("
INSERT INTO `catalog_category_entity` (`entity_type_id`, `attribute_set_id`, `parent_id`, `created_at`, `updated_at`, `path`, `position`, `level`, `children_count`)
VALUES	('$eId', '$eId', '$parent', UTC_TIMESTAMP(), UTC_TIMESTAMP(), '', '0', '$level', '0')");
		
		$id = mysql_insert_id();
		
		$urlKey = strtolower($name);
		$replace = '~`!@#$%^&*()_+=[]{}|\\:;"\'<,>.?/ ';
		foreach (str_split($replace) as $c)
			$urlKey = str_replace($c, '-', $urlKey);
			
		$props['name'] = $name;
		$props['url_key'] = $urlKey;
		$props['display_mode'] = 'PRODUCTS';
		self::SetProps($e, 'varchar', $id, $props);

		unset($props);
		$props['is_active'] = 1;
		$props['include_in_menu'] = 1;
		$props['is_anchor'] = 0;
		$props['custom_use_parent_settings'] = 0;
		$props['custom_apply_to_products'] = 0;
		self::SetProps($e, 'int', $id, $props);

		$path = self::QueryDb("SELECT get_category_path($id)");
		mysql_query("
UPDATE `catalog_category_entity`
SET `path` = '$path'
WHERE (entity_id = '$id')");

		self::CallDb("CALL sort_categories()");
		self::CallDb("CALL update_category_children()");
		
		return $id;
	}
	
	public static function ConvertCategoryFromEbay($categoryId)
	{
		$json = file_get_contents("http://open.api.ebay.com/Shopping?callname=GetCategoryInfo&appid=" . APP_ID . "&version=849&CategoryID=${categoryId}&responseencoding=JSON");
		/*  start insert counter */
		CountersUtils::insertCounter('GetCategoryInfo','Ebay Monitor',APP_ID);
		/*  end insert counter */
		$res = json_decode($json, true);
		$path = $res['CategoryArray']['Category'][0]['CategoryNamePath'];
		$names = explode(':', $path);
		
		$root = 'eBay Categories';
		if (count($names) >= 5)
		{
			$primary = $names[3];
			$secondary = $names[4];
		}
		else if (count($names) == 4)
		{
			$primary = $names[3];
			$secondary = null;
		}
		else
		{
			$primary = null;
			$secondary = null;
		}
		
		return self::CreateCategory($root, $primary, $secondary);
	}
	
	public static function GetCategoryId($name, $parentId, $level)
	{
		if (self::$entityTypeIds == null) self::GetEntityTypeIds();
		if (self::$attributeIds == null) self::GetAttributeIds();
		
		return self::QueryDb(sprintf("
SELECT cce.entity_id
FROM catalog_category_entity cce INNER JOIN catalog_category_entity_varchar ccev
ON cce.entity_id = ccev.entity_id
AND ccev.attribute_id = '%s'
AND level = $level
AND parent_id = $parentId
AND ccev.value = '%s'",
		self::$attributeIds[self::$entityTypeIds['catalog_category']]['name'], mysql_real_escape_string($name)));
	}

	public static function CreateCategory($root, $primary = null, $secondary = null)
	{
		$rootId = self::GetCategoryId($root, 1, 1);
		if (empty($rootId)) $rootId = self::_CreateCategory($root, 1, 1);
		if (empty($primary)) return $rootId;

		$primaryId = self::GetCategoryId($primary, $rootId, 2);
		if (empty($primaryId)) $primaryId = self::_CreateCategory($primary, $rootId, 2);
		if (empty($secondary)) return $primaryId;

		$secondaryId = self::GetCategoryId($secondary, $primaryId, 3);
		if (empty($secondaryId)) $secondaryId = self::_CreateCategory($secondary, $primaryId, 3);
		return $secondaryId;
	}
	
	public static function SetProductImage($sku, $imageUrl, $replace = true)
	{
		if (empty(self::$soap) || empty(self::$session)) self::Connect();

		if ($replace)
		{
			$images = self::$soap->catalogProductAttributeMediaList(self::$session, $sku, null, 'sku');

			if (!empty($images))
			{
				foreach ($images as $image)
				{
					try
					{
						self::$soap->catalogProductAttributeMediaRemove(self::$session, $sku, $image->file, 'sku');
					}
					catch (Exception $e)
					{
						// ok if image removal fails
					}
				}
			}
		}
		
		if (!empty($imageUrl))
		{
			try
			{	
				self::$soap->catalogProductAttributeMediaCreate(self::$session, $sku, array
				(
					'file' => array
					(
						'content' => base64_encode(file_get_contents($imageUrl)),
						'mime' => 'image/jpeg'
					),
					'label' => '',
					'position' => '1',
					'types' => array('thumbnail', 'image', 'small_image'),
					'exclude' => '0'
				));
			}
			catch (Exception $e)
			{
				error_log("Error while uploading image for product ${sku}. " . $e->getMessage());
			}
		}
	}
	
	public static function CreateProduct($store, $title, $sku, $qty, $price, $mpn, $brand, $picture, $description, $shortDescription, $category, $partNotes, $weight, $ebayId, $compatibility)
	{
		if (self::$storeIds == null) self::GetStoreIds();
		if (self::$entityTypeIds == null) self::GetEntityTypeIds();
		$e = 'catalog_product';
		$eId = self::$entityTypeIds[$e];

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);

		$id = self::QueryDb(sprintf("SELECT entity_id FROM catalog_product_entity WHERE sku = '%s'",
			mysql_real_escape_string($sku)));
		
		if (empty($id))
		{
			mysql_query(sprintf("
INSERT INTO `catalog_product_entity` (`entity_type_id`, `attribute_set_id`, `type_id`, `sku`, `has_options`, `required_options`, `created_at`, `updated_at`)
VALUES	('$eId', '$eId', 'simple', '%s', '0', '0', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
			mysql_real_escape_string($sku)));
			$id = mysql_insert_id();
		}
		
		$urlKey = strtolower($title);
		$replace = '~`!@#$%^&*()_+=[]{}|\\:;"\'<,>.?/ ';
		foreach (str_split($replace) as $c)
			$urlKey = str_replace($c, '-', $urlKey);
			
		$props['brand'] = $brand;
		$props['mpn'] = $mpn;
		$props['name'] = $title;
		$props['url_key'] = $urlKey;
		$props['part_notes'] = $partNotes;
		$props['ebay_id'] = $ebayId;
		$props['msrp_enabled'] = '2';
		$props['msrp_display_actual_price_type']= '4';
		$props['options_container']= 'container2';
		self::SetProps($e, 'varchar', $id, $props);
		
		unset($props);
		$props['description'] = $description;
		$props['short_description'] = $shortDescription;
		self::SetProps($e, 'text', $id, $props);
		
		unset($props);
		$props['status'] = 1;
		$props['visibility'] = 4;
		$props['tax_class_id'] = 2;
		$props['is_recurring'] = 0;
		self::SetProps($e, 'int', $id, $props);
		
		unset($props);
		$props['weight'] = $weight;
		$props['price'] = $price;
		self::SetProps($e, 'decimal', $id, $props);

		mysql_query("INSERT IGNORE INTO `catalog_product_website` (`product_id`,`website_id`) VALUES ('$id', '" . self::$storeIds[$store] . "')");
		mysql_query("DELETE FROM `catalog_category_product` WHERE `product_id` = '$id'");
		mysql_query("INSERT IGNORE INTO `catalog_category_product` (`category_id`,`product_id`,`position`)
		VALUES ('$category', '$id', '1')");

		$available = ($qty == 0 ? '0' : '1');

		mysql_query("
INSERT INTO `cataloginventory_stock_item` (`product_id`, `stock_id`, `qty`, `use_config_min_qty`, `is_qty_decimal`, `use_config_backorders`, `use_config_min_sale_qty`, `use_config_max_sale_qty`, `is_in_stock`, `low_stock_date`, `use_config_notify_stock_qty`, `use_config_manage_stock`, `stock_status_changed_auto`, `use_config_qty_increments`, `use_config_enable_qty_inc`, `is_decimal_divided`)
VALUES ('$id', '1', '$qty', '1', '0', '1', '1', '1', '$available', NULL, '1', '1', '0', '1', '1', '0')
ON DUPLICATE KEY UPDATE `qty` = VALUES(`qty`), `is_in_stock` = VALUES(`is_in_stock`)");

		mysql_query("
INSERT INTO `cataloginventory_stock_status` (`product_id`,`website_id`,`stock_id`,`qty`,`stock_status`)
VALUES ('$id', '1', '1', '$qty', '$available')
ON DUPLICATE KEY UPDATE `qty` = VALUES(`qty`), `stock_status` = VALUES(`stock_status`)");

		// check cataloginventory_stock_status_idx?

		//self::ReplacePicture($sku, $picture);
	}
	
	public static function ReplacePicture($sku, $url)
	{
		/*
		if (empty($sku) || empty($url))
			return;

		if (self::$attributeIds == null) self::GetAttributeIds();
		if (self::$entityTypeIds == null) self::GetEntityTypeIds();
		$e = 'catalog_product';
		$eId = self::$entityTypeIds[$e];

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);

		$id = self::QueryDb(sprintf("SELECT entity_id FROM catalog_product_entity WHERE sku = '%s'",
			mysql_real_escape_string($sku)));
		
		if (empty($id))
			return false;
		
		if (strlen($id) < 2) $entId = '00' . $id;
		else $entId = $id;
		
		$dir = MAGENTO_DIR . 'media/catalog/product/' . substr($entId, 0, 1) . '/' . substr($entId, 1, 1);
		if (!is_dir($dir))
			mkdir($dir, 0777, true);
		
		$dbName = '/' . substr($entId, 0, 1) . '/' . substr($entId, 1, 1) . '/' . $id . '.jpg';
		$fileName = MAGENTO_DIR . 'media/catalog/product' . $dbName;
		file_put_contents($fileName, file_get_contents($url));
		
		if (file_exists($fileName))
		{
			$props['image'] = $dbName;
			$props['small_image'] = $dbName;
			$props['thumbnail'] = $dbName;
			self::SetProps($e, 'varchar', $id, $props);

			mysql_query("
DELETE FROM catalog_product_entity_media_gallery_value WHERE value_id IN (
SELECT value_id FROM catalog_product_entity_media_gallery
WHERE attribute_id = " . self::$attributeIds[$eId]['media_gallery'] . "
AND entity_id = $id)");
			
			mysql_query("
DELETE FROM catalog_product_entity_media_gallery
WHERE attribute_id = " . self::$attributeIds[$eId]['media_gallery'] . "
AND entity_id = $id");
			
			mysql_query("
INSERT INTO catalog_product_entity_media_gallery (attribute_id, entity_id, value)
VALUES (" . self::$attributeIds[$eId]['media_gallery'] . ", $id, '" . $dbName . "')");

			$vId = mysql_insert_id();
			
			mysql_query("
INSERT IGNORE INTO catalog_product_entity_media_gallery_value (value_id, store_id, label, position, disabled)
VALUES ($vId, 0, NULL, 1, 0)");

			return true;
		}

		*/
		return false;
	}
	
	public static function ReplaceFitment($sku, $fitment)
	{/*
		if (self::$attributeIds == null) self::GetAttributeIds();
		if (self::$entityTypeIds == null) self::GetEntityTypeIds();
		$e = 'catalog_product';
		$eId = self::$entityTypeIds[$e];

		mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
		mysql_select_db(MAGENTO_SCHEMA);

		$id = self::QueryDb(sprintf("SELECT entity_id FROM catalog_product_entity WHERE sku = '%s'",
			mysql_real_escape_string($sku)));
		
		if (empty($id))
			return false;

		mysql_select_db(DB_SCHEMA);
		$itemId = self::QueryDb(sprintf("SELECT item_id FROM ebay_listings WHERE sku = '%s' ORDER BY active DESC",
			mysql_real_escape_string($sku)));
		
		if (empty($itemId))
			return false;
		
		mysql_query(sprintf("
INSERT IGNORE INTO DELETE FROM catalog_product_entity_media_gallery_value WHERE value_id IN (
SELECT value_id FROM catalog_product_entity_media_gallery
WHERE attribute_id = " . self::$attributeIds[$eId]['media_gallery'] . "
AND entity_id = $id)");
			
			mysql_query("
DELETE FROM catalog_product_entity_media_gallery
WHERE attribute_id = " . self::$attributeIds[$eId]['media_gallery'] . "
AND entity_id = $id");
			
			mysql_query("
INSERT INTO catalog_product_entity_media_gallery (attribute_id, entity_id, value)
VALUES (" . self::$attributeIds[$eId]['media_gallery'] . ", $id, '" . $dbName . "')");

			$vId = mysql_insert_id();
			
			mysql_query("
INSERT IGNORE INTO catalog_product_entity_media_gallery_value (value_id, store_id, label, position, disabled)
VALUES ($vId, 0, NULL, 1, 0)");

			return true;
		}
		
		return false;*/
	}
}
?>