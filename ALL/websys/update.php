<?php
	include 'dbconn.php';
	$id = $_GET['id'];


	if(isset($_POST['update'])){
		$stud_name = $_POST['student_name'];  
		$course = $_POST['course']; 
		$address = $_POST['address'];  
		$contact = $_POST['contact_number']; 
		$tbl_id = $_POST['tbl_id'];

		$update = "UPDATE students SET Student_Name = '$stud_name', Course = '$course', 
			Address = '$address', 
			Contact_Number = '$contact' WHERE ID = '$tbl_id' ";
		$result = mysqli_query($conn, $update);
		if($result){
			header("location:index.php");
		}
	}

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Update Information</title>
</head>
<body>
	<form method = "post">
		<table>
			<?php
				$fetch = "SELECT * FROM students WHERE ID = '$id' ";
				$result = mysqli_query($conn, $fetch);

				while($row = mysqli_fetch_array($result)){
			?>
					<tr>
						<td>Student Name: </td>
						<td>
							<input type="text" name="student_name" value = "<?php echo $row['Student_Name']; ?>">
						</td>
					</tr>
					<tr>
						<td>Course: </td>
						<td>
							<input type="text" name="course" value = "<?php echo $row['Course']; ?>">
						</td>
					</tr>
					<tr>
						<td>Address: </td>
						<td>
							<input type="text" name="address" value = "<?php echo $row['Address']; ?>">
						</td>
					</tr>
					<tr>
						<td>Contact Number: </td>
						<td>
							<input type="text" name="contact_number" value = "<?php echo $row['Contact_Number']; ?>">
						</td>
					</tr>

					<input type="hidden" name="tbl_id" value = "<?php echo $row['ID']; ?>">
			<?php
				}
			?>
				<tr>
					<td colspan="2">
						<a href = "index.php">Cancel</a>
						<button name = "update">Update</button>
					</td>
				</tr>
		</table>
	</form>
		
</body>
</html>