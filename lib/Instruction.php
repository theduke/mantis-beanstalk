<?php

require_once 'vendor/mantisconnect-php/lib/MantisConnect.php';

class Instruction
{
	public $issueId;
	public $status;
	public $priority;
	public $resolution;
	public $severity;
	
	public $note;
	
	public $assignTo;
	
	/** @var array */
	protected $tags;
	
	public function getAsObjectRef($property)
	{
		if (!property_exists($this, $property)) throw new Exception('Property does not exist');
		
		if (empty($this->$property)) return null;
		
		$ref = new ObjectRef();
		$ref->name = $this->$property;
		
		return $ref;
	}
	
	public function setTags(array $tags)
	{
		$this->tags = $tags;
		return $this;
	}
	
	public function getTags()
	{
		return $this->tags;
	}
}