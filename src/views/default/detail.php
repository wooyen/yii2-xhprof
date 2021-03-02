<?php
use Yii;
use yii\bootstrap4\BootstrapAsset;
use yii\helpers\Html;
use yii\helpers\Url;

BootstrapAsset::register($this);
$this->title = 'Xhprof Run Report';
$xhprof = $data['xhprof'];
?>
<h1 class="text-center"><?= $this->title; ?></h1>
<div class="text-center">
	<b>Parent/Child report for <?= Html::encode($symbol); ?></b>
</div>
<a class="btn btn-primary btn-lg btn-block col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 my-5" href="<?= Url::toRoute(['callgraph', 'id' => $id, 'symbol' => $symbol]); ?>">View Callgraph</a>
<div class="mt-2"><a href="<?= Url::toRoute(['view', 'id' => $id, 'sort' => $sort]); ?>">Back to Top View</a></div>
<table class="table table-striped mt-1">
	<thead class="thead-dark">
		<tr>
			<th scope="col"><a href="<?= Url::toRoute(['', 'id' => $id, 'symbol' => $symbol, 'sort' => 'fn']); ?>">Function name</a></th>
			<th colspan="2" scope="col"><a href="<?= Url::toRoute(['', 'id' => $id, 'symbol' => $symbol, 'sort' => 'ct']); ?>">Calls</a></th>
<?php
foreach ($data['keys'] as $k) {
?>
			<th colspan="2" scope="col"><a href="<?= Url::toRoute(['', 'id' => $id, 'symbol' => $symbol, 'sort' => $k]); ?>"><?= "{$fields[$k][0]} ({$fields[$k][1]})"; ?></a></th>
<?php
}
$cols = count($data['keys'])*2 + 3;
?>
		</tr>
	</thead>
	<tbody class="text-right">
		<tr><td colspan="<?= $cols; ?>" class="bg-secondary text-left"><i>Current Function</i></td></tr>
<?php
echo $this->render('row', [
	'fn' => Html::a($symbol, ''),
	'opt' => ['class' => 'text-left'],
	'data' => $xhprof[0],
	'keys' => $data['keys'],
]);
echo $this->render('row', [
	'fn' => 'Exclusive Metrics for Current Function',
	'opt' => ['class' => 'text-primary'],
	'data' => $xhprof[1],
	'keys' => $data['keys'],
]);
?>
		<tr><td colspan="<?= $cols; ?>" class="bg-secondary text-left"><i>Parent Function</i></td></tr>
<?php
foreach ($xhprof[2] as $k => $v) {
	echo $this->render('row', [
		'fn' => Html::a($k, Url::toRoute(['', 'id' => $id, 'symbol' => $k, 'sort' => $sort])),
		'opt' => ['class' => 'text-left'],
		'data' => $v,
		'keys' => $data['keys'],
	]);
}
?>
		<tr><td colspan="<?= $cols; ?>" class="bg-secondary text-left"><i>Child Function</i></td></tr>
<?php
foreach ($xhprof[3] as $k => $v) {
	echo $this->render('row', [
		'fn' => Html::a($k, Url::toRoute(['', 'id' => $id, 'symbol' => $k, 'sort' => $sort])),
		'opt' => ['class' => 'text-left'],
		'data' => $v,
		'keys' => $data['keys'],
	]);
}
?>
	</tbody>
</table>
