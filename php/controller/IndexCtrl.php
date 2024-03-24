<?php
namespace controller;

class IndexCtrl
{

	public static function migrateGET ($f3)
	{
		$db_in = new \DB\SQL('sqlite:'.$f3->get("db_in.file"));
		$db_out = $f3->get("db"); /* @var $db_out \DB\SQL\Mapper */
		
		// check db's connected
		if(!$db_in->exists("lane")) {
			die("input database error");
		}
		if(!$db_out->exists("columns")) {
			die("output database error");
		}
		
		// remove output columns
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
	}
	
}
