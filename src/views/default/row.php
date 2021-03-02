<?php

use yii\helpers\Html;
use yii\helpers\Url;

?>
<tr>
	<?= Html::tag('td', $fn, $opt); ?>
	<td><?= number_format($data['ct']); ?></td>
	<td><?= number_format($data['ctp'], 2); ?>%</td>
<?php
foreach ($keys as $k) {
?>
	<td><?= number_format($data[$k]); ?></td>
	<td><?= number_format($data[$k . 'p'], 2); ?>%</td>
<?php
}
?>
</tr>
