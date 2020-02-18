<?php
class WorkspaceWidget_Calendar extends Extension_WorkspaceWidget implements ICerbWorkspaceWidget_ExportData {
	function render(Model_WorkspaceWidget $widget) {
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl = DevblocksPlatform::services()->template();
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		
		@$month = DevblocksPlatform::importGPC($_REQUEST['month'], 'integer', null);
		@$year = DevblocksPlatform::importGPC($_REQUEST['year'], 'integer', null);
		
		@$calendar_id_template = $widget->params['calendar_id'];
		
		$labels = $values = $merge_token_labels = $merge_token_values = [];
		
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $active_worker, $merge_token_labels, $merge_token_values, null, true, true);
		
		CerberusContexts::merge(
			'current_worker_',
			'Current Worker:',
			$merge_token_labels,
			$merge_token_values,
			$labels,
			$values
		);
		
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKSPACE_WIDGET, $widget, $merge_token_labels, $merge_token_values, null, true, true);
		
		CerberusContexts::merge(
			'widget_',
			'Widget:',
			$merge_token_labels,
			$merge_token_values,
			$labels,
			$values
		);
		
		$dict = DevblocksDictionaryDelegate::instance($values);
		
		$widget->_loadDashboardPrefsForWorker($active_worker, $dict);
		
		$calendar_id = $tpl_builder->build($calendar_id_template, $dict);
		
		if(empty($calendar_id) || null == ($calendar = DAO_Calendar::get($calendar_id))) { /* @var Model_Calendar $calendar */
			echo "A calendar isn't linked to this widget. Configure it to select one.";
			return;
		}
		
		$start_on_mon = @$calendar->params['start_on_mon'] ? true : false;
		$calendar_properties = DevblocksCalendarHelper::getCalendar($month, $year, $start_on_mon);
		
		$calendar_events = $calendar->getEvents($calendar_properties['date_range_from'], $calendar_properties['date_range_to']);
		
		// Occlusion
		
		$availability = $calendar->computeAvailability($calendar_properties['date_range_from'], $calendar_properties['date_range_to'], $calendar_events);
		$availability->occludeCalendarEvents($calendar_events);
		
		// Template scope
		
		$tpl->assign('widget', $widget);
		$tpl->assign('calendar', $calendar);
		$tpl->assign('calendar_events', $calendar_events);
		$tpl->assign('calendar_properties', $calendar_properties);
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/calendar/calendar.tpl');
	}
	
	// Config
	
	function renderConfig(Model_WorkspaceWidget $widget) {
		if(empty($widget))
			return;
		
		$tpl = DevblocksPlatform::services()->template();
		
		// Widget
		
		$tpl->assign('widget', $widget);
		
		// Calendars
		
		$calendars = DAO_Calendar::getAll();
		$tpl->assign('calendars', $calendars);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/calendar/config.tpl');
	}
	
	function saveConfig(Model_WorkspaceWidget $widget) {
		@$params = DevblocksPlatform::importGPC($_POST['params'], 'array', array());
		
		DAO_WorkspaceWidget::update($widget->id, array(
			DAO_WorkspaceWidget::PARAMS_JSON => json_encode($params),
		));
	}
	
	function showCalendarTabAction(Model_WorkspaceWidget $model) {
		@$calendar_id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer');
		
		@$month = DevblocksPlatform::importGPC($_REQUEST['month'],'integer', 0);
		@$year = DevblocksPlatform::importGPC($_REQUEST['year'],'integer', 0);
		
		$tpl = DevblocksPlatform::services()->template();
		
		if(null == ($calendar = DAO_Calendar::get($calendar_id))) /* @var Model_Calendar $calendar */
			return;
		
		$start_on_mon = @$calendar->params['start_on_mon'] ? true : false;
		$calendar_properties = DevblocksCalendarHelper::getCalendar($month, $year, $start_on_mon);
		
		$calendar_events = $calendar->getEvents($calendar_properties['date_range_from'], $calendar_properties['date_range_to']);
		
		// Occlusion
		
		$availability = $calendar->computeAvailability($calendar_properties['date_range_from'], $calendar_properties['date_range_to'], $calendar_events);
		$availability->occludeCalendarEvents($calendar_events);
		
		// Template scope
		$tpl->assign('widget', $model);
		$tpl->assign('calendar', $calendar);
		$tpl->assign('calendar_events', $calendar_events);
		$tpl->assign('calendar_properties', $calendar_properties);
		
		// Template
		
		$tpl->display('devblocks:cerberusweb.core::internal/workspaces/widgets/calendar/calendar.tpl');
	}
	
	// Export
	
	function exportData(Model_WorkspaceWidget $widget, $format=null) {
		switch(DevblocksPlatform::strLower($format)) {
			case 'csv':
				return $this->_exportDataAsCsv($widget);
				break;
				
			default:
			case 'json':
				return $this->_exportDataAsJson($widget);
				break;
		}
		
		return false;
	}
	
	private function _exportDataAsCsv(Model_WorkspaceWidget $widget) {
		@$calendar_id = $widget->params['calendar_id'];
		
		if(false == ($calendar = DAO_Calendar::get($calendar_id)))
			return false;
		
		$calendar_properties = DevblocksCalendarHelper::getCalendar(null, null);
		
		$fp = fopen("php://temp", 'r+');
		
		$headings = array(
			'Date',
			'Label',
			'Start',
			'End',
			'Is Available',
			'Color',
			//Link',
		);
		
		fputcsv($fp, $headings);
		
		// [TODO] This needs to use the selected month/year from widget
		$calendar_events = $calendar->getEvents($calendar_properties['date_range_from'], $calendar_properties['date_range_to']);
		
		foreach($calendar_events as $events) {
			foreach($events as $event) {
				fputcsv($fp, array(
					date('r', $event['ts']),
					$event['label'],
					$event['ts'],
					$event['ts_end'],
					$event['is_available'],
					$event['color'],
					//$event['link'], // [TODO] Translate ctx:// links
				));
			}
		}
		
		unset($calendar_events);
		
		rewind($fp);

		$output = "";
		
		while(!feof($fp)) {
			$output .= fgets($fp);
		}
		
		fclose($fp);
		
		return $output;
	}
	
	private function _exportDataAsJson(Model_WorkspaceWidget $widget) {
		@$calendar_id = $widget->params['calendar_id'];
		
		if(false == ($calendar = DAO_Calendar::get($calendar_id)))
			return false;
		
		$calendar_properties = DevblocksCalendarHelper::getCalendar(null, null);
		
		// [TODO] This needs to use the selected month/year from widget
		$calendar_events = $calendar->getEvents($calendar_properties['date_range_from'], $calendar_properties['date_range_to']);
		
		$json_events = array();
		
		// [TODO] This should export a fully formed calendar (headings, weeks, days)
		// [TODO] The widget export should give the date range used as well
		
		foreach($calendar_events as $events) {
			foreach($events as $event) {
				$json_events[] = array(
					'label' => $event['label'],
					'date' => date('r', $event['ts']),
					'ts' => $event['ts'],
					'ts_end' => $event['ts_end'],
					'is_available' => $event['is_available'],
					'color' => $event['color'],
					//'link' => $event['link'], // [TODO] Translate ctx:// links
				);
			}
		}
		
		unset($calendar_events);
		
		$results = array(
			'widget' => array(
				'label' => $widget->label,
				'type' => 'calendar',
				'version' => 'Cerb ' . APP_VERSION,
				'events' => $json_events,
			),
		);
		
		return DevblocksPlatform::strFormatJson($results);
	}
};