<?php

class TrackingUtils
{
	public static function GetFedExETD($tracking)
	{
		try
		{
            $ch = curl_init('https://www.fedex.com/trackingCal/track');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_POSTFIELDS,
                "data=%7B%22TrackPackagesRequest%22%3A%7B%22appType%22%3A%22WTRK%22%2C%22uniqueKey%22%3A%22%22%2C%22processingParameters%22%3A%7B%7D%2C%22trackingInfoList%22%3A%5B%7B%22trackNumberInfo%22%3A%7B%22trackingNumber%22%3A%22{$tracking}%22%2C%22trackingQualifier%22%3A%22%22%2C%22trackingCarrier%22%3A%22%22%7D%7D%5D%7D%7D&action=trackpackages&locale=en_US&version=1&format=json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            curl_close($ch);

            $json = json_decode($res, true);
            $date = $json['TrackPackagesResponse']['packageList'][0]['estDeliveryDt'];
            if (empty($date)) $date = $json['TrackPackagesResponse']['packageList'][0]['actDeliveryDt'];
            return explode('T', $date)[0];
		}
		catch (Exception $e)
		{
		}

        return '';
	}

    public static function GetUpsETD($tracking)
    {
        try
        {
            $res = file_get_contents("http://wwwapps.ups.com/WebTracking/track?loc=en_US&track.x=Track&trackNums={$tracking}");

            $re = "/((Scheduled Delivery)|(Delivered On)).+?dd>.+?(?<etd>[\\d\\/]+)/s";
            preg_match($re, $res, $matches);

            $etd = '';
            if (isset($matches['etd']))
                $etd = $matches['etd'];

            $etd = trim($etd);
            $etd = strtotime(trim($etd));
            if (!$etd) return '';
            return date('Y-m-d', $etd);
        }
        catch (Exception $e)
        {
        }

        return '';
    }

    public static function GetUspsETD($tracking)
    {
        try
        {
            $res = file_get_contents("https://tools.usps.com/go/TrackConfirmAction.action?tRef=fullpage&tLc=1&text28777=&tLabels={$tracking}");
            $re = "/Delivery Day.+?value\">\\s*(?<etd>[^<]+)/s";
            preg_match($re, $res, $matches);

            $etd = '';
            if (isset($matches['etd']))
                $etd = $matches['etd'];

            $etd = trim($etd);
            $etd = strtotime(trim($etd));
            if (!$etd) return '';
            return date('Y-m-d', $etd);
        }
        catch (Exception $e)
        {
        }

        return '';
    }

    public static function GetETD($tracking)
    {
        if (stripos($tracking, '1Z') === 0)
            return self::GetUpsETD($tracking);

        $etd = self::GetFedExETD($tracking);
        if (empty($etd)) $etd = self::GetUpsETD($tracking);
        if (empty($etd)) $etd = self::GetUspsETD($tracking);
        return $etd;
    }
}

?>
