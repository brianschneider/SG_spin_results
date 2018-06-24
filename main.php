<?php main() ;

// Main function is a placeholder and unit test case for the actual code to update spin results
function main()
{
	require_once("class_db.php") ;		// DB library class
	require_once("functions.php") ;		// Spin update and hash functions
	
	error_reporting(E_ALL ^ E_WARNING); // Only show app generated messages. Comment out during dev.

	// *** Replace to use actual values for the real DB connection
	$host = "127.0.0.1" ;
	$name = "spin_results" ;
	$user = "<DB User Name>" ;
	$pass = "<DB Password" ;

	// *** Replace to use actual values for the update
	$salt = "43a5$8dw934d5-23sdcndqwdqwd12w13_2f4as" ;  // Made up salt value
	$player_id = 1 ;

	$hash = get_hash($player_id, $salt) ;
	if ($hash===FALSE)
	{
		die("\nCould not generate a valid hash for the provided values.\n") ;
	}
	
	$coins_bet = 5 ;			// For testing, assuming average bet is 5
	$won = 0 ;					// For testing, randomizing winnings
	srand() ;
	if (rand(0, 1))
		$won+=rand(1, 25) ;
	$coins_won = $won ;			// User has 50/50 chance and can win up to 25
	
	// Create a DB object and connect it to the DB
	$db = new DB($host, $user, $pass, $name) ;
	if ($db->Connect()===FALSE) 
	{
		die("\n".$db->GetLastError()."\n") ;
	}
	
	// Pass the DB object and update info to the spin_update function
	$msg = "" ;
	$ret = spin_update($db, $player_id, $hash, $coins_bet, $coins_won, $msg) ;
	$db->Close() ;
	
	// If error, display error and exit. 
	if ($ret===FALSE)
	{
		die("\n".$msg."\n") ;
	}
	
	// Decode the results and output as an array
	echo "\nCoins bet: $coins_bet" ;
	echo "\nCoins won: $coins_won\n" ;

	print_r(json_decode($ret, TRUE)) ;
	echo "\n" ;
	exit(0) ;
}

?>