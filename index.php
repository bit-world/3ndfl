<?php
	require __DIR__ . '/vendor/autoload.php';
	
	//error_reporting(E_ALL);
	define('EXT', 'dc2');
	define('REPORT', 'OUT_INC_STATE_REPORT.pdf');
	define('CurrencyRates', 'CurrencyRates2022.xml');
	
	function show_table($table, $is_excel) {
		$to_excel = !$is_excel;
		
		$nalog = new PDF\NDFL3('base.' . EXT, CurrencyRates, date('d.m.Y'));
		
		echo <<<HTML
<table border="1">
	<tr>
		<td>Дата</td>
		<td>Наименование</td>
		<td>Выплата</td>
		<td>Выплата, руб</td>
		<td>Налог</td>
		<td>Налог, руб</td>
		<td>Процент</td>
		<td>Доплатa</td>
		<td>Валюта</td>
		<td>Курс</td>
		<td>Страна</td>
		<td>Ошибки</td>
	</tr>
HTML;

		$i = 0;
		$s1 = 0;
		$s2 = 0;
		$s3 = 0;
		$s4 = 0;
		$s5 = 0;
		$s6 = 0;
		foreach($table as $el) {
			$n3 = $nalog->gen_income(
				ndfl3_format($el), 
				$to_excel
			)['info'];
			$n3_date = $el[0];
			if($is_excel) {
				$n3_date = $nalog->from_excel_date($n3_date);
			}
			//
			$per = $nalog->nalog_round($el[3] / $el[2] * 100);
			$rate = $nalog->nalog_round($n3[10] / $n3[11]);
			if($per < 13) {
				$add_nalog = $nalog->nalog_round($n3[16] * (13 - $per) / 100);
			} else {
				$add_nalog = 0;
			}
			//
			$check = $el[6];
			if(!isset($base[$check])) {
				$base[$check] = true;
				$er = ['', ''];
			} else {
				$er = ['style="color: red"', 'ВОЗМОЖНО, ЗАПИСЬ ДУБЛИРУЕТСЯ В ОТЧЕТЕ'];
			}
			//add to sum
			$i++;
			$s1 += $el[2];
			$s2 += $n3[16];
			$s3 += $el[3];
			$s4 += $n3[18];
			$s6 += $add_nalog;
		
			//echo '<tr><td colspan="11">' . print_r($n3, true) . '</td></tr>';
			
			echo <<<HTML
<tr $er[0]>
	<td>$n3_date</td>
	<td>$el[1]</td>
	<td>$el[2]</td>
	<td>$n3[16]</td>
	<td>$el[3]</td>
	<td>$n3[18]</td>
	<td>$per%</td>
	<td>$add_nalog</td>
	<td>$n3[14]</td>
	<td>$rate</td>
	<td>$el[5]</td>
	<td>$er[1]</td>
</tr>
HTML;
			
			
		}
		$s5 = round($s3 / $s1 * 100, 2) . '%';
		echo <<<HTML
<tr>
	<td>Записей: $i</td>
	<td></td>
	<td>$s1</td>
	<td>$s2</td>
	<td>$s3</td>
	<td>$s4</td>
	<td>$s5</td>
	<td>$s6</td>
	<td colspan="4"></td>
</tr></table>
HTML;

	}
	
	function ndfl3_format($in) {
		$out = [
			'date' => $in[0],
			'name' => $in[1],
			'in_usd' => $in[2],
			'tax_usd' => $in[3],
			'currency_code' => $in[4],
			'country' => $in[5]
		];
		return $out;
	}
	
	function ndfl3_save($table, $is_excel) {
		$to_excel = !$is_excel;
		
		$nalog = new PDF\NDFL3('base.' . EXT, CurrencyRates, date('d.m.Y'));
		
		foreach($table as $el) {
			$check = $el[6];
			if(!isset($base[$check])) {
				$base[$check] = true;
			}
			$n3 = ndfl3_format($el);
			$nalog->append($n3, $to_excel);
		}
		
		$file = $nalog->save();
		//echo $file;
		//die();
		
		header('Cache-control: private');
		header('Content-Type: application/octet-stream');
		//header('Content-Length: '. sizeof($file));
		header('Content-Disposition: filename=form.' . EXT);
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
				if(empty($row)) break;
				$row[6] = $row[0] . $row[1];
				$table[] = $row;
				$xlsx->next();
			}
			//print_r($table);
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
			<li>Загружаем <a href="https://www.tinkoff.ru/invest/" target="_blank">отчет из тинькофф</a> или заполняем <a href="sample.xlsx" target="_blank">шаблон Excel</a></li>
			<li>Генерируем <?=EXT?></li>
			<li>Загружаем <?=EXT?> в <a href="https://www.gnivc.ru/software/fnspo/ndfl_3_4/" target="_blank">"Программу подготовки 3-НДФЛ"</a></li>
		</ul>
	</p>
	<p>Одно из двух (xlsx или <?=REPORT?>):</p>
	<p>Excel:</p>
	<form method="post" action="index.php" enctype="multipart/form-data">
		<input type="file" name="userfile">
		<input type="hidden" name="type" value="excel">
		<input type="submit" name="test" value="Проверка">
		<input type="submit" name="generate" value="Генерировать <?=EXT?>">
	</form>
	<br><br><br>
	<p>Tinkoff:</p>
	<form method="post" action="index.php" enctype="multipart/form-data">
		<input type="file" name="userfile">
		<input type="hidden" name="type" value="tinkoff">
		<input type="submit" name="test" value="Проверка">
		<input type="submit" name="generate" value="Генерировать <?=EXT?>">
	</form>
</body>
</html>
<?php
	}
?>