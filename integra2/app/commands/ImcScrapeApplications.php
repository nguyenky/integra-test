<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ImcScrapeApplications extends Command
{
	protected $name = 'imc:scrape_applications';
    protected $description = 'Scrapes application data from IMC for products that have application numbers.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
	{
        DB::disableQueryLog();

        $url = 'http://www.imcparts.net/webapp/wcs/stores/servlet/';

        $config = Config::get('integra');
        $username = $config['imc_web']['username'];
        $password = $config['imc_web']['password'];
        $store = $config['imc_web']['store'];

        $cookie = tempnam("/tmp", "imcweb");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_URL, "{$url}Logon");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "storeId={$store}&catalogId={$store}&langId=-1&reLogonURL=LogonForm&URL=HomePageView%3Flogon*%3D&postLogonPage=HomePageView&logonId={$username}&logonPassword={$password}&logonImg.x=32&logonImg.y=15");
        $output = curl_exec($ch);

        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_POST, false);

        $q = <<<EOQ
SELECT em.application_id, em.make, GROUP_CONCAT(DISTINCT(em.model)) AS models
FROM magento.elite_note en, magento.elite_1_mapping em
WHERE en.id = em.note_id
AND em.application_id > ''
AND en.message NOT LIKE '%osition%'
GROUP BY 1, 2
EOQ;
        $rows = DB::select($q);
        $i = 1;
        $max = count($rows);

        foreach ($rows as $row)
        {
            $this->info("{$i}/{$max}: " . $row['application_id'] . ' - ' . $row['make']);
            $i++;

            $curUrl = "${url}AjaxApplicationInformationURL" . "?applicationNumber=" . urlencode($row['application_id']) . "&makeName=" . urlencode($row['make']);

            $models = explode(',', $row['models']);
            foreach ($models as $model)
                $curUrl .= "&modelName=" . urlencode($model);

            curl_setopt($ch, CURLOPT_URL, $curUrl);
            $output = curl_exec($ch);

            if (stripos($output, 'NotesBean') === false) continue;
            $res = json_decode($output, true);
            if (!isset($res['vehicles'])) continue;

            foreach ($res['vehicles'] as $vehicle)
            {
                if (!isset($vehicle['position']) || empty($vehicle['position'])) continue;
                $this->info('=> Position: ' . $vehicle['position']);

                DB::update('UPDATE magento.elite_1_mapping SET `position` = ? WHERE application_id = ? AND make = ?', [$vehicle['position'], $row['application_id'], $row['make']]);

                break;
            }
        }
	}
}
