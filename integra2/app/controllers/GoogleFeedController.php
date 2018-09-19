<?php

class GoogleFeedController extends \BaseController
{
    public function graph()
    {
        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 2;

        $edited = DB::select(<<<EOQ
SELECT DATE_FORMAT( edited_on,  '%m/%d %H:00' ) AS d, edited_by AS u, COUNT(DISTINCT gf.mpn) AS c
FROM integra_prod.google_feed_log_edit gfl, integra_prod.google_feed gf
WHERE edited_on >= DATE_SUB( CURDATE( ) , INTERVAL ? DAY )
AND gfl.item_id = gf.id
GROUP BY 1, 2
ORDER BY edited_on
EOQ
            , [$days]);

        $created = DB::select(<<<EOQ
SELECT DATE_FORMAT( created_on,  '%m/%d %H:00' ) AS d, created_by AS u, COUNT(DISTINCT mpn) AS c
FROM integra_prod.google_feed_log_create
WHERE created_on >= DATE_SUB( CURDATE( ) , INTERVAL ? DAY )
GROUP BY 1, 2
ORDER BY created_on
EOQ
            , [$days]);

        return ['edited' => $edited, 'created' => $created];
    }

    public function update($id)
    {
        DB::beginTransaction();

        $rowsBefore = DB::select("SELECT * FROM integra_prod.google_feed WHERE id = ? AND store = ?", [ $id, 'europortparts' ]);

        DB::update(<<<EOQ
UPDATE integra_prod.google_feed
SET mpn = ?,
brand = ?,
title = ?,
description = ?,
price = ?,
shipping = ?,
availability = ?,
category = ?,
custom_label0 = ?,
custom_label1 = ?,
custom_label2 = ?,
custom_label3 = ?,
custom_label4 = ?,
item_condition = ?,
link = ?,
image_link = ?
WHERE id = ?
AND store = ?
EOQ
            ,
            [
                Input::get('mpn'),
                Input::get('brand'),
                Input::get('title'),
                Input::get('description'),
                Input::get('price'),
                Input::get('shipping'),
                Input::get('availability'),
                Input::get('category'),
                Input::get('custom_label0'),
                Input::get('custom_label1'),
                Input::get('custom_label2'),
                Input::get('custom_label3'),
                Input::get('custom_label4'),
                Input::get('item_condition'),
                Input::get('link'),
                Input::get('image_link'),
                $id,
                'europortparts'
            ]);

        $rowsAfter = DB::select("SELECT * FROM integra_prod.google_feed WHERE id = ? AND store = ?", [ $id, 'europortparts' ]);

        if ($rowsBefore && $rowsAfter && $rowsBefore[0] && $rowsAfter[0])
        {
            foreach ($rowsBefore[0] as $field => $value)
            {
                if ($field == 'ts') continue;
                if ($rowsAfter[0][$field] != $value)
                {
                    DB::insert("INSERT INTO integra_prod.google_feed_log_edit (edited_on, edited_by, item_id, edited_field, before_value, after_value) VALUES(NOW(), ?, ?, ?, ?, ?)",
                        [ Cookie::get('user'), $id, $field, $value, $rowsAfter[0][$field] ]);
                }
            }
        }

        $result = GoogleUtils::upsertFeed($id, 'europortparts');
        if ($result['success']) DB::commit();
        else DB::rollBack();

        return $result;
    }

    public function updateMultiple()
    {
        $allowed = ['price', 'category', 'custom_label0', 'custom_label1', 'custom_label2', 'custom_label3', 'custom_label4'];
        $good = false;
        $field = Input::get('field');

        foreach ($allowed as $a)
        {
            if ($field == $a)
            {
                $good = true;
                break;
            }
        }

        if (!$good) return '';

        foreach (Input::get('ids') as $id)
        {
            DB::beginTransaction();

            $rowsBefore = DB::select("SELECT * FROM integra_prod.google_feed WHERE id = ? AND store = ?", [ $id, 'europortparts' ]);

            DB::update(<<<EOQ
UPDATE integra_prod.google_feed
SET {$field} = ?
WHERE id = ?
AND store = ?
EOQ
                ,
                [
                    Input::get('value'),
                    $id,
                    'europortparts'
                ]);

            $rowsAfter = DB::select("SELECT * FROM integra_prod.google_feed WHERE id = ? AND store = ?", [ $id, 'europortparts' ]);

            if ($rowsBefore && $rowsAfter && $rowsBefore[0] && $rowsAfter[0])
            {
                foreach ($rowsBefore[0] as $field => $value)
                {
                    if ($rowsAfter[0][$field] != $value)
                    {
                        DB::insert("INSERT INTO integra_prod.google_feed_log_edit (edited_on, edited_by, item_id, edited_field, before_value, after_value) VALUES(NOW(), ?, ?, ?, ?, ?)",
                            [ Cookie::get('user'), $id, $field, $value, $rowsAfter[0][$field] ]);
                    }
                }
            }

            $result = GoogleUtils::upsertFeed($id, 'europortparts');
            if ($result['success']) DB::commit();
            else {
                DB::rollBack();
                return $result;
            }
        }

        return ['success' => true, 'msg' => ''];
    }

    public function destroy($id)
    {
        return GoogleUtils::destroyFeed($id, 'europortparts');
    }

    public function destroyMultiple()
    {
        foreach (Input::get('ids') as $id)
        {
            $res = GoogleUtils::destroyFeed($id, 'europortparts');
            if (!$res['success']) return $res;
        }

        return ['success' => true, 'msg' => ''];
    }

    public function search($mpn)
    {
        return DB::select('SELECT * FROM integra_prod.google_feed WHERE mpn = ?', [trim(strtoupper($mpn))]);
    }

    public function generate()
    {
        $mpn = trim(strtoupper(Input::get('mpn')));
        $customMpn = trim(strtoupper(Input::get('custom_mpn')));
        if (empty($customMpn)) $customMpn = $mpn;
        $split = intval(Input::get('split'));
        $prefix = trim(Input::get('prefix'));
        $customPrice = trim(Input::get('price'));
        $customCategory = trim(Input::get('category'));
        $custom_label0 = trim(Input::get('custom_label0'));
        $custom_label1 = trim(Input::get('custom_label1'));
        $custom_label2 = trim(Input::get('custom_label2'));
        $custom_label3 = trim(Input::get('custom_label3'));
        $custom_label4 = trim(Input::get('custom_label4'));
        $store = 'europortparts';

        $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev
WHERE cpe.sku = ?
AND cpe.entity_id = cpev.entity_id
AND cpev.attribute_id = 71
AND cpev.store_id = 0
LIMIT 1
EOQ
            , [$mpn]);
        if (empty($rows)) return ['success' => false, 'msg' => 'MPN not found in Magento'];
        $name = $rows[0]['value'];

        $rows = DB::select(<<<EOQ
SELECT COUNT(*) AS c
FROM magento.elite_1_mapping em, magento.catalog_product_entity cpe
WHERE cpe.sku = ?
AND cpe.entity_id = em.entity_id
EOQ
            , [$mpn]);
        $numCompat = $rows[0]['c'];
        if ($numCompat < $split) return ['success' => false, 'msg' => "Maximum of only {$numCompat} entries possible for this MPN"];

        $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_varchar cpev
WHERE cpe.sku = ?
AND cpe.entity_id = cpev.entity_id
AND cpev.attribute_id = 135
AND cpev.store_id = 0
LIMIT 1
EOQ
            , [$mpn]);
        if (empty($rows)) return ['success' => false, 'msg' => 'No brand defined'];
        $brand = $rows[0]['value'];

        $rows = DB::select(<<<EOQ
SELECT value
FROM magento.catalog_product_entity cpe, magento.catalog_product_entity_decimal cped, magento.core_store cs
WHERE cpe.sku = ?
AND cpe.entity_id = cped.entity_id
AND cped.attribute_id = 75
AND cped.store_id = cs.store_id
AND cs.code = ?
LIMIT 1
EOQ
            , [$mpn, $store]);
        if (empty($rows)) return ['success' => false, 'msg' => 'No price defined'];
        $price = $rows[0]['value'];

        $rows = DB::select(<<<EOQ
SELECT price
FROM magento.shipping_matrixrate sm, magento.catalog_product_entity cpe, magento.catalog_product_entity_decimal cped, magento.core_website cw
WHERE cw.code = ?
AND cw.website_id = sm.website_id
AND sm.dest_country_id = 'US'
AND sm.condition_name = 'package_weight'
AND delivery_type = 'Ground'
AND cpe.sku = ?
AND cpe.entity_id = cped.entity_id
AND cped.attribute_id = 80
AND cped.store_id = 0
AND cped.value >= sm.condition_from_value
AND cped.value <= sm.condition_to_value
LIMIT 1
EOQ
        , [$store, $mpn]);
        if (empty($rows)) return ['success' => false, 'msg' => 'No shipping rate defined'];
        $shipping = $rows[0]['price'];

        $rows = DB::select(<<<EOQ
SELECT MAX(qty) AS q
FROM magento.catalog_product_entity cpe, magento.cataloginventory_stock_status css
WHERE cpe.sku = ?
AND cpe.entity_id = css.product_id
EOQ
            , [$mpn]);

        $qty = $rows[0]['q'];

        $compat = DB::select(<<<EOQ
SELECT em.make, em.model, em.year
FROM magento.elite_1_mapping em, magento.catalog_product_entity cpe
WHERE cpe.sku = ?
AND cpe.entity_id = em.entity_id
ORDER BY 1, 2, 3
EOQ
        , [$mpn]);

        $compatGroups = IntegraUtils::array_chunk_fixed($compat, $split);
        $entries = [];

        foreach ($compatGroups as $compatGroup)
        {
            $entry = [];
            $entry['brand'] = $brand;
            $entry['price'] = $price;

            if (!empty($customPrice))
                $entry['price'] = $customPrice;

            $entry['shipping'] = $shipping;
            $entry['mpn'] = $mpn;
            $entry['custom_mpn'] = $customMpn;
            $entry['id'] = time() . rand(1000, 9999);
            $entry['link'] = "https://europortparts.com/sku/{$mpn}/" . rand(10000, 99999);
            $entry['image_link'] = "https://europortparts.com/img/{$mpn}/cl1";
            $entry['item_condition'] = (stripos($brand, 'reman') !== false) ? 'refurbished' : 'new';
            $entry['availability'] = ($qty > 0 ? 'in stock' : 'out of stock');

            $entry['custom_label0'] = $custom_label0;
            $entry['custom_label1'] = $custom_label1;
            $entry['custom_label2'] = $custom_label2;
            $entry['custom_label3'] = $custom_label3;
            $entry['custom_label4'] = $custom_label4;

            $entry['category'] = 'Vehicles & Parts > Vehicle Parts & Accessories > Motor Vehicle Parts';

            if (!empty($customCategory))
                $entry['category'] = $customCategory;

            $title = "{$brand} {$name} ";
            $desc = "{$prefix} ";

            $lastMake = '';
            $lastModel = '';

            foreach ($compatGroup as $c)
            {
                if ($lastMake != $c['make'])
                {
                    $title .= $c['make'] . ' ';
                    $desc .= $c['make'] . ' ';
                }

                if ($lastModel != $c['model'])
                {
                    $title .= $c['model'] . ' ';
                    if ($lastModel != '') $desc .= ', ';
                    $desc .= $c['model'] . ' ';
                    $desc = str_replace(' ,', ',', $desc);
                }

                $desc .= $c['year'] . ' ';
                $lastMake = $c['make'];
                $lastModel = $c['model'];
            }

            $entry['title'] = trim(str_replace('  ', ' ', IntegraUtils::tokenTruncate("{$title}{$mpn}", 150)));
            $entry['description'] = trim(str_replace('  ', ' ', IntegraUtils::tokenTruncate($desc, 5000)));
            $entries[] = $entry;
        }

        return ['success' => true, 'msg' => '', 'entries' => $entries];
    }

    public function upload()
    {
        $store = 'europortparts';

        DB::beginTransaction();

        DB::update(<<<EOQ
INSERT INTO integra_prod.google_feed
(mpn,brand,title,description,price,shipping,availability,category,custom_label0,custom_label1,custom_label2,custom_label3,custom_label4,item_condition,link,image_link,id,store,custom_mpn)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
mpn=VALUES(mpn),
brand=VALUES(brand),
title=VALUES(title),
description=VALUES(description),
price=VALUES(price),
shipping=VALUES(shipping),
availability=VALUES(availability),
category=VALUES(category),
custom_label0=VALUES(custom_label0),
custom_label1=VALUES(custom_label1),
custom_label2=VALUES(custom_label2),
custom_label3=VALUES(custom_label3),
custom_label4=VALUES(custom_label4),
item_condition=VALUES(item_condition),
link=VALUES(link),
image_link=VALUES(image_link),
store=VALUES(store),
custom_mpn=VALUES(custom_mpn)
EOQ
            ,
            [
                Input::get('mpn'),
                Input::get('brand'),
                Input::get('title'),
                Input::get('description'),
                Input::get('price'),
                Input::get('shipping'),
                Input::get('availability'),
                Input::get('category'),
                Input::get('custom_label0'),
                Input::get('custom_label1'),
                Input::get('custom_label2'),
                Input::get('custom_label3'),
                Input::get('custom_label4'),
                Input::get('item_condition'),
                Input::get('link'),
                Input::get('image_link'),
                Input::get('id'),
                $store,
                Input::get('custom_mpn')
            ]);

        DB::insert("INSERT INTO integra_prod.google_feed_log_create (created_on, created_by, mpn, item_id) VALUES(NOW(), ?, ?, ?)",
            [ Cookie::get('user'), Input::get('mpn'), Input::get('id') ]);

        $result = GoogleUtils::upsertFeed(Input::get('id'), $store);
        if ($result['success']) DB::commit();
        else DB::rollBack();

        return $result;
    }
}
