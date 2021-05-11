<?php
require('conf/config.php');
?>
<!-- Website starts here -->
<!DOCTYPE html>
<html>

<head>
	<title>L1 Consultoria - LogsV6</title>
	<link rel="stylesheet" href="<?php echo INCLUDE_PATH; ?>css/font-awesome.min.css">
	<link href="<?php echo INCLUDE_PATH; ?>css/style.css" rel="stylesheet" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="keywords" content="keywords,chave">
	<meta name="description" content="Descrição do site">
	<meta charset="utf-8" />
</head>

<body>
	<?php
	$url = isset($_GET['url']) ? $_GET['url'] : 'home';
	?>
	<header>
		<div class="top-bar">
			<div class="logo left"><?php echo '<a href="' . INCLUDE_PATH . '">' . COMPANY_NAME . '</a>'; ?></div>
			<!--Company Name-->
			<nav class=" desktop right">
				<ul>
					<li><a href="<?php echo INCLUDE_PATH; ?>home">Home</a></li>
					<li><a href="<?php echo INCLUDE_PATH; ?>consulta">Consulta</a></li>
				</ul>
			</nav>
			<nav class="mobile right">
				<div class="botao-menu-mobile"><i class="fa fa-bars" aria-hidden="true"></i></div>
				<ul>
					<li><a href="<?php echo INCLUDE_PATH; ?>home">Home</a></li>
					<li><a href="<?php echo INCLUDE_PATH; ?>consulta">Consulta</a></li>
				</ul>
			</nav>
			<div class="clear"></div>
		</div>
	</header>
	<main>
		<?php
		if (file_exists('pages/' . $url . '.php')) {
			include('pages/' . $url . '.php');
		} else {
			$pagina404 = true;
			include('pages/404.php');
		}
		?>
	</main>
	<footer>
		<div class="center">
			<p>Todos direitos reservados</p>
		</div>
	</footer>
</body>

</html>