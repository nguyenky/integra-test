<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Carbon\Carbon;

class GoogleClear extends Command
{
    protected $name = 'google:clear';
    protected $description = 'Clears Google feed entries.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        DB::disableQueryLog();

        $clientId = '943117101369-85b2thb807rod7lh8q1mtfe8ssi68o67.apps.googleusercontent.com';
        $clientSecret = 'I4cIvu73yQDBSGYl0EiAglgv';
        $refreshToken = '1/BlE_bU7TUtSTkood06aJ4qgrInVy4MlkA-J5dnqidb8';
        $merchantId = '9199475';

        set_time_limit(0);

        $pageNum = 0;
        $pageSize = 1000;

        while (true)
        {
            $this->info("Page " . ($pageNum + 1));
            $offset = $pageNum * $pageSize;
            $rows = DB::select("SELECT id FROM integra_prod.google_feed LIMIT {$offset}, {$pageSize}");
            $entries = [];
            $batchId = 0;

            foreach ($rows as $row)
            {
                $entries[] = [
                    'batchId' => $batchId++,
                    'merchantId' => $merchantId,
                    'method' => 'delete',
                    'productId' => 'online:en:US:' . $row['id']
                ];
            }

            if (empty($entries)) break;
            $pageNum++;

            $client = new Google_Client();
            $client->setApplicationName('Integra 2');
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->refreshToken($refreshToken);

            try {
                $service = new Google_Service_ShoppingContent($client);
                $params = new Google_Service_ShoppingContent_ProductsCustomBatchRequest();
                $params->setEntries($entries);
                $res = $service->products->custombatch($params);
                if (get_class($res) == 'Google_Service_ShoppingContent_ProductsCustomBatchResponse')
                {
                    $resEntries = $res->getEntries();
                    if (!empty($resEntries)) {
                        foreach ($resEntries as $entry) {
                            $entryErrors = $entry->getErrors();

                            if (!empty($entryErrors)) {
                                foreach ($entryErrors as $error) {
                                    $this->info($error->getMessage());
                                }
                            }
                            else $this->info('OK!');
                        }
                    }
                }
                else $this->info($res);
            }
            catch (Exception $e)
            {
                $this->info($e->getMessage());
            }

            sleep(1);
        }
    }
}
