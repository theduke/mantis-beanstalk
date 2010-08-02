<?php 

require_once 'InstructionParser.php';

class MantisBeanstalk
{
	/** @var MantisConnector */
	protected $mantisClient;
	
	/** @var InstructionParser */
	protected $parser;
	
	protected $data;
	
	protected $logContent;
	
	/**
	 * the mantis project id
	 * @var string
	 */
	protected $projectId;
	
	public function __construct($projectId)
	{
		$this->projectId = $projectId;
		
		$this->parser = new InstructionParser();
	}
	
	public function run()
	{
		$path = realpath(dirname(__FILE__) . '/../log');
		$i = count(scandir($path)) -2;
		$filename = $path . '/log_' . $i . '.log';
		
		$logContent = '';
		
		if (isset($_REQUEST['commit'])) $logContent .= $_REQUEST['commit'] . PHP_EOL . PHP_EOL . PHP_EOL;
		
		$e = null;
		try {
			$this->execute();
		} catch (Exception $e) {
			$logContent .= $e->getMessage() . PHP_EOL . PHP_EOL . $e->getTraceAsString();
		}
		
		file_put_contents($filename, $logContent);
		
		// rethrow exception
		if ($e) throw $e;
	}
	
	public function execute()
	{
		if (!isset($_REQUEST['commit']))
		{
			throw new Exception('Could not find commit data in POST.');
		}
		
		$data = $_REQUEST['commit'];
		$data = json_decode($data);
		$data = get_object_vars($data);
		
		if (!$this->verifyData($data))
		{
			throw new Exception('Could not verify json data.');
		}
		
		$this->processHook($data);
	}
	
	public function processHook(array $data)
	{
		$instructions = $this->parser->parse($data['message'], $data);		
		
		foreach ($instructions as $instruction)
		{
			$this->processInstruction($instruction, $data);
		}
	}
	
	public function processInstruction(Instruction $instruction, $data)
	{
		/** @var IssueData */
		$issue = $this->mantisClient->mc_issue_get($instruction->issueId);
		
		if (!$issue) return false;
		
		//$issue = new IssueData();
		
		$user = $this->mantisClient->getUserByEmail($this->projectId, $data['author_email']);
		if (!$user) throw new Exception('User could not be found.');
		
		if ($assignTo = $instruction->assignTo)
		{
			$handler = $this->mantisClient->getUserBy('name', $this->projectId, $assignTo);
			
			if ($handler)
			{
				$issue->handler = $handler;
			}
		}
		if ($status = $instruction->getAsObjectRef('status'))
		{
			$issue->status = $status;
		}
		if ($priority = $instruction->getAsObjectRef('priority'))
		{
			$issue->priority = $priority;
		}
		if ($severity = $instruction->getAsObjectRef('severity'))
		{
			$issue->severity = $severity;
		}
		if ($resolution = $instruction->getAsObjectRef('resolution'))
		{
			$issue->resolution = $resolution;
		}
		
		if ($instruction->note)
		{
			$noteText = $instruction->note;
			
			$noteText .= PHP_EOL . PHP_EOL . str_repeat('-', 20) . 
			  PHP_EOL .  'revision: ' . $data['revision'];
			
			$note = new IssueNoteData();
			$note->text = $noteText;
			$note->reporter = $user;
			
			$this->mantisClient->mc_issue_note_add($instruction->issueId, $note);
		}
		
		$this->mantisClient->mc_issue_update($instruction->issueId, $issue);
	} 
	
	protected function verifyData(array $data)
	{
		$flag = true;
		
		if (!isset($data['message'])) $flag = false;
		
		return $flag;
	}
	
	public function setMantisClient($mantisClient)
	{
		$this->mantisClient = $mantisClient;
		return $this;
	}
	
	public function getMantisClient($mantisClient)
	{
		return $this->mantisClient;
	}
	
	public function setInstructionParser($instructionParser)
	{
		$this->instructionParser = $instructionParser;
		return $this;
	}
	
	public function getInstructionParser($instructionParser)
	{
		return $this->instructionParser;
	}
	
	public function setProjectId($projectId)
	{
		$this->projectId = $projectId;
		return $this;
	}
	
	public function getProjectId($projectId)
	{
		return $this->projectId;
	}
}