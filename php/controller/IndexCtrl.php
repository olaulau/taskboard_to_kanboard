<?php
namespace controller;

class IndexCtrl
{

	public static function beforeRoute ()
	{
		
	}
    
	
	public static function afterRoute ()
	{
		
	}

	
	public static function indexGET ()
	{
		$view = new \View();
		echo $view->render('index.phtml');
	}
	
	
	public static function migrateGET ()
	{
		$f3 = \Base::instance();
		$db_in = $f3->get("db_in"); /* @var $db_in \DB\SQL\Mapper */
		$db_out = $f3->get("db_out"); /* @var $db_in \DB\SQL\Mapper */
		
		
		// check db's connected
		if(!$db_in->exists("lane")) {
			die("input database error");
		}
		if(!$db_out->exists("columns")) {
			die("output database error");
		}
		
		
		// remove  output columns
		$out_columns_wrapper = new \DB\SQL\Mapper($db_out, "columns");
		$out_columns = $out_columns_wrapper->find(['project_id = ?', $f3->get("kanboard.project_id")], ["order" => "position DESC"]);
		foreach ($out_columns as $out_column) {
			$out_column->erase();
		}
		
		// copy columns
		$in_lane_wrapper = new \DB\SQL\Mapper($db_in, "lane");
		$in_lanes = $in_lane_wrapper->find(['board_id = ?', $f3->get("taskboard.board_id")], ["order" => "position ASC"]);
		$lane_to_column = [];
		foreach ($in_lanes as $i => $in_lane) {
			$column = new \DB\SQL\Mapper($db_out, "columns");
			$column->title = $in_lane->name;
			$column->position = $i+1;
			$column->project_id = $f3->get("kanboard.project_id");
			$column->save();
			$lane_to_column[$in_lane->id] = $column->id;
		}
		
		
		// copy tickets
		$in_item_wrapper = new \DB\SQL\Mapper($db_in, "item");
		$now = time();
		$colors = [
			"#c3f4b5" => "green",
			"#ffbaba" => "red",
			"#ffffe0" => "yellow",
			"#bee7f4" => "blue",
		];
		
		$out_swimlane_wrapper = new \DB\SQL\Mapper($db_out, "swimlanes");
		$default_swimlane = $out_swimlane_wrapper->findone(["project_id = ? AND position = ?", $f3->get("kanboard.project_id"), 1], []);
		
		foreach ($lane_to_column as  $lane_id => $column_id) {
			$in_items = $in_item_wrapper->find(['lane_id = ?', $lane_id], ["order" => "position ASC"]);
			foreach ($in_items as $i => $in_item) {
				$task = new \DB\SQL\Mapper($db_out, "tasks");
				$task->title = $in_item->title;
				$task->description = $in_item->description;
				$task->date_creation = $now;

				$task->date_due = null;
				$d = \DateTime::createFromFormat("m/d/Y", $in_item->due_date);
				if($d !== false) {
					$task->date_due = $d->getTimestamp();
				}
				
				$task->color_id = null;
				if( $in_item->color && !empty($colors[ $in_item->color ]) ) {
					$color = $colors[ $in_item->color ];
					$task->color_id = $color;
				}
				
				$task->project_id = $f3->get("kanboard.project_id");
				$task->column_id = $column_id;
				$task->position = $i+1;
				$task->date_modification = $now;
				$task->swimlane_id = $default_swimlane->id;
				$task->date_moved = $now;
				$task->save();
			}
		}
		
		die;
	}
	
	
	public static function priorityGET ()
	{
		$f3 = \Base::instance();
		$db_out = $f3->get("db_out"); /* @var $db_in \DB\SQL\Mapper */
		
		$sql = '
			SELECT		c.title AS étape, t.title AS titre, t.description, t.date_due as délai
			FROM		tasks t
			INNER JOIN	columns c
				ON		t.column_id = c.id
			WHERE		c.title  NOT LIKE "%FACTURATION%"
				AND		t.date_due != ""
				AND		c.project_id = ?
			ORDER BY	t.date_due ASC, c.position ASC
		';
		$data = $db_out->exec($sql, [$f3->get("kanboard.project_id")]);
		
		$data2 = [];
		foreach ($data as $id => $row) {
			if (!empty ($row['délai'])) {
				$date = new \DateTime();
				$date->setTimestamp ( $row['délai'] );
				$real_date = $date->format('Y-m-d');
				unset($row['délai']);
		    	if (!isset ($data2[$real_date])) {
		    		$data2[$real_date] = [];
		    	}
		    	$data2[$real_date][] = $row;
			}
		}
		ksort($data2);
		
		$months = [
		    'en' => [
		        'January',
		        'February',
		        'March',
		        'April',
		        'May',
		        'June',
		        'July',
		        'August',
		        'September',
		        'October',
		        'November',
		        'December',
		    ],
		    'fr' => [
		        'Janvier',
		        'Février',
		        'Mars',
		        'Avril',
		        'Mai',
		        'Juin',
		        'Juillet',
		        'Août',
		        'Septembre',
		        'Octobre',
		        'Novembre',
		        'Décembre',
		    ]
		];
		
		$days_of_week = [
		    'en' => [
		        'Sunday',
		        'Monday',
		        'Tuesday',
		        'Wednesday',
		        'Thursday',
		        'Friday',
		        'Saturday',
		    ],
		    'fr' => [
		        'Dimanche',
		        'Lundi',
		        'Mardi',
		        'Mercredi',
		        'Jeudi',
		        'Vendredi',
		        'Samedi',
		    ]
		];
		
		$f3->set("PAGE.title", "Planning par ordre de délai");
		$f3->set("data2", $data2);
		$f3->set("months", $months);
		$f3->set("days_of_week", $days_of_week);
		
		
		$view = new \View();
		echo $view->render('priority.phtml');
	}
	
}
