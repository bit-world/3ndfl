<?php
	require __DIR__ . '/vendor/autoload.php';
	
	//error_reporting(E_ALL);
	
	function show_table($table, $is_excel) {
		echo '<table border="1">';
		echo '<tr><td>Дата</td><td>Наименование</td>' .
			'<td>Выплата</td><td>Налог</td>' .
			'<td>Процент</td><td>Валюта</td><td>Страна</td>' .
			'<td>Ошибки</td></tr>';
		$i = 0;
		$s1 = 0;
		$s2 = 0;
		foreach($table as $el) {
			$check = $el[6];
			if(!isset($base[$check])) {
				$base[$check] = true;
				$er = ['', ''];
			} else {
				$er = ['style="color: red"', 'ЗАПИСЬ ДУБЛИРУЕТСЯ В ОТЧЕТЕ, ИГНОРИРУЕМ'];
			}
			if($is_excel) {
				$el[0] = date('d.m.Y', strtotime('1899-12-30 +' . $el[0] . ' day'));
			}
			$per00 = round($el[3] / $el[2] * 100, 2) . '%';
			echo '<tr ' . $er[0] . '><td>' . 
					$el[0] . '</td><td>' . 
					$el[1] . '</td><td>' . 
					$el[2] . '</td><td>' . 
					$el[3] . '</td><td>' . 
					$per00 . '</td><td>' . 
					$el[4] . '</td><td>' . 
					$el[5] . '</td><td>' . 
					$er[1] . '</td></tr>';
			$i++;
			$s1 += (double)$el[2];
			$s2 += (double)$el[3];
		}
		$s3 = round($s2 / $s1 * 100, 2) . '%';
		echo '<tr><td>Записей: ' . $i .  
			'</td><td>Сумма</td>' .
			'<td>' . $s1 . '</td>' . 
			'<td>' . $s2 . '</td>' .
			'<td>' . $s3 . '</td>' .
			'<td></td><td></td><td></td></tr>';
		echo '</table>';
	}
	
	function ndfl3_save($table, $is_excel) {
		$to_excel = !$is_excel;
		
		$nalog = new PDF\NDFL3('base.dc0', 'CurrencyRates2020.xml', date('d.m.Y'));
		
		
		foreach($table as $el) {
			$check = $el[6];
			if(!isset($base[$check])) {
				$base[$check] = true;
				$nalog->append([
					'date' => $el[0],
					'name' => $el[1],
					'in_usd' => $el[2],
					'tax_usd' => $el[3],
					'currency_code' => $el[4],
					'country' => $el[5]
				], $to_excel);
			}
		}
		
		
		
		$file = $nalog->save();
		//echo $file;
		
		//die();
		
		header('Cache-control: private');
		header('Content-Type: application/octet-stream');
		//header('Content-Length: '. sizeof($file));
		header('Content-Disposition: filename=form.dc0');
		echo $file;
	}
	
	function get_file($is_excel) {
		$file_path = __DIR__ . '/tmp/';
		$name = 'tmp' . date('dmYHis') . '_' . rand(1, 10000);
		$name .= $is_excel ? '.xlsx' : '.pdf';
		return $file_path . $name;
	}
	
	function read_file($file_path, $is_excel, $show) {
		if($is_excel) {
			$table = [];
			$xlsx = new PDF\Excel($file_path);
			$xlsx->sheet(1);
			$xlsx->next();
			while($xlsx->valid()) {
				$row = $xlsx->current();
				$table[] = [
					$row[0], $row[1], $row[2], $row[3], $row[4], $row[5]
				];
				$xlsx->next();
			}
		} else {
			$table = new PDF\Tinkoff;
			$table = $table->parse($file_path);
		}
		if($show) {
			show_table($table, $is_excel);
		} else {
			ndfl3_save($table, $is_excel);
		}
	}
	
	if(isset($_POST['type']) && (isset($_POST['generate']) || isset($_POST['test']))) {
		$is_excel = ($_POST['type'] == 'excel');
		$is_test = isset($_POST['test']);
		
		$tmp_path = $_FILES['userfile']['tmp_name'];
		$file_path = get_file($is_excel);
		if(move_uploaded_file($tmp_path, $file_path)) {
			read_file($file_path, $is_excel, $is_test);
			unlink($file_path);
		} else {
			echo 'Непредвиденная ошибка';
		}
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
			<li>Загружаем <a href="https://www.tinkoff.ru/invest/broker_account/about/" target="_blank">отчет из тинькофф</a> или заполняем <a href="sample.xlsx" target="_blank">шаблон Excel</a></li>
			<li>Генерируем dc0</li>
			<li>Загружаем dc0 в <a href="https://www.gnivc.ru/software/fnspo/ndfl_3_4/" target="_blank">"Программу подготовки 3-НДФЛ"</a></li>
		</ul>
	</p>
	<p>Одно из двух (xlsx или out-inc-state-2020.pdf):</p>
	<p>Excel:</p>
	<form method="post" action="index.php" enctype="multipart/form-data">
		<input type="file" name="userfile">
		<input type="hidden" name="type" value="excel">
		<input type="submit" name="test" value="Проверка">
		<input type="submit" name="generate" value="Генерировать dc0">
	</form>
	<br><br><br>
	<p>Tinkoff:</p>
	<form method="post" action="index.php" enctype="multipart/form-data">
		<input type="file" name="userfile">
		<input type="hidden" name="type" value="tinkoff">
		<input type="submit" name="test" value="Проверка">
		<input type="submit" name="generate" value="Генерировать dc0">
	</form>
</body>
</html>
<?php
	}
?>