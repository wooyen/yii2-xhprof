<?php
use yii\helpers\Html;

$this->beginPage();
?>

<!DOCTYPE html>
<html lang="<?= Yii::$app->language; ?>">
	<head>
		<meta charset="<?= Yii::$app->charset; ?>" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="robots" content="none" />
		<?php $this->registerCsrfMetaTags(); ?>
		<title><?= Html::encode($this->title) ?></title>
		<?php $this->head(); ?>
	</head>
	<body>
		<?php $this->beginBody(); ?>
		<div class="wrap">
			<div class="container">
				<?= $content; ?>
			</div>
		</div>
	</body>
	<?php $this->endBody(); ?>
</html>
<?php $this->endPage(); ?>
