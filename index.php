<?php
	require __DIR__ . '/vendor/autoload.php';
	
	if(isset($_POST['upload'])) {
		//if(move_uploaded_file($_FILES['userfile']['tmp_name'], $file_path)) {
			//
			$nalog = new PDF\NDFL3('base.dc0', 'CurrencyRates2020.xml', date('d.m.Y'));

			$xlsx = new PDF\Excel($_FILES['userfile']['tmp_name']);
			$xlsx->sheet(1);
			$xlsx->next();
			while($xlsx->valid()) {
				$row = $xlsx->current();
				//print_r($row);
				$nalog->append([
					'date' => $row[0],
					'name' => $row[1],
					'in_usd' => $row[2],
					'tax_usd' => $row[3],
					'currency_code' => $row[4],
					'country' => $row[5],
					
				], false);
				$xlsx->next();
			}
			$file = $nalog->save();
			header('Cache-control: private');
			header('Content-Type: application/octet-stream');
			header('Content-Length: '. sizeof($file));
			header('Content-Disposition: filename=form.dc0');
			echo $file;
			exit();
		//}
	} else {
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>NDFL3</title>
	<meta name="description" content="NDFL3">
</head>
<body>
	<p>
		<ul>
			<li>Заполняем шаблон xlsx</li>
			<li>Генерируем dc0</li>
			<li>Загружаем dc0 в "Программе подготовки 3-НДФЛ"</li>
		</ul>
	</p>
	<ul>
		<li><a href="https://www.gnivc.ru/software/fnspo/ndfl_3_4/" target="_blank">Программа подготовки 3-НДФЛ</a></li>
		<li><a href="sample.xlsx" target="_blank">Шаблон для заполнения таблицы Excel</a></li>
	</ul>
	<form method="post" action="index.php" enctype="multipart/form-data">
		<input type="file" name="userfile">
		<input type="submit" name="upload" value="Генерировать dc0">
	</form>
</body>
</html>
<?php
	}
?>