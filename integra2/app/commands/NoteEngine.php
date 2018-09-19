<?php

use Illuminate\Console\Command;

class NoteEngine extends Command
{
	protected $name = 'note:engine';
    protected $description = 'Extract engine size from notes.';

	public function __construct()
	{
		parent::__construct();
	}

	public function fire()
    {
        DB::disableQueryLog();

        while (true)
        {
            $q = <<<EOQ
SELECT em.id, en.message
FROM magento.elite_1_mapping em, magento.elite_note en, magento.catalog_product_entity cpe
WHERE em.note_id = en.id
AND em.entity_id = cpe.entity_id
AND en.message REGEXP '[1-9][:digit:]?(.[:digit:])?[:space:]?L'
AND em.engine_size IS NULL
LIMIT 1000
EOQ;
            $rows = DB::select($q);
            // empty queue
            if (empty($rows)) break;

            $i = 1;
            $max = count($rows);

            foreach ($rows as $row)
            {
                $re = "/(?<eng>[1-9]\\d?(\\.\\d)?)\\s*L/";
                preg_match($re, $row['message'], $matches);

                if (isset($matches['eng']))
                {
                    $eng = $matches['eng'];
                    DB::update("UPDATE magento.elite_1_mapping SET engine_size = ? WHERE id = ?", [$eng, $row['id']]);
                }
                else
                {
                    $eng = '';
                    DB::update("UPDATE magento.elite_1_mapping SET engine_size = ? WHERE id = ?", [$eng, $row['id']]);
                }

                $this->info("{$i}/{$max} - ID: " . $row['id'] . ", Engine: {$eng}");
                $i++;
            }
        }
    }
}
