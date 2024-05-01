<?php
class ApiCommand_CerbEmailSpamTrain extends Extension_AutomationApiCommand {
	const ID = 'cerb.commands.email.spam.train';
	
	function run(array $params=[], &$error=null) : array|false {
		$ticket_id = intval($params['ticket_id'] ?? null);
		
		CerberusBayes::markTicketAsSpam($ticket_id);
		
		return [
			'status' => true,
		];
	}
	
	public function getAutocompleteSuggestions($key_path, $prefix, $key_fullpath, $script) : array {
		if('' == $key_path) {
			return [
				'ticket_id@int:',
			];
		}
		
		return [];
	}
}