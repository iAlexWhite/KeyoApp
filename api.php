<?php

	/* This should not be used in production! */
	//ini_set('display_errors', 1);
	//error_reporting(E_ALL);

	include 'includes/functions.php';


	/* Simplified API Version 4 ~ no crap */

	/* Declare our API Classes */
	$api = new apiFunctions();
	$error = new errorReport();

	define('ADDR', 'http://keyo.co'); //Main Website
	define('STRE', 'keyUploads'); //Where to store the files
	$whiteList = array('jpg','png','gif','jpeg','txt','zip','rar','doc','xls','md'); //Extension whitelist

	define('HOST', ''); //Database Host
	define('USER', ''); //Database User
	define('NAME', ''); //Database Name
	define('PASS', ''); //Database Pass

	mysql_connect(HOST, USER, PASS) or die("MySQL Error: " . mysql_error());
	mysql_select_db(NAME) or die("MySQL Error: " . mysql_error());

	//Make the URLs cleaner
	$getParams = array();
	$getParts = explode('/', $_SERVER['REQUEST_URI']);
	//Skip through the segments by 2
	for($i = 0; $i < count($getParts); $i = $i + 2){
		//First segment is the param name, second is the value 
		$getParams[$getParts[$i]] = $getParts[$i+1];
	}

	/* Make it work with all existing code */
	$_GET = $getParams;

	/* Collect API credentials if they are there */
	if (!empty($_GET['user']) && !empty($_GET['key']))
	{
		$apiUser = $api->simpleSanitize($_GET['user']);
		$apiKey = $_GET['key'];

		$getUser = mysql_query("SELECT * FROM users WHERE Username = '$apiUser'");
		$userExist = mysql_num_rows($getUser);

		/* Include the graphing code, a lot cleaner! */
		include 'graph.php';

		/* User doesn't exist in the database */
		if ($userExist == 0)
		{
			$noExist = $error->returnError(1);
			echo $noExist;
			die;
		}

		/* Get all the users info */
		$userInfo = mysql_fetch_assoc($getUser);

		/* See what their API key should be */
		$checkAPI = $api->apiKey($apiUser, $userInfo['UserID']);
		$oldAPI = $api->apiSuperseded($apiUser);

		/* API is not correct, try the old key */
		if ($apiKey != $checkAPI)
		{
			if ($apiKey != $oldAPI)
			{
				/* API key is still not correct, return an error */
				$keyCheck = $error->returnError(2);
				echo $keyCheck;
				die;
			}
		}
		/* Just for debugging, nothing important in here */
		if (!empty($_GET['debug']))
		{
			echo 'Currently using: ' . $apiKey . '<br />';
			echo 'Old API: ' . $oldAPI . '<br />';
			echo 'New API: ' . $checkAPI . '<br />';
			die;
		}

		/* Check for login, with the old key too! */
		if (!empty($_GET['login']))
		{
			if ($apiKey == $checkAPI || $apiKey == $oldAPI)
			{
				echo 'Correct';
				die;
			}
			else
			{
				echo 'Incorrect';
				die;
			}
		}
		/* Getting the views of a file */
		if (!empty($_GET['views']))
		{
			$file = $api->simpleSanitize($_GET['views']);
			$views = $api->getViews($file);
			echo $views;
			die;
		}
		/* Come here to remove an image */
		if (!empty($_GET['remove']))
		{
			$file = $api->simpleSanitize($_GET['remove']);
			$remove = $api->removeFile($apiUser, $file);
			echo $remove;
			die;
		}
		/* Adding a new category */
		if (!empty($_GET['add']) && !empty($_GET['category']))
		{
			$file = $api->simpleSanitize($_GET['add']);
			$fileExist = $api->fileExists($file, $apiUser);
			
			if ($fileExist)
			{
				$cat = $api->simpleSanitize($_GET['category']);
				$catExist = $api->catExists($cat, $apiUser);
				
				if ($catExist)
				{
					$sqlMain = mysql_query("UPDATE files SET category = '$cat' WHERE newfilename='$file' AND user='$apiUser'");
					echo 'File ' . $file . ' is now in the ' . $cat . ' category.';
					die;
				}
				else
				{
					$catCheck = $error->returnError(3);
					echo $catCheck;
					die;
				}
			}
			else
			{
				$fileCheck = $error->returnError(4);
				echo $fileCheck;
				die;
			}
			
		}
		
		if (empty($_FILES))
		{
			$postCheck = $error->returnError(5);
			echo $postCheck;
			die;
		}
		else
		{
			/* Get the time and date */
			$theDateTime = date('Y-m-d H:i:s');
			/* Just the date */
			$theDate = date('Y-m-d');
			$YearMonthDay = explode("-", $theDate);
			$theTime = date('H:i:s');
			$HourMinuteSecond = explode(":", $theTime);
			//IP Address
			$ipAddress = $_SERVER['REMOTE_ADDR'];
			
			/* The old files name */
			$oldFile = basename($_FILES['file']['name']);
			
			if (empty($oldFile))
			{
				$oldFile = basename($_FILES['uploaded']['name']);
				$android = 1;
			}
			
			/* Gets the extension */
			$getExt = pathinfo($oldFile);
			$checkExt = strtolower($getExt['extension']);
			
			/* This checks what type of file it is, and provides the correct link (hopefully) */
			$l = $api->getLink($checkExt);

			/* Check if the files extension is okay */
			if (!in_array($checkExt, $whiteList))
			{
				$typeCheck = $error->returnError(6);
				echo $typeCheck;
				die;
			}
			
			/* Check the size of the file */
			$fileSize = $_SERVER['CONTENT_LENGTH'];
			$sizeKB = $fileSize / 1024; //In KB
	
			/* Using MB */
			if ($sizeKB > 999)
			{
				$sizeKB = $sizeKB / 1024; //mb
				$sizeType = ' MB';
				if ($sizeFinalBlank > 10.0)
				{
					$sizeCheck = $error->returnError(7);
					echo $sizeCheck;
					die;
				}
			}
			else
			{
				$sizeType = ' KB';
			}
	
			/* Round the file up to 2 decimals */
			$sizeFinalBlank = round($sizeKB, 2);
			$sizeFinal = round($sizeKB, 2) . $sizeType;

			/* Create the new file name */
			$newFile = stripslashes($api->fileName() . "." . $checkExt);

			/* Check if the file exists, rename it if it does (needs work) */
			if (file_exists($newFile)) 
			{
				$newFile = stripslashes($api->fileName() . "." . $checkExt);
			}
			
			/* Full Upload Path */
			$fullUpload = STRE . '/' . $newFile;
	
			/* The current time */
			$theTimeStamp = time();
			
			/* Is it being uploaded through the app? */
			if (!$android)
			{
				if (is_uploaded_file($_FILES['file']['tmp_name']))
				{
					if (move_uploaded_file($_FILES['file']['tmp_name'], $fullUpload))
					{
						//Main SQL
						$sqlMain = mysql_query("INSERT INTO files (origfilename, newfilename, uploaderip, size, user, timedate, time, date, timestamp) VALUES ('$oldFile', '$newFile', '$ipAddress', '$sizeFinal', '$apiUser', '$theDateTime', '$theTime', '$theDate', '$theTimeStamp')");
						mysql_close();
				
						echo "http://keyo.co/?" . $l . "=" . $newFile;
					}
				}
			}
			else
			{
				if (is_uploaded_file($_FILES['uploaded']['tmp_name']))
				{
					if (move_uploaded_file($_FILES['uploaded']['tmp_name'], $fullUpload))
					{
						//Main SQL
						$sqlMain = mysql_query("INSERT INTO files (origfilename, newfilename, uploaderip, size, user, timedate, time, date, timestamp) VALUES ('$oldFile', '$newFile', '$ipAddress', '$sizeFinal', '$apiUser', '$theDateTime', '$theTime', '$theDate', '$theTimeStamp')");
						mysql_close();
				
						echo "http://keyo.co/?" . $l . "=" . $newFile;
					}
				}
			}
		}
	}
	
	
	class apiFunctions
	{
		function apiKey($keyoUser, $keyoID)
		{
			$uniqueSalt = sha1('REMOVED+EDITED');
			$keyoHash = sha1($keyoUser . $uniqueSalt . $keyoID);
			for ($i = 0; $i < 205; $i++) {
				$keyoHash = sha1($keyoHash);
			}
		return sha1($keyoUser . $keyoHash . $uniqueSalt . $keyoID);
		}
		
		function apiSuperseded($apiName)
		{
			$hashValue = "sQfYKDTXvOApaFYC";
			$apiChecked = sha1($apiName . $hashValue);
		return $apiChecked;
		}
	
		function fileName($nameLength = 5, $charChoice = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-$_.!(),')
		{
			$numChars = strlen($charChoice);
			$returnFile = '';
			for($i = 0; $i < $nameLength; ++$i)
			{
				$returnFile .= $charChoice[mt_rand(0, $numChars)];
			}
		return $returnFile;
		}
		
		function fileExists($file, $username)
		{
			$getFile = mysql_query("SELECT * FROM files WHERE newfilename = '$file' AND user = '$username'");
			$fileExist = mysql_num_rows($getFile);
		
			/* File doesn't exist in the database */
			if ($fileExist == 0)
			{
				return 0;
			}
			else
			{
				return 1;
			}
		}
		
		function catExists($cat, $username)
		{
			$getCat = mysql_query("SELECT * FROM categories WHERE name = '$cat' AND username = '$username'");
			$catExist = mysql_num_rows($getCat);
		
			/* Category doesn't exist in the database */
			if ($catExist == 0)
			{
				return 0;
			}
			else
			{
				return 1;
			}
		}
		
		function getLink($ext)
		{
			if ($ext == 'jpg' || $ext == 'png' || $ext == 'gif' || $ext == 'jpeg')
			{
				return 'i';
			}
			elseif ($ext == 'txt' || $ext == 'md')
			{
				return 't';
			}
			elseif ($ext == 'zip' || $ext == 'rar')
			{
				return 'c';
			}
			elseif ($ext == 'doc' || $ext == 'xls')
			{
				return 'd';
			}
		}
	
		function apiRequestsCount($apiUsername, $uploadsPerMin)
		{
			$callTime = time();
			$timeMinusMin = $callTime - 60;
			$getmyInfo = mysql_query("SELECT *, COUNT(newfilename) FROM files WHERE user = '$apiUsername' AND timestamp BETWEEN $timeMinusMin AND $callTime");
			$myInfo = mysql_fetch_assoc($getmyInfo);
			$myInfo = $myInfo['COUNT(newfilename)'];
			if ($myInfo <= $uploadsPerMin)
			{
				$apiStatus = 1;
			}
			else
			{
				$apiStatus = 0;
			}
		return $apiStatus;
		}
		
		function upgradeUser($upgradeUser, $userLevel)
		{
			$upgradeQuery = mysql_query("SELECT * FROM users WHERE Username = '$upgradeUser'");
			$upgradeDetails = mysql_fetch_assoc($upgradeQuery);
			$userExist = mysql_num_rows($upgradeQuery);
			if ($userExist == 0)
			{
				$upgradeResult = 'The user does not exist';
			}
			else
			{
				if ($upgradeDetails['AccountType'] > 1)
				{
					$upgradeResult = $upgradeUser . ' is already premium';
				}
				else
				{
					$finishUpgrade = mysql_query("UPDATE users SET AccountType='$userLevel' WHERE Username='$upgradeUser'");
					$upgradeResult = $upgradeUser . ' has been upgraded';
				}
			}
		return $upgradeResult;
		}
		
		function getViews($viewedFile)
		{
			$apiViews = mysql_query("SELECT * FROM files WHERE newfilename = '$viewedFile'");
			$viewsResult = mysql_fetch_assoc($apiViews);
			$viewsResult = $viewsResult['views'];
		return $viewsResult;
		}
		
		function removeFile($userCheck, $removedFile)
		{
			$apiRemove = mysql_query("SELECT * FROM files WHERE newfilename = '$removedFile'");
			$removeResult = mysql_fetch_assoc($apiRemove);
			$removeUser = $removeResult['user'];
			
			$removeCheck = mysql_num_rows($apiRemove); //Check if it exists
			
			if ($removeCheck == 0)
			{
				$remResult = "Doesn't exist";
			return $remResult;
			}
			
			if ($userCheck != $removeUser)
			{
				$remResult = "You do not own this image";
			return $remResult;
			}
			
			//Use this for the account page, so it forwards you back to the correct page!
			if ($_GET['forward'] == 'true')
			{
				$pastPage = $_GET['page'];
				$deleteMe = mysql_query("DELETE FROM files WHERE newfilename='$removedFile'");
				unlink("keyUploads/" . $removedFile);
				header("Location: http://keyo.co/gallery/page/$pastPage");
			}
			else
			{
				//Removing files from the api only, not the account page!
				$deleteMe = mysql_query("DELETE FROM files WHERE newfilename='$removedFile'");
				unlink("keyUploads/" . $removedFile);
				$remResult = "Success";
			return $remResult;
			}
		}
		
		function simpleSanitize($inputData)
		{
			$inputData = strip_tags(mysql_real_escape_string($inputData));
		return $inputData;
		}
		
	}
	
	class errorReport
	{
		function returnError($i)
		{
			switch ($i)
			{
				case 0:
					$msg = "No credentials given";
					return $msg;
					break;
				case 1:
					$msg = "User does not exist";
					return $msg;
					break;
				case 2:
					$msg = "Incorrect API credentials";
					return $msg;
					break;
				case 3:
					$msg = "Category does not exist";
					return $msg;
					break;
				case 4:
					$msg = "File does not exist";
					return $msg;
					break;
				case 5:
					$msg = "A POSTed file is needed";
					return $msg;
					break;
				case 6:
					$msg = "This file type is not allowed";
					return $msg;
					break;
				case 7:
					$msg = "This file is too large";
					return $msg;
					break;
				case 8:
					$msg = "";
					return $msg;
					break;
				case 9:
					$msg = "";
					return $msg;
					break;
				case 10:
					$msg = "";
					return $msg;
					break;
			}
		}
	}