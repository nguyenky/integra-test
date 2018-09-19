<?php

require_once(__DIR__ . '/../system/config.php');
require_once(__DIR__ . '/../system/utils.php');
require_once(__DIR__ . '/../system/swift/swift_required.php');

set_time_limit(0);
ini_set('memory_limit', '512M');

mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
mysql_select_db(DB_SCHEMA);

$q = <<<EOD
SELECT c.id AS campaign_id, r.id AS recipient_id, r.email, c.subject, c.body
FROM campaigns c, recipients r
WHERE c.id = r.campaign_id
AND r.sent_date IS NULL
AND DATE(c.send_date) <= CURDATE()
EOD;
$res = mysql_query($q);

while ($row = mysql_fetch_row($res))
{
	$item['campaign_id'] = $row[0];
	$item['recipient_id'] = $row[1];
	$item['email'] = $row[2];
	$item['subject'] = $row[3];
	$item['body'] = $row[4];
	
	$items[] = $item;
}

if (empty($items))
	return;

$transport = Swift_SmtpTransport::newInstance(CAMPAIGN_EMAIL_HOST, CAMPAIGN_EMAIL_PORT)
			->setUsername(CAMPAIGN_EMAIL_USERNAME)
			->setPassword(CAMPAIGN_EMAIL_PASSWORD);
			
$mailer = Swift_Mailer::newInstance($transport);
$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(300, 90));

foreach ($items as $item)
{
	translateVars($item['subject'], $item['email']);
	translateVars($item['body'], $item['email']);
	sendEmail($mailer, $item['campaign_id'], $item['recipient_id'], $item['email'], $item['subject'], $item['body']);
}

mysql_close();
return;

function translateVars(&$input, $email)
{
	$input = str_replace('[EMAIL]', $email, $input);
	
	if (stripos($input, '[BUYERID]') !== false)
	{
		$input = str_replace('[BUYERID]',
			equery("SELECT buyer_id FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[NAME]') !== false)
	{
		$input = str_replace('[NAME]',
			equery("SELECT name FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[CITY]') !== false)
	{
		$input = str_replace('[CITY]',
			equery("SELECT city FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[STATECODE]') !== false)
	{
		$state = convert_state(preg_replace('/[^a-zA-Z0-9 ]/s', '', equery("SELECT state FROM customers WHERE email = '%s'", $email)), 'abbrev');
		$input = str_replace('[STATECODE]', $state, $input);
	}
	
	if (stripos($input, '[STATENAME]') !== false)
	{
		$state = convert_state(preg_replace('/[^a-zA-Z0-9 ]/s', '', equery("SELECT state FROM customers WHERE email = '%s'", $email)), 'name');
		$input = str_replace('[STATENAME]', $state, $input);
	}
	
	if (stripos($input, '[PHONE]') !== false)
	{
		$input = str_replace('[PHONE]',
			equery("SELECT phone FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[LASTAGENT]') !== false)
	{
		$input = str_replace('[LASTAGENT]',
			equery("SELECT last_agent FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERDATE]') !== false)
	{
		$input = str_replace('[LASTORDERDATE]',
			equery("SELECT last_order FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERRECORDNUM]') !== false)
	{
		$input = str_replace('[LASTORDERRECORDNUM]',
			equery("SELECT record_num FROM sales WHERE email = '%s' ORDER BY order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERTOTAL]') !== false)
	{
		$input = str_replace('[LASTORDERTOTAL]',
			equery("SELECT total FROM sales WHERE email = '%s' ORDER BY order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERTRACKING]') !== false)
	{
		$input = str_replace('[LASTORDERTRACKING]',
			equery("SELECT tracking_num FROM sales WHERE email = '%s' ORDER BY order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERCARRIER]') !== false)
	{
		$input = str_replace('[LASTORDERCARRIER]',
			equery("SELECT carrier FROM sales WHERE email = '%s' ORDER BY order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERITEMNAME]') !== false)
	{
		$input = str_replace('[LASTORDERITEMNAME]',
			equery("SELECT si.description FROM sales_items si, sales s WHERE s.id = si.sales_id AND email = '%s' ORDER BY s.order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERITEMSKU]') !== false)
	{
		$input = str_replace('[LASTORDERITEMSKU]',
			equery("SELECT si.sku FROM sales_items si, sales s WHERE s.id = si.sales_id AND email = '%s' ORDER BY s.order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[LASTORDERITEMPRICE]') !== false)
	{
		$input = str_replace('[LASTORDERITEMPRICE]',
			equery("SELECT si.unit_price FROM sales_items si, sales s WHERE s.id = si.sales_id AND email = '%s' ORDER BY s.order_date DESC LIMIT 1", $email),
			$input);
	}
	
	if (stripos($input, '[TOTALORDERCOUNT]') !== false)
	{
		$input = str_replace('[TOTALORDERCOUNT]',
			equery("SELECT order_count FROM customers WHERE email = '%s'", $email),
			$input);
	}
	
	if (stripos($input, '[TOTALORDERAMOUNT]') !== false)
	{
		$input = str_replace('[TOTALORDERAMOUNT]',
			equery("SELECT order_total FROM customers WHERE email = '%s'", $email),
			$input);
	}
}

function equery($q, $email)
{
	if (stripos($q, '%s') !== false)
		$q = sprintf($q, $email);

	$row = mysql_fetch_row(mysql_query($q));
	return $row[0];
}

function sendEmail($mailer, $campaignId, $recipientId, $email, $subject, $body)
{
	try
	{
		$message = Swift_Message::newInstance($subject)
					->setFrom(CAMPAIGN_EMAIL_USERNAME)
					->setFrom(array(CAMPAIGN_EMAIL_USERNAME => CAMPAIGN_EMAIL_FROM))
					->setBody($body, 'text/html')
					->setTo($email);
					//->setTo('kbcware@yahoo.com');

		//$mailer->send($message);
		//die();
		//exit();

		if ($mailer->send($message))
		{
			mysql_query("UPDATE recipients SET SENT_DATE = NOW() WHERE id = ${recipientId}");
			file_put_contents("../logs/campaign_sender/${campaignId}.txt", date('Y-m-d H:i:s') . "] Successfully sent to ${email}:\n${body}\n\n", FILE_APPEND);
		}
		else
			file_put_contents("../logs/campaign_sender/${campaignId}.txt", date('Y-m-d H:i:s') . "] Error while sending to ${email}:\n${body}\n\n", FILE_APPEND);
	}
	catch (Exception $e)
	{
		file_put_contents("../logs/campaign_sender/${campaignId}.txt", date('Y-m-d H:i:s') . "] Error while sending to ${email}: " . $e->getMessage() . "\n${body}\n\n", FILE_APPEND);
	}
}

?>
