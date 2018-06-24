<?php

// Hash method was not specified for this assignment, so using SHA256
// Salting method was not specified for this assignment, so appending to the end of the value

global $HASH_METHOD ;
global $HASH_LENGTH ;
global $ALLOW_NEGATIVE_BALANCE ;

$HASH_METHOD = "SHA256" ;		// Update based on the hash method
$HASH_LENGTH = 64 ; 			// Update hash length based on the hash method
$ALLOW_NEGATIVE_BALANCE = 1 ;	// Update based on if a user can have a debt for a balance

// Funnel all hash generation to a single function for scalability and abstraction
// Return FALSE if the generated hash is not valid for the hash type
// Otherwise return the hash of the value + salt
function get_hash($value, $salt)
{
	global $HASH_LENGTH ;
	global $HASH_METHOD ;
	
	$hash = hash($HASH_METHOD, $value.$salt, FALSE) ; // Generate hash of value + salt
	if (strlen($hash) != $HASH_LENGTH)
	{
		return FALSE ;
	}
	
	return $hash ;
}


// Update the DB with the spin results
// A DB object is passed in and should already be created and connected to the DB.
// On failure, return FALSE and provide detail via the $msg variable
// Return JSON encoded result on success. 
function spin_update($db, $player_id, $hash, $coins_bet, $coins_won, &$msg)
{
	global $HASH_LENGTH ;
	global $ALLOW_NEGATIVE_BALANCE ;
	
	// Validate hash value.  No SQL injection concern since this value is never passed to the DB
	if (strlen($hash) != $HASH_LENGTH) 
	{
		$msg = "Hash value is invalid" ;
		return FALSE ;
	}
	
	// All other provided fields should be numeric. 
	// By checking, this should cover possible SQL injection
	if (!is_numeric($player_id))
	{
		$msg = "Inproper value provided for: player id" ;
		return FALSE ;
	}
	if ( (!is_numeric($coins_bet)) && ($coins_bet >= 0) ) // Adjust if a negative bet is possible
	{
		$msg = "Inproper value provided for: coins bet" ;
		return FALSE ;
	}	
	if ( (!is_numeric($coins_won)) && ($coins_won >= 0) ) // Adjust if game can take away more than just coins wagered
	{
		$msg = "Inproper value provided for: coins won" ;
		return FALSE ;
	}	
	
	// Get the entire record for the player id
	// This will allow verification of the hash and provide current balances
	$sql = "SELECT * FROM player WHERE PlayerID=$player_id" ;
	$ret = $db->select($sql) ;
	if ($ret===FALSE)
	{
		$msg = $db->GetLastError() ;
		return FALSE ;
	}
	$count = count($ret) ; 	// Number of records returned
		
	// Determine if the player id was found
	if (!$count)
	{
		$msg = "Player ID: $player_id not found in the DB" ;
		return FALSE ;
	}
	// There should only be 1 record in this dataset
	if ($count > 1)
	{
		$msg = "Incorrect number of records returned from the DB for $player_id (".count($ret).")" ;
		return FALSE ;
	}
	$record = $ret[0] ;	// Local storage of the record
	
	// Generate the hash from the DB player ID and DB salt value for the record
	$db_hash = get_hash($record["PlayerID"], $record["SaltValue"]) ;
	if ($db_hash===FALSE)
	{
		$msg = "The DB hash is corrupt for the player ID: $player_id" ;
		return FALSE ;
	}
	
	// Compare the the DB hash with the provided hash
	if ($db_hash != $hash)
	{
		$msg = "The provided hash does not match the DB" ;
		return FALSE ;
	}
	
	// Increment values 
	$record["LifetimeSpins"]++ ;
	$record["Credits"] += ($coins_won - $coins_bet) ; // Adjust credits accounting for coins used to bet
	
	// Number of spins at this point should be a least 1
	if (!$record["LifetimeSpins"])
	{
		$msg = "Spin count error (".$record["LifetimeSpins"].")" ;
		return FALSE ;
	}
	
	// If balance is below 0 and that is not allowed, make balance 0
	if ( (!$ALLOW_NEGATIVE_BALANCE) && ($record["Credits"] < 0) )
	{
		$record["Credits"] = 0 ;
	}
	
	// Update the DB w/ the new values
	$sql = "UPDATE player SET Credits=".$record["Credits"].",LifetimeSpins=".$record["LifetimeSpins"]." WHERE PlayerID=$player_id" ;
	$ret = $db->Update($sql) ;
	if ($ret===FALSE)
	{
		$msg = "Could not update the DB" ;
		return FALSE ;
	}
	
	// Since most of the values are already in $record, adjust it for return
	unset($record["SaltValue"]) ;
	
	// Calc. avg return (0 decimal places) and round up so they feel good about their return :)   
	$starting_credits = 0 ; // More accurate if we knew starting credits
	$record["LifetimeAverageReturn"] = round( ($record["Credits"] - $starting_credits) / $record["LifetimeSpins"], 0, PHP_ROUND_HALF_UP) ;

	// Return JSON Encoded
	return json_encode($record) ;

}


?>
