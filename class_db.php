<?php

// Create a DB object to establish connection and provide methods for performing DB queries
class DB
{
	private $host ;
	private $user ;
	private $pass ;
	private $name ;
	private $handle ;
	private $error_msg ;	// Internal storage for any error messages encountered
	private $is_connected ; // Internal method to track db connection status

	// Construct.  Capture incoming fields and set defaults
	public function __construct($db_host, $db_user, $db_pass, $db_name)
	{
		$this->host = $db_host ;
		$this->user = $db_user ;
		$this->pass = $db_pass ;
		$this->name = $db_name ;
		$this->is_connected = 0 ;	// Track whether DB is connected.
		$this->error_msg = "" ;		// Internal error message storage
	}
	
	// On destruction of the object, ensure the DB connection is closed.
	public function __destruct()
	{
		$this->Close() ;
	}
	
	// Set error message for retrieval by GetLastError
	private function SetError($msg)
	{
		$this->error_msg = $msg ;
	}
	
	// Retrieve last error message
	public function GetLastError()
	{
		return $this->error_msg ;
	}

	// Validate DB parameters and attempt connection
	public function Connect()
	{
		// Validate DB connection fields
		if ( (!strlen($this->host)) || (!strlen($this->user)) || (!strlen($this->pass)) || (!strlen($this->name)) )
		{
			$this->SetError("One or more parameters are incorrect") ;
			return FALSE ;
		}
		
		// Get DB resource
		$this->handle = mysqli_init();
		if ($this->handle===FALSE)
		{
			$this->SetError("Unable to establish DB resource") ;
			return FALSE ;
		}
		
		// Connect to the DB
		$ret = mysqli_real_connect($this->handle, $this->host, $this->user, $this->pass, $this->name);
		if ($ret===FALSE)
		{
			$this->SetError(mysqli_connect_error()) ;
			return FALSE ;
		}
		
		// DB connected.  Change internal status
		$this->is_connected = 1 ;
	}

	// Close the DB connection
	public function Close()
	{
		if ($this->is_connected)
		{
			$this->is_connected = 0 ;
			mysqli_close($this->handle) ;
		}
	}
	
	// Perform a DB query for a SQL select and format the output into an array of records
	public function Select($sql)
	{
		if (!$this->is_connected)
		{
			$this->SetError("Can't query because there is no DB connection") ;
			return FALSE ;
		}
		
		$ret = mysqli_query($this->handle, $sql) ;
		if ($ret === FALSE)
		{
			$this->SetError(mysqli_error($this->handle)) ;
			return FALSE ;
		}
		
		$output = array() ;
		while($row = mysqli_fetch_assoc($ret)) 
			$output[] = $row;
		mysqli_free_result($ret);
		return $output ;
	}
	
	// Perform a DB query for a SQL Update and return outcome
	public function Update($sql)
	{
		if (!$this->is_connected)
		{
			$this->SetError("Can't query because there is no DB connection") ;
			return FALSE ;
		}
		
		$ret = mysqli_query($this->handle, $sql) ;
		if ($ret === FALSE)
		{
			$this->SetError(mysqli_error($this->handle)) ;
			return FALSE ;
		}
		return TRUE ;
	}	
}

?>
