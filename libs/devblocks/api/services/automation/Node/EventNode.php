<?php
namespace Cerb\AutomationBuilder\Node;

use DevblocksDictionaryDelegate;
use DevblocksPlatform;
use Model_Automation;

class EventNode extends AbstractNode {
	function activate(Model_Automation $automation, DevblocksDictionaryDelegate $dict, array &$node_memory, string &$error=null) : string|false {
		$environment = $automation->getEnvironment();
		
		$state_from = $environment['state_last'] ?? null;
		
		if(!array_key_exists('stack', $node_memory)) {
			$node_memory['stack'] = array_map(function($child) { return $child->getId(); }, $this->node->getChildren());
		}
		
		if(!empty($node_memory['stack'])) {
			return array_shift($node_memory['stack']);
			
		} else {
			$node_id = $this->node->getId();
			
			if(DevblocksPlatform::strEndsWith($node_id, ':on_simulate')) {
				if($state_from == $node_id . ':success'
					&& null != ($event_success = $this->node->getParent()->getChildBySuffix(':on_success')))
				{
					return $event_success->getId();
					
				} else if($state_from == $this->node->getId() . ':error'
					&& null != ($event_error = $this->node->getParent()->getChildBySuffix(':on_error')))
				{
					return $event_error->getId();
					
				} else {
					return $this->node->getParent()->getId();
				}
				
			} else {
				return $this->node->getParent()->getId();
			}
		}
	}
}