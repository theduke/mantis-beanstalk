<?php 

require_once 'InstructionParser.php';

class MantisBeanstalk
{
	/** @var MantisConnector */
	protected $mantisClient;
	
	/** @var InstructionParser */
	protected $parser;
	
	/**
	 * simple assoc array that maps project names from beanstalk to mantis projectIds
	 * 
	 * @var array ('projectname' => 14) 
	 */
	protected $projectMapping;
	
	protected $data;
	
	/**
	 * @var string see TYPE_ constants
	 */
	protected $dataType;
	
	const TYPE_GIT = 'git';
	const TYPE_SVN = 'svn';
	
	protected $logContent;
	
	protected $logPath;
	
	/**
	 * the mantis project id
	 * @var string
	 */
	protected $projectId;
	
	public function __construct()
	{
		$this->parser = new InstructionParser();
	}
	
	public function run()
	{
		$logContent = '';
		
		$logContent .= print_r($_REQUEST, true) . PHP_EOL . PHP_EOL;
		
		$e = null;
		try {
			$this->execute();
		} catch (Exception $e) {
			$logContent .= $e->getMessage() . PHP_EOL . PHP_EOL . $e->getTraceAsString();
		}
		
		if ($this->logPath)	$this->doLog($logContent);
		
		// rethrow exception for testing
		if ($e)
		{
			var_dump($logContent);
			throw $e;
		}
	}
	
	protected function doLog($content)
	{
		if (!is_dir($this->logPath) || !is_writable($this->logPath))
		{
			throw new Exception('Specified log path is not writable or does not exist.');
		}
		
		if (isset($this->data['revision']))
		{
			$filename = 'revision' . $this->data['revision'] . '.log';
		} else {
			$i = count(scandir($this->logPath)) -1;
			$filename = "log_{$i}.log";
		}
		
		$path = $this->getLogPath() .  $filename;
		
    	$flag = file_put_contents($path, $content);
		
	    if ($flag === false)
	    {
	    	throw new Exception('Could not write log file.');
	    }
	}
	
	public function execute()
	{
		if (isset($_REQUEST['commit']))
		{
			$this->dataType = self::TYPE_SVN;
			$data = $_REQUEST['commit'];
		} else if (isset($_REQUEST['payload']))
		{
			$this->dataType = self::TYPE_GIT;
			$data = $_REQUEST['payload'];
		} else {
			throw new Exception('Could not find Beanstalk data in POST.');
		}
		
		// remove stupid escapes from data
		$data = str_replace('\"', '"', $data);
		
		$data = json_decode($data);
		$data = get_object_vars($data);
		
		if (!$data || !$this->verifyData($data))
		{
			throw new Exception('Could not verify json data.');
		}
		
		$this->data = $data;
		
		if ($this->dataType === self::TYPE_SVN)
		{
			// for svn , wrap data in another array for foreach
			$data = array('commits' => array($data));
		}
		
		foreach ($data['commits'] as $commit)
		{
			if (is_object($commit)) $commit = get_object_vars($commit);
			
			$this->processHook($commit);
		}
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
		
		$projectId = $issue->project->id;
		
		if (!$this->projectId && $projectId)
		{
			$this->projectId = $projectId;
		} else {
			throw new Exception('Could not determine project ID!');
		}
		
		$user = $this->determineUser($data); 
		
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
			
			$revision = $this->dataType === self::TYPE_GIT ? $data['id'] : $data['revision'];
			
			if ($revision)
			{
				$noteText .= PHP_EOL . PHP_EOL . str_repeat('-', 20) . 
				  PHP_EOL .  'revision: ' . $revision;
			}
			
			$note = new IssueNoteData();
			$note->text = $noteText;
			$note->reporter = $user;
			
			$this->mantisClient->mc_issue_note_add($instruction->issueId, $note);
		}
		
		$this->mantisClient->mc_issue_update($instruction->issueId, $issue);
	}
	
	protected function determineUser($commit)
	{
		if ($this->dataType === self::TYPE_GIT)
		{
			$email = $commit['author']->email;
			$name = $commit['author']->name;
		} else if ($this->dataType === self::TYPE_SVN) {
			$email = $commit['author_email'];
			$name = $commit['author'];
		}
		
		$user = $this->mantisClient->getUserByEmail($this->projectId, $email);
		if (!$user) $user = $this->mantisClient->getUserBy('name', $this->projectId, $name);
		
		return $user;
	}
	
	protected function verifyData(array $data)
	{
		$flag = true;
		
		if ($this->dataType === self::TYPE_GIT) 
		{
			if (!isset($data['commits']) || empty($data['commits'])) $flag = false;
		} else if ($this->dataType === self::TYPE_SVN) {
			if (!isset($data['message'])) $flag = false;
		}
		
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
	
  /**
   * 
   * @param string $logPath
   */
  public function setLogPath($logPath)
  {
    $this->logPath = $logPath;
    return $this;
  }
  
  /**
   * @return MantisConnect $client
   */
  public function getLogPath()
  {
  	if (substr($this->logPath, -1) !== '/') $this->logPath .= '/';
    return $this->logPath;
  }
  
  public function setProjectMapping(array $map)
  {
  	$this->projectMapping = $map;
  }
}