<?php
	include 'dbconn.php';
	$id = $_GET['id'];

	$delete = "DELETE FROM students WHERE ID = '$id' ";
	$result = mysqli_query($conn, $delete);

	if($result){
		header("location:index.php");
	}

?>