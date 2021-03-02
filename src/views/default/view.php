<?php
use Yii;
use yii\bootstrap4\BootstrapAsset;
use yii\helpers\Html;
use yii\helpers\Url;

BootstrapAsset::register($this);
$this->title = 'Xhprof Run Report';
$xhprof = $data['xhprof'];
$main = $xhprof['main()'];
$all = $url['all'];
?>
<h1 class="text-center"><?= $this->title; ?></h1>
<div class="row">
	<div class="col-5 col-sm-4 col-md-3 col-lg-2 text-right">Request URI:</div>
	<div class="col-7 col-sm-8 col-md-9 col-lg-10"><?= $data['uri']; ?></div>
	<div class="col-5 col-sm-4 col-md-3 col-lg-2 text-right">Request Time:</div>
	<div class="col-7 col-sm-8 col-md-9 col-lg-10"><?= $data['time']; ?></div>
</div>
<div class="container">
	<div class="row">
		<div class="col-sm-12 col-md-8 offset-md-2 bg-secondary text-light">
			<div class="text-center"><b>Overall Summary</b></div>
			<table id="summary" class="table table-borderless text-right">
<?php
foreach ($data['keys'] as $k) {
?>
				<tr>
					<th scope="row">Total Incl. <?= "{$fields[$k][0]} ({$fields[$k][1]})"; ?>:</th>
					<td><?= number_format($main[$k]) . " {$fields[$k][1]}"; ?></td>
				</tr>
<?php
}
?>
				<tr>
					<th scope="row">Number of Function Calls:</th>
					<td><?= number_format($data['ct']); ?></td>
				</tr>
			</table>
		</div>
	</div>
</div>
<a class="btn btn-primary btn-lg btn-block col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 my-5" href="<?= Url::toRoute($callgraphUrl); ?>">View Full Callgraph</a>
<div class="text-center">
	<b>
		<?= $all ? '': 'Displaying top 100 functions: '; ?>
		Sorted by <?= $sortLabel; ?> 
		[<a href="<?= Url::toRoute(array_merge($url, ['all' => $all xor 1])); ?>"><?= $all ? 'Display top 100 functions' : 'Display all'; ?></a>]
	</b>
</div>
<table class="table table-striped">
	<thead class="thead-dark">
		<tr>
			<th scope="col"><a href="<?= Url::toRoute(array_merge($url, ['sort' => 'fn'])); ?>">Function name</a></th>
			<th scope="col"><a href="<?= Url::toRoute(array_merge($url, ['sort' => 'ct'])); ?>">Calls</a></th>
			<th scope="col">Calls%</th>
<?php
foreach ($data['keys'] as $k) {
?>
			<th scope="col"><a href="<?= Url::toRoute(array_merge($url, ['sort' => $k])); ?>">Incl. <?= "{$fields[$k][0]} ({$fields[$k][1]})"; ?></a></th>
			<th scope="col">I<?= $fields[$k][0]; ?>%</th>
			<th scope="col"><a href="<?= Url::toRoute(array_merge($url, ['sort' => $k. 'e'])); ?>">Excl. <?= "{$fields[$k][0]} ({$fields[$k][1]})"; ?></a></th>
			<th scope="col">E<?= $fields[$k][0]; ?>%</th>
<?php
}
?>
		</tr>
	</thead>
<?php
$n = 0;
$url[0] = 'detail';
unset($url['all']);
foreach ($xhprof as $k => $v) {
	$url['symbol'] = $k;
?>
		<tr>
			<td><a href="<?= Url::toRoute($url); ?>"><?= Html::encode($k); ?></a></td>
			<td><?= $v['ct']; ?></td>
			<td><?= number_format($v['ctp'], 2); ?>%</td>
<?php
	foreach ($data['keys'] as $key) {
?>
			<td><?= $v[$key]; ?></td>
			<td><?= number_format($v[$key . 'p'], 2); ?>%</td>
			<td><?= $v[$key . 'e']; ?></td>
			<td><?= number_format($v[$key . 'ep'], 2); ?>%</td>
<?php
	}
?>
		</tr>
<?php
	if (!$all && ++$n >= 100) {
		break;
	}
}
?>
	<tbody>
	</tbody>
</table>
