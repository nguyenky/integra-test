<?php

use Illuminate\Console\Command;

class NoteSplit extends Command
{
	protected $name = 'note:split';
    protected $description = 'Split the notes to separate qty and position.';

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
AND (en.message LIKE '%Position:%' OR en.message LIKE '%Qty:%')
AND em.qty_required IS NULL
LIMIT 1000
EOQ;
            $rows = DB::select($q);
            // empty queue
            if (empty($rows)) break;

            $i = 1;
            $max = count($rows);

            foreach ($rows as $row)
            {
                $notes = explode(';', $row['message']);
                $position = '';
                $qty = 1;
                foreach ($notes as $note)
                {
                    $fields = explode(':', $note);
                    if (count($fields) < 2) continue; // must have key and value
                    if (trim($fields[0]) == 'Position') $position = trim($fields[1]);
                    else if (trim($fields[0]) == 'Qty') $qty = intval(str_replace('(', '', str_replace(')', '', trim($fields[1]))));
                }

                if (!empty($position))
                    DB::update("UPDATE magento.elite_1_mapping SET `position` = ? WHERE id = ? AND (position IS NULL OR position = '')", [$position, $row['id']]);

                if (empty($qty)) $qty = 1;
                DB::update('UPDATE magento.elite_1_mapping SET qty_required = ? WHERE id = ?', [$qty, $row['id']]);

                $this->info("{$i}/{$max} - ID: " . $row['id'] . ", Position: {$position}, Qty: {$qty}");
                $i++;
            }
        }
    }
}
