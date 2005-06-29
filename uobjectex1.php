<?php 
require("redback.php"); 

$rb = new redback;
$rb->__Set_Debug();
$rb->open('http://192.168.211.2:80/cgi-bin/rgw/rbexamples', 'EXMOD:Employee');

if ($_POST) {
	$rb->setproperty($_POST['edit']);
	$rb->ReadData();
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>uobject example 1</title>
</head>

<body>
<div>
  <form name="form1" method="post">
    <table border="0">
      <tr>
        <td>Employee Number </td>
        <td><input name="edit[EmpId]" type="text" id="edit-EmpId" value="<?php echo $rb->EmpId; ?>"></td>
      </tr>
      <tr>
        <td>First Name </td>
        <td>
        <input name="edit[FirstName]" type="text" id="edit[FirstName]" value="<?php echo $rb->FirstName; ?>"></td>
      </tr>
      <tr>
        <td>Last Name </td>
        <td>
        <input name="edit[LastName]" type="text" id="edit[LastName]" value="<?php echo $rb->LastName; ?>"></td>
      </tr>
      <tr>
        <td>Interests</td>
        <td><?php 
				if (is_array($rb->Interests)) {
					echo implode(',', $rb->Interests[0]);
				}
				else {
					echo $rb->Interests;
				}
				 ?></td>
      </tr>
      <tr>
        <td colspan="2"><div align="center">
          <input type="submit" name="Submit" value="Submit">
        </div></td>
      </tr>
    </table> 
  </form><?php var_dump($rb->__Debug_Data); ?>
</div>
</body>
</html>
