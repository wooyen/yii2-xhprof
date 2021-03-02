<?php
use yii\bootstrap4\BootstrapAsset;

BootstrapAsset::register($this);
?>
<table class="table table-hover">
	<thead>
		<tr>
			<th scope="col">ID</th>
			<th scope="col">Request Time</th>
			<th scope="col">URI</th>
		</tr>
	</thead>
	<tbody>
<?php
foreach ($data as $row) {
?>
		<tr>
			<td><a href="<?= "/$moduleId/{$row['id']}"; ?>"><?= $row['id']; ?></a></td>
			<td><?= $row['time']; ?></td>
			<td><?= $row['uri']; ?></td>
		</tr>
<?php
}
?>
	</tbody>
</table>
