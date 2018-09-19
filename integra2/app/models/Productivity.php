<?php
class Productivity extends Eloquent {

	const DAILY = 1;
	const WEEKLY = 2;
	const MONTHLY = 3;

	
	/** =============================================================
	*	array(
	*			0=> array('d' => date, 'u' => user, 'c' => c), 
	*			1=> array('d' => date, 'u' => user, 'c' => c), 
	*		  ) 
	**  ==============================================================
	*/

	public function getEbayCreated($viewMode, $startDate, $endDate) {
		$isCreated = 1;
		
		return $this->getData($viewMode, $startDate, $endDate, $isCreated);
	}

	private function getData($viewMode, $startDate, $endDate, $isCreated) {
		$sql = $this->prepareSql($viewMode);

		$results = DB::select($sql, [$startDate, $endDate, $isCreated]);
		if($viewMode == self::WEEKLY) {
			$results = $this->prepareWeeklyData($results, $startDate, $endDate);
		}

		return $results;
	}

	public function getEbayEdited($viewMode, $startDate, $endDate) {
		$isCreated = 0;

		return $this->getData($viewMode, $startDate, $endDate, $isCreated);
	}


	public function buildDataToDisplayOnChart($data, $startDate, $endDate, $viewMode) {
		$axisX = $this->buildAxisX($startDate, $endDate, $viewMode);
		$series = $this->chartSeries($axisX, $data);
		$chartData = $this->chartData($axisX, $series, $data);
		return array('axisX' => $axisX, 'series' => $series, 'chartData' => $chartData);
	}

	public function getCreatedStats($startDate, $endDate) {
		$sql = "
			SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, 
			    FORMAT(AVG(output), 2) AS average, FORMAT(STD(output), 2) AS variance, 
			    SUM(output) AS total, MIN(output) AS min, MAX(output) AS max, 
			    ROUND(SUM(IF(output >= 8, 1, 0)) * 100 / COUNT(*)) AS hit_pct, 
			    SUM(IF(output < 8, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
			FROM productivity
			WHERE task = 3
			AND hr BETWEEN ? AND ? 
			GROUP BY 1, 2
			ORDER BY 1 DESC, 8 DESC
		";

		$stats = DB::select($sql, [$startDate, $endDate]);
		DB::statement("CALL integra_prod.compute_ebay_new_productivity(CURDATE())");
		return $stats;
	}

	public function getEditedStats($startDate, $endDate) {
		$sql = "
			SELECT DATE(hr) AS date, REPLACE(email, '@eocenterprise.com', '') AS user, 
			    FORMAT(AVG(output), 2) AS average, FORMAT(STD(output), 2) AS variance, 
			    SUM(output) AS total, MIN(output) AS min, MAX(output) AS max, 
			    ROUND(SUM(IF(output >= 18, 1, 0)) * 100 / COUNT(*)) AS hit_pct, 
			    SUM(IF(output < 18, -1, 0)) + ROUND(SQRT(AVG(output) * SUM(output) / (STD(output)+1))) AS score
			FROM productivity
			WHERE task = 2
			AND hr BETWEEN ? AND ? 
			GROUP BY 1, 2
			ORDER BY 1 DESC, 8 DESC
		";

		$stats = DB::select($sql, [$startDate, $endDate]);
		DB::statement("CALL integra_prod.compute_ebay_edit_productivity(CURDATE())");
		return $stats;
	}

	private function buildAxisX($startDate, $endDate, $viewMode) {
		$axisX = array();
		
		$date = clone $startDate;
		$step = $this->getDateSteps($viewMode);
		while($date <= $endDate) {
			$d = $this->dateOnAxisX($date, $viewMode);
			if(!in_array($d, $axisX)) {
				array_push($axisX, $d);
			}
			$date = $date->modify($step.' days');
		}

		return $axisX;
	}

	private function dateOnAxisX($date, $viewMode) {
		$axisXDate = '';
		switch ($viewMode) {
			case self::DAILY:
				$axisXDate = $date->format('m/d');
				break;
			case self::WEEKLY:
				$week = $this->getStartEndWeekByDate($date);
				$axisXDate = $week[0];

				break;
			case self::MONTHLY:
				$axisXDate = date('m/Y', $date->getTimestamp());
				break;
			default:
				$axisXDate = $date->format('m/d');
				break;
		}

		return $axisXDate;

	}

	private function getFormatDateForAxisX($viewMode) {
		$format = '';
		switch ($viewMode) {
			case self::DAILY:
				$format = 'm/d';
				break;
			case self::WEEKLY:
				$format = 'm/d';
				break;
			case self::MONTHLY:
				$format = 'm/Y';
				break;
			default:
				$format = 'm/d';
				break;
		}

		return $format;
	}

	private function getDateSteps($viewMode) {
		$step = 0;
		switch ($viewMode) {
			case self::DAILY:
				$step = 1;
				break;

			case self::WEEKLY:
				$step = 7;
				break;

			case self::MONTHLY:
				$step = 32;
				break;
			
			default:
				$step = 1;
				break;
		}

		return $step;

	}

	private function chartSeries($axisX, $records) {
		$series = array();
		foreach($records as $record) {
		    if(!in_array($record['u'], $series)) {
		        array_push($series, $record['u']);
		    }            
		}
		return $series;
	}

	private function chartData($axisX, $series, $records) {
		$chartData = array();
		foreach($series as $ser) {
		    $current = array();
		    foreach($axisX as $time) {
		        $val = 0;
		        foreach ($records as $record) {
		            if ($record['u'] == $ser && $record['d'] == $time) {
		                $val = $record['c'];
		                break;
		            }
		        }
		        array_push($current, $val);
		    }
		    array_push($chartData, $current);
		}
		return $chartData;
	}

	private function prepareWeeklyData($data, $startDate, $endDate) {

		$weeks = $this->weeklyTimeFrame($startDate, $endDate);

		$users = array();

		foreach($data as $row) {
			if(!in_array($row['u'], $users)) {
				array_push($users, $row['u']);
			}
		}
		
		$results = array();
		
		foreach($weeks as $week) {
			foreach($users as $user) {
				$weekData = $this->collectDataInWeek($week, $user, $data);
				if(!empty($weekData)) {
					array_push($results, $weekData);
				}
			}
		}

		return $results;
	}

	private function collectDataInWeek($week, $user, $data) {
		$weekData = array();
		$weekData['c'] = 0;
		$found = 0;
		foreach($data as $row) {
			if($row['d'] >= $week[0] && $row['d'] <= $week[1]) {
				$weekData['d'] = $week[0];
				if($row['u'] == $user) {
					$found = 1;
					$weekData['u'] = $row['u'];
					$weekData['c'] += (int)$row['c'];
				}

			}
		}
		return $found ? $weekData : array();
	}

	
	/** 
	** ================================================
	** GET Axis X in chart for weekly
	** INPUT: Start Date, End Date as date
	** OUTPUT: array(
	**					0 => array(start, end),
	**					1 => array(start, end),
	**					........
	**				)
	** ================================================
	*/
	private function weeklyTimeFrame($startDate, $endDate) {
		$weeks = array();
		$date = clone $startDate;
		while($date <= $endDate) {
			$week = $this->getStartEndWeekByDate($date);
			array_push($weeks, $week);
			$date = $date->modify('+7 day');
		}

		return $weeks;
	}

	private function getStartEndWeekByDate($date) {
		$w = $date->format('W');
		$y = $date->format('Y');
		$from = date("m/d", strtotime("{$y}-W{$w}-1"));
		$end = date("m/d", strtotime("{$y}-W{$w}-7"));
		
		return [$from, $end];
	}

	private function prepareSql($viewMode) {
		
		switch ($viewMode) {
			case self::DAILY:
				return $this->prepareDailySql();

			case self::WEEKLY: 
			 	return $this->prepareWeeklySql();

			 case self::MONTHLY: 
			 	return $this->prepareMonthlySql();
			default:
				return $this->prepareDailySql();
				break;
		}
	}


	private function prepareDailySql() {
		$sql = "
			SELECT DATE_FORMAT( created_on,  '%m/%d' ) AS d, created_by AS u, COUNT(DISTINCT item_id) AS c
			FROM eoc.ebay_edit_log
			WHERE created_on BETWEEN ? AND ?
			AND is_new = ?
			GROUP BY 1, 2
			ORDER BY created_on
		";
		return $sql;
	}

	private function prepareWeeklySql() {
		return $this->prepareDailySql();
	}

	private function prepareMonthlySql() {
		$sql = "
			SELECT DATE_FORMAT( created_on,  '%m/%Y' ) AS d, created_by AS u, COUNT(DISTINCT item_id) AS c
			FROM eoc.ebay_edit_log
			WHERE created_on BETWEEN ? AND ?
			AND is_new = ?
			GROUP BY 1, 2
			ORDER BY created_on
		";
		return $sql;
	}


}