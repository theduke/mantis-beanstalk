<?php

require_once 'Instruction.php';

class InstructionParser
{
	public function parse($message, $data)
	{
		$instructions = array();
		
		$matches = array();
		$pattern = '/\[#.*?\]/';
		
		$count = preg_match_all($pattern, $message, $matches);
		
		foreach ($matches as $match)
		{
			$ins = $this->buildInstruction($match[0], $data);
			if ($ins) $instructions[] = $ins;
		}
		
		return $instructions;
	}
	
	protected function buildInstruction($message, $data)
	{
		$instruction = new Instruction();
		
		$parseResult = $this->parseInstruction($message);
		if (!$parseResult) return false;
		
		
		$instruction->issueId = (int)$parseResult[0];
		
		$parsedCommands = $parseResult[1];
		
		if (isset($parsedCommands['status'])) $instruction->status = $parsedCommands['status'];
		if (isset($parsedCommands['priority'])) $instruction->status = $parsedCommands['priority'];
		if (isset($parsedCommands['resolution'])) $instruction->resolution = $parsedCommands['resolution'];
		if (isset($parsedCommands['assign'])) $instruction->assignTo = $parsedCommands['assign'];
		
		if (isset($parsedCommands['tags']))
		{
			$tags = explode(',', $parsedCommands['tags']);
			if ($tags) $instruction->setTags($tags);
		}
		
		if (isset($parsedCommands['message']))
		{
			$instruction->note = $parsedCommands['message'];
		} else 
		{
			$instruction->note = str_replace($parseResult[2], '', $data['message']);	
		}
		
		return $instruction;
	}
	
	protected function parseInstruction($instruction)
	{
		$pattern = '/(?<=\[#)(\d+)(.*?)(?=\])/';
		$matches = array();
		$count = preg_match($pattern, $instruction, $matches);
		
		//$matches = $matches[0];

		if (count($matches) < 2) return false;
		$issueId = $matches[1];
		
		$commands = $this->parseCommands($matches[2]);
		
		return array($issueId, $commands, '[#' . $matches[0] . ']');
	}
	
	protected function parseCommands($str)
	{
		$commands = array();
		
		$str = trim($str) . ']';
		
		// remove double or more whitespaces
		$str = preg_replace('/\s{2,}/', ' ', $str);
		
		$arr = str_split($str);
		
		$inQuotes = false;
		$tmp = '';
		$parts = array();
		
		$l = count($arr);
		for ($i=0; $i<$l; $i++)
		{
			$char = $arr[$i];
			
			if ($char === '=')
			{
				$parts[] = $tmp;
				$tmp = '';
			} else if ($char === '"')
			{
				$inQuotes = !$inQuotes;
			} else if ($char === ' ' || $char === ']')
			{
				if ($char === ' ' && $inQuotes)
				{
					 $tmp .= ' ';
				} else {
					$parts[] = $tmp;
					$tmp = '';
				}
			}
			else 
			{
				$tmp .= $char;
			}
		}
		
		$l = count($parts);
		for ($i=1; $i<=$l; $i++)
		{
			$part = $parts[$i-1];
			
			if ($i % 2 === 0)
			{
				$commands[$key] = $part;
			} else {
				$key = $part;
			}
		}
		
		return $commands;
	}
}