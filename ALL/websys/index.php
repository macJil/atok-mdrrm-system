<?php	
	include 'dbconn.php';

	if(isset($_POST['submit'])){
		$stud_name = $_POST['student_name'];  
		$course = $_POST['course']; 
		$address = $_POST['address'];  
		$contact = $_POST['contact_number']; 

		$insert = "INSERT INTO students(Student_Name,Course,Address,Contact_Number) VALUES('$stud_name','$course','$address','$contact')";
		$result = mysqli_query($conn, $insert);

	}

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
<body>
	<form method="post">
		<table>
			<tr>
				<th colspan="2">Student Information System</th>
			</tr>
			<tr>
				<td>Student Name</td>
				<td>
					<input type="text" name="student_name">
				</td>
			</tr>
			<tr>
				<td>Course</td>
				<td>
					<input type="text" name="course">
				</td>
			</tr>
			<tr>
				<td>Address</td>
				<td>
					<input type="text" name="address">
				</td>
			</tr>
			<tr>
				<td>Contact Number</td>
				<td>
					<input type="text" name="contact_number">
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<button name = "submit">Ipasa</button>
				</td>
			</tr>
		</table>
	</form>
	
	<hr>

	<table border="1">
		<?php
			$fetch = "SELECT * FROM students";
			$result = mysqli_query($conn, $fetch);

			if(mysqli_num_rows($result) < 1){
				echo "
					<center>
						<h1>Awan Karga na!!!</h1>
					</center>
				";
			}
			else{
				echo "
					<tr>
						<th>Student Name</th>
						<th>Course</th>
						<th>Address</th>
						<th>Contact Number</th>
						<th>Actions</th>
					</tr>
				";
				while ($row = mysqli_fetch_array($result)) {
		?>	
				<tr>
					<td><?php echo $row['Student_Name']; ?></td>
					<td><?php echo $row['Course']; ?></td>
					<td><?php echo $row['Address']; ?></td>
					<td><?php echo $row['Contact_Number']; ?></td>
					<td>
						<a href = "update.php?id=<?php echo $row['ID']; ?>">Update</a>
						<a href = "delete.php?id=<?php echo $row['ID']; ?>">Delete</a>
					</td>
				</tr>
		<?php
				}
			}
		?>
	</table>
</body>
</html>