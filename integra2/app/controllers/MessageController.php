<?php

use \Carbon\Carbon;

class MessageController extends \BaseController
{
    public function graph()
    {
        if (Input::has('days'))
            $days = Input::get('days');
        else $days = 2;

        $storeFilter = " AND source_name != 'EOC Management' ";

        if (Input::has('store'))
        {
            $store = Input::get('store');
            if ($store == 'eBay')
                $storeFilter = " AND (sourceID = 0 OR sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_ebay = 1)) ";
            else if ($store == 'Amazon')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_amazon = 1) ";
            else if ($store == 'Online Stores')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_magento = 1) ";
        }

        $agentFilter = '';
        $agentFilter2 = '';

        if (Input::has('agent')) {
            $agent = Input::get('agent');
            if ($agent != 'All') {
                $agentFilter = " AND replied_by = '" . str_replace("'", '', $agent) . "' ";
                $agentFilter2 = " AND log_user = '" . str_replace("'", '', $agent) . "' ";
            }
        }

            $activities = DB::select(<<<EOQ
SELECT DATE_FORMAT(receive_date_norm, '%m/%d %H:00') AS d, 'Incoming' as u, COUNT(*) AS c
FROM eoc.api_getmymessages
WHERE receive_date_work >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
{$storeFilter}
GROUP BY 1, 2
UNION ALL
SELECT DATE_FORMAT(log_timestamp, '%m/%d %H:00') AS d, 'Compositions' as u, COUNT(*) AS c
FROM eoc.api_messages_logs
WHERE log_timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
AND log_activity IN ('compose', 'auto-response')
{$agentFilter2}
GROUP BY 1, 2
UNION ALL
SELECT DATE_FORMAT(log_timestamp, '%m/%d %H:00') AS d, 'Flags' as u, COUNT(*) AS c
FROM eoc.api_getmymessages am, eoc.api_messages_logs aml
WHERE aml.message_id = am.id
AND log_activity = 'flagged'
AND am.receive_date_work >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
{$agentFilter2}
GROUP BY 1, 2
UNION ALL
SELECT DATE_FORMAT(reply_date_norm, '%m/%d %H:00') AS d, 'Replies' as u, COUNT(*) AS c
FROM eoc.api_getmymessages
WHERE receive_date_work >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
{$storeFilter}
{$agentFilter}
AND reply_date_norm IS NOT NULL
GROUP BY 1, 2
ORDER BY 1
EOQ
            , [$days, $days, $days, $days]);

        $agents = DB::select(<<<EOQ
SELECT email
FROM integra_prod.users
WHERE service_agent = 1
ORDER BY 1
EOQ
        );

        return ['activities' => $activities, 'agents' => $agents];
    }

    public function table()
    {
        $date = Input::get('date');

        $storeFilter = "";

        if (Input::has('store'))
        {
            $store = Input::get('store');
            if ($store == 'eBay')
                $storeFilter = " AND (sourceID = 0 OR sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_ebay = 1)) ";
            else if ($store == 'Amazon')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_amazon = 1) ";
            else if ($store == 'Online Stores')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_magento = 1) ";
        }

        $agentFilter = '';

        if (Input::has('agent')) {
            $agent = Input::get('agent');
            if ($agent != 'All') {
                $agentFilter = " AND log_user = '" . str_replace("'", '', $agent) . "' ";
            }
        }

        $incoming = DB::select(<<<EOQ
SELECT COUNT(*) AS c
FROM eoc.api_getmymessages
WHERE receive_date_norm >= ?
AND DATE(receive_date_norm) = ?
AND source_name != 'EOC Management'
{$storeFilter}
EOQ
        , [$date, $date]);

        $flagged = DB::select(<<<EOQ
SELECT log_user AS u, COUNT(*) AS c
FROM eoc.api_messages_logs aml, eoc.api_getmymessages am
WHERE log_timestamp >= ?
AND DATE(log_timestamp) = ?
AND log_activity = 'flagged'
AND am.id = aml.message_id
AND source_name != 'EOC Management'
{$storeFilter}
{$agentFilter}
GROUP BY 1
EOQ
        , [$date, $date]);

        $reply = DB::select(<<<EOQ
SELECT log_user AS u, COUNT(*) AS c
FROM eoc.api_messages_logs aml, eoc.api_getmymessages am
WHERE log_timestamp >= ?
AND DATE(log_timestamp) = ?
AND log_activity = 'reply'
AND am.id = aml.message_id
AND source_name != 'EOC Management'
{$storeFilter}
{$agentFilter}
GROUP BY 1
EOQ
        , [$date, $date]);

        $compose = DB::select(<<<EOQ
SELECT log_user AS u, COUNT(*) AS c
FROM eoc.api_messages_logs aml
WHERE log_timestamp >= ?
AND DATE(log_timestamp) = ?
AND log_activity IN ('compose', 'auto-response')
{$agentFilter}
GROUP BY 1
EOQ
        , [$date, $date]);

        $agents = DB::select(<<<EOQ
SELECT email
FROM integra_prod.users
WHERE service_agent = 1
ORDER BY 1
EOQ
        );

        return ['totals' =>
            [
                'incoming' => $incoming[0]['c'],
                'compose' => array_sum(IntegraUtils::array_column($compose, 'c')),
                'reply' => array_sum(IntegraUtils::array_column($reply, 'c')),
                'flagged' => array_sum(IntegraUtils::array_column($flagged, 'c'))
            ],
            'reply' => $reply,
            'flagged' => $flagged,
            'compose' => $compose,
            'agents' => $agents];
    }

    public function replies()
    {
        $date = Input::get('date');

        if (empty($date))
            $date = date('Y-m-d');

        $storeFilter = "";

        if (Input::has('store'))
        {
            $store = Input::get('store');
            if ($store == 'eBay')
                $storeFilter = " AND (sourceID = 0 OR sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_ebay = 1)) ";
            else if ($store == 'Amazon')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_amazon = 1) ";
            else if ($store == 'Online Stores')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_magento = 1) ";
        }

        $agentFilter = '';

        if (Input::has('agent')) {
            $agent = Input::get('agent');
            if ($agent != 'All') {
                $agentFilter = " AND log_user = '" . str_replace("'", '', $agent) . "' ";
            }
        }

        return DB::select(<<<EOQ
SELECT aml.log_timestamp AS d, aml.log_user AS u, m.sender AS b, ar.response AS m
FROM eoc.api_messages_logs aml, eoc.api_response ar, eoc.api_getmymessages m
WHERE aml.log_timestamp = ar.timestamp
AND ar.messageID = m.messageID
AND m.id = aml.message_id
AND aml.log_activity = 'reply'
AND DATE(aml.log_timestamp) = ?
AND aml.log_timestamp >= ?
{$storeFilter}
{$agentFilter}
ORDER BY 1
EOQ
            , [$date, $date]);
    }

    public function flagged()
    {
        $date = Input::get('date');

        if (empty($date))
            $date = date('Y-m-d');

        $storeFilter = "";

        if (Input::has('store'))
        {
            $store = Input::get('store');
            if ($store == 'eBay')
                $storeFilter = " AND (sourceID = 0 OR sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_ebay = 1)) ";
            else if ($store == 'Amazon')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_amazon = 1) ";
            else if ($store == 'Online Stores')
                $storeFilter = " AND sourceID IN (SELECT id FROM eoc.api_eoc_email_accounts WHERE is_magento = 1) ";
        }

        $agentFilter = '';

        if (Input::has('agent')) {
            $agent = Input::get('agent');
            if ($agent != 'All') {
                $agentFilter = " AND log_user = '" . str_replace("'", '', $agent) . "' ";
            }
        }

        return DB::select(<<<EOQ
SELECT aml.log_timestamp AS d, aml.log_user AS u, m.sender AS b, m.subject AS m
FROM eoc.api_messages_logs aml, eoc.api_getmymessages m
WHERE m.id = aml.message_id
AND aml.log_activity = 'flagged'
AND DATE(aml.log_timestamp) = ?
AND aml.log_timestamp >= ?
{$storeFilter}
{$agentFilter}
ORDER BY 1
EOQ
            , [$date, $date]);
    }

    public function compositions()
    {
        $date = Input::get('date');

        if (empty($date))
            $date = date('Y-m-d');

        $agentFilter = '';

        if (Input::has('agent')) {
            $agent = Input::get('agent');
            if ($agent != 'All') {
                $agentFilter = " AND cm_user = '" . str_replace("'", '', $agent) . "' ";
            }
        }

        return DB::select(<<<EOQ
SELECT cm_timestamp AS d, cm_user AS u, cm_to AS b, cm_body AS m
FROM eoc.api_composed_messages
WHERE DATE(cm_timestamp) = ?
AND cm_timestamp >= ?
{$agentFilter}
ORDER BY 1
EOQ
            , [$date, $date]);
    }
}
